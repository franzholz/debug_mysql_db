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

use Psr\Http\Message\ServerRequestInterface;

use TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Geithware\DebugMysqlDb\Api\DebugApi;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\DebugUtility;


/**
* extension of TYPO3 mysql database debug
* This contains a debug extension for mysql-db calls based on $GLOBALS['TYPO3_DB']
*
* @author	TYPO3 Community
* @author	Stefan Geith <typo3dev2020@geithware.de>
* @package TYPO3
* @subpackage debug_mysql_db
*/
class Typo3DbLegacyConnection extends DatabaseConnection implements SingletonInterface {
    protected $debugApi = null;
    public $debugOutput = false;
    protected $ticker = '';
    protected ?ServerRequestInterface $request = null;

    /**
     * Internal property to mark if a deprecation log warning has been thrown in this request
     * in order to avoid a load of deprecation.
     * @var bool
     */
    protected $deprecationWarningThrown = true;

    /**
     * Initialize the database connection
     */
    public function initialize(): void
    {
        $extensionConfiguration =
            GeneralUtility::makeInstance(
                ExtensionConfiguration::class
            )->get('debug_mysql_db'); // unserializing the configuration so we can use it here
        $this->debugOutput = (intval($extensionConfiguration['DISABLE_ERRORS'])) ? false : true;
        $this->ticker = $extensionConfiguration['TICKER'] ? floatval($extensionConfiguration['TICKER']) / 1000 : '';

        $this->debugApi =
            GeneralUtility::makeInstance(
                DebugApi::class
            );
        $this->debugApi->init($extensionConfiguration);
    }

    /**
    * only needed for TYPO3 6.1:
    *
    * Central query method. Also checks if there is a database connection.
    * Use this to execute database queries instead of directly calling $this->link->query()
    *
    * @param string $query The query to send to the database
    * @return bool|\mysqli_result
    */
    protected function query($query)
    {
        if (!$this->isConnected) {
            $this->connectDB();
        }
        return $this->link->query($query);
    }

    /************************************
    *
    * Query execution
    *
    * These functions are the RECOMMENDED DBAL functions for use in your applications
    * Using these functions will allow the DBAL to use alternative ways of accessing data (contrary to if a query is returned!)
    * They compile a query AND execute it immediately and then return the result
    * This principle heightens our ability to create various forms of DBAL of the functions.
    * Generally: We want to return a result pointer/object, never queries.
    * Also, having the table name together with the actual query execution allows us to direct the request to other databases.
    *
    **************************************/

    /**
    * Creates and executes an INSERT SQL-statement for $table from the array with field/value pairs $fields_values.
    * Using this function specifically allows us to handle BLOB and CLOB fields depending on DB
    *
    * @param string $table Table name
    * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$insertFields" with 'fieldname'=>'value' and pass it to this function as argument.
    * @param bool|array|string $no_quote_fields See fullQuoteArray()
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    */
    public function exec_INSERTquery ($table, $fields_values, $no_quote_fields = false) {
        if (!$this->isConnected) {
            $this->connectDB();
        }
        $query = $this->INSERTquery($table, $fields_values, $no_quote_fields);
        $starttime = microtime(true);
        $resultSet = $this->query($query);
        $endtime = microtime(true);
        $error = $this->sql_error();
        if ($this->bDisplayOutput($error, $starttime, $endtime)) {
            $myName = 'exec_INSERTquery';
            $this->myDebug($myName, $error, 'INSERT', $table, $query, $resultSet, $endtime - $starttime);
        }

        if ($this->debugOutput) {
            $this->debug('exec_INSERTquery');
        }
        foreach ($this->postProcessHookObjects as $hookObject) {
            /** @var $hookObject PostProcessQueryHookInterface */
            $hookObject->exec_INSERTquery_postProcessAction($table, $fields_values, $no_quote_fields, $this);
        }
        return $resultSet;
    }

