<?php

declare(strict_types=1);

/**
 * This file is part of Kooditorm/hyperf-repository.
 *
 * @link     https://github.com/Kooditorm/hyperf-repository
 * @contact  oswin.hu@gmail.com
 * @license  https://github.com/Kooditorm/hyperf-repositoryt/blob/master/LICENSE
 */

use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    function value(mixed $value, ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('event')) {
    /**
     * 触发一个事件，支持两种入参方式：
     *  - event(new SomeEvent($payload))              // 直接传入事件对象
     *  - event(SomeEvent::class)                     // 仅传类名，自动从容器解析
     *  - event(SomeEvent::class, [$arg1, $arg2])     // 传类名 + 构造参数
     *
     * 通过容器获取当前协程的 EventDispatcher，符合 Hyperf Swoole 协程规范。
     *
     * @param object|string $event 事件对象或事件类名
     * @param array $payload 事件类的构造参数（仅当 $event 为字符串时生效）
     * @return object 派发的事件对象（PSR-14 规范返回原事件）
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function event(object|string $event, array $payload = []): object
    {
        $container = ApplicationContext::getContainer();

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get(EventDispatcherInterface::class);

        if (is_string($event)) {
            $event = $container->make($event, $payload);
        }

        return $dispatcher->dispatch($event);
    }
}