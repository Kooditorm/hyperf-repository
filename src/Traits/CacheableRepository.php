<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Traits;

use Hyperf\HttpServer\Contract\RequestInterface;
use Kooditorm\Repository\BaseRepository;
use Kooditorm\Repository\Contracts\CacheInterface;
use function Hyperf\Support\make;

trait CacheableRepository
{

    /**
     * @var CacheInterface|null
     */
    protected CacheInterface|null $cacheRepository = null;

    /**
     * Set Cache Repository
     *
     * @param CacheInterface $cacheRepository
     * @return BaseRepository
     */
    public function setCacheRepository(CacheInterface $cacheRepository): BaseRepository
    {
        $this->cacheRepository = $cacheRepository;
        return $this;
    }

    /**
     * @return CacheInterface
     */
    public function getCacheRepository(): CacheInterface
    {
        if (is_null($this->cacheRepository)) {
            $this->cacheRepository = make(CacheInterface::class);
        }

        return $this->cacheRepository;
    }

    /**
     * Get Cache key for the method
     *
     * @param mixed $method
     * @param mixed|null $args
     * @return string
     */
    public function getCacheKey(mixed $method, mixed $args = null): string
    {
        $args = serialize($args);
        $req = $this->app->get(RequestInterface::class);
        $md5 = md5($args . $req->fullUrl());
        return sprintf('%s@%s-%s', static::class, $method, $md5);
    }

    /**
     * Get cache time
     *
     * @return int
     */
    public function getCacheTime(): int
    {
        return $this->cacheMinutes ?? 300;
    }


}