    /**
    * Creates and executes an INSERT SQL-statement for $table with multiple rows.
    *
    * @param string $table Table name
    * @param array $fields Field names
    * @param array $rows Table rows. Each row should be an array with field values mapping to $fields
    * @param bool|array|string $no_quote_fields See fullQuoteArray()
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    */
    public function exec_INSERTmultipleRows ($table, array $fields, array $rows, $no_quote_fields = false)
    {
        if (!$this->isConnected) {
            $this->connectDB();
        }
        $query = $this->INSERTmultipleRows($table, $fields, $rows, $no_quote_fields);
        $starttime = microtime(true);
        $resultSet = $this->query($query);
        $endtime = microtime(true);
        $error = $this->sql_error();
        if ($this->bDisplayOutput($error, $starttime, $endtime)) {
            $myName = 'exec_INSERTmultipleRows';
            $this->myDebug($myName, $error, 'INSERT', $table, $query, $resultSet, $endtime - $starttime);
        }

        if ($this->debugOutput) {
            $this->debug('exec_INSERTmultipleRows');
        }

        foreach ($this->postProcessHookObjects as $hookObject) {
            /** @var $hookObject PostProcessQueryHookInterface */
            $hookObject->exec_INSERTmultipleRows_postProcessAction($table, $fields, $rows, $no_quote_fields, $this);
        }
        return $resultSet;
    }

    /**
    * Creates and executes an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array with field/value pairs $fields_values.
    * Using this function specifically allow us to handle BLOB and CLOB fields depending on DB
    *
    * @param string $table Database tablename
    * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
    * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$updateFields" with 'fieldname'=>'value' and pass it to this function as argument.
    * @param bool|array|string $no_quote_fields See fullQuoteArray()
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    */
    public function exec_UPDATEquery ($table, $where, $fields_values, $no_quote_fields = false)
    {
        if (!$this->isConnected) {
            $this->connectDB();
        }
        $query = $this->UPDATEquery($table, $where, $fields_values, $no_quote_fields);
        $starttime = microtime(true);
        $resultSet = $this->query($query);
        $endtime = microtime(true);
        $error = $this->sql_error();
        if ($this->bDisplayOutput($error, $starttime, $endtime)) {
            $myName = 'exec_UPDATEquery';
            $this->myDebug($myName, $error, 'UPDATE', $table, $query, $resultSet, $endtime - $starttime);
        }
        foreach ($this->postProcessHookObjects as $hookObject) {
            /** @var $hookObject PostProcessQueryHookInterface */
            $hookObject->exec_UPDATEquery_postProcessAction($table, $where, $fields_values, $no_quote_fields, $this);
        }
        return $resultSet;
    }

    /**
    * Creates and executes a DELETE SQL-statement for $table where $where-clause
    *
    * @param string $table Database tablename
    * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    */
    public function exec_DELETEquery ($table, $where)
    {
        if (!$this->isConnected) {
            $this->connectDB();
        }
        $query = $this->DELETEquery($table, $where);
        $starttime = microtime(true);
        $resultSet = $this->query($query);
        $endtime = microtime(true);
        $error = $this->sql_error();
        if ($this->bDisplayOutput($error, $starttime, $endtime)) {
            $myName = 'exec_DELETEquery';
            $this->myDebug($myName, $error, 'DELETE', $table, $query, $resultSet, $endtime - $starttime);
        }
        if ($this->debugOutput) {
            $this->debug('exec_DELETEquery');
        }
        foreach ($this->postProcessHookObjects as $hookObject) {
            /** @var $hookObject PostProcessQueryHookInterface */
            $hookObject->exec_DELETEquery_postProcessAction($table, $where, $this);
        }
        return $resultSet;
    }

