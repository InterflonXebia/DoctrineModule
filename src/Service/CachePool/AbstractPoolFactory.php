<?php

declare(strict_types=1);

namespace DoctrineModule\Service\CachePool;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Base factory for the Symfony Cache pools exposed as "doctrinemodule.cache.*"
 *
 * Options are read from the "doctrine_module.cache_pools.{service name}" configuration key.
 *
 * @internal
 */
abstract class AbstractPoolFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @param mixed[]|null $options
     */
    final public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array|null $options = null,
    ): CacheItemPoolInterface {
        if ($options === null) {
            $config  = $container->has('config') ? $container->get('config') : [];
            $options = $config['doctrine_module']['cache_pools'][$requestedName] ?? [];
        }

        if (! is_array($options)) {
            throw new RuntimeException(sprintf(
                'Configuration "doctrine_module.cache_pools.%s" must be an array.',
                $requestedName,
            ));
        }

        return $this->createPool((string) $requestedName, $options);
    }

    /** @param mixed[] $options */
    abstract protected function createPool(string $serviceName, array $options): CacheItemPoolInterface;

    /**
     * Persistent pools share their backend with other applications or pools, so they must always be
     * isolated by an explicit namespace. An empty namespace would also make clear() wipe the whole
     * backend (e.g. FLUSHDB on Redis).
     *
     * @param mixed[] $options
     *
     * @return non-empty-string
     */
    protected function getNamespace(string $serviceName, array $options): string
    {
        $namespace = $options['namespace'] ?? null;

        if (! is_string($namespace) || $namespace === '') {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" requires a non-empty "namespace" option in "doctrine_module.cache_pools.%s".',
                $serviceName,
                $serviceName,
            ));
        }

        if (! preg_match('/^[-+.A-Za-z0-9]+$/D', $namespace)) {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" has an invalid namespace "%s"; only characters [-+.A-Za-z0-9] are allowed.',
                $serviceName,
                $namespace,
            ));
        }

        return $namespace;
    }

    /** @param mixed[] $options */
    protected function getDefaultLifetime(string $serviceName, array $options): int
    {
        $lifetime = $options['default_lifetime'] ?? 0;

        if (! is_int($lifetime) || $lifetime < 0) {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" option "default_lifetime" must be a non-negative integer.',
                $serviceName,
            ));
        }

        return $lifetime;
    }

    /**
     * @param mixed[] $options
     *
     * @return mixed[]
     */
    protected function getConnectionOptions(string $serviceName, array $options): array
    {
        $connectionOptions = $options['connection_options'] ?? [];

        if (! is_array($connectionOptions)) {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" option "connection_options" must be an array.',
                $serviceName,
            ));
        }

        return $connectionOptions;
    }
}
