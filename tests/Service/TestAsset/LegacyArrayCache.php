<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Service\TestAsset;

use Doctrine\Common\Cache\CacheProvider;

use function array_key_exists;

/**
 * Minimal custom doctrine/cache implementation, as an application could still provide one
 */
final class LegacyArrayCache extends CacheProvider
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * {@inheritDoc}
     */
    protected function doFetch($id)
    {
        return $this->data[$id] ?? false;
    }

    /**
     * {@inheritDoc}
     */
    protected function doContains($id)
    {
        return array_key_exists($id, $this->data);
    }

    /**
     * {@inheritDoc}
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        $this->data[$id] = $data;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function doDelete($id)
    {
        unset($this->data[$id]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function doFlush()
    {
        $this->data = [];

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function doGetStats()
    {
        return null;
    }
}
