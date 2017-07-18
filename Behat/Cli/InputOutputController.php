<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Cli;

use Behat\Testwork\Cli\Controller;
use TestFrameworkBundle\Behat\Isolation\TestIsolationSubscriber;
use TestFrameworkBundle\Behat\Listener\FixturesSubscriber;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class InputOutputController
 * @package TestFrameworkBundle\Behat\Cli
 */
class InputOutputController implements Controller
{
    /**
     * @var TestIsolationSubscriber $testIsolationSubscriber
     */
    protected $testIsolationSubscriber;

    /**
     * InputOutputController constructor
     *
     * @param TestIsolationSubscriber $testIsolationSubscriber
     */
    public function __construct(TestIsolationSubscriber $testIsolationSubscriber)
    {
        $this->testIsolationSubscriber = $testIsolationSubscriber;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(SymfonyCommand $command)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var bool $skip */
        $skip = $input->getOption('dry-run');
        $this->testIsolationSubscriber->setInput($input);
        $this->testIsolationSubscriber->setOutput($output);
        $this->testIsolationSubscriber->setSkip($skip);
    }
}
