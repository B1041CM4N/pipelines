<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\Runner;

use Ktomk\Pipelines\TestCase;

/**
 * Class StepContainerTest
 *
 * @package Ktomk\Pipelines\Runner
 * @covers \Ktomk\Pipelines\Runner\StepContainer
 */
class StepContainerTest extends TestCase
{
    public function testCreation()
    {
        $step = $this->getStepMock();

        $container = new StepContainer($step);
        $this->assertNotNull($container);

        $container = StepContainer::create($step);
        $this->assertInstanceOf('Ktomk\Pipelines\Runner\StepContainer', $container);

        return $container;
    }

    /**
     * @depends testCreation
     *
     * @param StepContainer $container
     */
    public function testGenerateName(StepContainer $container)
    {
        $expected = 'pipelines-1.no-name.null.test-project';
        $actual = $container->generateName('pipelines', 'test-project');
        $this->assertSame($expected, $actual);
    }

    public function testCreateName()
    {
        $expected = 'pipelines-1.no-name.null.test-project';
        $actual = StepContainer::createName(
            $this->getStepMock(),
            'pipelines',
            'test-project'
        );
        $this->assertSame($expected, $actual);
    }
    /**
     * @return \Ktomk\Pipelines\File\Step|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getStepMock()
    {
        $step = $this->createPartialMock(
            'Ktomk\Pipelines\File\Step',
            array('getPipeline')
        );
        $step->method('getPipeline')
            ->willReturn(
                $this->createMock('Ktomk\Pipelines\File\Pipeline')
            );

        return $step;
    }
}
