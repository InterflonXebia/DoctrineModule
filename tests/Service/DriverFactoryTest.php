<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Service;

use Doctrine\Common\Annotations\PsrCachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver as MongoDBODMAnnotationDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver as MongoDBODMAttributeDriver;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver as ORMAnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver as ORMAttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use DoctrineModule\Service\DriverFactory;
use DoctrineModuleTest\Service\Mock\MetadataDriverMock;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Base test case to be used when a service manager instance is required
 */
class DriverFactoryTest extends BaseTestCase
{
    public function testCreateDriver(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            'config',
            [
                'doctrine' => [
                    'driver' => [
                        'testDriver' => ['class' => MetadataDriverMock::class],
                    ],
                ],
            ],
        );

        $factory = new DriverFactory('testDriver');
        $driver  = $factory->__invoke($serviceManager, MetadataDriverMock::class);
        $this->assertInstanceOf(MetadataDriverMock::class, $driver);
    }

    public function testCreateDriverChain(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            'config',
            [
                'doctrine' => [
                    'driver' => [
                        'testDriver' => ['class' => MetadataDriverMock::class],
                        'testChainDriver' => [
                            'class' => MappingDriverChain::class,
                            'drivers' => [
                                'Foo\Bar' => 'testDriver',
                                'Foo\Baz' => null,
                            ],
                        ],
                    ],
                ],
            ],
        );

        $factory = new DriverFactory('testChainDriver');
        $driver  = $factory->__invoke($serviceManager, MappingDriverChain::class);
        $this->assertInstanceOf(MappingDriverChain::class, $driver);

        $drivers = $driver->getDrivers();
        $this->assertCount(1, $drivers);
        $this->assertArrayHasKey('Foo\Bar', $drivers);
        $this->assertInstanceOf(MetadataDriverMock::class, $drivers['Foo\Bar']);
    }

    /** @requires PHP 8.0 */
    public function testCreateORMAttributeDriver(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            'config',
            [
                'doctrine' => [
                    'driver' => [
                        'testDriver' => ['class' => ORMAttributeDriver::class],
                    ],
                ],
            ],
        );

        $factory = new DriverFactory('testDriver');
        $driver  = $factory->__invoke($serviceManager, ORMAttributeDriver::class);
        $this->assertInstanceOf(ORMAttributeDriver::class, $driver);
    }

    /** @requires PHP 8.0 */
    public function testCreateMongoDBODMAttributeDriver(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            'config',
            [
                'doctrine' => [
                    'driver' => [
                        'testDriver' => ['class' => MongoDBODMAttributeDriver::class],
                    ],
                ],
            ],
        );

        $factory = new DriverFactory('testDriver');
        $driver  = $factory->__invoke($serviceManager, MongoDBODMAttributeDriver::class);
        $this->assertInstanceOf(MongoDBODMAttributeDriver::class, $driver);
    }

    public function testCreateORMAnnotationDriver(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            'config',
            [
                'doctrine' => [
                    'driver' => [
                        'testDriver' => ['class' => ORMAnnotationDriver::class],
                    ],
                ],
            ],
        );
        $serviceManager->setService(
            'doctrine.cache.array',
            DoctrineProvider::wrap(new ArrayAdapter()),
        );

        $factory = new DriverFactory('testDriver');
        $driver  = $factory->__invoke($serviceManager, ORMAnnotationDriver::class);
        $this->assertInstanceOf(ORMAnnotationDriver::class, $driver);
        $this->assertInstanceOf(Reader::class, $driver->getReader());
    }

    public function testCreateMongoDBODMAnnotationDriver(): void
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            'config',
            [
                'doctrine' => [
                    'driver' => [
                        'testDriver' => ['class' => MongoDBODMAnnotationDriver::class],
                    ],
                ],
            ],
        );
        $serviceManager->setService(
            'doctrine.cache.array',
            DoctrineProvider::wrap(new ArrayAdapter()),
        );

        $factory = new DriverFactory('testDriver');
        $driver  = $factory->__invoke($serviceManager, MongoDBODMAnnotationDriver::class);
        $this->assertInstanceOf(MongoDBODMAnnotationDriver::class, $driver);
        $this->assertInstanceOf(Reader::class, $driver->getReader());
    }

    private function createAnnotationDriver(string $cache, object $cacheService): ORMAnnotationDriver
    {
        $serviceManager = new ServiceManager();
        $serviceManager->setService(
            'config',
            [
                'doctrine' => [
                    'driver' => [
                        'testDriver' => ['class' => ORMAnnotationDriver::class, 'cache' => $cache],
                    ],
                ],
            ],
        );
        $serviceManager->setService('doctrine.cache.' . $cache, $cacheService);

        $driver = (new DriverFactory('testDriver'))->__invoke($serviceManager, ORMAnnotationDriver::class);
        $this->assertInstanceOf(ORMAnnotationDriver::class, $driver);

        return $driver;
    }

    private function getReaderCache(Reader $reader): object
    {
        $this->assertInstanceOf(PsrCachedReader::class, $reader);

        return (new ReflectionProperty(PsrCachedReader::class, 'cache'))->getValue($reader);
    }

    public function testAnnotationCacheUsesPoolBehindLegacyDoctrineProvider(): void
    {
        $pool   = new ArrayAdapter();
        $driver = $this->createAnnotationDriver('array', DoctrineProvider::wrap($pool));

        $this->assertSame($pool, $this->getReaderCache($driver->getReader()));
    }

    public function testAnnotationCacheUsesNativePsr6Pool(): void
    {
        // e.g. an application registering "doctrine.cache.annotations" as a Symfony ArrayAdapter
        $pool   = new ArrayAdapter();
        $driver = $this->createAnnotationDriver('annotations', $pool);

        $this->assertSame($pool, $this->getReaderCache($driver->getReader()));
    }

    public function testAnnotationCacheAdaptsCustomLegacyCache(): void
    {
        $driver = $this->createAnnotationDriver('custom', new TestAsset\LegacyArrayCache());

        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->getReaderCache($driver->getReader()));
    }

    public function testAnnotationCacheRejectsInvalidService(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Annotation cache service "doctrine.cache.invalid" must implement');

        $this->createAnnotationDriver('invalid', new stdClass());
    }
}
