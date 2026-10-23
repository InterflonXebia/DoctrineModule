<?php

declare(strict_types=1);

namespace DoctrineModule\Options;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Laminas\Stdlib\AbstractOptions;

/**
 * Cache options
 *
 * @template-extends AbstractOptions<mixed>
 */
final class Cache extends AbstractOptions
{
    /**
     * Class (or container service name) used to instantiate the legacy doctrine/cache instance.
     * The default wraps the PSR-6 pool referenced by "instance".
     */
    protected string $class = DoctrineProvider::class;

    /**
     * Namespace to prefix all cache ids with.
     */
    protected string $namespace = '';

    /**
     * Directory for file-based caching
     */
    protected string $directory;

    /**
     * Service name of the PSR-6 cache pool wrapped by the DoctrineProvider class.
     */
    protected string|null $instance = null;

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setInstance(string $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    public function getInstance(): string|null
    {
        return $this->instance;
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setDirectory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }
}
