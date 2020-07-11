<?php

namespace Geithware\DebugMysqlDb\Database;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Log\LoggerAwareTrait;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Mysqli\Driver;

use TYPO3\CMS\Core\Utility\GeneralUtility;




class DoctrineConnection extends \TYPO3\CMS\Core\Database\Connection implements \Psr\Log\LoggerAwareInterface {
    use LoggerAwareTrait;

    protected $debugApi = null;
    protected $doctrineApi = null;
    protected $debugOutput = false;
    protected $ticker = '';
    protected $fileWriterMode = 0;
    /** @var bool */
    protected $backTrace = false;

    /**
     * Internal property to mark if a deprecation log warning has been thrown in this request
     * in order to avoid a load of deprecation.
     * @var bool
     */
    protected $deprecationWarningThrown = true;

    /**
     * Initializes a new instance of the Connection class.
     *
     * @param array $params The connection parameters.
     * @param Driver $driver The driver to use.
     * @param Configuration|null $config The configuration, optional.
     * @param EventManager|null $em The event manager, optional.
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function __construct (array $params, Driver $driver, Configuration $config = null, EventManager $em = null)
    {
        parent::__construct($params, $driver, $config, $em);
        $extensionConfiguration = [];
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['debug_mysql_db'])) {
            $extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['debug_mysql_db']);
        } else if (
            version_compare(TYPO3_version, '9.0.0', '>=')
        ) {
            $extensionConfiguration =
                \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                    \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
                )->get('debug_mysql_db'); // unserializing the configuration so we can use it here 
        }
        $this->debugOutput = (intval($extensionConfiguration['DISABLE_ERRORS'])) ? false : true;
        $this->ticker = $extensionConfiguration['TICKER'] ? floatval($extensionConfiguration['TICKER']) / 1000 : '';
        $this->fileWriterMode = $extensionConfiguration['FILEWRITER'] ? intval($extensionConfiguration['FILEWRITER']) : 0;
        $this->backTrace = (bool) $extensionConfiguration['BTRACE_SQL'];

        $this->debugApi = GeneralUtility::makeInstance(\Geithware\DebugMysqlDb\Api\DebugApi::class, $extensionConfiguration);
        $this->doctrineApi = GeneralUtility::makeInstance(\Geithware\DebugMysqlDb\Api\DoctrineApi::class);
    }

    /**
    * dependency injection of a sql logger
    *
    * @return bool
    */
    public function connect(): bool
    {
        // Early return if the connection is already open and custom setup has been done.
        if (!parent::connect()) {
            return false;
        }
        $logger = 
            GeneralUtility::makeInstance(
                Logging\SqlQueryLogger::class,
                $this->fileWriterMode,
                $this->backTrace
            );
        $configuration = $this->getConfiguration()->setSQLLogger($logger);
        return true;
    }

    /**
     * Executes an, optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.
     * If an SQLLogger is configured, the execution is logged.
     *
     * @param string                 $query  The SQL query to execute.
     * @param mixed[]                $params The parameters to bind to the query, if any.
     * @param int[]|string[]         $types  The types the previous parameters are in.
     * @param QueryCacheProfile|null $qcp    The query cache profile, optional.
     *
     * @return ResultStatement The executed statement.
     *
     * @throws DBALException
     */
    public function executeQuery ($query, array $params = [], $types = [], ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null)
    {
        $starttime = microtime(true);
        $stmt = parent::executeQuery($query, $params, $types, $qcp);
        $endtime = microtime(true);
        $errorCode = $this->errorCode();

        if ($this->bDisplayOutput($errorCode, $starttime, $endtime)) {
            $errorInfo = null;
            
            if ($errorCode) {
                $errorInfo = $errorCode . ':' . $this->errorInfo();
            }

            $expandedQuery = 
                $this->doctrineApi->getExpandedQuery(
                    $query,
                    $params
                );
            $myName = 'executeQuery';
            $table = 'executeQuery: table not found';

            if (strpos($expandedQuery, '`')) {
                preg_match('/FROM `(\w+)`/s' , $expandedQuery, $matches);
            } else {
                preg_match('/FROM (\w+) /s' , $expandedQuery, $matches);
            }

            if (is_array($matches) && isset($matches['1'])) {
                $table = $matches['1'];
            }
            $this->myDebug($myName, $errorInfo, 'SELECT', $table, $expandedQuery, $stmt, $endtime - $starttime);
        }
    
        if ($this->debugOutput) {
            $this->debug('executeQuery');
        }

        return $stmt;
    }



    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @param string $statement
     *
     * @return int The number of affected rows.
     *
     * @throws DBALException
     */
    public function exec ($statement)
    {
        $starttime = microtime(true);
        try {
            $result = parent::exec($statement);
        } catch (Throwable $ex) {
            debug($ex);
        }
        $endtime = microtime(true);
        $errorCode = $this->errorCode();

        if ($this->bDisplayOutput($errorCode, $starttime, $endtime)) {
            $errorInfo = $errorCode . ':' . $this->errorInfo();
            $myName = 'exec';
            // TODO:
            $table = 'exec Test- Tabelle';
            $query = 'exec Test- Query';
            $this->myDebug($myName, $errorInfo, 'SQL', $table, $query, $result, $endtime - $starttime);
        }
    
        if ($this->debugOutput) {
            $this->debug('exec');
        }

        return $result;
    }

    /**
    * Determines if the debug output should be displayed. An error message or a time comsuming SQL query shall be displayed.
    *
    * @param	string		error text
    * @param	float		startime of mysql-command
    * @param	float		endime of mysql-command
    * @return	boolean		true if output should be displayed
    */
    public function bDisplayOutput ($error, $starttime, $endtime)
    {
        if (
            $error != '' ||
            $this->ticker == '' ||
            $this->ticker <= $endtime - $starttime
        ) {
            $result = true;
        } else {
            $result = false;
        }
        return $result;
    }

    /******************************
     *
     * Debugging
     *
     ******************************/
    /**
     * Debug function: Outputs error if any
     *
     * @param string $func Function calling debug()
     * @param string $query Last query if not last built query
     */
    public function debug ($func, $query = '')
    {
        $errorCode = $this->errorCode();
 
        if ($errorCode || (int)$this->debugOutput === 2) {
            $errorInfo = $this->errorInfo();

            \TYPO3\CMS\Core\Utility\DebugUtility::debug(
                [
                    'caller' => \TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection::class . '::' . $func,
                    'ERROR' => $errorCode . ':' . $errorInfo,
                    'lastBuiltQuery' => $query ? $query : $this->debug_lastBuiltQuery,
                    'debug_backtrace' => \TYPO3\CMS\Core\Utility\DebugUtility::debugTrail()
                ],
                $func,
                is_object($GLOBALS['error']) && @is_callable([$GLOBALS['error'], 'debug'])
                    ? ''
                    : 'DB Error'
            );
        }
    }

    /**
    * Debug function: Outputs error if any
    *
    * @param	string		Function calling debug()
    * @param	string		error text
    * @param	string		mode
    * @param	string		table name
    * @param	string		SQL query
    * @param	resource	SQL resource
    * @param	string		consumed time in microseconds
    * @return	void
    */
    public function myDebug ($func, $error, $mode, $table, $query, $resultSet, $microseconds)
    {
        $this->debugApi->myDebug($this, $func, $error, $mode, $table, $query, $resultSet, $microseconds);
    }
}
 

