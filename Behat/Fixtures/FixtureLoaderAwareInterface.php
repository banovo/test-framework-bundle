<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Fixtures;

/**
 * Interface FixtureLoaderAwareInterface
 * @package TestFrameworkBundle\Behat\Fixtures
 */
interface FixtureLoaderAwareInterface
{
    /**
     * @param FixtureLoader $fixtureLoader
     */
    public function setFixtureLoader(FixtureLoader $fixtureLoader);
}
