<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Contracts;


use Closure;
use DateInterval;
use DateTimeInterface;

interface CacheInterface
{

    /**
     * Store an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @param DateInterval|DateTimeInterface|int|null $ttl
     * @return bool
     */
    public function put(string $key, mixed $value, DateInterval|DateTimeInterface|int $ttl = null): bool;


    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @template TCacheValue
     *
     * @param string $key
     * @param DateTimeInterface|DateInterval|Closure|int|null $ttl
     * @param Closure():TCacheValue $callback
     * @return Closure():TCacheValue
     */
    public function remember(string $key, DateTimeInterface|DateInterval|Closure|int|null $ttl, Closure $callback): Closure;
}