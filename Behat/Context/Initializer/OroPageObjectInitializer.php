<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use TestFrameworkBundle\Behat\Element\OroElementFactory;
use TestFrameworkBundle\Behat\Element\OroPageFactory;
use TestFrameworkBundle\Behat\Element\OroPageObjectAware;

/**
 * Class OroPageObjectInitializer
 * @package TestFrameworkBundle\Behat\Context\Initializer
 */
class OroPageObjectInitializer implements ContextInitializer
{
    /**
     * @var OroElementFactory
     */
    protected $elementFactory;

    /**
     * @var OroPageFactory
     */
    protected $pageFactory;

    /**
     * @param OroElementFactory $elementFactory
     */
    public function __construct(OroElementFactory $elementFactory, OroPageFactory $pageFactory)
    {
        $this->elementFactory = $elementFactory;
        $this->pageFactory = $pageFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof OroPageObjectAware) {
            $context->setElementFactory($this->elementFactory);
            $context->setPageFactory($this->pageFactory);
        }
    }
}
