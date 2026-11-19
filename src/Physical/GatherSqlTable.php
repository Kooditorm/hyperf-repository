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


final class GatherSqlTable
{

    protected SqlLexer $lexer;

    public function __construct(private array $options = [])
    {
        $this->options = [...$this->defaults(), ...$this->options];
        $this->lexer   = new SqlLexer();
    }

    /** 合并配置，返回自身以便链式调用（static 返回类型，PHP 8.0+） */
    public function setOptions(array $options): self
    {
        $this->options = [...$this->options, ...$options];
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /** 只要表名数组 */
    public function getTables(string $sql, array $options = []): array
    {
        return $this->parse($sql, $options)['tables'];
    }

    /** 只要引用明细 */
    public function getDetails(string $sql, array $options = []): array
    {
        return $this->parse($sql, $options)['details'];
    }

    /** 按语句分组 */
    public function getByStatement(string $sql, array $options = []): array
    {
        return $this->parse($sql, $options)['getByStatement'];
    }

    public function parse(string $sql, array $options = []): array
    {

        $opts     = [...$this->options, ...$options];
        $t0       = microtime(true);
        $warnings = [];

        if (substr_count($sql, '/*') !== substr_count($sql, '*/')) {
            $warnings[] = '块注释 /* 未闭合，其后内容可能已被忽略';
        }

        if (preg_match('//u', $sql) !== 1) {
            $warnings[] = '输入不是合法 UTF-8，已尝试按 GB18030 转码';
            $converted  = $this->toUtf8($sql);
            // is_string 严格区分“转码成功”（含 '' / '0'）与“失败返回 false”
            if (is_string($converted)) {
                $sql = $converted;
            } else {
                // 双重降级也失败（preg_replace 出错返回 null），保留原文避免静默丢数据
                $sql = preg_replace('/[\x80-\xFF]/', '', $sql) ?? $sql;
            }
        }

        $tokens     = $this->lexer->tokenize($sql, $opts);
        $statements = $this->splitStatements($tokens);
        $details    = [];
        $excluded   = [];
        $stmtRows   = [];
        $allCotes   = [];

        $open  = 0;
        $close = 0;

        foreach ($tokens as $t) {
            if ($t['t'] === SqlTokenType::Paren) {
                $t['v'] === '(' ? $open++ : $close++;
            }
        }

        if ($open !== $close) {
            $warnings[] = '括号数量不匹配（'.$open.' 个 "(" / '.$close.' 个 ")"），结果可能不完整';
        }

        foreach ($statements as $sIdx => $toks) {
            $type      = $this->statementType($toks);
            $cotes     = $this->collectCotes($toks);
            $allCotes  = [...$allCotes, ...$cotes];
            $allowOn   = in_array($type, ['GRANT', 'REVOKE'], true)
                || (in_array($type, ['CREATE', 'ALTER', 'DROP'], true) && $this->hasWord($toks));
            $ctx       = $this->parenContexts($toks);
            $n         = count($toks);
            $firstWord = null;
            foreach ($toks as $k => $tk) {
                if ($tk['t'] === SqlTokenType::Word) {
                    $firstWord = [$k, strtoupper($tk['v'])];
                    break;
                }
            }

            for ($i = 0; $i < $n; $i++) {
                $tok = $toks[$i];
                if ($tok['t'] !== SqlTokenType::Word) {
                    continue;
                }
                $w    = strtoupper($tok['v']);
                $next = $toks[$i + 1] ?? null;

                // match(true) 替代原来的 if / elseif 链；null 表示「不是表引用起点」
                $clause = match (true) {
                    $w === 'FROM' && $type !== 'SHOW' && ($ctx[$i] ?? '') !== 'func' => 'FROM',
                    $w === 'JOIN' => 'JOIN',
                    $w === 'INTO' => 'INTO',
                    $w === 'UPDATE' && $firstWord !== null && $firstWord[0] === $i => 'UPDATE',
                    $w === 'TABLE' => 'TABLE',
                    $w === 'TRUNCATE' && !$this->isWord($next, 'TABLE') => 'TRUNCATE',
                    $w === 'USING' && !$this->isPunct($next, '(') => 'USING',
                    ($w === 'DESCRIBE' || $w === 'DESC')
                    && ($w !== 'DESC' || $type === 'DESCRIBE') => 'DESCRIBE',
                    $w === 'REFERENCES' => 'REFERENCES',
                    $w === 'ON' && $allowOn => 'ON',
                    $w === 'RENAME' && $this->isWord($next, 'TO') => 'RENAME TO',
                    default => null,
                };
                if ($clause === null) {
                    continue;
                }
                $start = $clause === 'RENAME TO' ? $i + 2 : $i + 1;

                $notes = [];
                $refs  = $this->parseTableList($toks, $start, $clause, $opts, $notes);
                foreach ($notes as $note) {
                    $excluded[] = [
                        ...$note,
                        'statement' => $sIdx + 1,
                        'type'      => $type,
                        'clause'    => $clause,
                    ];
                }
                foreach ($refs as $ref) {
                    $key = $this->dedupKey($ref['full'], $opts);
                    if (!$opts['include_cte'] && in_array($key, $cotes, true)) {
                        $excluded[] = [
                            'name'      => $ref['full'],
                            'reason'    => 'cte',
                            'statement' => $sIdx + 1,
                            'type'      => $type,
                            'clause'    => $clause,
                        ];
                        continue;
                    }
                    $details[] = [
                        ...$ref,
                        'statement' => $sIdx + 1,
                        'type'      => $type,
                        'clause'    => $clause,
                    ];
                }
            }

            $stmtTables = [];
            $seen       = [];
            foreach ($details as $d) {
                if ($d['statement'] !== $sIdx + 1) {
                    continue;
                }
                $name = $this->displayName($d, $opts);
                $k    = $this->dedupKey($name, $opts);
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k]     = true;
                $stmtTables[] = $name;
            }
            $stmtRows[] = [
                'index'  => $sIdx + 1,
                'type'   => $type,
                'tables' => $stmtTables,
                'count'  => count($stmtTables),
                'cotes'  => $cotes,
            ];
        }

        $tables = [];
        $seen   = [];
        foreach ($details as $d) {
            $name = $this->displayName($d, $opts);
            $k    = $this->dedupKey($name, $opts);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $tables[] = $name;
        }

        $uniqueCotes = array_values(array_unique($allCotes));
        return [
            'tables'     => $tables,
            'details'    => $details,
            'statements' => $stmtRows,
            'cotes'      => $uniqueCotes,
            'excluded'   => $excluded,
            'summary'    => [
                'statements'    => count($stmtRows),
                'references'    => count($details),
                'unique_tables' => count($tables),
                'cotes'         => count($uniqueCotes),
                'excluded'      => count($excluded),
                'elapsed_ms'    => round((microtime(true) - $t0) * 1000, 2),
            ],
            'warnings'   => $warnings,
        ];
    }


