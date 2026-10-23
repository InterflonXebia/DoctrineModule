<?php

declare(strict_types=1);

namespace DoctrineModule\Service\CachePool;

use Predis\ClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\RedisAdapter;

use function extension_loaded;
use function interface_exists;
use function is_string;
use function sprintf;

/**
 * Creates a Symfony RedisAdapter on a dedicated, lazily-connected Redis connection built from the
 * configured DSN. No connection is opened until the pool is first used, and no shared application
 * connection is ever reused or reconfigured.
 */
final class RedisPoolFactory extends AbstractPoolFactory
{
    /**
     * {@inheritDoc}
     */
    protected function createPool(string $serviceName, array $options): CacheItemPoolInterface
    {
        if (! extension_loaded('redis') && ! interface_exists(ClientInterface::class)) {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" requires the "redis" PHP extension or the "predis/predis" package.',
                $serviceName,
            ));
        }

        $dsn = $options['dsn'] ?? null;

        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" requires a non-empty "dsn" option (e.g. "redis://localhost:6379") in '
                . '"doctrine_module.cache_pools.%s".',
                $serviceName,
                $serviceName,
            ));
        }

        $namespace  = $this->getNamespace($serviceName, $options);
        $lifetime   = $this->getDefaultLifetime($serviceName, $options);
        $connection = RedisAdapter::createConnection(
            $dsn,
            $this->getConnectionOptions($serviceName, $options) + ['lazy' => true],
        );

        return new RedisAdapter($connection, $namespace, $lifetime);
    }
}
