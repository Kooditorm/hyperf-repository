<?php
declare(strict_types=1);

namespace Kooditorm\Repository\Contracts;


/**
 * Interface Presentable
 * @package Kooditorm\Repository\Contracts
 */
interface Presentable
{
    /**
     * @param PresenterInterface $presenter
     *
     * @return mixed
     */
    public function setPresenter(PresenterInterface $presenter): mixed;

    /**
     * @return mixed
     */
    public function presenter(): mixed;
}
