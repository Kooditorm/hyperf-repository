<?php
declare(strict_types=1);

namespace Kooditorm\Repository\Contracts;

use Closure;
use Hyperf\Collection\Collection;

/**
 * Interface RepositoryInterface
 * @package Kooditorm\Repository\Contracts
 */
interface RepositoryInterface
{
    /**
     * Retrieve data array for populate field select
     *
     * @param  string  $column
     * @param  string|null  $key
     *
     * @return Collection|array
     */
    public function lists(string $column, string $key = null): array|Collection;

    /**
     * Retrieve data array for populate field select
     *
     * @param  string  $column
     * @param  string|null  $key
     *
     * @return Collection|array
     */
    public function pluck(string $column, string $key = null): array|Collection;

    /**
     * Sync relations
     *
     * @param $id
     * @param $relation
     * @param $attributes
     * @param  bool  $detaching
     * @return mixed
     */
    public function sync($id, $relation, $attributes, bool $detaching = true): mixed;

    /**
     * SyncWithoutDetaching
     *
     * @param $id
     * @param $relation
     * @param $attributes
     * @return mixed
     */
    public function syncWithoutDetaching($id, $relation, $attributes): mixed;

    /**
     * Retrieve all data of repository
     *
     * @param  array  $columns
     *
     * @return mixed
     */
    public function all(array $columns = ['*']): mixed;

    /**
     * Retrieve all data of repository, paginated
     *
     * @param  null  $limit
     * @param  array  $columns
     *
     * @return mixed
     */
    public function paginate($limit = null, array $columns = ['*']): mixed;

    /**
     * Retrieve all data of repository, simple paginated
     *
     * @param  null  $limit
     * @param  array  $columns
     *
     * @return mixed
     */
    public function simplePaginate($limit = null, array $columns = ['*']): mixed;

    /**
     * Find data by id
     *
     * @param       $id
     * @param  array  $columns
     *
     * @return mixed
     */
    public function find($id, array $columns = ['*']): mixed;

    /**
     * Find data by field and value
     *
     * @param       $field
     * @param       $value
     * @param  array  $columns
     *
     * @return mixed
     */
    public function findByField($field, $value, array $columns = ['*']): mixed;

    /**
     * Find data by multiple fields
     *
     * @param  array  $where
     * @param  array  $columns
     *
     * @return mixed
     */
    public function findWhere(array $where, array $columns = ['*']): mixed;

    /**
     * Find data by multiple values in one field
     *
     * @param       $field
     * @param  array  $values
     * @param  array  $columns
     *
     * @return mixed
     */
    public function findWhereIn($field, array $values, array $columns = ['*']): mixed;

    /**
     * Find data by excluding multiple values in one field
     *
     * @param       $field
     * @param  array  $values
     * @param  array  $columns
     *
     * @return mixed
     */
    public function findWhereNotIn($field, array $values, array $columns = ['*']): mixed;

    /**
     * Find data by between values in one field
     *
     * @param       $field
     * @param  array  $values
     * @param  array  $columns
     *
     * @return mixed
     */
    public function findWhereBetween($field, array $values, array $columns = ['*']): mixed;

    /**
     * Save a new entity in repository
     *
     * @param  array  $attributes
     *
     * @return mixed
     */
    public function create(array $attributes): mixed;

    /**
     * Update a entity in repository by id
     *
     * @param  array  $attributes
     * @param       $id
     *
     * @return mixed
     */
    public function update(array $attributes, $id): mixed;

    /**
     * Update or Create an entity in repository
     *
     *
     * @param  array  $attributes
     * @param  array  $values
     *
     * @return mixed
     */
    public function updateOrCreate(array $attributes, array $values = []): mixed;

    /**
     * Delete a entity in repository by id
     *
     * @param $id
     *
     * @return int
     */
    public function delete($id): int;

    /**
     * Order collection by a given column
     *
     * @param  string  $column
     * @param  string  $direction
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): self;

    /**
     * Load relations
     *
     * @param $relations
     *
     * @return $this
     */
    public function with($relations): self;

    /**
     * Load relation with closure
     *
     * @param  string  $relation
     * @param  closure  $closure
     *
     * @return $this
     */
    public function whereHas(string $relation, closure $closure): self;

    /**
     * Add subselect queries to count the relations.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function withCount(mixed $relations): self;

    /**
     * Set hidden fields
     *
     * @param  array  $fields
     *
     * @return $this
     */
    public function hidden(array $fields): self;

    /**
     * Set visible fields
     *
     * @param  array  $fields
     *
     * @return $this
     */
    public function visible(array $fields): self;

    /**
     * Query Scope
     *
     * @param  Closure  $scope
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

    /**
     * Get Searchable Fields
     *
     * @return array
     */
    public function getFieldsSearchable(): array;

    /**
     * Set Presenter
     *
     * @param $presenter
     *
     * @return mixed
     */
    public function setPresenter($presenter): mixed;

    /**
     * Skip Presenter Wrapper
     *
     * @param  bool  $status
     *
     * @return $this
     */
    public function skipPresenter(bool $status = true): self;

    /**
     * Retrieve first data of repository, or return new Entity
     *
     * @param  array  $attributes
     *
     * @return mixed
     */
    public function firstOrNew(array $attributes = []): mixed;

    /**
     * Retrieve first data of repository, or create new Entity
     *
     * @param  array  $attributes
     *
     * @return mixed
     */
    public function firstOrCreate(array $attributes = []): mixed;

    /**
     * Trigger static method calls to the model
     *
     * @param $method
     * @param $arguments
     *
     * @return mixed
     */
    public static function __callStatic($method, $arguments);

    /**
     * Trigger method calls to the model
     *
     * @param  string  $method
     * @param  array  $arguments
     *
     * @return mixed
     */
    public function __call(string $method, array $arguments);
}
