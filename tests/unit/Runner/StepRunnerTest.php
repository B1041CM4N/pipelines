<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\Runner;

use Ktomk\Pipelines\Cli\Exec;
use Ktomk\Pipelines\Cli\ExecTester;
use Ktomk\Pipelines\Cli\Streams;
use Ktomk\Pipelines\LibFs;
use Ktomk\Pipelines\LibTmp;
use Ktomk\Pipelines\Runner\Docker\Binary\Repository;
use Ktomk\Pipelines\Runner\Docker\Binary\UnPackager;
use Ktomk\Pipelines\Utility\OptionsMock;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class StepRunnerTest
 *
 * @covers \Ktomk\Pipelines\Runner\StepRunner
 */
class StepRunnerTest extends RunnerTestCase
{
    public function testFailOnContainerCreation()
    {
        $exec = new ExecTester($this);
        $exec->expect('capture', 'docker', 1);
        $exec->expect('capture', 'docker', 126);

        $step = $this->createTestStep();
        $runner = $this->createTestStepRunner($exec, null, array(null, 'php://output'));

        $this->expectOutputRegex('~pipelines: setting up the container failed~');
        $actual = $runner->runStep($step);
        $this->assertNotSame(0, $actual);
    }

    public function testRunning()
    {
        /** @var Exec|MockObject $exec */
        $exec = $this->createMock('Ktomk\Pipelines\Cli\Exec');
        $exec->method('pass')->willReturn(0);
        $exec->method('capture')->willReturn(0);

        $step = $this->createTestStep();
        $runner = $this->createTestStepRunner($exec, null, 'php://output');

        $this->expectOutputRegex('{^\x1d\+\+\+ step #1\n}');
        $actual = $runner->runStep($step);
        $this->assertSame(0, $actual);
    }

