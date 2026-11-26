<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Kooditorm\Repository\Contracts\CacheInterface;
use Kooditorm\Repository\Event\RepositoryEntityCreated;
use Kooditorm\Repository\Event\RepositoryEntityDeleted;
use Kooditorm\Repository\Event\RepositoryEntityUpdated;

class CleanCacheRepository implements ListenerInterface
{
    /** 与 GenTableCacheKeyMapping::MAPPING_PREFIX 保持一致 */
    private const MAPPING_PREFIX = 'repo:tk:';

    public function __construct(protected CacheInterface $cache)
    {

    }

    public function listen(): array
    {
        return [
            RepositoryEntityCreated::class,
            RepositoryEntityUpdated::class,
            RepositoryEntityDeleted::class,
        ];
    }

    public function process(object $event): void
    {
        $table = $event->getRepository()->getModel()->getTable();
        $hash  = self::MAPPING_PREFIX.$table;

        $store = $this->cache->getStore();
        $redis = $store->getConnection();
        $map   = $redis->hGetAll($hash);
        if (empty($map)) {
            return;
        }

        $now = time();
        foreach ($map as $key => $expireAt) {
            $expireAt = (int) $expireAt;
            if ($expireAt > 0 && $expireAt <= $now) {
                // 缓存本身早已过期，仅清理映射，防止 hash 无限膨胀
                $redis->hDel($hash, $key);
                continue;
            }

            // 必须走缓存驱动删除（驱动有自己的 key 前缀），不能直接 redis del
            $store->delete($key);
            $redis->hDel($hash, $key);
        }
    }
}