    /**
    * Creates and executes a SELECT SQL-statement
    * Using this function specifically allow us to handle the LIMIT feature independently of DB.
    *
    * @param string $select_fields List of fields to select from the table. This is what comes right after "SELECT ...". Required value.
    * @param string $from_table Table(s) from which to select. This is what comes right after "FROM ...". Required value.
    * @param string $where_clause Additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
    * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
    * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
    * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
    * @param boolean / array $dbgModes['name'] gives the debugging name
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    */
    public function exec_SELECTquery ($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', $dbgModes = false)
    {
        if (!$this->isConnected) {
            $this->connectDB();
        }

        $query = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, 1);
        $starttime = microtime(true);

        $level = error_reporting();
        error_reporting($level & (E_ALL ^ E_WARNING));
        $resultSet = $this->query($query);
        error_reporting($level);

        $endtime = microtime(true);
        $error = $this->sql_error();

        if ($this->bDisplayOutput($error, $starttime, $endtime)) {
            $myName = is_array($dbgModes) ? ($dbgModes['name'] ?: __FILE__ . ':' . __LINE__ ) : 'exec_SELECTquery';
            $this->myDebug($myName, $error, 'SELECT', $from_table, $query, $resultSet, $endtime - $starttime);
        }
        if ($this->debugOutput) {
            $this->debug('exec_SELECTquery');
        }
        foreach ($this->postProcessHookObjects as $hookObject) {
            /** @var $hookObject PostProcessQueryHookInterface */
            $hookObject->exec_SELECTquery_postProcessAction($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', $this);
        }
        return $resultSet;
    }

    /**
    * Creates and executes a SELECT query, selecting fields ($select) from two/three tables joined
    * Use $mm_table together with $local_table or $foreign_table to select over two tables. Or use all three tables to select the full MM-relation.
    * The JOIN is done with [$local_table].uid <--> [$mm_table].uid_local  / [$mm_table].uid_foreign <--> [$foreign_table].uid
    * The function is very useful for selecting MM-relations between tables adhering to the MM-format used by TCE (TYPO3 Core Engine). See the section on $GLOBALS['TCA'] in Inside TYPO3 for more details.
    *
    * @param string $select Field list for SELECT
    * @param string $local_table Tablename, local table
    * @param string $mm_table Tablename, relation table
    * @param string $foreign_table Tablename, foreign table
    * @param string $whereClause Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT! You have to prepend 'AND ' to this parameter yourself!
    * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
    * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
    * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    * @see exec_SELECTquery()
    */
    public function exec_SELECT_mm_query ($select, $local_table, $mm_table, $foreign_table, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '')
    {
        $foreign_table_as = $foreign_table == $local_table ? $foreign_table . str_replace('.', '', uniqid('_join', true)) : '';
        $mmWhere = $local_table ? $local_table . '.uid=' . $mm_table . '.uid_local' : '';
        $mmWhere .= ($local_table and $foreign_table) ? ' AND ' : '';
        $tables = ($local_table ? $local_table . ',' : '') . $mm_table;
        if ($foreign_table) {
            $mmWhere .= ($foreign_table_as ?: $foreign_table) . '.uid=' . $mm_table . '.uid_foreign';
            $tables .= ',' . $foreign_table . ($foreign_table_as ? ' AS ' . $foreign_table_as : '');
        }
        return $this->exec_SELECTquery($select, $tables, $mmWhere . ' ' . $whereClause, $groupBy, $orderBy, $limit, ['name' => 'exec_SELECT_mm_query']);
    }

    /**
    * Executes a select based on input query parts array
    *
    * @param array $queryParts Query parts array
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    * @see exec_SELECTquery()
    */
    public function exec_SELECT_queryArray ($queryParts)
    {
        return $this->exec_SELECTquery($queryParts['SELECT'], $queryParts['FROM'], $queryParts['WHERE'], $queryParts['GROUPBY'], $queryParts['ORDERBY'], $queryParts['LIMIT'], ['name' => 'exec_SELECT_queryArray']);
    }

    /**
    * Creates and executes a SELECT SQL-statement AND traverse result set and returns array with records in.
    *
    * @param string $select_fields See exec_SELECTquery()
    * @param string $from_table See exec_SELECTquery()
    * @param string $where_clause See exec_SELECTquery()
    * @param string $groupBy See exec_SELECTquery()
    * @param string $orderBy See exec_SELECTquery()
    * @param string $limit See exec_SELECTquery()
    * @param string $uidIndexField If set, the result array will carry this field names value as index. Requires that field to be selected of course!
    * @return array|null Array of rows, or null in case of SQL error
    */
    public function exec_SELECTgetRows ($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', $uidIndexField = '')
    {
        $resultSet = $this->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, ['name' => 'exec_SELECTgetRows']);
        if ($this->debugOutput) {
            $this->debug('exec_SELECTquery');
        }
        if ($this->sql_error()) {
            return null;
        }
        $output = [];
        $firstRecord = true;
        while ($record = $this->sql_fetch_assoc($resultSet)) {
            if ($uidIndexField) {
                if ($firstRecord) {
                    $firstRecord = false;
                    if (!array_key_exists($uidIndexField, $record)) {
                        $this->sql_free_result($resultSet);
                        throw new \InvalidArgumentException('The given $uidIndexField "' . $uidIndexField . '" is not available in the result.', 1432933855);
                    }
                }
                $output[$record[$uidIndexField]] = $record;
            } else {
                $output[] = $record;
            }
        }
        $this->sql_free_result($resultSet);
        return $output;
    }

    /**
    * Creates and executes a SELECT SQL-statement AND gets a result set and returns an array with a single record in.
    * LIMIT is automatically set to 1 and can not be overridden.
    *
    * @param string $select_fields List of fields to select from the table.
    * @param string $from_table Table(s) from which to select.
    * @param string $where_clause Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
    * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
    * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
    * @param bool $numIndex If set, the result will be fetched with sql_fetch_row, otherwise sql_fetch_assoc will be used.
    * @return array|false|null Single row, false on empty result, null on error
    */
    public function exec_SELECTgetSingleRow ($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $numIndex = false)
    {
        $resultSet = $this->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, '1', ['name' => 'exec_SELECTgetSingleRow']);
        if ($this->debugOutput) {
            $this->debug('exec_SELECTquery');
        }
        $output = null;
        if ($resultSet !== false) {
            if ($numIndex) {
                $output = $this->sql_fetch_row($resultSet);
            } else {
                $output = $this->sql_fetch_assoc($resultSet);
            }
            $this->sql_free_result($resultSet);
        }
        return $output;
    }

