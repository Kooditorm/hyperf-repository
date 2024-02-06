<?php
declare(strict_types=1);

namespace Kooditorm\Repository\Contracts;

/**
 * Interface CriteriaInterface
 * @package Kooditorm\Repository\Contracts
 */
interface CriteriaInterface
{

    /**
     * Apply criteria in query repository
     *
     * @param                     $model
     * @param RepositoryInterface $repository
     *
     * @return mixed
     */
    public function apply($model, RepositoryInterface $repository): mixed;
}
