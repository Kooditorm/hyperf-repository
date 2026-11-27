<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Crontab;

use Hyperf\Crontab\Annotation\Crontab;
use Kooditorm\Repository\Cache\TCKMapping;
use Kooditorm\Repository\Contracts\CacheInterface;
use Redis;

/**
 * 定时清理过期的「表 → 缓存 key」映射条目。
 *
 * 惰性清理（CleanCacheRepository）只覆盖发生写操作的表，
 * 冷表的映射 hash 会一直残留，需要定时任务兜底。
 *
 * 本类通过 #[Crontab] 注解随包扫描自动注册，无需宿主额外配置。
 * 前提：宿主安装 hyperf/crontab 并在 config/autoload/crontab.php 中开启 enable。
 * 未安装该组件时，ConfigProvider 的 ignore_annotations 会跳过此注解，不影响启动。
 */
class TCKMappingCleaner
{
    public function __construct(protected CacheInterface $cache)
    {

    }

    /**
     * 清理所有已过期的映射条目，返回清理的字段数
     */
    #[Crontab(rule: '* * * * *', name: 'CleanExpiredTCKMapping', memo: '清理过期的表-缓存key映射')]
    public function clean(): int
    {
        $store = $this->cache->getStore();
        $redis = $store->getConnection();
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

        $now     = time();
        $removed = 0;
        $it      = null;
        while (($tbs = $redis->sscan(TCKMapping::TABLES_SET, $it, '*', 200)) !== false) {
            foreach ($tbs as $tb) {
                $removed += $this->cleanTable($redis, TCKMapping::hash($tb), $tb, $now);
            }
        }

        return $removed;
    }

    private function cleanTable($redis, string $hash, string $tb, int $now): int
    {
        $removed = 0;
        $it      = null;
        while (($fields = $redis->hscan($hash, $it, '*', 200)) !== false) {
            $expired = [];
            foreach ($fields as $key => $expireAt) {
                if ((int) $expireAt > 0 && (int) $expireAt <= $now) {
                    $expired[] = $key;
                }
            }
            if ($expired !== []) {
                array_unshift($expired, $hash);
                $removed += (int) $redis->hdel(...$expired);
            }
        }

        // 整表映射已清空：删除 hash 并从表名索引中移除
        if ($redis->hlen($hash) === 0) {
            $redis->del($hash);
            $redis->srem(TCKMapping::TABLES_SET, $tb);
        }

        return $removed;
    }
}
