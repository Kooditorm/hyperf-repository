<?php
declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repository/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Event;

class RepositoryCacheRemember
{
    public function __construct(public string $key, public string $sql, public int $expireAt = 0)
    {

    }
}