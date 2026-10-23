<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Cache;

use DoctrineModule\Service\CachePool\ApcuPoolFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

/**
 * Requires ext-apcu with "apc.enable_cli=1" and "apc.use_request_time=0", so lifetimes are evaluated
 * against the current time instead of the request start time.
 *
 * @coversNothing
 */
class ApcuLegacyCacheTest extends LegacyCacheBehaviourTestCase
{
    protected function setUp(): void
    {
        if (ApcuAdapter::isSupported()) {
            return;
        }

        $this->markTestSkipped('ext-apcu with apc.enable_cli=1 is required');
    }

    protected function createPool(string $namespace): CacheItemPoolInterface
    {
        return (new ApcuPoolFactory())(new ServiceManager(), 'doctrinemodule.cache.apcu', ['namespace' => $namespace]);
    }
}
