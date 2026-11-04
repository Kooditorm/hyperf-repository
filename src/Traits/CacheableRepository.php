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

use Closure;
use Hyperf\HttpServer\Contract\RequestInterface;
use Kooditorm\Repository\BaseRepository;
use Kooditorm\Repository\Contracts\CacheInterface;
use Kooditorm\Repository\Exceptions\RepositoryException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

trait CacheableRepository
{

    /**
     * @var CacheInterface|null
     */
    protected CacheInterface|null $cacheRepository = null;

    protected bool $cacheSkip = false;

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
     * Skip Cache
     *
     * @param bool $status
     * @return BaseRepository
     */
    public function skipCache(bool $status = true): BaseRepository
    {
        $this->cacheSkip = $status;
        return $this;
    }

    /**
     * @param $method
     * @return bool
     */
    protected function allowedCache($method): bool
    {
        $cacheEnable = config('repository.cache.enable', true);

        if (!$cacheEnable) {
            return false;
        }

        $cacheOnly = $this->cacheOnly ?? config('repository.cache.allowed.only', null);
        $cacheExcept = $this->cacheExcept ?? config('repository.cache.allowed.except', null);

        if (is_array($cacheOnly)) {
            return in_array($method, $cacheOnly, true);
        }

        if (is_array($cacheExcept)) {
            return !in_array($method, $cacheExcept, true);
        }

        if (is_null($cacheOnly) && is_null($cacheExcept)) {
            return true;
        }

        return false;
    }

    /**
     * Get Cache key for the method
     *
     * @param mixed $method
     * @param mixed|null $args
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public
    function getCacheKey(mixed $method, mixed $args = null): string
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
    public
    function getCacheTime(): int
    {
        return $this->cacheMinutes ?? config('repository.cache.minutes', 30) * 60;
    }

    /**
     * Retrieve all data of repository
     *
     * @param array $columns
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface|RepositoryException
     */
    public function all(array $columns = ['*']): array
    {
        if (!$this->allowedCache('all')) {
            return parent::all($columns);
        }

        $key = $this->getCacheKey('all', func_get_args());
        $time = $this->getCacheTime();
        $value = $this->getCacheRepository()->remember($key, $time, function () use ($columns) {
            return parent::all($columns);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }

    /**
     * @param int|null $limit
     * @param array $columns
     * @param string $method
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface|RepositoryException
     */
    public function paginate(int|null $limit = null, array $columns = ['*'], string $method = 'paginate'): array
    {
        if (!$this->allowedCache('paginate')) {
            return parent::paginate($limit, $columns, $method);
        }

        $key = $this->getCacheKey('paginate', func_get_args());
        $time = $this->getCacheTime();
        $value = $this->getCacheRepository()->remember($key, $time, function () use ($limit, $columns, $method) {
            return parent::paginate($limit, $columns, $method);
        });

        $this->resetModel();
        $this->resetScope();

        return $value;
    }


}