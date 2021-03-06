<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Behat\Isolation;

abstract class AbstractOsRelatedIsolator
{
    const WINDOWS_OS = 'WINDOWS';
    const LINUX_OS   = 'LINUX';
    const MAC_OS     = 'DARWIN';

    /**
     * @return array of applicable OS
     */
    abstract protected function getApplicableOs();

    /**
     * @return bool
     */
    public function isApplicableOS()
    {
        $os = explode(' ', strtoupper(php_uname()))[0];

        return in_array($os, $this->getApplicableOs());
    }
}
