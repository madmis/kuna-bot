<?php

namespace App\Bot;

use App\Exception\BreakIterationException;
use App\Exception\StopBotException;
use App\Service\KunaClient;
use App\Strategy\SimpleStrategy;
use madmis\ExchangeApi\Exception\ClientException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SimpleBot implements BotInterface
{
    /**
     * @var string
     */
    private $pair;

    /**
     * @var ParameterBag
     */
    private $config;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param OutputInterface $output
     * @param string $pair
     * @param string $config
     * @throws ParseException
     */
    public function __construct(OutputInterface $output, string $pair, string $config)
    {
        $this->output = $output;
        $this->pair = $pair;

        $this->resolveConfiguration($config);
    }

    /**
     * @return void
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

                $this->processBaseFunds($strategy, $base);

                $this->processQuoteFunds($strategy, $base, $quote);
            } catch (BreakIterationException $e) {
                $prev = $e->getPrevious();
                if ($prev instanceof ClientException
                    && $prev->hasResponse()
                    && (int)$prev->getResponse()->getStatusCode() === 401) {
                    $this->output->writeln("<r>Authentication error: {$e->getMessage()}</r>");
                    $this->output->writeln('<r>Please check if the api keys are correct.</r>');
                    break;
                }

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
            } catch (\TypeError $e) {
                $this->output->writeln("<r>Type Error exception: {$e->getMessage()}</r>");
                sleep($timeout);
            } catch (\Throwable $e) {
                $this->output->writeln("<r>Unhandled exception: {$e->getMessage()}</r>");
                VarDumper::dump(FlattenException::create($e)->toArray());
            } finally {
                if ($memUsage) {
                    $usage = memory_get_peak_usage(true) / 1024;
                    $this->output->writeln(sprintf(
                        '<y>Memory usage: %s Kb (%s Mb)</y>',
                        $usage,
                        $usage / 1024
                    ));
                }
            }

            sleep($timeout);
        }
    }

    /**
     * @param SimpleStrategy $strategy
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @throws BreakIterationException
     * @throws StopBotException
     * @throws ClientException
     */
    protected function processQuoteFunds(SimpleStrategy $strategy, string $baseCurrency, string $quoteCurrency)
    {
        $this->output->writeln("<w>Check quote currency ({$quoteCurrency}) balance</w>");
        if ($strategy->isBalanceAllowTrading($quoteCurrency)) {
            /** @var ParameterBag $quoteConfig */
            $quoteConfig = $this->config->get('quote_currency');

            $this->output->writeln("<g>Quote funds ({$quoteCurrency}) processing</g>");
            $this->output->writeln("\t<w>Boundary: {$quoteConfig->get('boundary')}</w>");
            $this->output->writeln(sprintf(
                "\t<w>Orders count: %s</w>",
                count($quoteConfig->get('margin'))
            ));
            $this->output->writeln(sprintf(
                "\t<w>Margin: %s</w>",
                implode(', ', $quoteConfig->get('margin'))
            ));

            $orders = $strategy->createPreconfiguredOrders(
                $quoteCurrency,
                $strategy->getCurrentBuyPrice($this->pair),
                $quoteConfig->get('boundary'),
                $quoteConfig->get('margin')
            );

            $this->output->writeln("<g>Create {$baseCurrency} BUY orders</g>");
            foreach ($orders as $key => $order) {
                $order = $strategy
                    ->getClient()
                    ->signed()
                    ->createBuyOrder($this->pair, $order['volume'], $order['price'], true);

                $key++;
                $this->output->writeln("\t<g>Oder #{$key}</g>");
                $this->output->writeln("\t\t<w>Id: {$order->getId()}</w>");
                $this->output->writeln("\t\t<w>Type: {$order->getOrdType()}</w>");
                $this->output->writeln("\t\t<w>Price: {$order->getPrice()}</w>");
                $this->output->writeln("\t\t<w>Side: {$order->getSide()}</w>");
                $this->output->writeln("\t\t<w>State: {$order->getState()}</w>");
                $this->output->writeln("\t\t<w>Volume: {$order->getVolume()}</w>");
            }
        }
    }


    /**
     * @param SimpleStrategy $strategy
     * @param string $baseCurrency
     * @throws BreakIterationException
     * @throws StopBotException
     * @throws ClientException
     */
    protected function processBaseFunds(SimpleStrategy $strategy, string $baseCurrency)
    {
        $this->output->writeln("<w>Check base currency ({$baseCurrency}) balance</w>");
        if ($strategy->isBalanceAllowTrading($baseCurrency)) {
            /** @var ParameterBag $baseConfig */
            $baseConfig = $this->config->get('base_currency');

            $this->output->writeln("<g>Base funds ({$baseCurrency}) processing</g>");
            $this->output->writeln("\t<w>Boundary: {$baseConfig->get('boundary')}</w>");
            $this->output->writeln(sprintf(
                "\t<w>Orders count: %s</w>",
                count($baseConfig->get('margin'))
            ));
            $this->output->writeln(sprintf(
                "\t<w>Margin: %s</w>",
                implode(', ', $baseConfig->get('margin'))
            ));

            $orders = $strategy->createPreconfiguredOrders(
                $baseCurrency,
                $strategy->getCurrentSellPrice($this->pair),
                $baseConfig->get('boundary'),
                $baseConfig->get('margin')
            );

            $this->output->writeln("<g>Create {$baseCurrency} SELL orders</g>");
            foreach ($orders as $key => $order) {
                $order = $strategy
                    ->getClient()
                    ->signed()
                    ->createSellOrder($this->pair, $order['volume'], $order['price'], true);

                $key++;
                $this->output->writeln("\t<g>Oder #{$key}</g>");
                $this->output->writeln("\t\t<w>Id: {$order->getId()}</w>");
                $this->output->writeln("\t\t<w>Type: {$order->getOrdType()}</w>");
                $this->output->writeln("\t\t<w>Price: {$order->getPrice()}</w>");
                $this->output->writeln("\t\t<w>Side: {$order->getSide()}</w>");
                $this->output->writeln("\t\t<w>State: {$order->getState()}</w>");
                $this->output->writeln("\t\t<w>Volume: {$order->getVolume()}</w>");
            }
        }
    }

    /**
     * @return SimpleStrategy
     */
    protected function createStrategy(): SimpleStrategy
    {
        $client = new KunaClient(
            $this->config->get('public_key'),
            $this->config->get('secret_key')
        );
        $client->setMinTradeAmounts(
            $this->config->get('min_amounts')
        );

        return new SimpleStrategy($client, $this->output);
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

        $baseResolver = new OptionsResolver();
        $this->configurePairOptions($baseResolver);
        $baseConfig = $baseResolver->resolve(
            $this->config->get('base_currency')
        );
        $this->config->set('base_currency', new ParameterBag($baseConfig));

        $quoteResolver = new OptionsResolver();
        $this->configurePairOptions($quoteResolver);
        $quoteConfig = $quoteResolver->resolve(
            $this->config->get('quote_currency')
        );
        $this->config->set('quote_currency', new ParameterBag($quoteConfig));
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
                'base_currency',
                'quote_currency',
            ])
            ->setAllowedTypes('public_key', 'string')
            ->setAllowedTypes('secret_key', 'string')
            ->setAllowedTypes('base_currency', 'array')
            ->setAllowedTypes('quote_currency', 'array')
            ->setAllowedTypes('min_amounts', 'array')
            ->setAllowedTypes('show_memory_usage', 'bool');
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function configurePairOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired([
                'boundary',
                'margin',
            ])
            ->setAllowedTypes('boundary', ['float', 'int'])
            ->setAllowedTypes('margin', 'array');

        $resolver->setAllowedValues('margin', function ($value) {
            return count($value) > 0;
        });
    }
}
