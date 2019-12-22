<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\Runner;

use Ktomk\Pipelines\Utility\Options;

/**
 * Runner options parameter object
 */
class RunOpts
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var string
     */
    private $binaryPackage;

    /**
     * Static factory method
     *
     * @param string $prefix [optional]
     * @param string $binaryPackage package name or path to binary (string)
     *
     * @return RunOpts
     */
    public static function create($prefix = null, $binaryPackage = null)
    {
        return new self($prefix, Options::create(), $binaryPackage);
    }

    /**
     * RunOpts constructor.
     *
     * NOTE: All run options are optional by design (pass NULL).
     *
     * @param string $prefix
     * @param null|Options $options
     * @param string $binaryPackage package name or path to binary (string)
     */
    public function __construct($prefix = null, Options $options = null, $binaryPackage = null)
    {
        $this->prefix = $prefix;
        $this->options = $options;
        $this->binaryPackage = $binaryPackage;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * The prefix is used when creating containers for the container name.
     *
     * See --prefix option.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param string $name
     *
     * @return null|string
     */
    public function getOption($name)
    {
        if (!isset($this->options)) {
            return null;
        }

        return $this->options->get($name);
    }

    /**
     * @param string $binaryPackage
     */
    public function setBinaryPackage($binaryPackage)
    {
        $this->binaryPackage = $binaryPackage;
    }

    /**
     * @return string
     */
    public function getBinaryPackage()
    {
        return $this->binaryPackage;
    }
}
