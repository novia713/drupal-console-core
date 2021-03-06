<?php

namespace Drupal\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Drupal\Console\EventSubscriber\CallCommandListener;

/**
 * Class Application
 * @package Drupal\Console
 */
class ConsoleApplication extends Application
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $commandName;

    /**
     * ConsoleApplication constructor.
     * @param ContainerInterface $container
     * @param string             $name
     * @param string             $version
     */
    public function __construct(
        ContainerInterface$container,
        $name,
        $version
    ) {
        $this->container = $container;
        parent::__construct($name, $version);
        $this->addOptions();
    }

    public function getTranslator()
    {
        if ($this->container) {
            return $this->container->get('console.translator_manager');
        }

        return null;
    }

    /**
     * @param $key string
     *
     * @return string
     */
    public function trans($key)
    {
        if ($this->getTranslator()) {
            return $this->getTranslator()->trans($key);
        }

        return null;
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        if ($commandName = $this->getCommandName($input)) {
            $this->commandName = $commandName;
        }
        $this->registerEvents();
        $this->registerCommandsFromAutoWireConfiguration();
        return parent::doRun(
            $input,
            $output
        );
    }

    private function registerEvents()
    {
        $dispatcher = new EventDispatcher();
        /* @todo Register listeners as services */
        $dispatcher->addSubscriber(
            new CallCommandListener(
                $this->container->get('console.chain_queue')
            )
        );
        $this->setDispatcher($dispatcher);
    }

    private function addOptions()
    {
        $this->getDefinition()->addOption(
            new InputOption(
                '--env',
                '-e',
                InputOption::VALUE_OPTIONAL,
                $this->trans('application.options.env'), 'prod'
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--root',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('application.options.root')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--no-debug',
                null,
                InputOption::VALUE_NONE,
                $this->trans('application.options.no-debug')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--learning',
                null,
                InputOption::VALUE_NONE,
                $this->trans('application.options.learning')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--generate-chain',
                '-c',
                InputOption::VALUE_NONE,
                $this->trans('application.options.generate-chain')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--generate-inline',
                '-i',
                InputOption::VALUE_NONE,
                $this->trans('application.options.generate-inline')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--generate-doc',
                '-d',
                InputOption::VALUE_NONE,
                $this->trans('application.options.generate-doc')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--target',
                '-t',
                InputOption::VALUE_OPTIONAL,
                $this->trans('application.options.target')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--uri',
                '-l',
                InputOption::VALUE_REQUIRED,
                $this->trans('application.options.uri')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--yes',
                '-y',
                InputOption::VALUE_NONE,
                $this->trans('application.options.yes')
            )
        );
    }

    private function registerCommandsFromAutoWireConfiguration()
    {
        $configuration = $this->container->get('console.configuration_manager')
            ->getConfiguration();

        $autoWireForcedCommands = $configuration->get(
            sprintf(
                'application.autowire.commands.forced'
            )
        );

        foreach ($autoWireForcedCommands as $autoWireForcedCommand) {
            try {
                $reflectionClass = new \ReflectionClass(
                    $autoWireForcedCommand['class']
                );

                $command = $reflectionClass->newInstance();

                if (method_exists($command, 'setTranslator')) {
                    $command->setTranslator(
                        $this->container->get('console.translator_manager')
                    );
                }
                if (method_exists($command, 'setContainer')) {
                    $command->setContainer(
                        $this->container->get('service_container')
                    );
                }

                $this->add($command);
            } catch (\Exception $e) {
                continue;
            }
        }

        $autoWireNameCommand = $configuration->get(
            sprintf(
                'application.autowire.commands.name.%s',
                $this->commandName
            )
        );

        if ($autoWireNameCommand) {
            try {
                $arguments = [];
                if (array_key_exists('arguments', $autoWireNameCommand)) {
                    foreach ($autoWireNameCommand['arguments'] as $argument) {
                        $argument = substr($argument, 1);
                        $arguments[] = $this->container->get($argument);
                    }
                }

                $reflectionClass = new \ReflectionClass(
                    $autoWireNameCommand['class']
                );
                $command = $reflectionClass->newInstanceArgs($arguments);

                if (method_exists($command, 'setTranslator')) {
                    $command->setTranslator(
                        $this->container->get('console.translator_manager')
                    );
                }
                if (method_exists($command, 'setContainer')) {
                    $command->setContainer(
                        $this->container->get('service_container')
                    );
                }

                $this->add($command);
            } catch (\Exception $e) {
            }
        }
    }
}
