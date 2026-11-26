<?php
declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repository/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Kooditorm\Repository\Contracts\CacheInterface;
use Kooditorm\Repository\Event\RepositoryCacheRemember;
use Kooditorm\Repository\Physical\GatherSqlTable;

class GenTableCacheKeyMapping implements ListenerInterface
{
    public function __construct(protected CacheInterface $cache)
    {

    }

    public function listen(): array
    {
        return [
            RepositoryCacheRemember::class
        ];
    }

    public function process(Object $event): void
    {
        $cacheKey = $event->key;
        $tableSql = $event->sql;

        $table = new GatherSqlTable();
        $tbs = $table->getTables($tableSql, ['with_schema' => false]);

        var_dump($this->cache);
    }
}