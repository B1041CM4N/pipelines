<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\Runner\Docker;

use Ktomk\Pipelines\Cli\ExecTester;
use Ktomk\Pipelines\TestCase;

/**
 * @covers \Ktomk\Pipelines\Runner\Docker\ArtifactSource
 */
class ArtifactSourceTest extends TestCase
{
    public function testCreation()
    {
        $exec = new ExecTester($this);
        $source = new ArtifactSource($exec, '*fake*', '/app');
        $this->assertInstanceOf('Ktomk\Pipelines\Runner\Docker\ArtifactSource', $source);

        return $source;
    }

    /**
     * @param ArtifactSource $source
     * @depends testCreation
     */
    public function testGetId(ArtifactSource $source)
    {
        $this->assertSame('*fake*', $source->getId());
    }

    public function testGetFiles()
    {
        $buffer = file_get_contents(__DIR__ . '/../../../data/docker-find.txt');
        $exec = new ExecTester($this);
        $exec->expect('capture', 'docker', $buffer);
        $source = new ArtifactSource($exec, '*fake*', '/app');
        $actual = $source->getAllFiles();
        $this->assertInternalType('array', $actual);
        $this->assertGreaterThanOrEqual(18, count($actual));
    }

    public function testGetFileFindFailure()
    {
        $exec = new ExecTester($this);
        $exec->expect('capture', 'docker', 126);
        $source = new ArtifactSource($exec, '*fake*', '/app');
        $actual = $source->getAllFiles();
        $this->assertSame(array(), $actual);
    }

    public function testFindByPattern()
    {
        $buffer = file_get_contents(__DIR__ . '/../../../data/docker-find.txt');
        $exec = new ExecTester($this);
        $exec->expect('capture', 'docker', $buffer);
        $source = new ArtifactSource($exec, '*fake*', '/app');
        $expected = array(
            'build/html/testdox.html',
            'build/html/PharBuild/dashboard.html',
        );
        $result = $source->findByPattern('build/html/**.html');
        $this->assertInternalType('array', $result);
        $actual = array_intersect($expected, $result);
        $this->assertSame($expected, $actual, 'all expected must be found');
    }
}
