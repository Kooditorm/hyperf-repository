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

use Kooditorm\Repository\Contracts\CacheInterface;

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
     * @return $this
     */
    public function setCacheRepository(CacheInterface $cacheRepository)
    {
        $this->cacheRepository = $cacheRepository;
        return $this;
    }

    /**
     * @return CacheInterface|null
     */
    public function getCacheRepository(): CacheInterface|null
    {
        if (is_null($this->cacheRepository)) {
            $this->cacheRepository = \Hyperf\Support\make(CacheInterface::class);
        }

        return $this->cacheRepository;
    }

}