    public function testCopy()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1, 'zap')
            ->expect('capture', 'docker', 0)
            ->expect('pass', $this->deploy_copy_cmd, 0, 'copy deployment /app create')
            ->expect('pass', $this->deploy_copy_cmd_2, 0, 'copy deployment /app copy')
            ->expect('pass', '~ docker exec ~', 0)
            ->expect('capture', 'docker', 0, 'docker kill')
            ->expect('capture', 'docker', 0, 'docker rm');

        $step = $this->createTestStep();
        $runner = $this->createTestStepRunner($exec, Flags::FLAG_DEPLOY_COPY | Flags::FLAGS, 'php://output');

        $this->expectOutputRegex('{^\x1D\+\+\+ copying files into container\.\.\.\n}m');

        $status = $runner->runStep($step);
        $this->assertSame(0, $status);
    }

    /**
     * this is the first stage, however in general deploy copy fails, too.
     *
     * the first stage is to create the container target directory to copy
     * into.
     */
    public function testCopyFails()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1)
            ->expect('capture', 'docker', 0)
            ->expect('pass', $this->deploy_copy_cmd, 1);

        $step = $this->createTestStep();
        $runner = $this->createTestStepRunner($exec, Flags::FLAG_DEPLOY_COPY | Flags::FLAGS, array(null, 'php://output'));

        $this->expectOutputRegex('{^pipelines: deploy copy failure}');
        $status = $runner->runStep($step);
        $this->assertSame(1, $status);
    }

    /**
     * second stage is to copy project files into the container target directory
     */
    public function testCopyFailsAtSecondStage()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1)
            ->expect('capture', 'docker', 0)
            ->expect('pass', $this->deploy_copy_cmd, 0)
            ->expect('pass', $this->deploy_copy_cmd_2, 1);

        $step = $this->createTestStep();
        $runner = $this->createTestStepRunner($exec, Flags::FLAG_DEPLOY_COPY | Flags::FLAGS, array(null, 'php://output'));

        $this->expectOutputRegex('{^pipelines: deploy copy failure}');
        $status = $runner->runStep($step);
        $this->assertSame(1, $status);
    }

    public function testKeepContainerOnErrorWithNonExistentContainer()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1, 'no id for name of potential re-use')
            ->expect('capture', 'docker', 0, 'run the container')
            ->expect('pass', '~ docker exec ~', 255)
        ;

        $this->keepContainerOnErrorExecTest($exec);
    }

    public function testKeepContainerOnErrorWithExistingContainer()
    {
        $containerId = 'face42face42';

        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', $containerId) # id for name of potential re-use
            ->expect('pass', '~ docker exec ~', 255)
        ;

        $this->keepContainerOnErrorExecTest($exec, $containerId);
    }

    public function testZapExistingContainer()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', "123456789\n", 'zap: docker ps')
            ->expect('capture', 'docker', "123456789\n", 'zap: docker kill')
            ->expect('capture', 'docker', "123456789\n", 'zap: docker rm')
            ->expect('capture', 'docker', 0, 'docker run')
            ->expect('pass', $this->deploy_copy_cmd, 0, 'deploy copy stage 1')
            ->expect('pass', $this->deploy_copy_cmd_2, 0, 'deploy copy stage 1')
            ->expect('pass', '~ docker exec ~', 0)
            ->expect('capture', 'docker', 0, 'docker kill')
            ->expect('capture', 'docker', 0, 'docker rm')
        ;

        $runner = $this->createTestStepRunner($exec, Flags::FLAG_DEPLOY_COPY | Flags::FLAGS);
        $step = $this->createTestStep();

        $status = $runner->runStep($step);
        $this->assertSame(0, $status);
    }

    public function testKeepExistingContainer()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', "123456789\n", 'existing id')
            ->expect('pass', '~ docker exec ~', 0)
        ;

        $runner = $this->createTestStepRunner($exec, (Flags::FLAG_DOCKER_KILL | Flags::FLAG_DOCKER_REMOVE) ^ Flags::FLAGS);
        $step = $this->createTestStep();

        $status = $runner->runStep($step);
        $this->assertSame(0, $status);
    }

    public function testDockerHubImageLogin()
    {
        $exec = new Exec();
        $exec->setActive(false);

        $step = $this->createTestStep(array(
            'image' => array(
                'name' => 'foo/bar:latest',
                'username' => 'user',
                'password' => 'secret',
            ),
        ));
        $runner = $this->createTestStepRunner($exec, null, array(null, 'php://output'), array('PIPELINES_PARENT_CONTAINER_NAME' => 'foo'));

        $this->expectOutputString('');
        $status = $runner->runStep($step);
        $this->assertSame(0, $status);
    }

    public function testMountInCopyFails()
    {
        $exec = new Exec();
        $exec->setActive(false);

        $step = $this->createTestStep();
        $this->setTestProject('/app'); # fake test-directory as if being inside a container FIXME(tk): hard encoded /app
        $runner = $this->createTestStepRunner($exec, null, array(null, 'php://output'), array('PIPELINES_PARENT_CONTAINER_NAME' => 'foo'));

        $this->expectOutputString("pipelines: fatal: can not detect /app mount point. preventing new container.\n");
        $status = $runner->runStep($step);
        $this->assertSame(1, $status, 'non-zero status as mounting not possible with mock');
    }

    /* docker client tests */

    /**
     * @covers \Ktomk\Pipelines\Runner\StepRunner::deployDockerClient()
     */
    public function testDockerClientInjection()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1, 'no id for name of potential re-use')
            ->expect('capture', 'docker', 0, 'run the container')
            ->expect('capture', '~ docker$~', 0, 'inject (copy docker binary into container)')
            ->expect('pass', '~ docker exec ~', 0, 'run step script')
            ->expect('capture', 'docker', 0, 'kill')
            ->expect('capture', 'docker', 0, 'rm');

        $testDirectories = new Directories($_SERVER, $this->getTestProject());

        $testRepository = $this->getMockBuilder('Ktomk\Pipelines\Runner\Docker\Binary\Repository')
            ->setConstructorArgs(array($exec, array(), UnPackager::fromDirectories($exec, $testDirectories)))
            ->setMethods(array('getLocalBinary'))
            ->getMock()
        ;
        $testRepository->method('getLocalBinary')->willReturn(__DIR__ . '/../../data/package/docker-test-stub');

        /** @var MockObject|StepRunner $mockRunner */
        $mockRunner = $this->getMockBuilder('Ktomk\Pipelines\Runner\StepRunner')
            ->setConstructorArgs(array(
                RunOpts::create('pipelines-unit-test'),
                $testDirectories,
                $exec,
                new Flags(),
                Env::create(),
                new Streams()
            ))
            ->setMethods(array('getDockerBinaryRepository'))
            ->getMock();
        $mockRunner->method('getDockerBinaryRepository')->willReturn($testRepository);

        $step = $this->createTestStep(array('services' => array('docker')));
        $actual = $mockRunner->runStep($step);
        $this->assertSame(0, $actual);
    }

    public function testDockerClientInjectionFailure()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1, 'no id for name of potential re-use')
            ->expect('capture', 'docker', 0, 'run the container')
            ->expect('capture', '~ docker$~', 63, 'inject (copy docker binary into container)');

        $testDirectories = new Directories($_SERVER, $this->getTestProject());

        $testRepository = $this->getMockBuilder('Ktomk\Pipelines\Runner\Docker\Binary\Repository')
            ->setConstructorArgs(array($exec, array(), UnPackager::fromDirectories($exec, $testDirectories)))
            ->setMethods(array('getLocalBinary'))
            ->getMock()
        ;
        $testRepository->method('getLocalBinary')->willReturn(__DIR__ . '/../../data/package/docker-test-stub');

        /** @var MockObject|StepRunner $mockRunner */
        $mockRunner = $this->getMockBuilder('Ktomk\Pipelines\Runner\StepRunner')
            ->setConstructorArgs(array(
                RunOpts::create('pipelines-unit-test'),
                $testDirectories,
                $exec,
                new Flags(),
                Env::create(),
                new Streams()
            ))
            ->setMethods(array('getDockerBinaryRepository'))
            ->getMock();
        $mockRunner->method('getDockerBinaryRepository')->willReturn($testRepository);

        $step = $this->createTestStep(array('services' => array('docker')));
        $actual = $mockRunner->runStep($step);
        $this->assertSame(63, $actual);
    }

    /**
     *
     */
    public function testDockerClientInjectInvalidPackage()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1, 'no id for name of potential re-use')
            ->expect('capture', 'docker', 0, 'run the container');

        $testDirectories = new Directories($_SERVER, $this->getTestProject());

        /** @var MockObject|StepRunner $mockRunner */
        $mockRunner = $this->getMockBuilder('Ktomk\Pipelines\Runner\StepRunner')
            ->setConstructorArgs(array(
                RunOpts::create('pipelines-unit-test', 'wrong-package'),
                $testDirectories,
                $exec,
                new Flags(),
                Env::create(),
                new Streams()
            ))
            ->setMethods(null)
            ->getMock();

        $step = $this->createTestStep(array('services' => array('docker')));
        $this->setExpectedException('\InvalidArgumentException', 'not a readable file');
        $mockRunner->runStep($step);
        $this->fail('an expected exception was not thrown');
    }

    /* artifact tests */

    public function testArtifacts()
    {
        $tmpProjectDir = $this->getTestProject();

        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1)
            ->expect('capture', 'docker', 0)
            ->expect('pass', $this->deploy_copy_cmd, 0)
            ->expect('pass', $this->deploy_copy_cmd_2, 0)
            ->expect('pass', '~ docker exec ~', 0)
            ->expect('capture', 'docker', './build/foo-package.tgz')
            ->expect('pass', 'docker exec -w /app \'*dry-run*\' tar c -f - build/foo-package.tgz | tar x -f - -C ' . $tmpProjectDir, 0)
            ->expect('capture', 'docker', 0, 'docker kill')
            ->expect('capture', 'docker', 0, 'docker rm')
        ;

        $step = $this->createTestStep(array('artifacts' => array('build/foo-package.tgz')));
        $runner = $this->createTestStepRunner($exec, Flags::FLAG_DEPLOY_COPY  | Flags::FLAGS);

        $status = $runner->runStep($step);
        $this->assertSame(0, $status);
    }

    public function testArtifactsNoMatch()
    {
        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1)
            ->expect('capture', 'docker', 0)
            ->expect('pass', $this->deploy_copy_cmd, 0)
            ->expect('pass', $this->deploy_copy_cmd_2, 0)
            ->expect('pass', '~ docker exec ~', 0)
            ->expect('capture', 'docker', './build/foo-package.tgz')
            ->expect('capture', 'docker', 0) # docker kill
            ->expect('capture', 'docker', 0) # docker rm
        ;

        $step = $this->createTestStep(array('artifacts' => array('build/bar-package.tgz')));
        $runner = $this->createTestStepRunner($exec, Flags::FLAG_DEPLOY_COPY  | Flags::FLAGS);

        $status = $runner->runStep($step);
        $this->assertSame(0, $status);
    }

    public function testArtifactsFailure()
    {
        $tmpProjectDir = $this->getTestProject();

        $exec = new ExecTester($this);
        $exec
            ->expect('capture', 'docker', 1)
            ->expect('capture', 'docker', 0)
            ->expect('pass', $this->deploy_copy_cmd, 0)
            ->expect('pass', $this->deploy_copy_cmd_2, 0)
            ->expect('pass', '~ docker exec ~', 0)
            ->expect('capture', 'docker', './build/foo-package.tgz')
            ->expect(
                'pass',
                'docker exec -w /app \'*dry-run*\' tar c -f - build/foo-package.tgz | tar x -f - -C ' . $tmpProjectDir,
                1
            )
            ->expect('capture', 'docker', 0, 'docker kill')
            ->expect('capture', 'docker', 0, 'docker rm')
        ;

        $this->expectOutputRegex('~^pipelines: Artifact failure: \'build/foo-package.tgz\' \\(1, 1 paths, 1\\d\\d bytes\\)$~m');
        $step = $this->createTestStep(array('artifacts' => array('build/foo-package.tgz')));
        $runner = $this->createTestStepRunner($exec, Flags::FLAG_DEPLOY_COPY  | Flags::FLAGS, array(null, 'php://output'));

        $status = $runner->runStep($step);
        $this->assertSame(0, $status);
    }

    /* public/new */

    public function testGetDockerBinaryRepository()
    {
        $exec = new ExecTester($this);
        $runner = new StepRunner(
            RunOpts::create('foo', Repository::PKG_TEST),
            new Directories($_SERVER, 'foo'),
            $exec,
            new Flags(),
            new Env(),
            new Streams()
        );
        $actual = $runner->getDockerBinaryRepository();
        $this->assertInstanceOf('Ktomk\Pipelines\Runner\Docker\Binary\Repository', $actual);
    }

    private function keepContainerOnErrorExecTest(ExecTester $exec, $id = '*dry-run*')
    {
        $expectedRegex = sprintf(
            '{script non-zero exit status: 255\nerror, keeping container id %s}',
            preg_quote($id, '{}')
        );

        $runner = $this->createTestStepRunner($exec, Flags::FLAGS | Flags::FLAG_KEEP_ON_ERROR, array(null, 'php://output'));
        $step = $this->createTestStep(); # 'script' => array('fatal me an error') not necessary

        $this->expectOutputRegex($expectedRegex);
        $status = $runner->runStep($step);
        $this->assertSame(255, $status);
    }

    /**
     * @param Exec $exec
     * @param int $flags [optional] to override default flags
     * @param null|array|string $outErr [optional]
     * @param array $inherit [optional] inherit from environment
     * @return StepRunner
     */
    private function createTestStepRunner(Exec $exec, $flags = null, $outErr = null, array $inherit = array())
    {
        list($out, $err) = ((array)$outErr) + array(null, null);

        $testProject = $this->getTestProject();

        $options = OptionsMock::create();

        if (('/app' !== $testProject) && is_dir($testProject)) { // FIXME(tk): hard encoded /app
            // fake docker.sock file inside temporary test directory so it exists
            $value = $testProject . '/var';
            LibFs::mkDir($value);
            $value .= '/run';
            LibFs::mkDir($value);
            $value .= '/docker.sock';
            touch($value);
        } else {
            $value = LibTmp::tmpFilePut('');
        }

        $options->define('docker.socket.path', $value);
        $runOpts = new RunOpts('pipelines-unit-test', $options);

        return new StepRunner(
            $runOpts,
            new Directories($_SERVER, $testProject),
            $exec,
            new Flags($flags),
            Env::create($inherit),
            new Streams(null, $out, $err)
        );
    }
}
