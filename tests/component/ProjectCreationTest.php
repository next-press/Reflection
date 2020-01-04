<?php
/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2010-2018 Mike van Riel<mike@phpdoc.org>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace phpDocumentor\Reflection;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\File\LocalFile;
use phpDocumentor\Reflection\Php\Function_;
use phpDocumentor\Reflection\Php\ProjectFactory;
use phpDocumentor\Reflection\Types\Object_;
use phpDocumentor\Reflection\Types\String_;

/**
 * Integration tests to check the correct working of processing a file into a project.
 *
 * @coversNothing
 */
class ProjectCreationTest extends MockeryTestCase
{
    /** @var ProjectFactory */
    private $fixture;

    protected function setUp() : void
    {
        $this->fixture = ProjectFactory::createInstance();
    }

    public function testCreateProjectWithFunctions() : void
    {
        $fileName = __DIR__ . '/project/simpleFunction.php';

        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey($fileName, $project->getFiles());
        $file = $project->getFiles()[$fileName];
        $this->assertArrayHasKey('\simpleFunction()', $file->getFunctions());

        /** @var Function_ $function */
        $function = $file->getFunctions()['\simpleFunction()'];
        $this->assertSame('\simpleFunction()', (string) $function->getFqsen());
        $this->assertCount(1, $function->getArguments());
    }

    public function testCreateProjectWithClass() : void
    {
        $fileName = __DIR__ . '/project/Pizza.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey($fileName, $project->getFiles());
        $this->assertArrayHasKey('\\Pizza', $project->getFiles()[$fileName]->getClasses());
        $this->assertArrayHasKey(
            '\\Pizza::PACKAGING',
            $project->getFiles()[$fileName]->getClasses()['\\Pizza']->getConstants()
        );
        $constant = $project->getFiles()[$fileName]->getClasses()['\\Pizza']->getConstants()['\\Pizza::PACKAGING'];

        $this->assertEquals('box', $constant->getValue());
    }

    public function testFileWithDocBlock() : void
    {
        $fileName = __DIR__ . '/project/Pizza.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey($fileName, $project->getFiles());
        $this->assertInstanceOf(Docblock::class, $project->getFiles()[$fileName]->getDocBlock());
    }

    public function testWithNamespacedClass() : void
    {
        $fileName = __DIR__ . '/project/Luigi/Pizza.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey($fileName, $project->getFiles());
        $this->assertArrayHasKey('\\Luigi\\Pizza', $project->getFiles()[$fileName]->getClasses());
        $this->assertEquals('\Pizza', $project->getFiles()[$fileName]->getClasses()['\\Luigi\\Pizza']->getParent());
        $this->assertArrayHasKey(
            '\\Luigi\\Pizza::$instance',
            $project->getFiles()[$fileName]->getClasses()['\\Luigi\\Pizza']->getProperties()
        );

        $methods = $project->getFiles()[$fileName]->getClasses()['\\Luigi\\Pizza']->getMethods();
        $this->assertArrayHasKey(
            '\\Luigi\\Pizza::__construct()',
            $methods
        );

        $this->assertEquals('style', $methods['\\Luigi\\Pizza::__construct()']->getArguments()[0]->getName());
        $this->assertEquals(
            new Object_(new Fqsen('\\Luigi\\Pizza\Style')),
            $methods['\\Luigi\\Pizza::__construct()']->getArguments()[0]->getType()
        );
    }

    public function testDocblockOfMethodIsProcessed() : void
    {
        $fileName = __DIR__ . '/project/Luigi/Pizza.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey($fileName, $project->getFiles());

        $methods = $project->getFiles()[$fileName]->getClasses()['\\Luigi\\Pizza']->getMethods();

        $createInstanceMethod = $methods['\\Luigi\\Pizza::createInstance()'];

        $this->assertInstanceOf(DocBlock::class, $createInstanceMethod->getDocBlock());

        $docblock = $createInstanceMethod->getDocBlock();
        /** @var Param[] $params */
        $params = $docblock->getTagsByName('param');

        /** @var Object_ $objectType */
        $objectType = $params[0]->getType();

        $this->assertEquals(new Fqsen('\Luigi\Pizza\Style'), $objectType->getFqsen());
    }

    public function testWithUsedParent() : void
    {
        $fileName = __DIR__ . '/project/Luigi/StyleFactory.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey($fileName, $project->getFiles());
        $this->assertArrayHasKey('\\Luigi\\StyleFactory', $project->getFiles()[$fileName]->getClasses());
        $this->assertEquals(
            '\\Luigi\\Pizza\\PizzaComponentFactory',
            $project->getFiles()[$fileName]->getClasses()['\\Luigi\\StyleFactory']->getParent()
        );
    }

    public function testWithInterface() : void
    {
        $fileName = __DIR__ . '/project/Luigi/Valued.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey('\\Luigi\\Valued', $project->getFiles()[$fileName]->getInterfaces());
    }

    public function testWithTrait() : void
    {
        $fileName = __DIR__ . '/project/Luigi/ExampleNestedTrait.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey('\\Luigi\\ExampleNestedTrait', $project->getFiles()[$fileName]->getTraits());
    }

    public function testWithGlobalConstants() : void
    {
        $fileName = __DIR__ . '/project/Luigi/constants.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey('\\Luigi\\OVEN_TEMPERATURE', $project->getFiles()[$fileName]->getConstants());
    }

    public function testInterfaceExtends() : void
    {
        $fileName = __DIR__ . '/project/Luigi/Packing.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey('\\Luigi\\Packing', $project->getFiles()[$fileName]->getInterfaces());
        $interface = current($project->getFiles()[$fileName]->getInterfaces());

        $this->assertEquals(['\\Packing' => new Fqsen('\\Packing')], $interface->getParents());
    }

    public function testMethodReturnType() : void
    {
        $fileName = __DIR__ . '/project/Packing.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertArrayHasKey('\\Packing', $project->getFiles()[$fileName]->getInterfaces());
        $interface = current($project->getFiles()[$fileName]->getInterfaces());

        $this->assertEquals(new String_(), $interface->getMethods()['\Packing::getName()']->getReturnType());
    }

    public function testFileDocblock() : void
    {
        $fileName = __DIR__ . '/project/empty.php';
        $project = $this->fixture->create(
            'MyProject',
            [new LocalFile($fileName)]
        );

        $this->assertEquals(
            'This file is part of phpDocumentor.',
            $project->getFiles()[$fileName]->getDocBlock()->getSummary()
        );
    }
}
