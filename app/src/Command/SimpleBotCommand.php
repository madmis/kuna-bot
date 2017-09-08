<?php

namespace App\Command;

use App\Bot\SimpleBot;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;


/**
 * Class BotCommand
 * @package App\Command
 */
class SimpleBotCommand extends ContainerAwareCommand
{
    /**
     * @var InputInterface
     */
    private $input;

    protected function configure()
    {
        $this->setName('simple-bot:run')
            ->addArgument('config', InputArgument::REQUIRED, 'Strategy configuration file')
            ->setDescription('Simple Kuna.io Bot')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> run bot:

<comment>Run</comment>
    <info>php %command.full_name% /var/www/conf.btcuah.yaml</info>
EOF
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Yaml\Exception\ParseException
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @throws \Exception
     * @throws \RuntimeException
     * @throws \LogicException
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setColors($output);
        $this->input = $input;

        $configFile = $input->getArgument('config');
        if (!is_file($configFile) && !file_exists($configFile)) {
            throw new \RuntimeException("Can't find configuration file");
        }
        $configSrc = file_get_contents($configFile);

        $config = Yaml::parse($configSrc, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if ($config === null) {
            throw new \RuntimeException('Invalid bot configuration');
        }
        if (empty($config['pair'])) {
            throw new \RuntimeException('Pair is not configured. Try to add pair to config file.');
        }
        $pair = $config['pair'];

        $logDir = $this->getContainer()->getParameter('kernel.logs_dir');
        $loggerName = "simple-bot.{$pair}";
        $logger = new Logger($loggerName);
        $logger->pushHandler(new StreamHandler("$logDir/{$loggerName}.log", Logger::DEBUG));

        (new SimpleBot($output, $pair, $configSrc))
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
