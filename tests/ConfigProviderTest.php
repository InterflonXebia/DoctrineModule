<?php

declare(strict_types=1);

namespace DoctrineModuleTest;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use DoctrineModule\ConfigProvider;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use function serialize;
use function sys_get_temp_dir;
use function uniqid;
use function unserialize;

/**
 * Tests used to ensure ConfigProvider operates as expected
 */
class ConfigProviderTest extends TestCase
{
    public function testInvokeHasCorrectKeys(): void
    {
        $config = (new ConfigProvider())->__invoke();

        self::assertIsArray($config);

        self::assertArrayHasKey('doctrine', $config, 'Expected config to have "doctrine" array key');
        self::assertArrayHasKey(
            'doctrine_factories',
            $config,
            'Expected config to have "doctrine_factories" array key',
        );
        self::assertArrayHasKey('doctrine_module', $config, 'Expected config to have "doctrine_module" array key');
        self::assertArrayHasKey('dependencies', $config, 'Expected config to have "dependencies" array key');

        // Laminas Cache storage adapter configuration is no longer provided
        self::assertArrayNotHasKey('caches', $config, 'Config should not have "caches" array key');

        // Config Provider should not have service_manager key; should only exist in ZF Module
        self::assertArrayNotHasKey('service_manager', $config, 'Config should not have "service_manager" array key');

        self::assertSame($config, unserialize(serialize($config)));
    }

    public function testRegistersCachePoolFactories(): void
    {
        $factories = (new ConfigProvider())->getDependencyConfig()['factories'];

        foreach (['apcu', 'array', 'filesystem', 'memcached', 'redis'] as $name) {
            self::assertArrayHasKey('doctrinemodule.cache.' . $name, $factories);
        }
    }

    public function testPersistentPoolsHaveExplicitNamespaces(): void
    {
        foreach ((new ConfigProvider())->getCachePoolsConfig() as $name => $options) {
            if ($name === 'doctrinemodule.cache.array') {
                continue;
            }

            self::assertNotEmpty($options['namespace'] ?? null, $name . ' must have a namespace');
        }
    }

    public function testDependencyConfigurationProvidesCaches(): void
    {
        $config                                                                                   = (new ConfigProvider())->__invoke();
        $config['doctrine_module']['cache_pools']['doctrinemodule.cache.filesystem']['directory'] =
            sys_get_temp_dir() . '/' . uniqid('doctrine-module-', true);

        $serviceManager = new ServiceManager($config['dependencies']);
        $serviceManager->setService('config', $config);

        self::assertInstanceOf(ArrayAdapter::class, $serviceManager->get('doctrinemodule.cache.array'));
        self::assertInstanceOf(FilesystemAdapter::class, $serviceManager->get('doctrinemodule.cache.filesystem'));

        foreach (['array', 'filesystem'] as $name) {
            $cache = $serviceManager->get('doctrine.cache.' . $name);
            self::assertInstanceOf(DoctrineProvider::class, $cache);
            self::assertSame($serviceManager->get('doctrinemodule.cache.' . $name), $cache->getPool());
        }

        // Resolving the Redis service must not require a running Redis server
        self::assertTrue($serviceManager->has('doctrine.cache.redis'));
    }

    public function testDoctrineCompatibleCacheKeyConfiguration(): void
    {
        $options = (new ConfigProvider())->getCachePoolsConfig()['doctrinemodule.cache.filesystem'];
        $cache   = DoctrineProvider::wrap(new FilesystemAdapter(
            $options['namespace'],
            0,
            sys_get_temp_dir() . '/' . uniqid('doctrine-module-', true),
        ));
        $key     = 'MyTestKey[something\inside\here#with$specialChars]';
        $cache->save($key, 'foo');
        $this->assertEquals('foo', $cache->fetch($key));
    }
}
