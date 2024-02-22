<?php
declare(strict_types=1);

namespace Kooditorm\Repository\Eloquent;

use Closure;
use Hyperf\Collection\Collection;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Di\Container;
use Hyperf\Di\Exception\NotFoundException;
use Kooditorm\Repository\Contracts\CriteriaInterface;
use Kooditorm\Repository\Contracts\Presentable;
use Kooditorm\Repository\Contracts\PresenterInterface;
use Kooditorm\Repository\Contracts\RepositoryCriteriaInterface;
use Kooditorm\Repository\Contracts\RepositoryInterface;
use Kooditorm\Repository\Exceptions\RepositoryException;

abstract class BaseRepository implements RepositoryInterface, RepositoryCriteriaInterface
{
    protected Model|Builder $model;

    /**
     * @var array
     */
    protected array $fieldSearchable = [];

    /**
     * @var PresenterInterface|null
     */
    protected PresenterInterface|null $presenter;

    /**
     * Collection of Criteria
     *
     * @var Collection
     */
    protected Collection $criteria;


    /**
     * @var bool
     */
    protected bool $skipCriteria = false;

    /**
     * @var bool
     */
    protected bool $skipPresenter = false;

    /**
     * @var Closure|null
     */
    protected ?Closure $scopeQuery = null;

    public function __construct(protected Container $app)
    {
        $this->criteria = new Collection();
        $this->boot();
    }

    public function boot()
    {

    }

    /**
     * Returns the current Model instance
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @throws RepositoryException|NotFoundException
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
     * Specify Presenter class name
     *
     * @return string|null
     */
    public function presenter(): ?string
    {
        return null;
    }

    /**
     * Set Presenter
     *
     * @param $presenter
     *
     * @return $this
     * @throws NotFoundException
     */
    public function setPresenter($presenter): self
    {
        $this->makePresenter($presenter);

        return $this;
    }

    /**
     * @return Model
     * @throws RepositoryException|NotFoundException
     */
    public function makeModel(): Model
    {
        $model = $this->app->make($this->model());

        if (!$model instanceof Model) {
            throw new RepositoryException("Class {$this->model()} must be an instance of hyperf\\DbConnection\\Model\\Model");
        }

        return $this->model = $model;
    }


    /**
     * @param  null  $presenter
     *
     * @return PresenterInterface|null
     * @throws NotFoundException
     */
    public function makePresenter($presenter = null): ?PresenterInterface
    {
        $presenter = !is_null($presenter) ? $presenter : $this->presenter();

        if (!is_null($presenter)) {
            $this->presenter = is_string($presenter) ? $this->app->make($presenter) : $presenter;

            return $this->presenter;
        }

        return null;
    }

    /**
     * Get Searchable Fields
     *
     * @return array
     */
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    /**
     * Query Scope
     *
     * @param  Closure  $scope
     *
     * @return BaseRepository
     */
    public function scopeQuery(Closure $scope): self
    {
        $this->scopeQuery = $scope;

        return $this;
    }

    /**
     * Retrieve data array for populate field select
     *
     * @param  string  $column
     * @param  string|null  $key
     *
     * @return Collection|array
     */
    public function lists(string $column, $key = null): array|Collection
    {
        $this->applyCriteria();

        return $this->model->lists($column, $key);
    }


    /**
     * Retrieve data array for populate field select
     *
     * @param  string  $column
     * @param  string|null  $key
     *
     * @return Collection|array
     */
    public function pluck(string $column, $key = null): array|Collection
    {
        $this->applyCriteria();

        return $this->model->pluck($column, $key);
    }


    /**
     * Sync relations
     *
     * @param      $id
     * @param      $relation
     * @param      $attributes
     * @param  bool  $detaching
     *
     * @return mixed
     */
    public function sync($id, $relation, $attributes, bool $detaching = true): mixed
    {
        return $this->find($id)->{$relation}()->sync($attributes, $detaching);
    }


    /**
     * SyncWithoutDetaching
     *
     * @param $id
     * @param $relation
     * @param $attributes
     *
     * @return mixed
     */
    public function syncWithoutDetaching($id, $relation, $attributes): mixed
    {
        return $this->sync($id, $relation, $attributes, false);
    }


    /**
     * Retrieve all data of repository
     *
     * @param  array  $columns
     *
     * @return mixed
     * @throws NotFoundException
     * @throws RepositoryException
     */
    public function all(array $columns = ['*']): mixed
    {
        $this->applyCriteria();
        $this->applyScope();

        if ($this->model instanceof Builder) {
            $results = $this->model->get($columns);
        } else {
            $results = $this->model->all($columns);
        }

        $this->resetModel();
        $this->resetScope();

        return $this->parserResult($results);
    }


    /**
     * Apply scope in current Query
     *
     * @return $this
     */
    protected function applyScope(): self
    {
        if (isset($this->scopeQuery) && is_callable($this->scopeQuery)) {
            $callback    = $this->scopeQuery;
            $this->model = $callback($this->model);
        }

        return $this;
    }

    /**
     * Apply criteria in current Query
     *
     * @return $this
     */
    protected function applyCriteria(): self
    {
        if ($this->skipCriteria === true) {
            return $this;
        }

        $criteria = $this->getCriteria();

        if ($criteria) {
            foreach ($criteria as $c) {
                if ($c instanceof CriteriaInterface) {
                    $this->model = $c->apply($this->model, $this);
                }
            }
        }

        return $this;
    }

    /**
     * @param $result
     * @return BaseRepository|null
     * @throws NotFoundException
     */
    public function parserResult($result): null|static
    {
        if ($this->presenter instanceof PresenterInterface) {
            if ($result instanceof Collection || $result instanceof LengthAwarePaginatorInterface) {
                $result->each(function ($model) {
                    if ($model instanceof Presentable) {
                        $model->setPresenter($this->presenter);
                    }

                    return $model;
                });
            } elseif ($result instanceof Presentable) {
                $result = $this->setPresenter($this->presenter);
            }

            if (!$this->skipPresenter) {
                return $this->presenter->present($result);
            }
        }

        return $result;
    }

}
