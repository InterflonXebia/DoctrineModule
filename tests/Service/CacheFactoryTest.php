<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Service;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use DoctrineModule\Service\CacheFactory;
use DoctrineModuleTest\Service\TestAsset\LegacyArrayCache;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/** @covers \DoctrineModule\Service\CacheFactory */
class CacheFactoryTest extends BaseTestCase
{
    /** @param array<string, mixed> $cacheConfig */
    private function createServiceManager(array $cacheConfig): ServiceManager
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService('config', ['doctrine' => ['cache' => ['phpunit' => $cacheConfig]]]);

        return $serviceManager;
    }

    public function testWrapsConfiguredPoolInDoctrineProviderByDefault(): void
    {
        $pool           = new ArrayAdapter();
        $serviceManager = $this->createServiceManager(['instance' => 'my-pool']);
        $serviceManager->setService('my-pool', $pool);

        $cache = (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');

        $this->assertInstanceOf(DoctrineProvider::class, $cache);
        $this->assertSame($pool, $cache->getPool());
        $this->assertSame('', $cache->getNamespace());
    }

    public function testAppliesConfiguredNamespace(): void
    {
        $pool           = new ArrayAdapter();
        $serviceManager = $this->createServiceManager([
            'class' => DoctrineProvider::class,
            'instance' => 'my-pool',
            'namespace' => 'DoctrineModule',
        ]);
        $serviceManager->setService('my-pool', $pool);

        $cache = (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');

        $this->assertInstanceOf(DoctrineProvider::class, $cache);
        $this->assertSame('DoctrineModule', $cache->getNamespace());

        $cache->save('foo', 'bar');
        $this->assertFalse($pool->hasItem('foo'), 'Namespace must prefix the stored key');
        $this->assertSame('bar', $cache->fetch('foo'));
    }

    public function testUsesContainerProvidedLegacyCache(): void
    {
        $legacyCache    = new LegacyArrayCache();
        $serviceManager = $this->createServiceManager(['class' => 'my-legacy-cache', 'namespace' => 'ns']);
        $serviceManager->setService('my-legacy-cache', $legacyCache);

        $cache = (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');

        $this->assertSame($legacyCache, $cache);
        $this->assertSame('ns', $legacyCache->getNamespace());
    }

    public function testInstantiatesCustomLegacyCacheClass(): void
    {
        $serviceManager = $this->createServiceManager(['class' => LegacyArrayCache::class]);

        $cache = (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');

        $this->assertInstanceOf(LegacyArrayCache::class, $cache);
    }

    public function testRejectsMissingInstance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires an "instance" option');

        (new CacheFactory('phpunit'))->__invoke($this->createServiceManager([]), 'doctrine.cache.phpunit');
    }

    public function testRejectsUnregisteredInstance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"missing-pool", which is not registered');

        (new CacheFactory('phpunit'))->__invoke(
            $this->createServiceManager(['instance' => 'missing-pool']),
            'doctrine.cache.phpunit',
        );
    }

    public function testRejectsInstanceThatIsNotPsr6Pool(): void
    {
        $serviceManager = $this->createServiceManager(['instance' => 'not-a-pool']);
        $serviceManager->setService('not-a-pool', new LegacyArrayCache());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement Psr\Cache\CacheItemPoolInterface');

        (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');
    }

    public function testRejectsRemovedLaminasStorageCacheClass(): void
    {
        $serviceManager = $this->createServiceManager([
            'class' => 'DoctrineModule\Cache\LaminasStorageCache',
            'instance' => 'doctrinemodule.cache.array',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"class" => Doctrine\Common\Cache\Psr6\DoctrineProvider::class');

        (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');
    }

    public function testRejectsServiceThatIsNotLegacyCache(): void
    {
        $serviceManager = $this->createServiceManager(['class' => 'my-service']);
        $serviceManager->setService('my-service', new stdClass());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement Doctrine\Common\Cache\Cache, got stdClass');

        (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');
    }

    public function testDoesNotReturnRawPsr6PoolForOrmCacheSetters(): void
    {
        $serviceManager = $this->createServiceManager(['class' => 'my-pool']);
        $serviceManager->setService('my-pool', new ArrayAdapter());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement Doctrine\Common\Cache\Cache');

        (new CacheFactory('phpunit'))->__invoke($serviceManager, 'doctrine.cache.phpunit');
    }
}