    private function displayName(array $detail, array $opts): string
    {
        return $opts['with_schema'] ? $detail['full'] : $detail['table'];
    }

    private function dedupKey(string $name, array $opts): string
    {
        return $opts['case_insensitive_dedup'] ? strtolower($name) : $name;
    }

    private function hasWord(array $tokens): bool
    {
        foreach ($tokens as $t) {
            if ($t['t'] === SqlTokenType::Word && strtoupper($t['v']) === 'INDEX') {
                return true;
            }
        }
        return false;
    }

    /**
     * 从某个子句起点解析「一个或多个」表引用（FROM a, b / DROP TABLE a, b）。
     * 主扫描始终逐 token 前进，这里只负责提取，不吞掉外层 token，
     * 因此括号内的子查询 FROM 不会被漏掉；$notes 记录被判为非物理表的项。
     */
    private function parseTableList(array $tokens, int $i, string $clause, array $opts, array &$notes): array
    {
        $refs  = [];
        $n     = count($tokens);
        $guard = 0;
        while ($i < $n && $guard++ < 200) {
            $i = $this->skipModifiers($tokens, $i);
            if ($this->isPunct($tokens[$i] ?? null, '(')) {
                $notes[] = ['name' => '(subquery)', 'reason' => 'derived_table'];
                break; // 派生表：外层不算表，内层 FROM 由主扫描继续处理
            }

            $end  = $i;
            $name = $this->readQualifiedName($tokens, $i, $end);
            if ($name === null) {
                break;
            }

            // FROM UNNEST(...) / JOIN generate_series(...) 是表值函数，不是物理表
            if ($this->isPunct($tokens[$end] ?? null, '(')
                && in_array($clause, ['FROM', 'JOIN', 'USING', 'DESCRIBE'], true)) {
                $notes[] = ['name' => $name['full'], 'reason' => 'table_function'];
                break;
            }

            $alias = null;
            $after = $end;
            $this->readAlias($tokens, $end, $after, $alias);

            $upper     = strtoupper($name['table']);
            $isPseudo  = $upper === 'DUAL' && !$opts['keep_pseudo_tables'];
            $isKeyword = in_array($upper, SQLWords::RESERVED, true);
            if ($isPseudo) {
                $notes[] = ['name' => $name['full'], 'reason' => 'pseudo_table'];
            } elseif ($isKeyword) {
                $notes[] = ['name' => $name['full'], 'reason' => 'reserved_keyword'];
            } else {
                $refs[] = [
                    'statement' => 0,
                    'type'      => '',
                    'clause'    => $clause,
                    'schema'    => $name['schema'],
                    'table'     => $name['table'],
                    'full'      => $name['full'],
                    'alias'     => $alias,
                    'quoted'    => $name['quoted'],
                    'quote'     => $name['quote'],
                ];
            }

            $j = $this->skipHints($tokens, $after);
            if ($this->isPunct($tokens[$j] ?? null, ',')) {
                $i = $j + 1;
                continue;
            }
            break;
        }
        return $refs;
    }


