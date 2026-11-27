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


class TCKMapping
{
    /** 表 → 缓存 key 映射的命名空间前缀，避免与业务缓存 key 冲突 */
    public const MAPPING_PREFIX = 'repo:tk:';

    /** 表名索引 SET，登记所有已建立映射的表名 */
    public const TABLES_SET = 'repo:tk:tables';

    /**
     * 获取某张表的映射 hash key
     */
    public static function hash(string $table): string
    {
        return self::MAPPING_PREFIX.$table;
    }
}
