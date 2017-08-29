<?php

namespace App\Command;

use App\Bot\SimpleBot;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class BotCommand
 * @package App\Command
 */
class BotCommand extends ContainerAwareCommand
{
    /**
     * @var InputInterface
     */
    private $input;

    protected function configure()
    {
        $this->setName('simple-bot:run')
            ->addArgument('pair', InputArgument::REQUIRED, 'Pair for trades')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Strategy configuration file')
            ->setDescription('Simple Kuna.io Bot')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> run bot:

<comment>Run</comment>
    <info>php %command.full_name% ethuah --margin=200 --buy-price-increase-unit=0.1 --show-memory-usage</info>
EOF
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setColors($output);
        $this->input = $input;

        $pair = $input->getArgument('pair');

        $configFile = $input->getOption('config');
        if (!is_file($configFile) && !file_exists($configFile)) {
            throw new \RuntimeException("Can't find configuration file");
        }
        $config = file_get_contents($configFile);

        $logDir = $this->getContainer()->getParameter('kernel.logs_dir');
        $loggerName = "simple-bot.{$pair}";
        $logger = new Logger($loggerName);
        $logger->pushHandler(new StreamHandler("$logDir/{$loggerName}.log", Logger::DEBUG));

        (new SimpleBot($output, $pair, $config))
            ->setLogger($logger)
            ->run();
    }

    /**
     * @param OutputInterface $output
     */
    protected function setColors(OutputInterface $output)
    {
        $output->getFormatter()->setStyle('r', new OutputFormatterStyle('red', null));
        $output->getFormatter()->setStyle('g', new OutputFormatterStyle('green', null));
        $output->getFormatter()->setStyle('y', new OutputFormatterStyle('yellow', null));
        $output->getFormatter()->setStyle('b', new OutputFormatterStyle('blue', null));
        $output->getFormatter()->setStyle('m', new OutputFormatterStyle('magenta', null));
        $output->getFormatter()->setStyle('c', new OutputFormatterStyle('cyan', null));
        $output->getFormatter()->setStyle('w', new OutputFormatterStyle('white', null));
    }
}
