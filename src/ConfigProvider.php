<?php

declare(strict_types=1);

namespace DoctrineModule;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Laminas\Authentication\Storage\Session as LaminasSessionStorage;

/**
 * Config provider for DoctrineORMModule config
 */
final class ConfigProvider
{
    /** @return array<non-empty-string, mixed[]> */
    public function __invoke(): array
    {
        return [
            'doctrine' => $this->getDoctrineConfig(),
            'doctrine_factories' => $this->getDoctrineFactoryConfig(),
            'doctrine_module' => $this->getDoctrineModuleConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'validators' => $this->getValidatorConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration
     *
     * @return array<non-empty-string, array<non-empty-string, class-string>>
     */
    public function getDependencyConfig(): array
    {
        return [
            'invokables' => ['DoctrineModule\Authentication\Storage\Session' => LaminasSessionStorage::class],
            'factories' => [
                'doctrine.cli' => Service\CliFactory::class,
                'doctrinemodule.cache.apcu' => Service\CachePool\ApcuPoolFactory::class,
                'doctrinemodule.cache.array' => Service\CachePool\ArrayPoolFactory::class,
                'doctrinemodule.cache.filesystem' => Service\CachePool\FilesystemPoolFactory::class,
                'doctrinemodule.cache.memcached' => Service\CachePool\MemcachedPoolFactory::class,
                'doctrinemodule.cache.redis' => Service\CachePool\RedisPoolFactory::class,
            ],
            'abstract_factories' => ['DoctrineModule' => ServiceFactory\AbstractDoctrineServiceFactory::class],
        ];
    }

    /**
     * Default configuration for Doctrine module
     *
     * @return array<non-empty-string, mixed[]>
     */
    public function getDoctrineConfig(): array
    {
        return [
            'cache' => $this->getDoctrineCacheConfig(),

            //These authentication settings are a hack to tide things over until version 1.0
            //Normall doctrineModule should have no mention of odm or orm
            'authentication' => [
                //default authentication options should be set in either the odm or orm modules
                'odm_default' => [],
                'orm_default' => [],
            ],
            'authenticationadapter' => [
                'odm_default' => true,
                'orm_default' => true,
            ],
            'authenticationstorage' => [
                'odm_default' => true,
                'orm_default' => true,
            ],
            'authenticationservice' => [
                'odm_default' => true,
                'orm_default' => true,
            ],
        ];
    }

    /**
     * Factory mappings - used to define which factory to use to instantiate a particular doctrine service type
     *
     * @return array<non-empty-string, class-string>
     */
    public function getDoctrineFactoryConfig(): array
    {
        return [
            'cache' => Service\CacheFactory::class,
            'eventmanager' => Service\EventManagerFactory::class,
            'driver' => Service\DriverFactory::class,
            'authenticationadapter' => Service\Authentication\AdapterFactory::class,
            'authenticationstorage' => Service\Authentication\StorageFactory::class,
            'authenticationservice' => Service\Authentication\AuthenticationServiceFactory::class,
        ];
    }

    /** @return array<non-empty-string, mixed[]> */
    public function getValidatorConfig(): array
    {
        return [
            'aliases' => [
                'DoctrineNoObjectExists' => Validator\NoObjectExists::class,
                'DoctrineObjectExists' => Validator\ObjectExists::class,
                'DoctrineUniqueObject' => Validator\UniqueObject::class,
            ],
            'factories' => [
                Validator\NoObjectExists::class => Validator\Service\NoObjectExistsFactory::class,
                Validator\ObjectExists::class => Validator\Service\ObjectExistsFactory::class,
                Validator\UniqueObject::class => Validator\Service\UniqueObjectFactory::class,
            ],
        ];
    }

    /** @return array{cache_pools: array<non-empty-string, mixed[]>} */
    public function getDoctrineModuleConfig(): array
    {
        return ['cache_pools' => $this->getCachePoolsConfig()];
    }

    /**
     * Options for the Symfony Cache pools registered as "doctrinemodule.cache.*" services
     *
     * @return array<non-empty-string, mixed[]>
     */
    public function getCachePoolsConfig(): array
    {
        return [
            'doctrinemodule.cache.apcu' => ['namespace' => 'DoctrineModule'],
            'doctrinemodule.cache.array' => [],
            'doctrinemodule.cache.filesystem' => [
                'namespace' => 'DoctrineModule',
                'directory' => 'data/DoctrineModule/cache',
            ],
            'doctrinemodule.cache.memcached' => [
                'namespace' => 'DoctrineModule',
                'servers' => [],
            ],
            'doctrinemodule.cache.redis' => [
                'namespace' => 'DoctrineModule',
                'dsn' => 'redis://localhost:6379',
            ],
        ];
    }

    /**
     * Legacy doctrine/cache services, wrapping the Symfony Cache pools above, as doctrine/cache ^2.0
     * does not include any cache adapters anymore
     *
     * @return array<non-empty-string,array{class:class-string,instance?:string,namespace?:string,directory?:string}>
     */
    private function getDoctrineCacheConfig(): array
    {
        return [
            'apcu' => [
                'class' => DoctrineProvider::class,
                'instance' => 'doctrinemodule.cache.apcu',
            ],
            'array' => [
                'class' => DoctrineProvider::class,
                'instance' => 'doctrinemodule.cache.array',
            ],
            'filesystem' => [
                'class' => DoctrineProvider::class,
                'instance' => 'doctrinemodule.cache.filesystem',
            ],
            'memcached' => [
                'class' => DoctrineProvider::class,
                'instance' => 'doctrinemodule.cache.memcached',
            ],
            'redis' => [
                'class' => DoctrineProvider::class,
                'instance' => 'doctrinemodule.cache.redis',
            ],
        ];
    }
}
