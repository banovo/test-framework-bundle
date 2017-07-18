<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Element;

/**
 * Interface OroPageObjectAware
 * @package TestFrameworkBundle\Behat\Element
 */
interface OroPageObjectAware
{
    /**
     * @param OroElementFactory $elementFactory
     *
     * @return void
     */
    public function setElementFactory(OroElementFactory $elementFactory);

    /**
     * @param OroPageFactory $elementFactory
     *
     * @return void
     */
    public function setPageFactory(OroPageFactory $elementFactory);
}
