<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\Utility;

use Ktomk\Pipelines\Cli\Args;
use Ktomk\Pipelines\Cli\ArgsException;
use Ktomk\Pipelines\Cli\Exec;
use Ktomk\Pipelines\Cli\Streams;
use Ktomk\Pipelines\Lib;
use Ktomk\Pipelines\Runner\Directories;
use Ktomk\Pipelines\Runner\Docker\Binary\Repository;
use Ktomk\Pipelines\Runner\RunOpts;
use Prophecy\Exception\Prediction\AggregateException;

/**
 * aggregated args parser for RunOpts / runner options
 *
 * @package Ktomk\Pipelines\Utility\Args
 */
class RunnerOptions
{
    /**
     * @var Args
     */
    private $args;
    /**
     * @var Streams
     */
    private $streams;

    /**
     * @param Args $args
     * @param Streams $streams
     *
     * @return RunnerOptions
     */
    public static function bind(Args $args, Streams $streams)
    {
        return new self($args, $streams);
    }

    /**
     * the repository used for listing and validation
     *
     * @return Repository
     */
    public static function createRepository()
    {
        return Repository::create(
            new Exec(),
            new Directories(Lib::env($_SERVER), 'fake')
        );
    }

    /**
     * list all statically available docker client package names
     * that ship w/ pipelines
     *
     * @param Streams $streams
     */
    public static function listPackages(Streams $streams)
    {
        $list = self::createRepository()->listPackages();

        $streams->out(implode("\n", $list));
        $streams->out("\n");
    }

    /**
     * @param Args $args
     * @param Streams $streams
     */
    public function __construct(Args $args, Streams $streams)
    {
        $this->args = $args;
        $this->streams = $streams;
    }

    /**
     * @throws ArgsException
     * @throws StatusException
     * @return RunOpts
     */
    public function run()
    {
        $runOpts = RunOpts::create();
        $this->parse($this->args, $runOpts);

        return $runOpts;
    }

    /**
     * Parse keep arguments
     *
     * @param Args $args
     * @param RunOpts $runOpts
     *
     * @throws ArgsException
     * @throws StatusException
     */
    public function parse(Args $args, RunOpts $runOpts)
    {
        $runOpts->setPrefix($this->parsePrefix($args));

        $runOpts->setBinaryPackage($this->parseDockerClient($args));

        $this->parseDockerClientListPackages($args);
    }

    /**
     * @param Args $args
     * @throws ArgsException
     * @return string
     */
    private function parseDockerClient(Args $args)
    {
        $default = Repository::PKG_INTEGRATE;
        $binaryClient = $args->getOptionArgument('docker-client', $default);
        if ($binaryClient !== $default) {
            $repository = self::createRepository();

            try {
                $repository->resolve($binaryClient);
            } catch (\InvalidArgumentException $ex) {
                $message = "--docker-client needs a valid package name, file or docker client binary path; '${binaryClient}' given";
                $message .= "\n  docker client binary packages shipping w/ pipelines:";
                $message .= "\n    - " . implode("\n    - ", $repository->listPackages());
                ArgsException::__($message);
            }
        }

        return $binaryClient;
    }

    /**
     * @param Args $args
     *
     * @throws StatusException
     */
    private function parseDockerClientListPackages(Args $args)
    {
        if (!$args->hasOption('docker-client-pkgs')) {
            return;
        }

        self::listPackages($this->streams);

        throw new StatusException('', 0);
    }

    /**
     * @param Args $args
     * @throws ArgsException
     * @return string
     */
    private function parsePrefix(Args $args)
    {
        $prefix = $args->getOptionArgument('prefix', App::UTILITY_NAME);
        if (!preg_match('~^[a-z]{3,}$~', $prefix)) {
            ArgsException::__(sprintf("invalid prefix: '%s'", $prefix));
        }

        return $prefix;
    }
}
