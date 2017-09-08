<?php

namespace App\Bot;

use App\Exception\BreakIterationException;
use App\Exception\StopBotException;
use App\Service\KunaClient;
use App\Strategy\ShortingStrategy;
use madmis\ExchangeApi\Exception\ClientException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ShortingBot
 * @package App\Bot
 */
class ShortingBot implements BotInterface
{
    const SCALE = 6;

    /**
     * @var string
     */
    private $pair;

    /**
     * @var ParameterBag
     */
    private $config;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $pair
     * @param string $config
     * @throws \Symfony\Component\Yaml\Exception\ParseException
     * @throws \RuntimeException
     */
    public function __construct(OutputInterface $output, string $pair, string $config)
    {
        $this->output = $output;
        $this->pair = $pair;

        $this->resolveConfiguration($config);
        $this->logger = new NullLogger();
    }


    /**
     * @return void
     * @throws \Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException
     */
    public function run(): void
    {
        $strategy = $this->createStrategy();
        [$base, $quote] = KunaClient::splitPair($this->pair);
        $memUsage = $this->config->get('show_memory_usage');
        $timeout = (int)$this->config->get('iteration_timeout');

        while (true) {
            try {
                $this->output->writeln("<g>***************{$base}/{$quote}***************</g>");

                $strategy->closeNotTopBuyOrders($this->pair);

                // check, if exists active buy order, do nothing
                // waiting for it execution or until it will be canceled
                $activeOrders = $strategy->getClient()->getActiveBuyOrders($this->pair);
                if ($activeOrders) {
                    $this->output->writeln('<w>Waiting until active BUY order will be executed or canceled ...</w>');
                    sleep($timeout);
                    continue;
                }

                // no active orders. Check if we can sell something
                $this->processBaseFunds($strategy, $base, $quote);

                // check if we can buy something
                $this->processQuoteFunds($strategy, $base, $quote);

            } catch (BreakIterationException $e) {
                if ($e->getMessage()) {
                    $this->output->writeln("<r>{$e->getMessage()}</r>");
                }
                sleep($e->getTimeout());
                continue;
            } catch (StopBotException $e) {
                if ($e->getMessage()) {
                    $this->output->writeln("<r>{$e->getMessage()}</r>");
                }
                break;
            } catch (ClientException $e) {
                $this->output->writeln("<r>{$e->getMessage()}</r>");
                $context = ['exception' => $e->getTrace()];
                if ($e->hasResponse()) {
                    $context['response'] = (string)$e->getResponse()->getBody();
                    $this->output->writeln("<r>{$context['response']}</r>");
                }
                $this->logger->log(Logger::ERROR, $e->getMessage(), $context);
            } catch (\TypeError $e) {
                $this->output->writeln("<r>Type Error exception: {$e->getMessage()}</r>");
                sleep($timeout);
            } catch (\Throwable $e) {
                $this->output->writeln("<r>Unhandled exception: {$e->getMessage()}</r>");
                $this->logger->log(Logger::ERROR, $e->getMessage(), [
                    'exception' => $e->getTrace(),
                ]);
            } finally {
                if ($memUsage) {
                    $usage = memory_get_peak_usage(true) / 1024;
                    $this->output->writeln(sprintf(
                        '<y>Memory usage: %s Kb (%s Mb)</y>',
                        $usage,
                        $usage / 1024
                    ));
                }
                sleep($timeout);
            }

            sleep($timeout);
        }
    }


    /**
     * @param ShortingStrategy $strategy
     * @param string $base
     * @param string $quote
     * @throws BreakIterationException
     * @throws StopBotException
     * @throws ClientException
     */
    protected function processQuoteFunds(ShortingStrategy $strategy, string $base, string $quote)
    {
        $this->output->writeln("<w>Check quote currency ({$quote}) balance</w>");
        if ($strategy->isBalanceAllowTrading($quote)) {
            // don't buy close to highest price
            $ticker = $strategy->getClient()->shared()->tickers($this->pair, true);
            $priceDiff = bcsub($ticker->getHigh(), $ticker->getLow(), self::SCALE);
            $allowedPrice = bcadd(
                bcmul($priceDiff, 0.50, self::SCALE),
                $ticker->getLow(),
                self::SCALE
            );
            $this->output->writeln("\t<w>Maximum allowed buy price: {$allowedPrice}</w>");
            $this->output->writeln("\t<w>Latest buy price (from ticker): {$ticker->getBuy()}</w>");
            $this->output->writeln("\t<w>Highest price (from ticker): {$ticker->getHigh()}</w>");
            $this->output->writeln("\t<w>Lowest price (from ticker): {$ticker->getLow()}</w>");
            $currentPrice = $strategy->getCurrentBuyPrice($this->pair);
            $this->output->writeln("\t<w>Current TOP buy price: {$currentPrice}</w>");
            $increaseUnit = (float)$this->config->get('increase_unit');
            $price = bcadd($currentPrice, $increaseUnit, self::SCALE);
            $this->output->writeln("\t<w>My price: {$price}</w>");

            if ($price <= $allowedPrice) {
                $account = $strategy->getClient()->getCurrencyAccount($quote);
                $volume = bcdiv($account->getBalance(), $price, self::SCALE);

                $this->output->writeln("\t<g>Create BUY order.</g>");
                $this->output->writeln("\t\t<w>Buy price: {$price}</w>");
                $this->output->writeln("\t\t<w>Buy volume: {$volume}</w>");
                $order = $strategy
                    ->getClient()
                    ->signed()
                    ->createBuyOrder($this->pair, $volume, $price, true);
                $this->output->writeln("\t<g>BUY order created.</g>");
                $this->output->writeln("\t\t<w>Id: {$order->getId()}</w>");
                $this->output->writeln("\t\t<w>Type: {$order->getOrdType()}</w>");
                $this->output->writeln("\t\t<w>Price: {$order->getPrice()}</w>");
                $this->output->writeln("\t\t<w>Side: {$order->getSide()}</w>");
                $this->output->writeln("\t\t<w>State: {$order->getState()}</w>");
                $this->output->writeln("\t\t<w>Volume: {$order->getVolume()}</w>");
            } else {
                $this->output->writeln("\t<y>*!!! Buy is not allowed: to prevent HIGH prices trading !!!*</y>");
            }
        }
    }

