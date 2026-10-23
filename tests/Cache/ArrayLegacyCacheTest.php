<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Cache;

use DoctrineModule\Service\CachePool\ArrayPoolFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Cache\CacheItemPoolInterface;

/** @coversNothing */
class ArrayLegacyCacheTest extends LegacyCacheBehaviourTestCase
{
    protected function createPool(string $namespace): CacheItemPoolInterface
    {
        return (new ArrayPoolFactory())(new ServiceManager(), 'doctrinemodule.cache.array', []);
    }
}
