<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Element;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\ElementInterface;
use TestFrameworkBundle\Behat\Driver\OroSelenium2Driver;

/**
 * Class InputValue
 * @package TestFrameworkBundle\Behat\Element
 */
class InputValue implements ElementValueInterface
{
    /**
     * @var string
     */
    private $value;

    /**
     * @var string TYPE|SET
     */
    private $inputMethod;

    /**
     * @param string $inputMethod
     * @param string $value
     */
    public function __construct($inputMethod, $value)
    {
        $this->value = $value;
        $this->inputMethod = $inputMethod;
    }

    /**
     * @param string $xpath
     * @param DriverInterface $driver
     */
    public function set($xpath, DriverInterface $driver)
    {
        if (InputMethod::SET === $this->inputMethod) {
            $script = <<<JS
            var node = {{ELEMENT}};
            node.value = '$this->value';
JS;
            $driver->executeJsOnXpath($xpath, $script);
        } elseif (InputMethod::TYPE === $this->inputMethod) {
            $driver->typeIntoInput($xpath, $this->value);
        } else {
            throw new \RuntimeException('Unsupported input method');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->value;
    }
}