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


use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * This implements a Doctrine SQL Query Logger for TYPO3
 */
class SqlQueryLogger implements SQLLogger, LoggerAwareInterface
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
    public $currentQuery = [];

    private $doctrineApi;
    
    
    public function __construct() {
        $this->doctrineApi = GeneralUtility::makeInstance(\Geithware\DebugMysqlDb\Api\DoctrineApi::class);
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        if (! $this->enabled) {
            return;
        }

        $this->start = microtime(true);
        $this->queryCount += 1;
        $this->currentQuery = ['sql' => $sql, 'params' => $params, 'types' => $types, 'executionMS' => 0];
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        if (! $this->enabled) {
            return;
        }

        $logData = [];
        $logData['miliseconds'] = round((microtime(true) - $this->start) * 1000, 3);

        $logData['sql'] = $this->doctrineApi->getExpandedQuery(
            $this->currentQuery['sql'],
            $this->currentQuery['params']
        );
        $this->logger->debug("SQL Debug", $logData);
    }
}


