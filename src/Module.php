<?php

declare(strict_types=1);

namespace DoctrineModule;

/**
 * Base module for integration of Doctrine projects with Laminas applications
 */
final class Module
{
    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();

        return [
            'doctrine' => $provider->getDoctrineConfig(),
            'doctrine_factories' => $provider->getDoctrineFactoryConfig(),
            'doctrine_module' => $provider->getDoctrineModuleConfig(),
            'service_manager' => $provider->getDependencyConfig(),
            'validators' => $provider->getValidatorConfig(),
        ];
    }
}
