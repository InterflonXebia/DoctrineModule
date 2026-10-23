<?php

declare(strict_types=1);

namespace DoctrineModule\Service\CachePool;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Creates an in-memory Symfony ArrayAdapter, scoped to the current request/process
 */
final class ArrayPoolFactory extends AbstractPoolFactory
{
    /**
     * {@inheritDoc}
     */
    protected function createPool(string $serviceName, array $options): CacheItemPoolInterface
    {
        return new ArrayAdapter($this->getDefaultLifetime($serviceName, $options));
    }
}
