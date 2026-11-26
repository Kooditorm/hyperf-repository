<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Cache;


use Closure;
use DateInterval;
use DateTimeInterface;
use Hyperf\Cache\CacheManager;
use Hyperf\Cache\Driver\DriverInterface;
use Hyperf\DbConnection\Db;
use Kooditorm\Repository\Contracts\CacheInterface;
use Kooditorm\Repository\Event\RepositoryCacheRemember;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use function Kooditorm\Repository\value;
use function Kooditorm\Repository\event;

class CacheRepository implements CacheInterface
{
    /**
     * Create a new cache repository instance.
     *
     * @param  CacheManager  $store
     */
    public function __construct(protected CacheManager $store)
    {

    }

    /**
     * Determine if an item exists in the cache.
     *
     * @throws InvalidArgumentException
     */
    public function has(string|array $key): bool
    {
        return !is_null($this->get($key));
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @template TCacheValue
     *
     * @param  string|array  $key
     * @param  (Closure():TCacheValue)|null  $default
     * @return (TCacheValue is null ? mixed : TCacheValue)
     * @throws InvalidArgumentException
     */
    public function get(string|array $key, Closure $default = null): mixed
    {
        if (is_array($key)) {
            return $this->store->getDriver()->getMultiple($key);
        }

        $value = $this->store->getDriver()->get($key);

        if (is_null($value)) {
            $value = value($default);
        }

        return $value;
    }

    /**
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  DateInterval|DateTimeInterface|int|null  $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function put(string $key, mixed $value, DateInterval|DateTimeInterface|int $ttl = null): bool
    {
        if ($ttl === null) {
            return $this->forever($key, $value);
        }

        return $this->store->getDriver()->set($key, $value, $ttl);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     * @throws InvalidArgumentException
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->store->getDriver()->set($key, $value);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @template TCacheValue
     *
     * @param  string  $key
     * @param  DateTimeInterface|DateInterval|Closure|int|null  $ttl
     * @param  (Closure():TCacheValue)  $callback
     * @return TCacheValue
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     */
    public function remember(string $key, DateTimeInterface|DateInterval|Closure|int|null $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);

        if (!is_null($value)) {
            return $value;
        }

        $connection = Db::connection();
        $connection->enableQueryLog();
        $value     = $callback();
        $lastQuery = $connection->getQueryLog();
        $lastQuery = end($lastQuery);
        $lastSql   = is_array($lastQuery) ? ($lastQuery['query'] ?? '') : '';
        $connection->disableQueryLog();

        // 先写缓存，成功后再登记表 → 缓存 key 映射，避免留下孤儿映射
        $this->store->getDriver()->set($key, $value, value($ttl));
        event(new RepositoryCacheRemember($key, $lastSql, $this->expireAt($ttl)));

        return $value;
    }

    /**
     * 归一化 TTL 为过期时间戳；null / Closure（永久缓存）返回 0
     */
    private function expireAt(DateTimeInterface|DateInterval|Closure|int|null $ttl): int
    {
        $t = value($ttl);
        return match (true) {
            is_int($t) => time() + $t,
            $t instanceof DateTimeInterface => $t->getTimestamp(),
            $t instanceof DateInterval => time() + ($t->days ? $t->days * 86400
                : $t->d * 86400 + $t->h * 3600 + $t->i * 60 + $t->s),
            default => 0,
        };
    }

    public function getStore(): DriverInterface
    {
        return $this->store->getDriver();
    }

}