    /**
    * Counts the number of rows in a table.
    *
    * @param string $field Name of the field to use in the COUNT() expression (e.g. '*')
    * @param string $table Name of the table to count rows for
    * @param string $where (optional) WHERE statement of the query
    * @return mixed Number of rows counter (int) or false if something went wrong (bool)
    */
    public function exec_SELECTcountRows ($field, $table, $where = '')
    {
        $count = false;
        $resultSet = $this->exec_SELECTquery('COUNT(' . $field . ')', $table, $where, '', '', '', ['name' => 'exec_SELECTcountRows']);
        if ($resultSet !== false) {
            [$count] = $this->sql_fetch_row($resultSet);
            $count = (int) $count;
            $this->sql_free_result($resultSet);
        }
        return $count;
    }


    /**************************************
    *
    * Prepared Query Support
    *
    **************************************/
    /**
    * Creates a SELECT prepared SQL statement.
    *
    * @param string $select_fields See exec_SELECTquery()
    * @param string $from_table See exec_SELECTquery()
    * @param string $where_clause See exec_SELECTquery()
    * @param string $groupBy See exec_SELECTquery()
    * @param string $orderBy See exec_SELECTquery()
    * @param string $limit See exec_SELECTquery()
    * @param array $input_parameters An array of values with as many elements as there are bound parameters in the SQL statement being executed. All values are treated as \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
    * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
    */
    public function prepare_SELECTquery ($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', array $input_parameters = [])
    {
        $query = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, ['name' => 'prepare_SELECTquery']);
        /** @var $preparedStatement \TYPO3\CMS\Core\Database\PreparedStatement */
        $preparedStatement = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\PreparedStatement', $query, $from_table, []);
        // Bind values to parameters
        foreach ($input_parameters as $key => $value) {
            $preparedStatement->bindValue($key, $value, PreparedStatement::PARAM_AUTOTYPE);
        }
        // Return prepared statement
        return $preparedStatement;
    }

