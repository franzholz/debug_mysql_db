<?php

namespace Geithware\DebugMysqlDb\Api;

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

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

use Geithware\DebugMysqlDb\Database\DatabaseConnection;
use Geithware\DebugMysqlDb\Database\DoctrineConnection;
use Geithware\DebugMysqlDb\Database\Legacy\Typo3DbLegacyConnection;



/**
* extension of TYPO3 mysql database debug
* This contains a debug extension for mysql-db calls based on a TYPO3 database Connection class
*
* @author	TYPO3 Community
* @author	Stefan Geith <typo3dev2020@geithware.de>
* @package TYPO3
* @subpackage debug_mysql_db
*/
class DebugApi implements SingletonInterface {
    protected $dbgConf = [];
    protected $dbgQuery = [];
    protected $dbgExcludeTable = [];
    protected $dbgId = [];
    protected $dbgFeUser = [];
    protected $dbgOutput = '';
    protected $dbgTable = [];
    protected $dbgTca = false;
    protected $dbgTextformat = false;
    protected $typo3Tables = [
        'cache_treelist',
        'fe_sessions'
    ];
    protected $id = '0';
    protected $feUid = 0;


    public function __construct (
        private readonly Context $context,
    ) {}

    public function init (
        array $dbgConf,
    )
    {
        $this->dbgConf = $dbgConf;
        $this->dbgOutput = $this->dbgConf['OUTPUT'] ?: '\\TYPO3\\CMS\\Utility\\DebugUtility::debug';
        $this->dbgTextformat = $this->dbgConf['TEXTFORMAT'] ?: false;
        $this->dbgTca = $this->dbgConf['TCA'] ?: false;
        $this->dbgTable['all'] = 0;

        if (
            strtoupper((string) $this->dbgConf['QUERIES']) == 'ALL' ||
            !trim((string) $this->dbgConf['QUERIES'])
        ) {
            $this->dbgQuery = [
                'ALL' => 1,
                'ALTER' => 1,
                'CREATE' => 1,
                'DROP' => 1,
                'GRANT' => 1,
                'REVOKE' => 1,
                'SQL' => 1,
                'SELECT' => 1,
                'INSERT' => 1,
                'UPDATE' => 1,
                'DELETE'=>1,
                'FETCH' => 1,
                'FIRSTROW' => 1
            ];
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

        if (
            strtoupper((string) $this->dbgConf['TABLES']) == 'ALL' ||
            !trim((string) $this->dbgConf['TABLES'])
        ) {
            $this->dbgTable['all'] = 1;
        } else {
            $tmp = GeneralUtility::trimExplode(',', $this->dbgConf['TABLES']);
            $count = count($tmp);
            for ($i = 0; $i < $count; $i++) {
                $this->dbgTable[strtolower($tmp[$i])] = 1;
            }
        }

        if (
            ($this->dbgConf['EXCLUDETABLES'] != '') &&
            (
                $this->dbgTca ||
                count($this->dbgTable)
            )
        ) {
            $tmp = GeneralUtility::trimExplode(',', $this->dbgConf['EXCLUDETABLES']);
            $count = count($tmp);
            for ($i = 0; $i < $count; $i++) {
                $table = strtolower($tmp[$i]);
                if (!isset($this->dbgTable[$table])) {
                    $this->dbgExcludeTable[$table] = 1;
                }
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

        $this->feUid = intval($this->context->getPropertyFromAspect('frontend.user', 'id', ''));

    }

    public function debugTrail ($prependFileNames = false)
    {
        $trail = DebugUtility::debugTrail($prependFileNames);

        if (!empty($this->dbgConf['BTRACE_LIMIT'])) {
            $search = '// Geithware';
            $position = strpos($trail, $search);
            $trail = substr($trail, 0, $position - 1);
            $trail = substr($trail, -$this->dbgConf['BTRACE_LIMIT']);
        }
        return $trail;
    }

    /**
    * Debug function: Outputs error if any
    *
    * @param	object		parent object
    * @param	string		Function calling debug()
    * @param	string		error text
    * @param	string		mode
    * @param	string		table name
    * @param	string		SQL query
    * @param	integer	    count of affected rows
    * @param	integer	    insertion id
    * @param	string		consumed time in microseconds
    * @return	void
    */
    public function myDebug ($pObj, $func, $error, $mode, $table, $query, $affectedRows, $insertId, $microseconds): void
    {
        if ($table != '') {
            $sqlPart = $table;
        } else {
            $sqlPart = $query;
        }

        $pageInformation = $this->getRequest()->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            $this->id = $pageInformation->getId();
        }

        $debugArray = ['function/mode'=>'Pg' . $this->id . ' ' . $func . '(' . $table . ') - ',  'SQL query' => $query];

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
                        !empty($this->dbgTable['all']) ||
                        $bEnable ||
                        !$table
                    )
                ) {
                    $debugArray['function/mode'] .= $this->getTraceLine();
                    $debugArray['SQL ERROR ='] = $error;
                    $debugArray['lastBuiltQuery'] = $query;

                    if ($this->dbgConf['BTRACE_SQL']) {
                        $debugArray['debug_backtrace'] =  $this->debugTrail();
                    }
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
                            $debugOut,
                            true
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
                !empty($this->dbgQuery[$mode]) &&
                !$bDisable &&
                (
                    !empty($this->dbgTable['all']) ||
                    $bEnable ||
                    !$table
                ) &&
                (
                    count($this->dbgFeUser) == 0 ||
                    !empty($this->dbgFeUser[$this->feUid . '.'])
                ) &&
                (
                    isset($this->dbgId[$this->id . '.']) &&
                    $this->dbgId[$this->id . '.'] ||
                    isset($this->dbgId['0.']) &&
                    $this->dbgId['0.']
                )
            ) {
                $debugArray['function/mode'] .= $this->getTraceLine();

                if ($mode == 'SELECT') {
                    $debugArray['num_rows()'] = $affectedRows;
                } else {
                    $debugArray['affected_rows()'] = $affectedRows;
                }

                if ($insertId) {
                    $debugArray['insert_id()'] = $insertId;
                }

                if ($this->dbgConf['BTRACE_SQL']) {
                    $debugArray['debug_backtrace'] =  $this->debugTrail();
                }
                $debugArray['miliseconds'] = round($microseconds * 1000, 3);
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

    public function callDebugger ($debugFunc, $debugOut, $error = false): void
    {
        try {
            if (
                $debugFunc == 'debug' &&
                isset($GLOBALS['error']) &&
                is_object($GLOBALS['error']) &&
                @is_callable([$GLOBALS['error'], 'debug'])
            ) {
                if (!empty($error)) {
                    $GLOBALS['error']->debug($error, 'DebugApi::callDebugger $error');
                }
                $GLOBALS['error']->debug(
                    $debugOut,
                    'SQL debug' . ($error ? '*ERROR*' : ''),
                    ($error ? 'F' : null)
                );
            } else if (function_exists($debugFunc) && is_callable($debugFunc)) {
                call_user_func($debugFunc, $debugOut, 'SQL debug');
            } else {
                DebugUtility::debug($debugOut);
            }
        }
        catch(Exception) {
            DebugUtility::debug($debugOut);
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
    public function getEnableDisable ($sqlpart, $bErrorCase, &$bEnable, &$bDisable): void
    {
        $bEnable = false;
        $bDisable = false;
        if (str_contains((string) $sqlpart, 'table not found')) {
            $bEnable = true;
            return;
        }
        $sqlpart = preg_replace('/[`"\'?*()]*(:dcValue[0-9]*)*/', '', (string) $sqlpart);
        $strtokString = ',=<> ';
        $x = strtok($sqlpart, $strtokString);

        while ($x !== false) {
            $this->enableByTable($x, $bErrorCase, $bEnable, $bDisable);
            $x = strtok($strtokString);
        }

        if ($bEnable) {	// an explicitly set table overrides the excluded tables
            $bDisable = false;
        }
    }

    public function enableByTable ($tablePart, $bErrorCase, &$bEnable, &$bDisable): void
    {
        $tablePart = trim ((string) $tablePart);

        if (strlen($tablePart) > 1) {
            $partArray = explode('.', $tablePart);
            $lowerTable = strtolower($partArray['0']);
            $aliasArray = explode(' ', $lowerTable);
            $lowerTable = $aliasArray['0'];
            if ($lowerTable == '') {
                $lowerTable = $aliasArray['1'];
            }
            $lowerTable = trim($lowerTable);
            $keyWords = ['select', 'transaction', 'commit', 'update', 'delete', 'from', 'where', 'order', 'by', 'sorting', 'desc', 'insert', 'into', 'set', 'group', 'and', 'or'];

            if (
                !in_array($lowerTable, $keyWords) &&
                !MathUtility::canBeInterpretedAsInteger($lowerTable)
            ) {
                if (
                    isset($this->dbgExcludeTable[$lowerTable]) &&
                    $this->dbgExcludeTable[$lowerTable]
                ) {
                    $bDisable = true;
                } else if (
                    (
                        isset($GLOBALS['TCA'][$lowerTable]) &&
                        (
                            $this->dbgTca ||
                            $this->dbgTable['all']
                        )
                    ) ||
                    (
                        $this->dbgTable['all']  &&
                        in_array($lowerTable, $this->typo3Tables)
                    ) ||
                    isset($this->dbgTable[$lowerTable]) &&
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

    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}

