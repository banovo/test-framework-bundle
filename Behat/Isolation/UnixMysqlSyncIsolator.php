<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Isolation;

use TestFrameworkBundle\Behat\Isolation\Event\AfterFinishTestsEvent;
use TestFrameworkBundle\Behat\Isolation\Event\AfterIsolatedTestEvent;
use TestFrameworkBundle\Behat\Isolation\Event\BeforeIsolatedTestEvent;
use TestFrameworkBundle\Behat\Isolation\Event\BeforeStartTestsEvent;
use TestFrameworkBundle\Behat\Isolation\Event\RestoreStateEvent;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

final class UnixMysqlSyncIsolator extends AbstractOsRelatedIsolator implements IsolatorInterface
{
    const TIMEOUT = '240';

    /** @var string */
    protected $dbHost;

    /** @var  string */
    protected $dbPort;

    /** @var string */
    protected $dbName;

    /** @var string */
    protected $dbTempName;

    /** @var string */
    protected $dbPass;

    /** @var string */
    protected $dbUser;

    /**
     * @var string full path to DB dump file
     */
    protected $dbDump;

    /** @var  Process */
    protected $restoreDbFromDumpProcess;

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->dbHost = $container->getParameter('database_host');
        $this->dbPort = $container->getParameter('database_port');
        $this->dbName = $container->getParameter('database_name');
        $this->dbUser = $container->getParameter('database_user');
        $this->dbPass = $container->getParameter('database_password');
        $this->dbDump = sys_get_temp_dir().DIRECTORY_SEPARATOR.$this->dbName;
        $this->dbTempName = $this->dbName.'_temp';
    }

    /** {@inheritdoc} */
    public function start(BeforeStartTestsEvent $event)
    {
        $event->writeln('<info>Dumping current application database</info>');
        $this->makeDump();
    }

    /** {@inheritdoc} */
    public function beforeTest(BeforeIsolatedTestEvent $event)
    {
    }

    /** {@inheritdoc} */
    public function afterTest(AfterIsolatedTestEvent $event)
    {
        $this->dropDb();
        $this->createDb();
        $this->restoreDbFromDump();
    }

    /** {@inheritdoc} */
    public function terminate(AfterFinishTestsEvent $event)
    {
        $event->writeln('<info>Remove Db dump</info>');
        unlink($this->dbDump);
    }

    /** {@inheritdoc} */
    public function restoreState(RestoreStateEvent $event)
    {
        if (false === is_file($this->dbDump)) {
            throw new RuntimeException('Can\'t restore Db without sql dump');
        }

        $event->writeln('<info>Begin to restore the state of Db...</info>');

        $event->writeln('<info>Drop/Create Db</info>');
        $this->dropDb();
        $this->createDb();

        $event->writeln('<info>Restore Db from dump</info>');
        $this->restoreDbFromDump();

        $event->writeln('<info>Remove Db dump</info>');
        unlink($this->dbDump);

        $event->writeln('<info>Db was restored from dump</info>');
    }

    /** {@inheritdoc} */
    public function isOutdatedState()
    {
        return is_file($this->dbDump);
    }

    /** {@inheritdoc} */
    public function isApplicable(ContainerInterface $container)
    {
        return
            $this->isApplicableOS()
            && 'pdo_mysql' === $container->getParameter('database_driver');
    }

    /** {@inheritdoc} */
    public function getName()
    {
        return "MySql DB";
    }

    /** {@inheritdoc} */
    protected function makeDump()
    {
        $this->runProcess(sprintf(
            'mysqldump -p%s -h%s -u%s %s > %s',
            $this->dbPass,
            $this->dbHost,
            $this->dbUser,
            $this->dbName,
            $this->dbDump
        ));
    }

    /** {@inheritdoc} */
    protected function restoreDbFromDump()
    {
        $this->runProcess(sprintf(
            'mysql -p%s -h %s -u %s %s < %s',
            $this->dbPass,
            $this->dbHost,
            $this->dbUser,
            $this->dbName,
            $this->dbDump
        ));
    }

    /** {@inheritdoc} */
    protected function getApplicableOs()
    {
        return [
            AbstractOsRelatedIsolator::LINUX_OS,
            AbstractOsRelatedIsolator::MAC_OS,
        ];
    }

    protected function createTempDb()
    {
        if ($this->isDbExists($this->dbTempName)) {
            $this->dropTempDb();
        }

        $this->runProcess(sprintf(
            'mysql -p%s -e "create database %s;" -h %s -u %s',
            $this->dbPass,
            $this->dbTempName,
            $this->dbHost,
            $this->dbUser
        ));
    }

    protected function createDb()
    {
        if ($this->isDbExists($this->dbName)) {
            $this->dropDb();
        }

        $this->runProcess(sprintf(
            'mysql -p%s -e "create database %s;" -h %s -u %s',
            $this->dbPass,
            $this->dbName,
            $this->dbHost,
            $this->dbUser
        ));
    }

    /**
     * Rename temp database to current application database
     */
    protected function renameTempDb()
    {
        $this->restoreDbFromDumpProcess = $this->runProcess(sprintf(
            'for table in `mysql -p%s -h %s -u %s -s -N -e "use %s;show tables from %4$s;"`; '.
            'do mysql -p%1$s -h %2$s -u %3$s -s -N -e "use %4$s;rename table %4$s.$table to %5$s.$table;"; done;',
            $this->dbPass,
            $this->dbHost,
            $this->dbUser,
            $this->dbTempName,
            $this->dbName
        ));
    }

    protected function dropDb()
    {
        if (!$this->isDbExists($this->dbName)) {
            return;
        }

        $this->runProcess(sprintf(
            'mysql -p%s -e "drop database %s;" -h %s -u %s',
            $this->dbPass,
            $this->dbName,
            $this->dbHost,
            $this->dbUser
        ));
    }

    protected function dropTempDb()
    {
        $this->runProcess(sprintf(
            'mysql -p%s -e "drop database %s;" -h %s -u %s',
            $this->dbPass,
            $this->dbTempName,
            $this->dbHost,
            $this->dbUser
        ));
    }

    /**
     * @param string $commandline The command line to run
     * @param int $timeout The timeout in seconds
     * @return Process
     */
    protected function runProcess($commandline, $timeout = 120)
    {
        $process = new Process($commandline);

        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    /**
     * @param string $dbName
     * @return bool
     */
    private function isDbExists($dbName)
    {
        $process = $this->runProcess(sprintf(
            'mysql -p%s -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA '.
            'WHERE SCHEMA_NAME = \'%s\'" -h %s -u %s',
            $this->dbPass,
            $dbName,
            $this->dbHost,
            $this->dbUser
        ));

        return false !== strpos($process->getOutput(), $dbName);
    }
}
