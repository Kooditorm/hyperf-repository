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

class SqlLexer
{
    /**
     * @var string
     */
    private const TOKEN_RE = <<<'REGEX'
/
  (?<ws>      \s+ )                                        # 空白
| (?<comment> --[^\r\n]* | [#][^\r\n]* | \/\*[\s\S]*?\*\/ )  # 行注释与块注释
| (?<bq>      `(?:[^`]|``)*` )                            # 反引号标识符
| (?<sq>      '(?:[^'\\]|\\.|'')*' )                      # 单引号字符串
| (?<dq>      "(?:[^"]|"")*" )                            # 双引号标识符或字符串
| (?<br>      \[[^\]]*\] )                                # 方括号标识符
| (?<word>    [\p{L}_][\p{L}\p{N}_$]* )                   # 关键字与标识符，含中文
| (?<num>     [0-9]+(?:\.[0-9]+)? )                       # 数字
| (?<dot>     \. )
| (?<paren>   [()] )
| (?<comma>   , )
| (?<semi>    ; )
| (?<other>   [\s\S] )                                    # 兜底，其余任意单字符
/ux
REGEX;

    public function tokenize(string $sql, array $opts): array
    {
        // 刻意不加 PREG_UNMATCHED_AS_NULL：未命中组为空串、尾部组缺失，结果数组更小、实测更快
        preg_match_all(self::TOKEN_RE, $sql, $sets, PREG_SET_ORDER);
        $tokens = [];
        foreach ($sets as $m) {
            // 数字键是捕获组索引；唯一非空的命名组就是命中的分支
            $kind = null;
            foreach ($m as $key => $value) {
                if (is_string($key) && $value !== '') {
                    $kind = $key;
                    break;
                }
            }
            $token = match ($kind) {
                'bq' => [
                    't' => SqlTokenType::Ident,
                    'v' => str_replace('``', '`', substr($m['bq'], 1, -1)),
                    'q' => '`',
                ],
                'dq' => $opts['double_quote_identifier']
                    ? [
                        't' => SqlTokenType::Ident,
                        'v' => str_replace('""', '"', substr($m['dq'], 1, -1)),
                        'q' => '"',
                    ] : null,
                'br' => $opts['bracket_identifier']
                    ? [
                        't' => SqlTokenType::Ident,
                        'v' => str_replace(']]', ']', substr($m['br'], 1, -1)),
                        'q' => '[',
                    ] : null,
                'word' => [
                    't' => SqlTokenType::Word,
                    'v' => $m['word'],
                    'q' => null,
                ],
                'dot' => [
                    't' => SqlTokenType::Dot, 'v' => '.', 'q' => null,
                ],
                'paren' => [
                    't' => SqlTokenType::Paren, 'v' => $m['paren'], 'q' => null,
                ],
                'comma', 'semi' => [
                    't' => SqlTokenType::Punct,
                    'v' => $kind === 'semi' ? ';' : ',',
                    'q' => null,
                ],
                // ws / comment / sq / num / other 统一丢弃
                default => null,
            };
            if ($token !== null) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

}