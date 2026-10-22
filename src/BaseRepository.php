<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\Di\Annotation\Inject;
use Kooditorm\Repository\Contracts\RepositoryInterface;
use Kooditorm\Repository\Traits\CacheableRepository;

abstract class BaseRepository implements RepositoryInterface
{
    use CacheableRepository;
    #[Inject]
    protected ContainerInterface $app;

    public function __construct(protected Model|Builder $model)
    {

    }

}