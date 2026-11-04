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
use Kooditorm\Repository\Contracts\RepositoryInterface;
use Kooditorm\Repository\Event\RepositoryEntityCreated;
use Kooditorm\Repository\Event\RepositoryEntityCreating;
use Kooditorm\Repository\Event\RepositoryEntityDeleting;
use Kooditorm\Repository\Event\RepositoryEntityUpdated;
use Kooditorm\Repository\Event\RepositoryEntityUpdating;
use Kooditorm\Repository\Exceptions\RepositoryException;
use Kooditorm\Repository\Traits\CacheableRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;
use function Hyperf\Config\config;

abstract class BaseRepository implements RepositoryInterface
{
    use CacheableRepository;

    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $app;

    /**
     * @var Model
     */
    protected Model $model;

    /**
     * @var Closure|null
     */
    protected Closure|null $scopeQuery = null;

    /**
     * @param ContainerInterface $app
     * @throws RepositoryException
     */
    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
        $this->makeModel();
    }

    /**
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @throws RepositoryException
     */
    public function resetModel(): void
    {
        $this->makeModel();
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    abstract public function model(): string;

    /**
     * @return Model
     * @throws RepositoryException
     */
    public function makeModel(): Model
    {
        try {
            $model = $this->app->make($this->model());
        } catch (Throwable $e) {
            throw new RepositoryException($e->getMessage());
        }

        if (!$model instanceof Model) {
            throw new RepositoryException('The model class must be an instance of Hyperf\Database\Model\Model');
        }

        return $this->model = $model;
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
     * @throws RepositoryException
     */
    public function all(array $columns = ['*']): array
    {
        $this->applyScope();
        $results = $this->model::all($columns)->toArray();

        $this->resetModel();
        $this->resetScope();
        return $results;
    }

    /**
     * Count results of repository
     *
     * @param array $where
     * @param string $columns
     * @return int
     * @throws RepositoryException
     */
    public function count(array $where = [], string $columns = '*'): int
    {
        $this->applyScope();

        if (!empty($where)) {
            $this->model::where($where);
        }

        $result = $this->model::count($columns);

        $this->resetModel();
        $this->resetScope();
        return $result;
    }

    /**
     * @param array $columns
     * @return array
     * @throws RepositoryException
     */
    public function get(array $columns = ['*']): array
    {
        return $this->all($columns);
    }

    /**
     * etrieve all data of repository, paginated
     *
     * @param int|null $limit
     * @param array $columns
     * @param string $method
     * @return array
     * @throws RepositoryException
     */
    public function paginate(int|null $limit = null, array $columns = ['*'], string $method = 'paginate'): array
    {
        $this->applyScope();
        $limit = is_null($limit) ? config('repository.pagination.limit', 15) : $limit;
        $results = $this->model::{$method}($limit, $columns);
        $sql = $this->model::getQuery()->toSql();
        if ($results instanceof Collection || $results instanceof PaginatorInterface) {
            $results = $results->toArray();
        }
        $this->resetModel();
        return $results;
    }

    /**
     * Find data by id
     *
     * @param int|string $id
     * @param array $columns
     * @return array
     * @throws RepositoryException
     */
    public function find(int|string $id, array $columns = ['*']): array
    {
        $this->applyScope();
        $model = $this->query()->findOrFail($id, $columns)->toArray();
        $this->resetModel();
        return $model;
    }

    /**
     * Save a new entity in repository
     *
     * @param array $attributes
     * @return Model|Collection|array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|RepositoryException
     */
    public function create(array $attributes): Model|Collection|array
    {
        event(new RepositoryEntityCreating($this, $attributes));
        $model = $this->model->newInstance($attributes);
        $model->save();
        event(new RepositoryEntityCreated($this, $model));
        $this->resetModel();
        return $model;
    }

    /**
     * @param array $attributes
     * @param int|string $id
     * @return Model|Collection|array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|RepositoryException
     */
    public function update(array $attributes, int|string $id): Model|Collection|array
    {
        $this->applyScope();
        $model = $this->model::findOrFail($id);
        event(new RepositoryEntityUpdating($this, $model));
        $model->fill($attributes);
        $model->save();
        $this->resetModel();
        event(new RepositoryEntityUpdated($this, $model));
        return $model;
    }

    /**
     * @param int|string $id
     * @return int
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|RepositoryException
     */
    public function delete(int|string $id): int
    {
        $this->applyScope();
        $model = $this->find($id);
        $originalModel = clone $model;
        $this->resetModel();
        event(new RepositoryEntityDeleting($this, $model));
        $deleted = $model->delete();
        event(new RepositoryEntityUpdated($this, $originalModel));
        return $deleted;
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
            $this->model = $callback($this->model);
        }

        return $this;
    }

    /**
     * @return Builder
     */
    private function query(): Builder
    {
        return $this->model::query();
    }

}