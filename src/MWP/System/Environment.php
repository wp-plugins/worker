<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_System_Environment
{

    /**
     * @var MWP_ServiceContainer_Interface
     */
    private $container;

    public function __construct(MWP_ServiceContainer_Interface $container)
    {
        $this->container = $container;
    }

    public function getMemoryLimit()
    {
        return MWP_System_Utils::convertToBytes(ini_get('memory_limit'));
    }

    public function isPdoEnabled()
    {
        if ($this->container->getParameter('disable_pdo')) {
            return false;
        }

        return extension_loaded('pdo_mysql');
    }

    public function isMysqliEnabled()
    {
        if ($this->container->getParameter('disable_mysqli')) {
            return false;
        }

        return extension_loaded('mysqli');
    }

    public function isMysqlEnabled()
    {
        if ($this->container->getParameter('disable_mysql')) {
            return false;
        }

        return extension_loaded('mysql');
    }

    public function isCurlEnabled()
    {
        // Some hosting providers disable only curl_exec().
        return (function_exists('curl_init') && function_exists('curl_exec'));
    }
}
