<?php

declare(strict_types=1);

namespace DoctrineModuleTest\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FlushableCache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;

use function assert;
use function sleep;
use function uniqid;

/**
 * Behaviour contract for the legacy doctrine/cache services, as consumed by the ORM/ODM cache setters,
 * on top of each supported Symfony Cache pool.
 */
abstract class LegacyCacheBehaviourTestCase extends TestCase
{
    /** @var list<CacheItemPoolInterface> */
    private array $pools = [];

    /** @param non-empty-string $namespace */
    abstract protected function createPool(string $namespace): CacheItemPoolInterface;

    protected function tearDown(): void
    {
        foreach ($this->pools as $pool) {
            $pool->clear();
        }

        $this->pools = [];
    }

    /** @param non-empty-string|null $namespace */
    protected function createCache(string|null $namespace = null): Cache
    {
        $pool          = $this->createPool($namespace ?? uniqid('DoctrineModuleTest.'));
        $this->pools[] = $pool;

        return DoctrineProvider::wrap($pool);
    }

    public function testMissingKey(): void
    {
        $cache = $this->createCache();

        $this->assertFalse($cache->contains('missing'));
        $this->assertFalse($cache->fetch('missing'));
    }

    public function testSaveAndFetchRoundTrip(): void
    {
        $cache = $this->createCache();

        $object      = new stdClass();
        $object->foo = ['bar' => 1, 'baz' => [true, null, 1.5]];
        $array       = ['a' => 'b', 'nested' => ['c' => 1], 'object' => $object];

        $this->assertTrue($cache->save('array', $array));
        $this->assertTrue($cache->save('object', $object));
        $this->assertTrue($cache->save('MyTestKey[something\inside\here#with$specialChars]', 'special'));

        $this->assertEquals($array, $cache->fetch('array'));
        $this->assertEquals($object, $cache->fetch('object'));
        $this->assertSame('special', $cache->fetch('MyTestKey[something\inside\here#with$specialChars]'));
    }

    public function testPositiveLifetimeExpires(): void
    {
        $cache = $this->createCache();

        $cache->save('expiring', 'value', 1);
        $this->assertTrue($cache->contains('expiring'));

        sleep(2);

        $this->assertFalse($cache->contains('expiring'));
        $this->assertFalse($cache->fetch('expiring'));
    }

    public function testZeroLifetimeDoesNotExpire(): void
    {
        $cache = $this->createCache();

        $cache->save('forever', 'value', 0);

        sleep(2);

        $this->assertSame('value', $cache->fetch('forever'));
    }

    public function testZeroLifetimeRemovesEarlierExpiry(): void
    {
        $cache = $this->createCache();

        $cache->save('key', 'first', 1);
        $cache->save('key', 'second', 0);

        sleep(2);

        $this->assertSame('second', $cache->fetch('key'));
    }

    public function testDeleteOnlyRemovesRequestedEntry(): void
    {
        $cache = $this->createCache();

        $cache->save('a', 'A');
        $cache->save('b', 'B');

        $this->assertTrue($cache->delete('a'));

        $this->assertFalse($cache->contains('a'));
        $this->assertSame('B', $cache->fetch('b'));
    }

    public function testFlushOnlyClearsOwnPool(): void
    {
        $cache = $this->createCache('DoctrineModuleTest.first');
        $other = $this->createCache('DoctrineModuleTest.second');

        $cache->save('key', 'mine');
        $other->save('key', 'other');

        assert($cache instanceof FlushableCache);
        $this->assertTrue($cache->flushAll());

        $this->assertFalse($cache->contains('key'));
        $this->assertSame('other', $other->fetch('key'));
    }

    public function testNamespaceIsolation(): void
    {
        $cache = $this->createCache('DoctrineModuleTest.first');
        $other = $this->createCache('DoctrineModuleTest.second');

        $cache->save('key', 'mine');

        $this->assertFalse($other->contains('key'));
        $this->assertSame('mine', $cache->fetch('key'));
    }
}
