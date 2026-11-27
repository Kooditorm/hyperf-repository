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

use Kooditorm\Repository\Cache\CacheRepository;
use Kooditorm\Repository\Contracts\CacheInterface;
use Kooditorm\Repository\Listener\CleanCacheRepository;
use Kooditorm\Repository\Listener\GenTableCacheKeyMapping;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                CacheInterface::class => CacheRepository::class
            ],
            'listeners' => [
                CleanCacheRepository::class,
                GenTableCacheKeyMapping::class
            ],
            'commands' => [],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                    'ignore_annotations' => [
                        'mixin',
                        // 宿主未安装 hyperf/crontab 时跳过该注解，避免扫描报错
                        'Hyperf\Crontab\Annotation\Crontab',
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for repository.',
                    'source' => __DIR__ . '/../publish/repository.php',
                    'destination' => BASE_PATH . '/config/autoload/repository.php',
                ],
            ],
        ];
    }
}