    /** 跳过 PARTITION(p) / WITH (NOLOCK) / TABLESAMPLE(...) 等紧邻提示 */
    private function skipHints(array $tokens, int $i): int
    {
        $n     = count($tokens);
        $guard = 0;
        while ($i + 1 < $n && $guard++ < 20) {
            $t = $tokens[$i];
            if ($t['t'] === SqlTokenType::Word
                && $this->isPunct($tokens[$i + 1], '(')
                && in_array(strtoupper($t['v']), ['PARTITION', 'SUBPARTITION', 'WITH', 'TABLESAMPLE'], true)
            ) {
                $i = $this->skipBalanced($tokens, $i + 1);
                continue;
            }
            break;
        }
        return $i;
    }


    /** 读取 AS 别名或裸别名；关键字不会被吞掉 */
    private function readAlias(array $tokens, int $i, int &$end, ?string &$alias): void
    {
        $end   = $i;
        $alias = null;
        $t     = $tokens[$i] ?? null;
        if ($this->isWord($t, 'AS')) {
            $end = $i + 1;
            $t2  = $tokens[$i + 1] ?? null;
            if ($t2 !== null && in_array($t2['t'], [SqlTokenType::Word, SqlTokenType::Ident], true)
                && !in_array(strtoupper($t2['v']), SQLWords::RESERVED, true)) {
                $alias = $t2['v'];
                $end   = $i + 2;
            }
            return;
        }
        if ($t !== null && in_array($t['t'], [SqlTokenType::Word, SqlTokenType::Ident], true)
            && !in_array(strtoupper($t['v']), SQLWords::RESERVED, true)) {
            $alias = $t['v'];
            $end   = $i + 1;
        }
    }


