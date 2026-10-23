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

use Closure;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\PaginatorInterface;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Di\Annotation\Inject;
use Kooditorm\Repository\Contracts\RepositoryInterface;
use Kooditorm\Repository\Traits\CacheableRepository;
use function Hyperf\Config\config;

abstract class BaseRepository implements RepositoryInterface
{
    use CacheableRepository;

    /**
     * @var ContainerInterface
     */
    #[Inject]
    protected ContainerInterface $app;

    /**
     * @var Closure|null
     */
    protected Closure|null $scopeQuery = null;


    public function __construct(protected Model|Builder $model)
    {

    }

    /**
     * Query Scope
     *
     * @param Closure $scope
     * @return RepositoryInterface
     */
    public function scopeQuery(Closure $scope): RepositoryInterface
    {
        $this->scopeQuery = $scope;
        return $this;
    }

    /**
     * Retrieve all data of repository
     *
     * @param array $columns
     * @return array
     */
    public function all(array $columns = ['*']): array
    {
        $this->applyScope();
        $results = $this->query()->get($columns)->toArray();
        $this->resetScope();
        return $results;
    }

    /**
     * Count results of repository
     *
     * @param array $where
     * @param string $columns
     * @return int
     */
    public function count(array $where = [], string $columns = '*'): int
    {
        $this->applyScope();

        if (!empty($where)) {
            $this->query()->where($where);
        }

        $result = $this->query()->count($columns);

        $this->resetScope();
        return $result;
    }

    /**
     * etrieve all data of repository, paginated
     *
     * @param int|null $limit
     * @param array $columns
     * @param string $method
     * @return array
     */
    public function paginate(int|null $limit = null, array $columns = ['*'], string $method = 'paginate'): array
    {
        $this->applyScope();
        $limit = is_null($limit) ? config('repository.pagination.limit', 15) : $limit;
        $results = $this->query()->{$method}($limit, $columns);
        if ($results instanceof Collection || $results instanceof PaginatorInterface) {
            $results = $results->toArray();
        }
        $this->resetScope();
        return $results;
    }

    /**
     * Find data by id
     *
     * @param int|string $id
     * @param array $columns
     * @return array
     */
    public function find(int|string $id, array $columns = ['*']): array
    {
        $this->applyScope();
        $result = [];
        $model = $this->query()->findOrFail($id, $columns);
        if (!empty($model) && $model instanceof Collection) {
            $result = $model->toArray();
        }
        $this->resetScope();
        return $result;
    }

    public function create(array $attributes): mixed
    {
        // TODO: Implement create() method.
    }

    public function update(array $attributes, int|string $id): mixed
    {

    }

    public function delete(int|string $id): int
    {

    }

    /**
     * Reset Query Scope
     *
     * @return RepositoryInterface
     */
    public function resetScope(): RepositoryInterface
    {
        $this->scopeQuery = null;
        return $this;
    }


    /**
     * Apply scope in current Query
     *
     * @return $this
     */
    protected function applyScope(): self
    {
        if (isset($this->scopeQuery) && is_callable($this->scopeQuery)) {
            $callback = $this->scopeQuery;
            $this->model = $callback($this->query());
        }

        return $this;
    }


    /**
     * @return Model|Builder
     */
    private function query(): Model|Builder
    {
        return $this->model instanceof Builder ? clone $this->model : $this->model->newQuery();
    }

}