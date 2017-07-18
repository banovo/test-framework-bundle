<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Cli;

use Behat\Testwork\Cli\Controller;
use TestFrameworkBundle\Behat\Listener\InteractiveExecutionSubscriber;
use TestFrameworkBundle\Behat\Listener\StepDurationMeasureSubscriber;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class InteractiveExecutionController
 * @package TestFrameworkBundle\Behat\Cli
 */
class InteractiveExecutionController implements Controller
{
    /**
     * @var StepDurationMeasureSubscriber
     */
    protected $subscriber;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param InteractiveExecutionSubscriber $subscriber
     * @param EventDispatcherInterface       $eventDispatcher
     */
    public function __construct(InteractiveExecutionSubscriber $subscriber, EventDispatcherInterface $eventDispatcher)
    {
        $this->subscriber = $subscriber;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(SymfonyCommand $command)
    {
        $command->addOption(
            '--interactive',
            null,
            InputOption::VALUE_NONE,
            'Interactive execution. Wait after every step'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('interactive')) {
            return;
        }

        $this->subscriber->setOutput($output);
        $this->eventDispatcher->addSubscriber($this->subscriber);
    }
}