    /** 读取 db.schema.tbl / `db.tbl` / "Order Table"，最多保留全部段 */
    private function readQualifiedName(array $tokens, int $i, int &$end): ?array
    {
        if (!isset($tokens[$i]) || !in_array($tokens[$i]['t'], [SqlTokenType::Word, SqlTokenType::Ident], true)) {
            $end = $i;
            return null;
        }
        $parts   = [];
        $quoted  = false;
        $quote   = null;
        $parts[] = $tokens[$i]['v'];
        if ($tokens[$i]['t'] === SqlTokenType::Ident) {
            $quoted = true;
            $quote  = $tokens[$i]['q'];
        }
        $i++;
        while (isset($tokens[$i], $tokens[$i + 1])
            && $tokens[$i]['t'] === SqlTokenType::Dot
            && in_array($tokens[$i + 1]['t'], [SqlTokenType::Word, SqlTokenType::Ident], true)) {
            $parts[] = $tokens[$i + 1]['v'];
            if ($tokens[$i + 1]['t'] === SqlTokenType::Ident) {
                $quoted = true;
                $quote  = $tokens[$i + 1]['q'];
            }
            $i += 2;
        }
        $end    = $i;
        $table  = array_pop($parts);
        $schema = $parts !== [] ? implode('.', $parts) : null;
        return [
            'schema' => $schema,
            'table'  => $table,
            'full'   => $schema !== null ? $schema.'.'.$table : $table,
            'quoted' => $quoted,
            'quote'  => $quote,
        ];
    }

    private function skipModifiers(array $tokens, int $i): int
    {
        $n = count($tokens);
        while ($i < $n && $tokens[$i]['t'] === SqlTokenType::Word
            && in_array(strtoupper($tokens[$i]['v']), SQLWords::MODIFIERS, true)) {
            $i++;
        }
        return $i;
    }


    /** 每个 token 所在括号的类型：query / func / plain / 空（不在括号内） */
    private function parenContexts(array $tokens): array
    {
        $stack = [];
        $ctx   = [];
        foreach ($tokens as $i => $tok) {
            if ($tok['t'] === SqlTokenType::Paren && $tok['v'] === '(') {
                $first    = $tokens[$i + 1] ?? null;
                $isQuery  = $first !== null && $first['t'] === SqlTokenType::Word
                    && in_array(strtoupper($first['v']), ['SELECT', 'WITH', 'TABLE', 'VALUES'], true);
                $prev     = $tokens[$i - 1] ?? null;
                $prevWord = ($prev !== null
                    && in_array($prev['t'], [SqlTokenType::Word, SqlTokenType::Ident], true))
                    ? strtoupper($prev['v']) : '';
                $kind     = match (true) {
                    $isQuery => 'query',
                    $prevWord !== '' && !in_array($prevWord, SQLWords::NON_FUNC_WORDS, true) => 'func',
                    default => 'plain',
                };
                $stack[]  = $kind;
                $ctx[$i]  = $kind;
                continue;
            }
            if ($tok['t'] === SqlTokenType::Paren && $tok['v'] === ')') {
                array_pop($stack);
            }
            $ctx[$i] = $stack !== [] ? $stack[array_key_last($stack)] : '';
        }
        return $ctx;
    }

    /** 语句类型：括号深度 0 的第一个 DML/DDL 关键字 */
    private function statementType(array $tokens): string
    {
        $depth = 0;
        foreach ($tokens as $tok) {
            if ($tok['t'] === SqlTokenType::Paren && $tok['v'] === '(') {
                $depth++;
                continue;
            }
            if ($tok['t'] === SqlTokenType::Paren && $tok['v'] === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth !== 0 || $tok['t'] !== SqlTokenType::Word) {
                continue;
            }
            $w = strtoupper($tok['v']);
            if (in_array($w, SQLWords::STMT_WORDS, true)) {
                return $w;
            }
        }
        return 'UNKNOWN';
    }


