<?php

namespace App\Command;

use App\Bot\ShortingBot;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ShortingBotCommand
 * @package App\Command
 */
class ShortingBotCommand extends ContainerAwareCommand
{
    use ColorizedTrait;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    protected function configure()
    {
        $this->setName('shorting-bot:run')
            ->addArgument('config', InputArgument::REQUIRED, 'Strategy configuration file')
            ->setDescription('Kuna.io Bot - trade on the short distances')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> run bot:

<comment>Run</comment>
    <info>php %command.full_name% /var/www/shorting.btcuah.yaml</info>
EOF
            );
    }


    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
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
        $loggerName = "shorting-bot.{$pair}";
        $logger = new Logger($loggerName);
        $logger->pushHandler(new StreamHandler("$logDir/{$loggerName}.log", Logger::DEBUG));

        (new ShortingBot($output, $pair, $configSrc))
            ->setLogger($logger)
            ->run();
    }
}