    /**************************************
    *
    * MySQL wrapper functions
    * (For use in your applications)
    *
    **************************************/
    /**
    * Executes query
    * MySQLi query() wrapper function
    * Beware: Use of this method should be avoided as it is experimentally supported by DBAL. You should consider
    * using exec_SELECTquery() and similar methods instead.
    *
    * @param string $query Query to execute
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    */
    public function sql_query ($query)
    {
        if (!$this->isConnected) {
            $this->connectDB();
        }
        $starttime = microtime(true);
        $resultSet = $this->query($query);
        $endtime = microtime(true);
        if (!str_contains($query, 'SESSION')) {
            $error = $this->sql_error();
            if ($this->bDisplayOutput($error, $starttime, $endtime)) {
                $myName = 'TYPO3_DB->sql_query';
                $this->myDebug($myName, $error, 'SQL', '', $query, $resultSet, $endtime - $starttime);
            }
            if ($this->debugOutput) {
                $this->debug('sql_query', $query);
            }
        }
        return $resultSet;
    }

    /**
    * mysqli() wrapper function, used by the Install Tool and EM for all queries regarding management of the database!
    *
    * @param string $query Query to execute
    * @return bool|\mysqli_result|object MySQLi result object / DBAL object
    */
    public function admin_query ($query)
    {
        if (!$this->isConnected) {
            $this->connectDB();
        }
        $starttime = microtime(true);
        $resultSet = $this->query($query);
        $endtime = microtime(true);
        $error = $this->sql_error();
        if ($this->bDisplayOutput($error, $starttime, $endtime)) {
            $myName = 'admin_query';
            $this->myDebug($myName, $error, 'SQL', '', $query, $resultSet, $endtime - $starttime);
        }
        if ($this->debugOutput) {
            $this->debug('admin_query', $query);
        }
        return $resultSet;
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


    public function getLastInsertId ($table)
    {
        $result = false;
        $affectedRowsCount = $this->sql_affected_rows();

        if ($affectedRowsCount) {
            $result = $this->sql_insert_id();
        }

        if (
            !$result &&
            $affectedRowsCount &&
            $this->getDatabaseHandle()::class == 'mysqli' &&
            $table != '' &&
            !str_contains((string) $table, '_mm') &&
            !str_contains((string) $table, 'cache') &&
            isset($GLOBALS['TCA'][$table]) // Check if the uid field is present. Any TCA table must have it.
        ) {
            $sqlInsertId = 0;
            $lastInsertRes = $this->query( 'SELECT LAST_INSERT_ID() as insert_id' );

            if ($lastInsertRes !== false) {
                while ($lastInsertRow = $lastInsertRes->fetch_assoc()) {
                    $sqlInsertId = $lastInsertRow['insert_id'];
                }
                $lastInsertRes->free();
            }

            if ($sqlInsertId) {
                $rowArray = $this->exec_SELECTgetRows('uid', $table, 'uid=' . intval($sqlInsertId), ''); // LAST_INSERT_ID()

                if (
                    isset($rowArray) &&
                    is_array($rowArray) &&
                    isset($rowArray['0']) &&
                    is_array($rowArray['0'])
                ) {
                    $result = $rowArray['0']['uid'];
                }
            }
        }

        return $result;
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
    public function myDebug ($func, $error, $mode, $table, $query, $resultSet, $microseconds): void
    {
        $affectedRows = '';
        $insertId = 0;
        if ($mode == 'SELECT') {
            $affectedRows = $this->sql_num_rows($resultSet);
        } else if (in_array($mode, ['UPDATE', 'DELETE', 'INSERT', 'SQL'])) {
            $affectedRows = $this->sql_affected_rows();
        }

        if ($mode == 'INSERT' || $mode == 'SQL') {
            $insertId = $this->getLastInsertId($table);
        }

        $this->debugApi->myDebug($this, $func, $error, $mode, $table, $query, $affectedRows, $insertId, $microseconds);
    }
}