    /** 收集 WITH cte AS (...) 的名字（含列清单、MATERIALIZED、多个 CTE） */
    private function collectCotes(array $tokens): array
    {
        $names = [];
        foreach ($tokens as $i => $iValue) {
            if (!$this->isWord($iValue, 'WITH')) {
                continue;
            }
            $j = $i + 1;
            if ($this->isWord($tokens[$j] ?? null, 'RECURSIVE')) {
                $j++;
            }
            while (true) {
                $nameTok = $tokens[$j] ?? null;
                if ($nameTok === null || !in_array($nameTok['t'], [SqlTokenType::Word, SqlTokenType::Ident], true)) {
                    break;
                }
                $j++;
                if ($this->isPunct($tokens[$j] ?? null, '(')) {
                    $j = $this->skipBalanced($tokens, $j); // 可选列清单 cte(a, b)
                }
                if (!$this->isWord($tokens[$j] ?? null, 'AS')) {
                    break;
                }
                $j++;
                if ($this->isWord($tokens[$j] ?? null, 'NOT')) {
                    $j++;
                }
                if ($this->isWord($tokens[$j] ?? null, 'MATERIALIZED')) {
                    $j++;
                }
                if (!$this->isPunct($tokens[$j] ?? null, '(')) {
                    break;
                }
                $names[] = strtolower($nameTok['v']);
                $j       = $this->skipBalanced($tokens, $j);
                if (!$this->isPunct($tokens[$j] ?? null, ',')) {
                    break;
                }
                $j++;
            }
        }
        return $names;
    }

    private function isWord(?array $tok, string $word): bool
    {
        return $tok !== null && $tok['t'] === SqlTokenType::Word && strtoupper($tok['v']) === $word;
    }

    private function isPunct(?array $tok, string $value): bool
    {
        if ($tok === null) {
            return false;
        }
        return match ($value) {
            '(' => $tok['t'] === SqlTokenType::Paren && $tok['v'] === '(',
            default => $tok['t'] === SqlTokenType::Punct && $tok['v'] === $value,
        };
    }

    private function skipBalanced(array $tokens, int $openIdx): int
    {
        $depth = 0;
        $n     = count($tokens);
        for ($k = $openIdx; $k < $n; $k++) {
            if ($tokens[$k]['t'] === SqlTokenType::Paren && $tokens[$k]['v'] === '(') {
                $depth++;
            } elseif ($tokens[$k]['t'] === SqlTokenType::Paren && $tokens[$k]['v'] === ')') {
                $depth--;
                if ($depth <= 0) {
                    return $k + 1;
                }
            }
        }
        return $n;
    }

    /**
     * 非 UTF-8 输入转码；失败返回 false 交给调用方降级（string|bool 联合类型）
     *
     * @param  string  $sql
     * @return string|bool
     */
    private function toUtf8(string $sql): string|bool
    {
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($sql, 'UTF-8', 'GB18030');
            if ($converted !== false) {
                return $converted;
            }
        }
        if (function_exists('iconv')) {
            $converted = @iconv('GB18030', 'UTF-8//IGNORE', $sql);
            if ($converted !== false) {
                return $converted;
            }
        }
        return false;
    }

    /** 按顶层分号切多语句（字符串/注释里的分号已在词法阶段被丢弃） */
    protected function splitStatements(array $tokens): array
    {
        $out = [];
        $buf = [];

        foreach ($tokens as $t) {
            if ($t['t'] === 'punct' && $t['v'] === ';') {
                if (count($buf) > 0) {
                    $out[] = $buf;
                }
                $buf = [];
                continue;
            }
            $buf[] = $t;
        }
        if (count($buf) > 0) {
            $out[] = $buf;
        }


        return $out;
    }

    /** @return array<string, bool> */
    private function defaults(): array
    {
        return [
            'with_schema'             => true,
            'double_quote_identifier' => true,
            'bracket_identifier'      => true,
            'keep_pseudo_tables'      => false,
            'include_cte'             => false,
            'case_insensitive_dedup'  => true
        ];
    }

}