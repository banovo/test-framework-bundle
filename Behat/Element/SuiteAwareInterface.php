<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Element;

use Behat\Testwork\Suite\Suite;

/**
 * Interface SuiteAwareInterface
 * @package TestFrameworkBundle\Behat\Element
 */
interface SuiteAwareInterface
{
    /**
     * @param Suite $suite
     */
    public function setSuite(Suite $suite);
}
