<?php

namespace App\Strategy;

use App\Service\KunaClient;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Strategy
 * @package App\Strategy
 */
abstract class Strategy
{
    /**
     * @var KunaClient
     */
    protected $client;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @param KunaClient $client
     * @param OutputInterface $output
     */
    public function __construct(KunaClient $client, OutputInterface $output)
    {
        $this->client = $client;
        $this->output = $output;
    }

    /**
     * @return KunaClient
     */
    protected function getClient(): KunaClient
    {
        return $this->client;
    }

    /**
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        $this->output->writeln($message);
    }
}
