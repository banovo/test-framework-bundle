<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Fixtures;

use Doctrine\Common\Inflector\Inflector;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;

/**
 * Class EntityClassResolver
 * @package TestFrameworkBundle\Behat\Fixtures
 */
class EntityClassResolver
{
    /**
     * @var EntityAliasResolver
     */
    protected $aliasResolver;

    /**
     * EntityClassResolver constructor.
     * @param EntityAliasResolver $aliasResolver
     */
    public function __construct(EntityAliasResolver $aliasResolver)
    {
        $this->aliasResolver = $aliasResolver;
    }

    /**
     * @param string $entityName Entity name in plural or single form, e.g. Tasks, Calendar Event etc.
     * @return string Full namespace to class
     */
    public function getEntityClass($entityName)
    {
        return $this->aliasResolver->getClassByAlias($this->convertEntityNameToAlias($entityName));
    }

    /**
     * @param string $entityName
     * @return string
     */
    protected function convertEntityNameToAlias($entityName)
    {
        $name = strtolower($entityName);
        $nameParts = explode(' ', $name);
        $nameParts = array_map([new Inflector, 'singularize'], $nameParts);

        return implode('', $nameParts);
    }
}
