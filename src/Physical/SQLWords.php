<?php
declare(strict_types=1);

/**
 * This file is part of Kooditorm/transapi.
 *
 * @link     https://github.com/Kooditorm/transapi
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/transapi/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Physical;

class SQLWords
{

    /** 语句类型关键字（只取括号深度 0 的第一个） */
    public const STMT_WORDS = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'REPLACE',
        'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'SHOW', 'DESCRIBE',
        'GRANT', 'REVOKE', 'EXPLAIN', 'WITH', 'CALL', 'LOCK', 'UNLOCK',
    ];

    /** 这些词后面紧跟 "(" 时不视为函数调用（抑制 EXTRACT/SUBSTRING/TRIM 里的 FROM） */
    public const NON_FUNC_WORDS = [
        'FROM', 'JOIN', 'WHERE', 'IN', 'EXISTS', 'VALUES', 'ON', 'AND', 'OR',
        'SELECT', 'AS', 'SET', 'BY', 'HAVING', 'LIMIT', 'UNION', 'ALL', 'NOT',
        'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'BETWEEN', 'LIKE', 'IS', 'NULL',
        'INSERT', 'INTO', 'UPDATE', 'DELETE', 'CREATE', 'TABLE', 'IF', 'RETURNING',
        'USING', 'GROUP', 'ORDER', 'WITH', 'LATERAL', 'ONLY', 'PARTITION', 'FOR',
        'TO', 'OF', 'DISTINCT', 'EXCEPT', 'INTERSECT', 'QUALIFY', 'WINDOW', 'OVER',
    ];

    /** 不能当表名 / 别名的关键字（DUAL 由 keep_pseudo_tables 单独控制，不放这里） */
    public const RESERVED = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'REPLACE', 'CREATE',
        'ALTER', 'DROP', 'TRUNCATE', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN',
        'GRANT', 'REVOKE', 'FROM', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'FULL',
        'OUTER', 'CROSS', 'NATURAL', 'WHERE', 'GROUP', 'ORDER', 'HAVING',
        'LIMIT', 'OFFSET', 'FETCH', 'UNION', 'EXCEPT', 'INTERSECT', 'ON',
        'SET', 'VALUES', 'VALUE', 'AS', 'AND', 'OR', 'NOT', 'NULL', 'TRUE',
        'FALSE', 'IN', 'IS', 'BETWEEN', 'LIKE', 'ILIKE', 'EXISTS', 'ANY',
        'SOME', 'ALL', 'DISTINCT', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
        'BY', 'FOR', 'OF', 'TO', 'INTO', 'USING', 'WITH', 'RECURSIVE',
        'LATERAL', 'ONLY', 'PARTITION', 'WINDOW', 'QUALIFY', 'OVER', 'RETURNING',
        'IF', 'IGNORE', 'LOW_PRIORITY', 'HIGH_PRIORITY', 'DELAYED',
        'OUTFILE', 'DUMPFILE', 'INFILE', 'LOCAL', 'TABLESAMPLE',
        'INDEX', 'KEY', 'PRIMARY', 'UNIQUE', 'FOREIGN', 'REFERENCES', 'CHECK',
        'CONSTRAINT', 'DEFAULT', 'CASCADE', 'RESTRICT', 'TABLES', 'DATABASES',
        'MATERIALIZED', 'NOWAIT', 'SKIP', 'LOCKED', 'LOCK', 'SHARE', 'MODE',
        'PROCEDURE', 'FUNCTION', 'TRIGGER', 'VIEW', 'SCHEMA', 'DATABASE',
        'USE', 'FORCE', 'INDEXED', 'ROLLUP', 'CUBE', 'GROUPING', 'SETS',
        'START', 'CONNECT', 'MATCHED', 'DO', 'ADD', 'CHANGE', 'MODIFY', 'COLUMN',
        'ENGINE', 'CHARSET', 'COLLATE', 'AUTO_INCREMENT', 'COMMENT', 'OPTION',
        'FIELDS', 'LINES', 'TERMINATED', 'ENCLOSED', 'ESCAPED', 'SYSTEM',
        'BERNOULLI', 'REPEATABLE', 'ROWS', 'RANGE', 'CURRENT', 'ROW',
        'UNBOUNDED', 'PRECEDING', 'FOLLOWING', 'TIES', 'TOP', 'PERCENT',
    ];


    /** 解析表引用起点时需要跳过的修饰词 */
    public const MODIFIERS = [
        'ONLY', 'LATERAL', 'IF', 'NOT', 'EXISTS', 'TEMPORARY', 'TEMP',
        'UNLOGGED', 'LOW_PRIORITY', 'IGNORE', 'VIRTUAL', 'RECURSIVE',
    ];

}