<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Cache;

use Doctrine\Common\Cache\FlushableCache;
use DoctrineModule\Service\CachePool\RedisPoolFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Cache\CacheItemPoolInterface;
use Redis;
use Symfony\Component\Cache\Adapter\RedisAdapter;

use function extension_loaded;
use function getenv;
use function is_string;

/**
 * Requires a disposable Redis instance, e.g.:
 *   DOCTRINE_MODULE_TEST_REDIS_DSN=redis://localhost:6379/15 vendor/bin/phpunit
 *
 * @coversNothing
 */
class RedisLegacyCacheTest extends LegacyCacheBehaviourTestCase
{
    private string $dsn;

    protected function setUp(): void
    {
        $dsn = getenv('DOCTRINE_MODULE_TEST_REDIS_DSN');

        if (! extension_loaded('redis') || ! is_string($dsn) || $dsn === '') {
            $this->markTestSkipped('ext-redis and DOCTRINE_MODULE_TEST_REDIS_DSN are required');
        }

        $this->dsn = $dsn;
    }

    protected function createPool(string $namespace): CacheItemPoolInterface
    {
        return (new RedisPoolFactory())(
            new ServiceManager(),
            'doctrinemodule.cache.redis',
            ['namespace' => $namespace, 'dsn' => $this->dsn],
        );
    }

    public function testFlushKeepsUnrelatedRedisData(): void
    {
        $redis = RedisAdapter::createConnection($this->dsn);
        $this->assertInstanceOf(Redis::class, $redis);
        $redis->set('unrelated:application:key', 'keep-me');

        $cache = $this->createCache('DoctrineModuleTest.flush');
        $cache->save('key', 'value');
        $this->assertInstanceOf(FlushableCache::class, $cache);
        $this->assertTrue($cache->flushAll());

        $this->assertFalse($cache->contains('key'));
        $this->assertSame('keep-me', $redis->get('unrelated:application:key'));

        $redis->del('unrelated:application:key');
    }
}
