<?php

namespace App\Exception;

/**
 * Class BreakIterationException
 * @package App\Exception
 */
class BreakIterationException extends \RuntimeException implements TimeoutException
{
    private $timeout = 0;

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
