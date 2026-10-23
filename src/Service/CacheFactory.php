<?php

declare(strict_types=1);

namespace DoctrineModule\Service;

use Doctrine\Common\Cache as DoctrineCache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use DoctrineModule\Options\Cache as CacheOptions;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function class_exists;
use function get_debug_type;
use function sprintf;

/**
 * Cache ServiceManager factory
 *
 * Creates legacy doctrine/cache services, as still required by the ORM/ODM cache setters. By default it
 * wraps the PSR-6 pool configured as "instance" in a {@see DoctrineProvider}.
 */
final class CacheFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @return DoctrineCache\Cache
     *
     * @throws RuntimeException
     */
    public function __invoke(ContainerInterface $container, $requestedName, array|null $options = null)
    {
        $options = $this->getOptions($container, 'cache');

        if (! $options instanceof CacheOptions) {
            throw new RuntimeException(sprintf(
                'Invalid options received, expected %s, got %s.',
                CacheOptions::class,
                $options::class,
            ));
        }

        $class = $options->getClass();

        if (! $class) {
            throw new RuntimeException('Cache must have a class name to instantiate');
        }

        if ($container->has($class)) {
            $cache = $container->get($class);
        } elseif ($class === DoctrineProvider::class) {
            $cache = DoctrineProvider::wrap($this->getPool($container, $options));
        } elseif (class_exists($class)) {
            $cache = new $class();
        } else {
            throw new RuntimeException(sprintf(
                'Cache "doctrine.cache.%s" is configured with class "%s", which is neither a container service '
                . 'nor an existing class. To use a PSR-6 pool, configure "class" => %s::class and set '
                . '"instance" to the pool\'s service name.',
                $this->getName(),
                $class,
                DoctrineProvider::class,
            ));
        }

        if (! $cache instanceof DoctrineCache\Cache) {
            throw new RuntimeException(sprintf(
                'Cache "doctrine.cache.%s" must implement %s, got %s. To use a PSR-6 pool, configure '
                . '"class" => %s::class and set "instance" to the pool\'s service name.',
                $this->getName(),
                DoctrineCache\Cache::class,
                get_debug_type($cache),
                DoctrineProvider::class,
            ));
        }

        if ($cache instanceof DoctrineCache\CacheProvider) {
            $namespace = $options->getNamespace();
            if ($namespace) {
                $cache->setNamespace($namespace);
            }
        }

        return $cache;
    }

    public function getOptionsClass(): string
    {
        return CacheOptions::class;
    }

    /** @throws RuntimeException */
    private function getPool(ContainerInterface $container, CacheOptions $options): CacheItemPoolInterface
    {
        $instance = $options->getInstance();

        if ($instance === null || $instance === '') {
            throw new RuntimeException(sprintf(
                'Cache "doctrine.cache.%s" requires an "instance" option naming a PSR-6 cache pool service.',
                $this->getName(),
            ));
        }

        if (! $container->has($instance)) {
            throw new RuntimeException(sprintf(
                'Cache "doctrine.cache.%s" refers to cache pool service "%s", which is not registered '
                . 'in the container.',
                $this->getName(),
                $instance,
            ));
        }

        $pool = $container->get($instance);

        if (! $pool instanceof CacheItemPoolInterface) {
            throw new RuntimeException(sprintf(
                'Cache pool service "%s" used by "doctrine.cache.%s" must implement %s, got %s.',
                $instance,
                $this->getName(),
                CacheItemPoolInterface::class,
                get_debug_type($pool),
            ));
        }

        return $pool;
    }
}
