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

use Hyperf\Collection\Collection;

interface RepositoryInterface
{
    /**
     * @param string $column
     * @param string|null $key
     * @return Collection|array
     */
    public function lists(string $column, string|null $key = null): Collection|array;

    /**
     * @param array $columns
     * @return mixed
     */
    public function all(array $columns = ['*']): mixed;

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
     * @param int|string $id
     * @return int
     */
    public function delete(int|string $id): int;
}