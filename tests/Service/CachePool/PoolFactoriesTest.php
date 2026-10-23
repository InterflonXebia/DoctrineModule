<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Service\CachePool;

use DoctrineModule\Service\CachePool\AbstractPoolFactory;
use DoctrineModule\Service\CachePool\ApcuPoolFactory;
use DoctrineModule\Service\CachePool\ArrayPoolFactory;
use DoctrineModule\Service\CachePool\FilesystemPoolFactory;
use DoctrineModule\Service\CachePool\MemcachedPoolFactory;
use DoctrineModule\Service\CachePool\RedisPoolFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

use function extension_loaded;
use function is_dir;
use function sys_get_temp_dir;
use function uniqid;

/** @covers \DoctrineModule\Service\CachePool\AbstractPoolFactory */
class PoolFactoriesTest extends TestCase
{
    /** @param mixed[] $poolConfig */
    private function createServiceManager(string $serviceName, array $poolConfig): ServiceManager
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService('config', ['doctrine_module' => ['cache_pools' => [$serviceName => $poolConfig]]]);

        return $serviceManager;
    }

    /** @param mixed[] $poolConfig */
    private function create(AbstractPoolFactory $factory, array $poolConfig): object
    {
        return $factory($this->createServiceManager('phpunit.pool', $poolConfig), 'phpunit.pool');
    }

    /** @covers \DoctrineModule\Service\CachePool\ArrayPoolFactory */
    public function testCreatesArrayAdapter(): void
    {
        $this->assertInstanceOf(ArrayAdapter::class, $this->create(new ArrayPoolFactory(), []));
    }

    /** @covers \DoctrineModule\Service\CachePool\ArrayPoolFactory */
    public function testArrayAdapterWorksWithoutConfigService(): void
    {
        $this->assertInstanceOf(ArrayAdapter::class, (new ArrayPoolFactory())(new ServiceManager(), 'phpunit.pool'));
    }

    /** @covers \DoctrineModule\Service\CachePool\FilesystemPoolFactory */
    public function testCreatesFilesystemAdapterInNamespacedDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('doctrine-module-', true);

        $pool = $this->create(new FilesystemPoolFactory(), ['namespace' => 'DoctrineModule', 'directory' => $directory]);

        $this->assertInstanceOf(FilesystemAdapter::class, $pool);
        $this->assertTrue(is_dir($directory . '/DoctrineModule'));
    }

    /** @covers \DoctrineModule\Service\CachePool\FilesystemPoolFactory */
    public function testFilesystemRequiresDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires a non-empty "directory" option');

        $this->create(new FilesystemPoolFactory(), ['namespace' => 'DoctrineModule']);
    }

    /** @return array<string, array{mixed[], string}> */
    public static function invalidPersistentOptions(): array
    {
        return [
            'missing namespace' => [['directory' => '/tmp'], 'requires a non-empty "namespace" option'],
            'empty namespace' => [['namespace' => '', 'directory' => '/tmp'], 'requires a non-empty "namespace"'],
            'invalid namespace' => [['namespace' => 'a/b', 'directory' => '/tmp'], 'invalid namespace "a/b"'],
            'negative lifetime' => [
                ['namespace' => 'ns', 'directory' => '/tmp', 'default_lifetime' => -1],
                '"default_lifetime" must be a non-negative integer',
            ],
        ];
    }

    /**
     * @param mixed[] $options
     *
     * @dataProvider invalidPersistentOptions
     */
    public function testRejectsInvalidPersistentPoolOptions(array $options, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->create(new FilesystemPoolFactory(), $options);
    }

    public function testRejectsNonArrayPoolConfig(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService('config', ['doctrine_module' => ['cache_pools' => ['phpunit.pool' => 'nope']]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"doctrine_module.cache_pools.phpunit.pool" must be an array');

        (new ArrayPoolFactory())($serviceManager, 'phpunit.pool');
    }

    /** @covers \DoctrineModule\Service\CachePool\RedisPoolFactory */
    public function testRedisPoolIsCreatedWithoutConnecting(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is required');
        }

        // Nothing listens on this port: creating the pool must not open a connection.
        $pool = $this->create(new RedisPoolFactory(), ['namespace' => 'DoctrineModule', 'dsn' => 'redis://127.0.0.1:1']);

        $this->assertInstanceOf(RedisAdapter::class, $pool);
    }

    /** @covers \DoctrineModule\Service\CachePool\RedisPoolFactory */
    public function testRedisRequiresDsn(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is required');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires a non-empty "dsn" option');

        $this->create(new RedisPoolFactory(), ['namespace' => 'DoctrineModule']);
    }

    /** @covers \DoctrineModule\Service\CachePool\RedisPoolFactory */
    public function testRedisRequiresNamespace(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is required');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires a non-empty "namespace" option');

        $this->create(new RedisPoolFactory(), ['dsn' => 'redis://127.0.0.1:1']);
    }

    /** @covers \DoctrineModule\Service\CachePool\RedisPoolFactory */
    public function testRedisWithoutClientLibraryFailsClearly(): void
    {
        if (extension_loaded('redis')) {
            $this->markTestSkipped('Only relevant without ext-redis');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires the "redis" PHP extension or the "predis/predis" package');

        $this->create(new RedisPoolFactory(), ['namespace' => 'DoctrineModule', 'dsn' => 'redis://127.0.0.1:1']);
    }

    /** @covers \DoctrineModule\Service\CachePool\ApcuPoolFactory */
    public function testApcu(): void
    {
        if (ApcuAdapter::isSupported()) {
            $this->assertInstanceOf(ApcuAdapter::class, $this->create(new ApcuPoolFactory(), ['namespace' => 'ns']));

            return;
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires the "apcu" PHP extension');

        $this->create(new ApcuPoolFactory(), ['namespace' => 'ns']);
    }

    /** @covers \DoctrineModule\Service\CachePool\MemcachedPoolFactory */
    public function testMemcachedRequiresServers(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            MemcachedAdapter::isSupported() ? 'requires at least one entry' : 'requires the "memcached" PHP extension',
        );

        $this->create(new MemcachedPoolFactory(), ['namespace' => 'ns', 'servers' => []]);
    }

    /** @covers \DoctrineModule\Service\CachePool\MemcachedPoolFactory */
    public function testCreatesMemcachedAdapter(): void
    {
        if (! MemcachedAdapter::isSupported()) {
            $this->markTestSkipped('ext-memcached is required');
        }

        $pool = $this->create(new MemcachedPoolFactory(), [
            'namespace' => 'ns',
            'servers' => ['memcached://127.0.0.1:1'],
        ]);

        $this->assertInstanceOf(MemcachedAdapter::class, $pool);
    }
}
