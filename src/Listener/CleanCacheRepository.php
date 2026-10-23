<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

namespace Kooditorm\Repository\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Kooditorm\Repository\Event\RepositoryEntityCreated;
use Kooditorm\Repository\Event\RepositoryEntityDeleted;
use Kooditorm\Repository\Event\RepositoryEntityUpdated;

#[Listener]
class CleanCacheRepository implements ListenerInterface
{
    public function listen():array
    {
        return [
            RepositoryEntityCreated::class,
            RepositoryEntityUpdated::class,
            RepositoryEntityDeleted::class
        ];
    }

    public function process(object $event): void
    {
        var_dump($event->getAction());
    }
}