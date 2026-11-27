# hyperf-repository

Hyperf 仓库模式（Repository Pattern）扩展包。提供基于 Eloquent Model 的统一数据访问层，内置 CRUD、分页、查询作用域，以及基于事件驱动的**按表自动失效**的 Redis 查询缓存机制。

> 基于 Hyperf 3.1 / PHP 8.1+，MIT 开源协议。

## 目录

- [安装](#安装)
- [快速开始](#快速开始)
- [目录结构](#目录结构)
- [API 一览](#api-一览)
  - [模型管理](#模型管理)
  - [查询](#查询)
  - [写入](#写入)
  - [分页](#分页)
- [缓存机制](#缓存机制)
  - [配置](#配置)
  - [工作原理（TCK 映射）](#工作原理tck-映射)
  - [注意事项](#注意事项)
- [事件](#事件)
- [辅助函数](#辅助函数)
- [License](#license)

## 安装

```bash
composer require kooditorm/hyperf-repository
```

依赖要求：

| 依赖 | 版本 |
|---|---|
| PHP | >= 8.1 |
| Hyperf 组件（collection / di / db-connection / cache / http-server / config / crontab） | ~3.1.0 |
| ext-redis | * |

发布默认配置：

```bash
php bin/hyperf.php vendor:publish kooditorm/hyperf-repository
```

配置文件将发布到 `config/autoload/repository.php`。

## 快速开始

### 1. 定义 Repository

继承 `BaseRepository` 并实现抽象方法 `model()`，返回模型类名（必须是 `Hyperf\Database\Model\Model` 的子类）：

```php
<?php

namespace App\Repository;

use App\Model\User;
use Kooditorm\Repository\BaseRepository;

class UserRepository extends BaseRepository
{
    public function model(): string
    {
        return User::class;
    }
}
```

### 2. 注入使用

构造函数由 Hyperf DI 容器自动解析，直接在控制器 / 服务中注入即可：

```php
use App\Repository\UserRepository;

class UserController extends AbstractController
{
    public function __construct(private UserRepository $repo)
    {
    }

    public function index(): array
    {
        return $this->repo
            ->scopeQuery(fn ($query) => $query->where('status', 1)->orderByDesc('id'))
            ->paginate(20);
    }
}
```

### 3. 可选：Repository 级缓存配置

通过在子类中声明属性，可对单个 Repository 精细控制缓存：

```php
class UserRepository extends BaseRepository
{
    // 仅缓存这些方法（与配置 repository.cache.allowed.only 二选一，实例属性优先）
    protected array|null $cacheOnly = ['all', 'paginate'];

    // 排除这些方法
    protected array|null $cacheExcept = null;

    // 缓存时长（秒），优先于配置 repository.cache.minutes
    protected int|null $cacheMinutes = 3600;
}
```

## 目录结构

```
src/
├── BaseRepository.php              # 抽象仓储基类（CRUD / 分页 / scopeQuery）
├── ConfigProvider.php              # Hyperf 组件配置（依赖绑定、监听器、注解扫描、发布配置）
├── helpers.php                     # 全局辅助函数 value() / event()
├── Contracts/
│   ├── RepositoryInterface.php     # 仓储接口
│   └── CacheInterface.php          # 缓存接口（put / forever / remember / getStore）
├── Traits/
│   └── CacheableRepository.php     # 缓存 Trait（为 all()/paginate() 增加缓存层）
├── Cache/
│   ├── CacheRepository.php         # CacheInterface 默认实现（基于 Hyperf\Cache\CacheManager）
│   └── TCKMapping.php              # Redis「表 → 缓存 key」映射常量与工具
├── Event/
│   ├── RepositoryEventBase.php     # 事件基类
│   ├── RepositoryEntityCreating|Created|Updating|Updated|Deleting|Deleted.php
│   └── RepositoryCacheRemember.php # 缓存回填事件（携带 key / SQL / 过期时间）
├── Listener/
│   ├── CleanCacheRepository.php    # 实体变更后按表清空相关缓存
│   └── GenTCKMapping.php           # 缓存回填时解析 SQL，写入表映射
├── Crontab/
│   └── TCKMappingCleaner.php       # 每分钟清理过期映射的定时任务
├── Physical/
│   ├── GatherSqlTable.php          # SQL 表名解析器（支持 SELECT/INSERT/UPDATE/DELETE/JOIN/CTE 等）
│   ├── SqlLexer.php                # SQL 词法分析器
│   ├── SqlTokenType.php            # Token 类型枚举
│   └── SQLWords.php                # SQL 关键字常量集
└── Exceptions/
    └── RepositoryException.php     # 仓储异常
```

## API 一览

### 模型管理

| 方法 | 说明 |
|---|---|
| `model(): string` | 抽象方法，子类返回模型类名 |
| `makeModel(): Model` | 从容器实例化模型并重置查询构建器；非 `Hyperf\Database\Model\Model` 时抛出 `RepositoryException` |
| `getModel(): Model` | 返回当前模型实例 |
| `getQuery(): Builder` | 返回当前 `Hyperf\Database\Model\Builder`，可用于自由组装复杂查询 |
| `resetModel(): void` | 重新实例化模型，重置查询状态 |

### 查询

| 方法 | 签名 | 说明 |
|---|---|---|
| `scopeQuery` | `scopeQuery(Closure $scope): RepositoryInterface` | 设置查询作用域，闭包接收 `Builder` 并返回修改后的 `Builder`；执行查询后自动 `resetScope()` |
| `resetScope` | `resetScope(): RepositoryInterface` | 手动清空作用域 |
| `all` | `all(array $columns = ['*']): array` | 应用作用域后 `get()`，返回数组 |
| `get` | `get(array $columns = ['*']): array` | `all()` 的别名 |
| `first` | `first(array $columns = ['*']): array` | 应用作用域后取第一条，**无结果时抛出模型异常** |
| `find` | `find(int\|string $id, array $columns = ['*']): array` | `findOrFail`，**找不到时抛出模型异常** |
| `findWhere` | `findWhere(array $where, array $columns = ['*']): array` | 将 `$where` 数组传给 Builder 的 `where()` 后查询 |
| `count` | `count(array $where = [], string $columns = '*'): int` | 可选条件后计数 |

复杂条件示例（闭包作用域）：

```php
$users = $this->repo
    ->scopeQuery(fn ($query) => $query
        ->where('age', '>=', 18)
        ->when($keyword, fn ($q) => $q->where('name', 'like', "%{$keyword}%"))
    )
    ->all(['id', 'name']);
```

也可以直接使用底层 Builder：

```php
$query = $this->repo->getQuery();
$query->where('status', 1)->limit(10)->get();
```

### 写入

写入操作会自动触发对应的生命周期事件（见[事件](#事件)章节）：

| 方法 | 签名 | 说明 |
|---|---|---|
| `create` | `create(array $attributes): Model\|Collection\|array` | 派发 `Creating` → 保存 → 派发 `Created` |
| `update` | `update(array $attributes, int\|string $id): Model\|Collection\|array` | `findOrFail` → 派发 `Updating` → `fill()->save()` → 派发 `Updated` |
| `delete` | `delete(int\|string $id): bool` | 派发 `Deleting` → 删除 → 派发 `Deleted`；**删除异常会被捕获并返回 `false`** |

> 本包不封装事务。事务请直接在业务层使用 `Hyperf\DbConnection\Db::transaction()`。

### 分页

```php
paginate(int|null $limit = null, array $columns = ['*'], string $method = 'paginate'): array
```

- `$limit` 为 `null` 时取配置 `repository.pagination.limit`（默认 15）。
- `$method` 可传 `simplePaginate` 等其他分页方式，动态调用 Builder 的同名方法。

## 缓存机制

`CacheableRepository` Trait 为 `all()` 与 `paginate()` 提供透明缓存层，其余方法（`find`、`findWhere` 等）不走缓存。

### 配置

```php
// config/autoload/repository.php
return [
    'pagination' => [
        'limit' => 15,
    ],
    'cache' => [
        // 缓存总开关（代码读取键名为 enable）
        'enable'  => true,
        // 缓存时长（分钟）
        'minutes' => 30,
        // 方法白名单 / 黑名单，与 Repository 实例属性二选一（实例属性优先）
        'allowed' => [
            'only'   => null,   // 如 ['all', 'paginate']
            'except' => null,   // 如 ['find']
        ],
    ],
];
```

判定逻辑：总开关 `repository.cache.enable` 关闭时全部不缓存；否则先看实例属性 `$cacheOnly` / `$cacheExcept`，回退到配置项；两者均未设置时默认缓存所有走缓存层的方法。

### 工作原理（TCK 映射）

写操作如何精确失效读缓存？核心是 **Table-CacheKey（TCK）映射**：

1. **缓存回填**：`all()` / `paginate()` 未命中缓存时执行真实查询，`CacheRepository::remember()` 抓取本次查询的最后一条 SQL，派发 `RepositoryCacheRemember` 事件（携带缓存 key、SQL、过期时间戳）。
2. **生成映射**：监听器 `GenTCKMapping` 使用内置的 SQL 解析器（`GatherSqlTable`，词法 + 语法分析，支持 JOIN、子查询、CTE 等多表场景）解析 SQL 涉及的**所有表名**，写入 Redis：
   - `hset(repo:tk:{table}, cacheKey, expireAt)`
   - `sadd(repo:tk:tables, table)`
3. **缓存失效**：`create()` / `update()` / `delete()` 成功后派发对应事件，监听器 `CleanCacheRepository` 根据模型的表名读取 `repo:tk:{table}`，删除该表关联的所有缓存 key，并清理过期映射。
4. **兜底清理**：定时任务 `TCKMappingCleaner`（`#[Crontab(rule: '* * * * *')]`）每分钟扫描并删除过期条目，防止冷表残留映射。定时任务依赖宿主安装并启用 `hyperf/crontab`。

这样即使查询涉及多张 JOIN 表，任一相关表发生写操作都会正确失效对应缓存。

### 注意事项

- 缓存失效机制依赖 Redis：请将 Hyperf 缓存驱动配置为 Redis 驱动（监听器直接操作 Redis hash / set）。
- 缓存 key 中混入了当前 HTTP 请求的 `fullUrl()`，即缓存结果与请求 URL（含分页、筛选参数）绑定，非 HTTP 上下文（如 CLI）下使用缓存需注意此行为。
- 已发布配置中若写的是 `'enabled'`，请改为 `'enable'`（代码读取键名为 `enable`，且默认值为 `true`）。
- 自定义缓存实现：实现 `Kooditorm\Repository\Contracts\CacheInterface`，通过 `setCacheRepository()` 注入，或在依赖绑定中替换 `CacheInterface::class => YourCache::class`。

## 事件

所有实体事件继承 `RepositoryEventBase`，可通过 `getModel()`、`getRepository()`、`getAction()` 获取上下文。

| 事件 | 触发时机 | 说明 |
|---|---|---|
| `RepositoryEntityCreating` | `create()` 保存前 | 携带待写入的 `array $attributes` |
| `RepositoryEntityCreated` | `create()` 保存后 | 携带新建的 `Model` |
| `RepositoryEntityUpdating` | `update()` 保存前 | 携带原始 `Model` |
| `RepositoryEntityUpdated` | `update()` 保存后 | 携带更新后的 `Model` |
| `RepositoryEntityDeleting` | `delete()` 删除前 | 携带原始 `Model` |
| `RepositoryEntityDeleted` | `delete()` 删除后 | 携带删除前的 `Model` 克隆 |
| `RepositoryCacheRemember` | 缓存回填后 | 携带 `key`、`sql`、`expireAt` |

监听示例（标准 Hyperf 监听器，无需额外注册）：

```php
<?php

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Kooditorm\Repository\Event\RepositoryEntityCreated;

#[Listener]
class UserCreatedListener implements ListenerInterface
{
    public function listen(): array
    {
        return [RepositoryEntityCreated::class];
    }

    public function process(object $event): void
    {
        $model = $event->getModel();
        // ...
    }
}
```

包内监听器（`CleanCacheRepository`、`GenTCKMapping`）已通过 `ConfigProvider` 自动注册，无需配置。

## 辅助函数

由 composer autoload `files` 自动加载（均有 `function_exists` 保护）：

```php
// 闭包则立即执行并返回结果，否则原样返回
value(mixed $value, ...$args): mixed

// 通过 ApplicationContext 获取 PSR-14 EventDispatcherInterface 派发事件
// $event 可传对象、类名，或「类名 + 构造参数数组」
event(object|string $event, array $payload = []): object
```

## License

[MIT](./LICENSE)
