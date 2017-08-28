<?php

namespace App\Exception;

/**
 * Interface TimeoutException
 * @package App\Exception
 */
interface TimeoutException
{
    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void;

    /**
     * @return int
     */
    public function getTimeout(): int;
}
