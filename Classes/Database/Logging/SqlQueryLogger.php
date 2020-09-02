<?php
namespace Geithware\DebugMysqlDb\Database\Logging;

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

use Doctrine\DBAL\Logging\SQLLogger;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;


use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * This implements a Doctrine SQL Query Logger for TYPO3
 */
class SqlQueryLogger implements SQLLogger, LoggerAwareInterface, \TYPO3\CMS\Core\SingletonInterface
{
   use LoggerAwareTrait;

    /**
     * simple toggle to enable/disable logging
     *
     * @var bool
     */
    public $enabled = true;

    /** @var float|null */
    public $start = null;

    /** @var int */
    public $queryCount = 0;

    /** @var array */
    public $lastQuery = [];

    /** @var array */
    public $currentQuery = [];

    protected $doctrineApi;

    /** @var int */
    protected $fileWriterMode;

    /** @var bool */
    protected $backTrace;

    public function __construct($fileWriterMode, $backTrace) {
        $this->doctrineApi = GeneralUtility::makeInstance(\Geithware\DebugMysqlDb\Api\DoctrineApi::class);
        $this->fileWriterMode = $fileWriterMode;
        $this->backTrace = $backTrace;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        if (! $this->enabled) {
            return;
        }

        $this->lastQuery = $this->currentQuery;
        $this->start = microtime(true);
        $this->queryCount += 1;
        $this->currentQuery = ['sql' => $sql, 'params' => $params, 'types' => $types, 'executionMS' => 0];
        if ($this->backTrace) {
            $this->currentQuery['debug_backtrace'] = \TYPO3\CMS\Core\Utility\DebugUtility::debugTrail();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        if (! $this->enabled) {
            return;
        }

        // Maybe no logger is instantiated in TYPO3 8.7 
        if (!($this->logger instanceof LoggerInterface)) {
            $this->setLogger(GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__));    
        }

        $paramString = null;
        if (is_array($this->currentQuery['params'])) {
            $paramString = implode('', $this->currentQuery['params']);
        }
        $dbalException = (strpos($paramString, 'DBALException') > 0 || strpos($paramString, 'DriverException') > 0);

        $queries = [];
        if ($dbalException) {
        // no logging has yet been done for a query interrupted by an exception
            $this->lastQuery['error'] = 'SQL error';
            $queries[] = $this->lastQuery;
        }
        $queries[] = $this->currentQuery;

        foreach($queries as $query) {
            $logData = [];
            if (!$dbalException) {
                $logData['miliseconds'] = round((microtime(true) - $this->start) * 1000, 3);
            }

            $logData['sql'] = $this->doctrineApi->getExpandedQuery(
                $query['sql'],
                $query['params'],
                $query['types']
            );

            if (isset($query['error'])) {
                $logData['error'] = $query['error'];
            }

            if (isset($this->currentQuery['debug_backtrace'])) {
                $logData['trace'] = $query['debug_backtrace'];
            }
            $this->logger->debug('SQL Debug', $logData);
        }
    }
}

