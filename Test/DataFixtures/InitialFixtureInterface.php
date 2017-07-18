<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Test\DataFixtures;

/**
 * If a data fixture implements this interface the fixtures executor
 * will not clear the entity manager after this fixture.
 * This might be helpful if you need to load existing data to reference in nelmio/alice file.
 * @see TestFrameworkBundle\Test\DataFixtures\DataFixturesExecutor
 */
interface InitialFixtureInterface
{
}
