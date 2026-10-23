Caching
=======

DoctrineModule provides some pre-configured caches using
`Symfony Cache <https://symfony.com/doc/current/components/cache.html>`__, which can be utilized
by either Doctrine ORM or Doctrine ODM.

Two kinds of services are provided for every cache:

=================================== ============================================================
Service                             Contract
=================================== ============================================================
``doctrinemodule.cache.{name}``     Native PSR-6 pool (``Psr\Cache\CacheItemPoolInterface``)
``doctrine.cache.{name}``           Legacy ``Doctrine\Common\Cache\Cache``, a
                                    ``Doctrine\Common\Cache\Psr6\DoctrineProvider`` wrapping
                                    the pool above
=================================== ============================================================

The ``doctrine.cache.*`` services are the ones consumed by the ``metadata_cache``,
``query_cache``, ``result_cache`` and ``hydration_cache`` options of DoctrineORMModule, which
still use the legacy ``set*CacheImpl()`` setters.

Pools are configured below the ``doctrine_module.cache_pools`` key, using the service name of the
pool. All pools accept a ``default_lifetime`` option (in seconds, ``0`` meaning no expiry).
Persistent pools require a non-empty ``namespace`` (characters ``[-+.A-Za-z0-9]``), which isolates
their entries and ensures that clearing the cache never flushes the whole backend.

In-Memory
~~~~~~~~~

Provided by Symfony's ``ArrayAdapter``, you can pull this cache from the container under the
key ``doctrine.cache.array``. It only caches for the duration of the current request/process.

Filesystem
~~~~~~~~~~

Provided by Symfony's ``FilesystemAdapter``, you can pull this cache from the container under the
key ``doctrine.cache.filesystem``. Entries are stored in ``{directory}/{namespace}``. To override
the location for the cache storage folder, use the following configuration:

.. code:: php

    return [
        'doctrine_module' => [
            'cache_pools' => [
                'doctrinemodule.cache.filesystem' => [
                    'namespace' => 'DoctrineModule',
                    'directory' => './data/cache/',
                ],
            ],
        ],
    ];

APCu
~~~~

Provided by Symfony's ``ApcuAdapter``. This cache requires the ``apcu`` PHP extension (with
``apc.enable_cli=1`` for command line usage); requesting it without the extension fails with an
explicit error.

You can pull the cache from the container using the key ``doctrine.cache.apcu``:

.. code:: php

    return [
        'doctrine_module' => [
            'cache_pools' => [
                'doctrinemodule.cache.apcu' => [
                    'namespace' => 'MyApplication.doctrine',
                ],
            ],
        ],
    ];

Memcached
~~~~~~~~~

Provided by Symfony's ``MemcachedAdapter``. This cache requires the ``memcached`` PHP extension and
at least one configured server; there is no default server. Namespace versioning is enabled, so
clearing the pool only invalidates its own namespace instead of flushing the server.

You can pull the cache from the container using the key ``doctrine.cache.memcached``:

.. code:: php

    return [
        'doctrine_module' => [
            'cache_pools' => [
                'doctrinemodule.cache.memcached' => [
                    'namespace' => 'MyApplication.doctrine',
                    'servers' => ['memcached://localhost:11211'],
                    // optional, passed to MemcachedAdapter::createConnection()
                    'connection_options' => [],
                ],
            ],
        ],
    ];

Redis
~~~~~

Provided by Symfony's ``RedisAdapter``. This cache requires the ``redis`` PHP extension or the
``predis/predis`` package.

You can pull the cache from the container using the key ``doctrine.cache.redis``. The pool always
uses its own lazily-connected Redis connection created from the configured DSN: it does not
connect until the cache is actually used, and it never reuses or reconfigures a connection shared
with the application. The default config will access a Redis server at ``redis://localhost:6379``.

.. code:: php

    return [
        'doctrine_module' => [
            'cache_pools' => [
                'doctrinemodule.cache.redis' => [
                    'namespace' => 'MyApplication.doctrine',
                    'dsn' => 'redis://localhost:6379/2',
                    // optional, passed to RedisAdapter::createConnection(); "lazy" defaults to true
                    'connection_options' => ['timeout' => 5],
                ],
            ],
        ],
    ];

Custom caches
~~~~~~~~~~~~~

Any PSR-6 pool registered in the container can be exposed as a legacy Doctrine cache:

.. code:: php

    return [
        'doctrine' => [
            'cache' => [
                'my_cache' => [
                    // DoctrineProvider is the default class
                    'class' => \Doctrine\Common\Cache\Psr6\DoctrineProvider::class,
                    'instance' => 'my.psr6.pool.service',
                    // optional, prefixes the keys of this legacy cache
                    'namespace' => 'my_cache',
                ],
            ],
        ],
    ];

``class`` may also name a container service or a class implementing ``Doctrine\Common\Cache\Cache``.
Services that do not implement that interface (including raw PSR-6 pools) are rejected with an
explicit error; you may also register ``doctrine.cache.my_cache`` directly in the service manager.

Annotation cache
~~~~~~~~~~~~~~~~

The ``cache`` option of annotation drivers (default ``array``, i.e. ``doctrine.cache.array``) names
the service used by Doctrine Annotations' ``PsrCachedReader``. Native PSR-6 pools are used as they
are; legacy ``Doctrine\Common\Cache\Cache`` services are adapted through
``Doctrine\Common\Cache\Psr6\CacheAdapter`` (which unwraps a ``DoctrineProvider`` to its pool).

Upgrading from laminas-cache based versions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

* The top-level ``caches`` configuration (laminas-cache storage adapters) is no longer read; use
  ``doctrine_module.cache_pools`` as shown above.
* ``DoctrineModule\Cache\LaminasStorageCache`` and ``DoctrineModule\Cache\DoctrineCacheStorage``
  have been removed. Replace ``'class' => LaminasStorageCache::class`` with
  ``'class' => DoctrineProvider::class`` and point ``instance`` to a PSR-6 pool.
* ``doctrinemodule.cache.*`` services now return Symfony Cache pools instead of laminas-cache
  storage adapters, and the ``Laminas\Cache*`` modules no longer need to be registered.
* Expect a cold cache after deploying: entries written by laminas-cache are not read by Symfony
  Cache (filesystem entries also move to the ``{directory}/{namespace}`` sub-directory). Old and
  new deployments running side by side do not invalidate each other's entries, so clear or warm up
  the caches after the switch and remove obsolete cache files.
* Lifetimes passed to ``save()`` are now honoured (the laminas-cache bridge ignored them).
