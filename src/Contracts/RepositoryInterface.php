<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Contracts;

use Closure;

interface RepositoryInterface
{
    /**
     * Retrieve all data of repository
     *
     * @param array $columns
     * @return mixed
     */
    public function all(array $columns = ['*']): mixed;

    /**
     * Count results of repository
     *
     * @param array $where
     * @param string $columns
     * @return int
     */
    public function count(array $where = [], string $columns = '*'): int;

    /**
     * Retrieve all data of repository, paginated
     *
     * @param int|null $limit
     * @param array $columns
     * @param string $method 实际调用的模型分页方法（paginate / simplePaginate ...）
     * @return mixed
     */
    public function paginate(int|null $limit = null, array $columns = ['*'], string $method = 'paginate'): mixed;

    /**
     * Find data by id
     *
     * @param int|string $id
     * @param array $columns
     * @return mixed
     */
    public function find(int|string $id, array $columns = ['*']): mixed;

    /**
     * Save a new entity in repository
     *
     * @param array $attributes
     * @return mixed
     */
    public function create(array $attributes): mixed;


    /**
     * Update a entity in repository by id
     *
     * @param array $attributes
     * @param int|string $id
     *
     * @return mixed
     */
    public function update(array $attributes, int|string $id): mixed;

    /**
     * Delete a entity in repository by id
     *
     * @param int|string $id
     * @return int
     */
    public function delete(int|string $id): int;

    /**
     * Query Scope
     *
     * @param Closure $scope
     *
     * @return $this
     */
    public function scopeQuery(Closure $scope): self;


    /**
     * Reset Query Scope
     *
     * @return $this
     */
    public function resetScope(): self;
}