<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Cli;

use Behat\Testwork\Cli\Controller;
use Behat\Testwork\Suite\SuiteRepository;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AvailableSuitesController
 * @package TestFrameworkBundle\Behat\Cli
 */
class AvailableSuitesController implements Controller
{
    /**
     * @var SuiteRepository $suiteRepository
     */
    private $suiteRepository;

    /**
     * @param SuiteRepository $suiteRepository
     */
    public function __construct(SuiteRepository $suiteRepository)
    {
        $this->suiteRepository = $suiteRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(SymfonyCommand $command)
    {
        $command
            ->addOption(
                '--available-suites',
                null,
                InputOption::VALUE_NONE,
                'Show all available test suites.'.PHP_EOL.'Suites can be configured automatically by extensions, and manually by configuration'
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (false === $input->getOption('available-suites')) {
            return null;
        }

        foreach ($this->suiteRepository->getSuites() as $suite) {
            $output->writeln($suite->getName());
        }

        return 0;
    }
}
