<?php
declare(strict_types=1);

namespace Kooditorm\Repository\Contracts;

/**
 * Interface PresenterInterface
 * @package Kooditorm\Repository\Contracts
 */
interface PresenterInterface
{
    /**
     * Prepare data to present
     *
     * @param $data
     *
     * @return mixed
     */
    public function present($data): mixed;
}
