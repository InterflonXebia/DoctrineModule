<?php

declare(strict_types=1);

namespace DoctrineModule\Service\CachePool;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use function is_string;
use function sprintf;

/**
 * Creates a Symfony FilesystemAdapter. Entries are stored in "{directory}/{namespace}".
 */
final class FilesystemPoolFactory extends AbstractPoolFactory
{
    /**
     * {@inheritDoc}
     */
    protected function createPool(string $serviceName, array $options): CacheItemPoolInterface
    {
        $directory = $options['directory'] ?? null;

        if (! is_string($directory) || $directory === '') {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" requires a non-empty "directory" option in "doctrine_module.cache_pools.%s".',
                $serviceName,
                $serviceName,
            ));
        }

        return new FilesystemAdapter(
            $this->getNamespace($serviceName, $options),
            $this->getDefaultLifetime($serviceName, $options),
            $directory,
        );
    }
}
