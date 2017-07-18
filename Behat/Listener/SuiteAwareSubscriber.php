<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Listener;

use Behat\Testwork\EventDispatcher\Event\BeforeSuiteTested;
use TestFrameworkBundle\Behat\Element\SuiteAwareInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SuiteAwareSubscriber implements EventSubscriberInterface
{
    /** @var  SuiteAwareInterface[] */
    protected $services;

    /**
     * @param SuiteAwareInterface[] $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    /** {@inheritdoc} */
    public static function getSubscribedEvents()
    {
        return [
            BeforeSuiteTested::BEFORE => ['injectSuite', 5],
        ];
    }

    /**
     * @param BeforeSuiteTested $event
     */
    public function injectSuite(BeforeSuiteTested $event)
    {
        foreach ($this->services as $service) {
            $service->setSuite($event->getSuite());
        }
    }
}