    /**
     * @param ShortingStrategy $strategy
     * @param string $base
     * @param string $quote
     * @throws \App\Exception\BreakIterationException
     * @throws \App\Exception\StopBotException
     * @throws \madmis\ExchangeApi\Exception\ClientException
     */
    protected function processBaseFunds(ShortingStrategy $strategy, string $base, string $quote)
    {
        $this->output->writeln("<w>Check base currency ({$base}) balance</w>");
        if ($strategy->isBalanceAllowTrading($base)) {
            $latestBuy = $strategy->getClient()->getLatestBuyOrder($this->pair);
            if (!$latestBuy) {
                $this->output->writeln('<r>Can not define sell price (no closed buy orders available for this pair).</r>');
                $this->output->writeln('<y>Try to place sell order with properly sell price manually.</y>');

                throw new StopBotException('Stop bot');
            }

            $margin = (float)$this->config->get('margin');
            // we have base currency funds and last buy order.
            // So we can calculate sell price.
            $price = bcadd($latestBuy->getPrice(), $margin, self::SCALE);
            $this->output->writeln("\t<w>Sell price: {$price} {$quote}</w>");
            try {
                $account = $strategy->getClient()->getCurrencyAccount($base);
            } catch (ClientException $e) {
                $ex = new BreakIterationException($e->getMessage(), $e->getCode(), $e);
                $ex->setTimeout(10);

                throw $ex;
            }
            $receive = bcmul($account->getBalance(), $price, self::SCALE);
            $volume = (float)number_format($account->getBalance(), self::SCALE);
            $this->output->writeln("\t<w>Sell volume: {$volume} {$base}</w>");
            $this->output->writeln("\t<w>Will receive: {$receive} {$quote}</w>");

            $this->output->writeln("\t<g>Create {$base} SELL order</g>");
            $order = $strategy
                ->getClient()
                ->signed()
                ->createSellOrder($this->pair, $volume, $price, true);

            $this->output->writeln("\t\t<w>Id: {$order->getId()}</w>");
            $this->output->writeln("\t\t<w>Type: {$order->getOrdType()}</w>");
            $this->output->writeln("\t\t<w>Price: {$order->getPrice()}</w>");
            $this->output->writeln("\t\t<w>Side: {$order->getSide()}</w>");
            $this->output->writeln("\t\t<w>State: {$order->getState()}</w>");
            $this->output->writeln("\t\t<w>Volume: {$order->getVolume()}</w>");
        }
    }

    /**
     * @return \App\Strategy\ShortingStrategy
     */
    protected function createStrategy(): ShortingStrategy
    {
        $client = new KunaClient(
            $this->config->get('public_key'),
            $this->config->get('secret_key')
        );
        $client->setMinTradeAmounts(
            $this->config->get('min_amounts')
        );

        return new ShortingStrategy($client, $this->output);
    }

    /**
     * @param string $config
     * @throws \RuntimeException
     */
    protected function resolveConfiguration(string $config)
    {
        $config = Yaml::parse($config, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if ($config === null) {
            throw new \RuntimeException('Invalid bot configuration');
        }

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->config = new ParameterBag($resolver->resolve($config));
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'min_amounts' => [],
            'show_memory_usage' => false,
            'iteration_timeout' => 30,
        ]);
        $resolver
            ->setRequired([
                'public_key',
                'secret_key',
                'pair',
                'margin',
                'increase_unit',
            ])
            ->setAllowedTypes('public_key', 'string')
            ->setAllowedTypes('secret_key', 'string')
            ->setAllowedTypes('pair', 'string')
            ->setAllowedTypes('margin', ['float', 'int'])
            ->setAllowedTypes('increase_unit', ['float', 'int'])
            ->setAllowedTypes('min_amounts', 'array')
            ->setAllowedTypes('show_memory_usage', 'bool');
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return \App\Bot\ShortingBot
     */
    public function setLogger(LoggerInterface $logger): ShortingBot
    {
        $this->logger = $logger;

        return $this;
    }
}
