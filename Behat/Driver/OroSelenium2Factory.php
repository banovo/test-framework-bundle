<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Driver;

use Behat\MinkExtension\ServiceContainer\Driver\Selenium2Factory;

/**
 * Class OroSelenium2Factory
 * @package TestFrameworkBundle\Behat\Driver
 */
class OroSelenium2Factory extends Selenium2Factory
{
    /**
     * {@inheritdoc}
     */
    public function buildDriver(array $config)
    {
        $definition = parent::buildDriver($config);
        $definition->setClass('TestFrameworkBundle\Behat\Driver\OroSelenium2Driver');

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName()
    {
        return 'oroSelenium2';
    }
}
