<?php

declare(strict_types=1);

namespace DoctrineModule\Service\CachePool;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

use function is_array;
use function sprintf;

/**
 * Creates a Symfony MemcachedAdapter on a dedicated Memcached client.
 *
 * Memcached cannot delete keys by prefix, so namespace versioning is enabled: clear() invalidates
 * only this pool's namespace instead of flushing the whole server.
 */
final class MemcachedPoolFactory extends AbstractPoolFactory
{
    /**
     * {@inheritDoc}
     */
    protected function createPool(string $serviceName, array $options): CacheItemPoolInterface
    {
        if (! MemcachedAdapter::isSupported()) {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" requires the "memcached" PHP extension.',
                $serviceName,
            ));
        }

        $servers = $options['servers'] ?? [];

        if (! is_array($servers) || $servers === []) {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" requires at least one entry in "doctrine_module.cache_pools.%s.servers" '
                . '(e.g. "memcached://localhost:11211").',
                $serviceName,
                $serviceName,
            ));
        }

        $client = MemcachedAdapter::createConnection(
            $servers,
            $this->getConnectionOptions($serviceName, $options),
        );

        $pool = new MemcachedAdapter(
            $client,
            $this->getNamespace($serviceName, $options),
            $this->getDefaultLifetime($serviceName, $options),
        );
        $pool->enableVersioning();

        return $pool;
    }
}
