# Fork provenance

This repository is an organization-maintained fork of
[doctrine/DoctrineModule](https://github.com/doctrine/DoctrineModule). It is **not** an upstream
release and is not endorsed by the Doctrine project.

| | |
|---|---|
| Upstream base | `6.3.0` (`bac2ff88f29edaa2b25e17128214f8661a6024aa`) |
| Fork branch | `fork/symfony-cache` |
| Fork releases | four-segment versions `6.3.0.N` (first release: `6.3.0.1`), never three-segment upstream-style tags |
| Package name | `doctrine/doctrine-module` (kept so `doctrine/doctrine-orm-module` `^6.3.0` resolves to the fork) |
| Maintenance owner | _to be assigned_ |

## Patch scope

Only the cache integration is changed; everything else is upstream 6.3.0.

* Removed `laminas/laminas-cache`, `laminas/laminas-cache-storage-adapter-filesystem` and
  `laminas/laminas-cache-storage-adapter-memory`; added `symfony/cache` `^6.4` and `psr/cache`
  `^2.0 || ^3.0`. `doctrine/cache` is retained for DoctrineORMModule's legacy cache setters.
* `doctrinemodule.cache.{array,filesystem,redis,apcu,memcached}` are Symfony Cache PSR-6 pools,
  registered as explicit factories (`DoctrineModule\Service\CachePool\*`) in both
  `ConfigProvider::getDependencyConfig()` and `Module::getConfig()['service_manager']`, and
  configured under `doctrine_module.cache_pools`. The top-level `caches` configuration is gone.
* `doctrine.cache.*` remain legacy `Doctrine\Common\Cache\Cache` services, created by
  `Service\CacheFactory` as `DoctrineProvider::wrap($pool)` (now the default `class`).
* Annotation drivers use native PSR-6 pools directly and adapt legacy caches through
  `Doctrine\Common\Cache\Psr6\CacheAdapter`, so annotation caching is active by default.
* Removed `DoctrineModule\Cache\LaminasStorageCache` and `DoctrineModule\Cache\DoctrineCacheStorage`
  (API removal).

See [docs/en/caching.rst](docs/en/caching.rst) for configuration and upgrade notes, including the
cold-cache transition.

## Consuming the fork

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/InterflonXebia/DoctrineModule.git", "canonical": true}
    ],
    "require": {
        "doctrine/doctrine-module": "6.3.0.1"
    }
}
```

A `vcs` repository is canonical by default, so Composer will not look for
`doctrine/doctrine-module` on Packagist once it is declared. Commit the resulting `composer.lock`.

## Verification

Run with PHP 8.2 and without any laminas-cache package installed:

```bash
composer install
vendor/bin/phpcs
vendor/bin/phpstan analyse
DOCTRINE_MODULE_TEST_REDIS_DSN=redis://localhost:6379/15 \
DOCTRINE_MODULE_TEST_MEMCACHED_DSN=memcached://localhost:11211 \
php -d apc.enable_cli=1 -d apc.use_request_time=0 vendor/bin/phpunit
```

Use disposable Redis/Memcached instances: the suites write to and clear their own namespaces.

## Syncing with upstream

Rebase or cherry-pick the fork commits onto a new upstream tag, rerun the verification above and
release a new `X.Y.Z.N` version.
