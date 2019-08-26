<?php

namespace Geithware\DebugMysqlDb\Database;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\DebugUtility;

/***************************************************************
*  Copyright notice
*
*  (c) 2004-2019 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
* Contains a debug extension for mysql-db calls
*
* @author	TYPO3 Community
* @author	Stefan Geith <typo3dev2013@geithware.de>
*/


/**
* extension of TYPO3 mysql database debug
*
* @author	TYPO3 Community
* @package TYPO3
* @subpackage debug_mysql_db
*/
class DatabaseConnection extends \TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection {
    protected $dbgConf = array();
    protected $dbgQuery = array();
    protected $dbgTable = array();
    protected $dbgExcludeTable = array();
    protected $dbgId = array();
    protected $dbgFeUser = array();
    protected $dbgOutput = '';
    protected $dbgTextformat = false;
    protected $ticker = '';
    /**
     * Internal property to mark if a deprecation log warning has been thrown in this request
     * in order to avoid a load of deprecation.
     * @var bool
     */
    protected $deprecationWarningThrown = true;

    public function __construct ()
    {
        $this->dbgConf =
            \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
            )->get('debug_mysql_db'); // unserializing the configuration so we can use it here:

        $this->dbgOutput = $this->dbgConf['OUTPUT'] ? $this->dbgConf['OUTPUT'] : '\\TYPO3\\CMS\\Utility\\DebugUtility::debug';
        $this->dbgTextformat = $this->dbgConf['TEXTFORMAT'] ? $this->dbgConf['TEXTFORMAT'] : false;
        $this->dbgTca = $this->dbgConf['TCA'] ? $this->dbgConf['TCA'] : false;
        $this->debugOutput = (intval($this->dbgConf['DISABLE_ERRORS'])) ? false : true;
        $this->ticker = $this->dbgConf['TICKER'] ? floatval($this->dbgConf['TICKER']) / 1000 : '';

        if (strtoupper($this->dbgConf['QUERIES']) == 'ALL' || !trim($this->dbgConf['QUERIES'])) {
            $this->dbgQuery = Array(
                'ALL' => 1,
                'SQL' => 1,
                'SELECT' => 1,
                'INSERT' => 1,
                'UPDATE' => 1,
                'DELETE'=>1,
                'FETCH' => 1,
                'FIRSTROW' => 1
            );
        } else {
            $tmp =
                GeneralUtility::trimExplode(
                    ',',
                    $this->dbgConf['QUERIES']
                );
            for ($i = 0; $i < count($tmp); $i++) {
                $this->dbgQuery[strtoupper($tmp[$i])] = 1;
            }
        }

