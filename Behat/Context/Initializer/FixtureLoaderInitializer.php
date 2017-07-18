<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use TestFrameworkBundle\Behat\Fixtures\FixtureLoader;
use TestFrameworkBundle\Behat\Fixtures\FixtureLoaderAwareInterface;

/**
 * Class FixtureLoaderInitializer
 * @package TestFrameworkBundle\Behat\Context\Initializer
 */
class FixtureLoaderInitializer implements ContextInitializer
{
    /**
     * @var FixtureLoader
     */
    protected $fixtureLoader;

    /**
     * @param FixtureLoader $fixtureLoader
     */
    public function __construct(FixtureLoader $fixtureLoader)
    {
        $this->fixtureLoader = $fixtureLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof FixtureLoaderAwareInterface) {
            $context->setFixtureLoader($this->fixtureLoader);
        }
    }
}
