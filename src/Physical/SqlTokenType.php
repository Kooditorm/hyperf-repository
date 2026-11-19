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

enum SqlTokenType: string
{
    case Word  = 'word';
    case Ident = 'ident';
    case Dot   = 'dot';
    case Paren = 'paren';
    case Punct = 'punct';
}