        if (strtoupper($this->dbgConf['TABLES']) == 'ALL' || !trim($this->dbgConf['TABLES'])) {
            $this->dbgTable = Array('all' => 1);

            if ($this->dbgConf['EXCLUDETABLES'] != '') {
                $tmp = GeneralUtility::trimExplode(',', $this->dbgConf['EXCLUDETABLES']);
                $count = count($tmp);
                for ($i = 0; $i < $count; $i++) {
                    $this->dbgExcludeTable[strtolower($tmp[$i])] = 1;
                }
            }
        } else {
            $tmp = GeneralUtility::trimExplode(',', $this->dbgConf['TABLES']);
            $count = count($tmp);
            for ($i = 0; $i < $count; $i++) {
                $this->dbgTable[strtolower($tmp[$i])] = 1;
            }
        }
        $tmp = GeneralUtility::trimExplode(',', $this->dbgConf['PAGES']);
        $count = count($tmp);
        for ($i = 0; $i < $count; $i++) {
            $this->dbgId[intval($tmp[$i]) . '.'] = 1;
        }
        $tmp = GeneralUtility::trimExplode(',', $this->dbgConf['FEUSERS']);
        $count = count($tmp);
        for ($i = 0; $i < $count; $i++) if (intval($tmp[$i])) {
            $this->dbgFeUser[intval($tmp[$i]) . '.'] = 1;
        }
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
            $myName = is_array($dbgModes) ? ($dbgModes['name'] ? $dbgModes['name'] : __FILE__.':'.__LINE__ ) : 'exec_INSERTquery';
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
            $myName = is_array($dbgModes) ? ($dbgModes['name'] ? $dbgModes['name'] : __FILE__.':'.__LINE__ ) : 'exec_INSERTmultipleRows';
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
            $myName = is_array($dbgModes) ? ($dbgModes['name'] ? $dbgModes['name'] : __FILE__.':'.__LINE__ ) : 'exec_UPDATEquery';
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
            $myName = is_array($dbgModes) ? ($dbgModes['name'] ? $dbgModes['name'] : __FILE__.':'.__LINE__ ) : 'exec_DELETEquery';
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
            $myName = is_array($dbgModes) ? ($dbgModes['name'] ? $dbgModes['name'] : __FILE__ . ':' . __LINE__ ) : 'exec_SELECTquery';
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
        return $this->exec_SELECTquery($select, $tables, $mmWhere . ' ' . $whereClause, $groupBy, $orderBy, $limit, Array('name' => 'exec_SELECT_mm_query'));
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
        return $this->exec_SELECTquery($queryParts['SELECT'], $queryParts['FROM'], $queryParts['WHERE'], $queryParts['GROUPBY'], $queryParts['ORDERBY'], $queryParts['LIMIT'], array('name' => 'exec_SELECT_queryArray'));
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
        $resultSet = $this->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, array('name' => 'exec_SELECTgetRows'));
        if ($this->debugOutput) {
            $this->debug('exec_SELECTquery');
        }
        if ($this->sql_error()) {
            return null;
        }
        $output = array();
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
        $resultSet = $this->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, '1', array('name' => 'exec_SELECTgetSingleRow'));
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
        $resultSet = $this->exec_SELECTquery('COUNT(' . $field . ')', $table, $where, '', '', '', array('name' => 'exec_SELECTcountRows'));
        if ($resultSet !== false) {
            list($count) = $this->sql_fetch_row($resultSet);
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
    public function prepare_SELECTquery ($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', array $input_parameters = array())
    {
        $query = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, array('name' => 'prepare_SELECTquery'));
        /** @var $preparedStatement \TYPO3\CMS\Core\Database\PreparedStatement */
        $preparedStatement = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\PreparedStatement', $query, $from_table, array());
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
        if (strpos($query, 'SESSION') === false) {
            $error = $this->sql_error();
            if ($this->bDisplayOutput($error, $starttime, $endtime)) {
                $myName = is_array($dbgModes) ? ($dbgModes['name'] ? $dbgModes['name'] : __FILE__.':'.__LINE__ ) : 'TYPO3_DB->sql_query';
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
        if ($error != '' || $this->ticker == '' || $this->ticker <= $endtime - $starttime) {
            $result = true;
        } else {
            $result = false;
        }
        return $result;
    }

    public function enableByTable ($tablePart, $bErrorCase, &$bEnable, &$bDisable)
    {
        if ($tablePart != '') {
            $partArray = explode('.', $tablePart);
            $lowerTable = strtolower($partArray['0']);
            $aliasArray = explode(' ', $lowerTable);
            $lowerTable = $aliasArray['0'];
            if ($lowerTable == '') {
                $lowerTable = $aliasArray['1'];
            }
            $keyWords = array('select', 'transaction', 'commit', 'update', 'delete');

            if (!in_array($lowerTable, $keyWords)) {
                if (
                    $this->dbgExcludeTable[$lowerTable]
                ) {
                    $bDisable = true;
                } else if (
                    (
                        isset($GLOBALS['TCA'][$lowerTable]) &&
                        $this->dbgTca
                    ) ||
                    $this->dbgTable[$lowerTable]
                ) { // is this a table name inside of TYPO3?
                    $bEnable = true;
                } else if (
                    !$bErrorCase &&
                    $this->dbgTca
                ) { // an error message is also shown if the $GLOBALS['TCA'] is not loaded in the FE
                    $bDisable = true;
                }
            }
        }
    }

    /**
    * getEnableDisable function: determines if a table is enabled or disabled
    *
    * @param	string		SQL part which should contain a table name
    * @param	boolean		set in an error of the SQL
    * @param	boolean		output: table is enabled
    * @param	boolean		output: table is disabled
    * @return	void
    */
    public function getEnableDisable ($sqlpart, $bErrorCase, &$bEnable, &$bDisable)
    {
        $bEnable = false;
        $bDisable = false;
        $x = strtok($sqlpart, ',=');

        while ($x !== false) {
            self::enableByTable($x, $bErrorCase, $bEnable, $bDisable);
            $x = strtok(',=');
        }

        if ($bEnable) {	// an explicitely set table overrides the excluded tables
            $bDisable = false;
        }
    }

    /**
    * generates a debug backtrace line
    *
    * @return	string	file name and line numbers ob the backtrace
    */
    public function getTraceLine ()
    {
        $trail = debug_backtrace(false);

        $debugTrail1 = $trail[2];
        $debugTrail2 = $trail[3];
        $debugTrail3 = $trail[4];

        $result =
            basename($debugTrail3['file']) . '#' . $debugTrail3['line'] . '->' . $debugTrail3['function'] . ' // ' .
            basename($debugTrail2['file']) . '#' . $debugTrail2['line'] . '->' . $debugTrail2['function'] . ' // ' .
            basename($debugTrail1['file']) . '#' . $debugTrail1['line'] . '->' . $debugTrail1['function'];

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
            get_class($this->getDatabaseHandle()) == 'mysqli' &&
            $table != '' &&
            strpos($table, '_mm') === false &&
            strpos($table, 'cache') === false &&
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
    public function myDebug ($func, $error, $mode, $table, $query, $resultSet, $microseconds)
    {
        $debugArray = array('function/mode'=>'Pg' . $GLOBALS['TSFE']->id . ' ' . $func . '(' . $table . ') - ',  'SQL query' => $query);
        $feUid = 0;

        if (count($this->dbgFeUser) && is_object($GLOBALS['TSFE']->fe_user)) {
            if (is_array($GLOBALS['TSFE']->fe_user->user)) {
                $feUid = intval($GLOBALS['TSFE']->fe_user->user['uid']);
            }
        }

        if ($table != '') {
            $sqlPart = $table;
        } else {
            $sqlPart = $query;
        }

        if ($error) {
            if (!intval($this->dbgConf['DISABLE_ERRORS'])) {
                $this->getEnableDisable(
                    $sqlPart,
                    true,
                    $bEnable,
                    $bDisable
                );

                if (
                    (
                        !$bDisable ||
                        $this->dbgTca
                    ) &&
                    (
                        $this->dbgTable['all'] ||
                        $bEnable ||
                        !$table
                    )
                ) {
                    $debugArray['function/mode'] .= $this->getTraceLine();
                    $debugArray['SQL ERROR ='] = $error;
                    if ($this->debug_lastBuiltQuery != '') {
                        $debugArray['lastBuiltQuery'] = $this->debug_lastBuiltQuery;
                    } else {
                        $debugArray['lastBuiltQuery'] = $query;
                    }

                    $debugArray['debug_backtrace'] =  DebugUtility::debugTrail();
                    $debugArray['miliseconds'] = round($microseconds * 1000, 3);

                    if (
                        $this->dbgOutput == 'error_log' ||
                        $this->dbgTextformat
                    ) {
                        $debugOut = print_r($debugArray, true);
                    } else {
                        $debugOut = $debugArray;
                    }

                    if ($this->dbgOutput == 'error_log') {
                        error_log($debugOut);
                    } else {
                        $this->callDebugger(
                            $this->dbgOutput,
                            $debugOut
                        );
                    }
                }
            }
        } else {
            $this->getEnableDisable(
                $sqlPart,
                false,
                $bEnable,
                $bDisable
            );

            if (
                $this->dbgQuery[$mode] &&
                !$bDisable &&
                (
                    $this->dbgTable['all'] ||
                    $bEnable ||
                    !$table
                ) &&
                (
                    count($this->dbgFeUser) == 0 ||
                    $this->dbgFeUser[$feUid . '.']
                ) &&
                (
                    $this->dbgId[$GLOBALS['TSFE']->id . '.'] ||
                    $this->dbgId['0.']
                )
            ) {
                $debugArray['function/mode'] .= $this->getTraceLine();

                if ($mode == 'SELECT') {
                    $debugArray['num_rows()'] = $this->sql_num_rows($resultSet);
                }

                if ($mode == 'UPDATE' || $mode == 'DELETE' || $mode == 'INSERT') {
                    $debugArray['affected_rows()'] = $this->sql_affected_rows();
                }

                if ($mode == 'INSERT') {
                    $insertId = $this->getLastInsertId($table);
                    $debugArray['insert_id()'] = $insertId;
                }

                if ($mode == 'SQL') {
                    if (is_resource($resultSet)) {
                        $debugArray['num_rows()'] = $this->sql_num_rows($resultSet);
                    }
                    $debugArray['affected_rows()'] = $this->sql_affected_rows();
                    $insertId = $this->getLastInsertId($table);
                    $debugArray['insert_id()'] =  $insertId;
                }

                if ($this->dbgConf['BTRACE_SQL']) {

                    $debugArray['debug_backtrace'] =  DebugUtility::debugTrail();
                }
                $debugArray['miliseconds'] = round($microseconds * 1000,3);
                $debugArray['------------'] = '';

                if ($this->dbgTextformat) {
                    $debugOut = print_r($debugArray, true);
                } else {
                    $debugOut = $debugArray;
                }

                $this->callDebugger($this->dbgOutput, $debugOut);
            }
        }
    }

    public function callDebugger ($debugFunc, $debugOut)
    {
        try {
            if (
                $debugFunc == 'debug' &&
                is_object($GLOBALS['error']) &&
                @is_callable(array($GLOBALS['error'], 'debug'))
            ) {
                $GLOBALS['error']->debug($debugOut, 'SQL debug');
            } else if (function_exists($debugFunc) && is_callable($debugFunc)) {
                call_user_func($debugFunc, $debugOut, 'SQL debug');
            } else {
                DebugUtility::debug($debugOut);
            }
        }
        catch(Exception $e) {
            DebugUtility::debug($debugOut);
        }
    }
}
