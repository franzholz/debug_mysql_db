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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Http\Message\ServerRequestInterface;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Geithware\DebugMysqlDb\Api\DebugApi;
use Geithware\DebugMysqlDb\Api\DoctrineApi;
use Geithware\DebugMysqlDb\Database\Logging\SqlQueryLogger;
use TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\DebugUtility;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\SQLParserUtils;
use Exception;


use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;




class DoctrineConnection extends \TYPO3\CMS\Core\Database\Connection implements LoggerAwareInterface {
    use LoggerAwareTrait;

    protected $debugApi = null;
    protected $doctrineApi = null;
    protected $debugOutput = false;
    protected $debugUtilityErrors = false;
    protected $ticker = '';
    protected $fileWriterMode = 0;
    /** @var bool */
    protected $backTrace = false;
    protected $typeArray = ['DELETE', 'UPDATE', 'INSERT', 'CREATE', 'DROP', 'ALTER', 'GRANT', 'REVOKE'];

    /**
     * Internal property to mark if a deprecation log warning has been thrown in this request
     * in order to avoid a load of deprecation.
     * @var bool
     */
    protected $deprecationWarningThrown = true;
    
    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }

    /**
     * Initializes a new instance of the Connection class.
     *
     * @param array $params The connection parameters.
     * @param Driver $driver The driver to use.
     * @param Configuration|null $config The configuration, optional.
     * @param EventManager|null $eventManager The event manager, optional.
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function __construct(
        #[SensitiveParameter]
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    )
    {
        parent::__construct($params, $driver, $config, $eventManager);
        $extensionConfiguration =
            GeneralUtility::makeInstance(
                ExtensionConfiguration::class
            )->get('debug_mysql_db'); // unserializing the configuration so we can use it here 
        $this->debugOutput = (intval($extensionConfiguration['DISABLE_ERRORS'])) ? false : true;
        $this->debugUtilityErrors = (intval($extensionConfiguration['DEBUGUTILITY_ERRORS'])) ? true : false;

        $this->ticker = $extensionConfiguration['TICKER'] ? floatval($extensionConfiguration['TICKER']) / 1000 : '';
        $this->fileWriterMode = $extensionConfiguration['FILEWRITER'] ? intval($extensionConfiguration['FILEWRITER']) : 0;
        $this->backTrace = (bool) $extensionConfiguration['BTRACE_SQL'];

        $this->debugApi = GeneralUtility::makeInstance(DebugApi::class, $this->getRequest(), $extensionConfiguration);
        $this->doctrineApi = GeneralUtility::makeInstance(DoctrineApi::class);
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
                SqlQueryLogger::class,
                $this->fileWriterMode,
                $this->backTrace
            );
        $configuration = $this->getConfiguration()->setSQLLogger($logger);
        return true;
    }

    public function determineTablename($expandedQuery, $type) 
    {
        $result = 'table not found';
        $sqlSearchWord = '';
        if (
            !empty($type) &&
            stripos((string) $expandedQuery, (string) $type) !== false
        ) {
            switch ($type) {
                case 'SELECT':
                case 'DELETE':
                    $sqlSearchWord = 'FROM';
                    break;

                case 'INSERT':
                    $sqlSearchWord = 'INTO';
                    break;

                case 'CREATE':
                case 'DROP':
                case 'ALTER':
                case 'GRANT':
                case 'REVOKE':
                    $sqlSearchWord = 'TABLE';
                    break;
                case 'SHOW':
                    // nothing
                    break;
            }
        }
     
        if (
            $sqlSearchWord
        ) {    
            if (strpos((string) $expandedQuery, $sqlSearchWord . ' `')) {
                $search = '/'. $sqlSearchWord . '\s+`(\w*\.*\w+)`\s*/s';
                preg_match($search , (string) $expandedQuery, $matches);
            } else {
                $search = '/'. $sqlSearchWord . '\s+(\w*\.*\w+)\s*/s';
                preg_match($search , (string) $expandedQuery, $matches);
            }

            if (is_array($matches) && isset($matches['1'])) {
                $result = $matches['1'];
            }
        }
        return $result;
    }

    /**
     * Executes an, optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.
     * If an SQLLogger is configured, the execution is logged.
     *
     * @param string                 $query  The SQL query to execute with placeholders for the following parameters.
     * @param mixed[]                $params The parameters to bind to the query, if any.
     * @param int[]|string[]         $types  The types the previous parameters are in.
     * @param QueryCacheProfile|null $qcp    The query cache profile, optional.
     *
     * @return Result The executed statement.
     *
     * @throws Exception
     */
    public function executeQuery(
        string $sql,
        array $params = [],
        $types = [],
        ?QueryCacheProfile $qcp = null
    ): Result
    {
        $starttime = microtime(true);
        $stmt = null;
        $errorCode = 0;
        $errorMessage = '';
        $exception = false;
        $throwException = null;

        try {
            $stmt = parent::executeQuery($sql, $params, $types, $qcp);
        }
        catch (Throwable $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $throwException = $e;
        }
        finally {
            $endtime = microtime(true);

            if ($this->bDisplayOutput($errorMessage, $starttime, $endtime)) {
                $expandedQuery = 
                    $this->doctrineApi->getExpandedQuery(
                        $sql,
                        $params,
                        $types
                    );
                $myName = 'executeQuery';
                $table = $this->determineTablename($expandedQuery, 'SELECT');

                $affectedRows = '';
                $microseconds = $endtime - $starttime;
                $this->myDebug($myName, $errorMessage, 'SELECT', $table, $expandedQuery, $stmt, $affectedRows, $microseconds);
            }

            if ($this->debugOutput) {
                $this->debug('executeQuery', $errorCode, $errorMessage, $sql);
            }

            if (is_object($throwException)) {
                throw $throwException;
            }
        }

        return $stmt;
    }

    /**
     * Executes an SQL INSERT/UPDATE/DELETE query with the given parameters
     * and returns the number of affected rows.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string         $query  The SQL query.
     * @param mixed[]        $params The query parameters.
     * @param int[]|string[] $types  The parameter types.
     *
     * @return int The number of affected rows.
     *
     * @throws Exception
     */
    public function executeUpdate(string $sql, array $params = [], array $types = []): int
    {
        $myName = 'executeUpdate';
        $starttime = microtime(true);
        $errorCode = 0;
        $errorMessage = '';
        $affectedRows = '';
        $throwException = null;

        try {
            $affectedRows = parent::executeUpdate($sql, $params, $types);
        }
        catch (Throwable $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $throwException = $e;
        }
        finally {
            $endtime = microtime(true);

            if ($this->bDisplayOutput($errorMessage, $starttime, $endtime)) {
                $expandedQuery = 
                    $this->doctrineApi->getExpandedQuery(
                        $sql,
                        $params,
                        $types
                    );

                $type = '';
                foreach ($this->typeArray as $type) {
                    if (str_starts_with($sql, (string) $type)) {
                        break;
                    }
                }
                $table = $this->determineTablename($expandedQuery, $type);
                $this->myDebug($myName, $errorMessage, $type, $table, $expandedQuery, null, $affectedRows, $endtime - $starttime);
            }
            if ($this->debugOutput) {
                $this->debug($myName, $errorCode, $errorMessage, $sql);
            }

            if (is_object($throwException)) {
                throw $throwException;
            }
        }
    
        return $affectedRows;
    }

    
    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be used for:
     *  - DML statements: INSERT, UPDATE, DELETE, etc.
     *  - DDL statements: CREATE, DROP, ALTER, etc.
     *  - DCL statements: GRANT, REVOKE, etc.
     *  - Session control statements: ALTER SESSION, SET, DECLARE, etc.
     *  - Other statements that don't yield a row set.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                                                               $sql    SQL statement
     * @param array<int, mixed>|array<string, mixed>                               $params Statement parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     */
    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $myName = 'executeStatement';
        $starttime = microtime(true);
        $errorCode = 0;
        $errorMessage = '';
        $affectedRows = '';
        $throwException = null;
    
        $type = '';
        foreach ($this->typeArray as $type) {
            if (str_starts_with($sql, (string) $type)) {
                break;
            }
        }
            
        try {
            $affectedRows = parent::executeStatement($sql, $params, $types);
        }
        catch (Throwable $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $throwException = $e;
        }
        finally {
            $endtime = microtime(true);

            if (
                $this->bDisplayOutput($errorMessage, $starttime, $endtime)
            ) {
                $expandedSql = 
                    $this->doctrineApi->getExpandedQuery(
                        $sql,
                        $params,
                        $types
                    );
                $table = $this->determineTablename($expandedSql, $type);
                $this->myDebug($myName, $errorMessage, $type, $table, $expandedSql, null, $affectedRows, $endtime - $starttime);
            }
            if ($this->debugOutput) {
                $this->debug($myName, $errorCode, $errorMessage, $sql);
            }

            if (is_object($throwException)) {
                throw $throwException;
            }
        }
    
        return $affectedRows;
    }



    /**
     * Executes an SQL statement and return the number of affected rows.
     *
     * @param string $statement
     *
     * @return int The number of affected rows.
     *
     * @throws Exception
     */
    public function exec(string $statement): int
    {
        $myName = 'exec';
        $errorCode = 0;
        $errorMessage = '';
        $starttime = microtime(true);
        $throwException = null;

        try {
            $result = parent::exec($statement);
        } catch (Throwable $e) {
            if ($this->debugOutput) {
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
            }
            $throwException = $e;
        }
        finally {
            $endtime = microtime(true);

            if ($this->bDisplayOutput($errorMessage, $starttime, $endtime)) {
                // TODO:
                $table = 'exec Test- Tabelle';
                $sql = 'exec Test- Query';
                $this->myDebug($myName, $errorMessage, 'SQL', $table, $sql, $result, '', $endtime - $starttime);
            }
        
            if ($this->debugOutput) {
                $this->debug($myName, $errorCode, $errorMessage, $statement);
            }

            if (is_object($throwException)) {
                throw $throwException;
            }
        }

        return $result;
    }

    /**
    * Determines if the debug output should be displayed. An error message or a time consuming SQL query shall be displayed.
    *
    * @param	string		error text
    * @param	float		startime of mysql-command
    * @param	float		endime of mysql-command
    * @return	boolean		true if output should be displayed
    */
    public function bDisplayOutput($errorMessage, $starttime, $endtime)
    {
        if (
            $errorMessage != '' ||
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
    public function debug($func, $errorCode = 0, $errorMessage = '', $query = ''): void
    {
        if ($errorCode > 0) {
            $errorDebug = 
                [
                    'caller' => DatabaseConnection::class . '::' . $func,
                    'ERROR' => $errorCode . ':' . $errorMessage,
                    'lastBuiltQuery' => $query,
                    'debug_backtrace' => DebugUtility::debugTrail()
                ];

            if ($this->debugUtilityErrors) {
                DebugUtility::debug(
                    $errorDebug,
                    $func,
                    isset($GLOBALS['error']) &&
                    is_object($GLOBALS['error']) && 
                    @is_callable([$GLOBALS['error'], 'debug'])
                        ? ''
                        : 'DB Error'
                );
            } else if (
                isset($GLOBALS['error']) &&
                is_object($GLOBALS['error']) &&
                @is_callable([$GLOBALS['error'], 'debug'])
            ) {
                debug($errorDebug, '');
            }
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
    * @param	resource	optional: SQL resource must implement the ResultStatement interfacee
    * @param	integer	    optional: affected rows
    * @param	string		consumed time in microseconds
    * @return	void
    */
    public function myDebug($func, $errorMessage, $mode, $table, $query, $resultSet, $affectedRows, $microseconds): void
    {
        $insertId = '';
        if (
            !MathUtility::canBeInterpretedAsInteger($affectedRows) &&
            is_object($resultSet) &&
            ($resultSet instanceof \Doctrine\DBAL\Driver\ResultStatement)
        ) {
            $affectedRows = $resultSet->rowCount();
        }

        if ($mode == 'INSERT' || $mode == 'SQL') {
            $insertId = $this->lastInsertId($table);
        }

        $this->debugApi->myDebug($this, $func, $errorMessage, $mode, $table, $query, $affectedRows, $insertId, $microseconds);
    }
}

