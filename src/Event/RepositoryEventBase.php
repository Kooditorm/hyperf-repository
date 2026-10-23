<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Event;

use Hyperf\Database\Model\Model;
use Kooditorm\Repository\Contracts\RepositoryInterface;

abstract class RepositoryEventBase
{
    protected string $action;

    public function __construct(protected RepositoryInterface $repository, protected Model|array|null $model = null)
    {

    }

    /**
     * @return Model|array
     */
    public function getModel(): Model|array
    {
        return $this->model;
    }

    /**
     * @return RepositoryInterface
     */
    public function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}