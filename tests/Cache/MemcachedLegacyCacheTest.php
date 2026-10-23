<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Cache;

use DoctrineModule\Service\CachePool\MemcachedPoolFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

use function getenv;
use function is_string;

/**
 * Requires a disposable Memcached instance, e.g.:
 *   DOCTRINE_MODULE_TEST_MEMCACHED_DSN=memcached://localhost:11211 vendor/bin/phpunit
 *
 * @coversNothing
 */
class MemcachedLegacyCacheTest extends LegacyCacheBehaviourTestCase
{
    private string $dsn;

    protected function setUp(): void
    {
        $dsn = getenv('DOCTRINE_MODULE_TEST_MEMCACHED_DSN');

        if (! MemcachedAdapter::isSupported() || ! is_string($dsn) || $dsn === '') {
            $this->markTestSkipped('ext-memcached and DOCTRINE_MODULE_TEST_MEMCACHED_DSN are required');
        }

        $this->dsn = $dsn;
    }

    protected function createPool(string $namespace): CacheItemPoolInterface
    {
        return (new MemcachedPoolFactory())(
            new ServiceManager(),
            'doctrinemodule.cache.memcached',
            ['namespace' => $namespace, 'servers' => [$this->dsn]],
        );
    }
}
