<?php

namespace App\Bot;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface BotInterface
 * @package App\Bot
 */
interface BotInterface
{
    /**
     * @param string $pair
     * @param string $strategy
     */
    public function __construct(OutputInterface $output, string $pair, string $strategy);

    /**
     * @return void
     */
    public function run();
}
