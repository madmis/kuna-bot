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

        while (true) {
            try {

                $this->output->writeln("<g>***************{$base}/{$quote}***************</g>");

                $this->processBaseFunds($strategy, $base);

                if ($strategy->isBalanceAllowTrading($quote)) {
                    $quoteConfig = $this->config->get('quote_currency');
                }

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
            } catch (\TypeError $e) {
                $this->output->writeln("<r>Type Error exception: {$e->getMessage()}</r>");
                sleep(30);
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

            sleep(30);
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
                $strategy->getCurrentPrice($this->pair),
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
     */
    protected function resolveConfiguration(string $config)
    {
        $config = Yaml::parse($config, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

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
            ->setAllowedTypes('boundary', 'float')
            ->setAllowedTypes('margin', 'array');

        $resolver->setAllowedValues('margin', function ($value) {
            return count($value) > 0;
        });
    }
}
