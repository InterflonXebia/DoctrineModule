<?php

declare(strict_types=1);

namespace DoctrineModule\Service\CachePool;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

use function sprintf;

/**
 * Creates a Symfony ApcuAdapter
 */
final class ApcuPoolFactory extends AbstractPoolFactory
{
    /**
     * {@inheritDoc}
     */
    protected function createPool(string $serviceName, array $options): CacheItemPoolInterface
    {
        if (! ApcuAdapter::isSupported()) {
            throw new RuntimeException(sprintf(
                'Cache pool "%s" requires the "apcu" PHP extension to be loaded and enabled '
                . '(set "apc.enable_cli=1" when running from the command line).',
                $serviceName,
            ));
        }

        return new ApcuAdapter(
            $this->getNamespace($serviceName, $options),
            $this->getDefaultLifetime($serviceName, $options),
        );
    }
}
