<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Element;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\ElementInterface;

/**
 * Interface ElementValueInterface
 * @package TestFrameworkBundle\Behat\Element
 */
interface ElementValueInterface
{
    /**
     * Set current ElementValueInterface state to element value
     *
     * @param string $xpath
     * @param DriverInterface $driver
     * @return void
     */
    public function set($xpath, DriverInterface $driver);

    /**
     * @return string
     */
    public function __toString();
}
