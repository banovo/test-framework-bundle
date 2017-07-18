<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Fixtures;

use Nelmio\Alice\Fixtures\Loader;
use Nelmio\Alice\Instances\Collection as AliceCollection;
use Nelmio\Alice\Persister\Doctrine as AliceDoctrine;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class OroAliceLoader
 * @package TestFrameworkBundle\Behat\Fixtures
 */
class OroAliceLoader extends Loader
{
    /**
     * {@inheritdoc}
     */
    public function __construct($locale = 'en_US', array $providers = [], $seed = 1, array $parameters = [])
    {
        parent::__construct($locale, $providers, $seed, $parameters);
    }

    /**
     * @return AliceCollection
     */
    public function getReferenceRepository()
    {
        return $this->objects;
    }

    /**
     * @param RegistryInterface $doctrine
     */
    public function setDoctrine(RegistryInterface $doctrine)
    {
        $this->typeHintChecker->setPersister(new AliceDoctrine($doctrine->getManager()));
    }
}
