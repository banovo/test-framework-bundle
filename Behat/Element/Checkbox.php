<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Element;

/**
 * Class Checkbox
 * @package TestFrameworkBundle\Behat\Element
 */
class Checkbox extends Element
{
    /**
     * @param array|bool|string $value
     */
    public function setValue($value)
    {
        if ('false' === $value) {
            $this->uncheck();
        } else {
            $this->check();
        }
    }
}
