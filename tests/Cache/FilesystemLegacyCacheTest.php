<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Cache;

use DoctrineModule\Service\CachePool\FilesystemPoolFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Cache\CacheItemPoolInterface;

use function sys_get_temp_dir;
use function uniqid;

/** @coversNothing */
class FilesystemLegacyCacheTest extends LegacyCacheBehaviourTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('doctrine-module-', true);
    }

    protected function createPool(string $namespace): CacheItemPoolInterface
    {
        return (new FilesystemPoolFactory())(
            new ServiceManager(),
            'doctrinemodule.cache.filesystem',
            ['namespace' => $namespace, 'directory' => $this->directory],
        );
    }
}
