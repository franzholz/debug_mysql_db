<?php

namespace Geithware\DebugMysqlDb\Log\Writer;

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

use TYPO3\CMS\Core\Log\LogRecord;

 
/**
 * Log writer that writes the log records into a file.
 */
class FileWriter extends \TYPO3\CMS\Core\Log\Writer\FileWriter
{
    /**
     * Default log file path
     *
     * @var string
     */
    protected $defaultLogFileTemplate = '/log/database_%s.log';

    /** @var int */
    protected $mode;

    public function setMode ($param): void {
        $this->mode = $param;
    }

    public function getMode () {
        return $this->mode;
    }

    /**
     * Writes the log record
     *
     * @param LogRecord $record Log record
     * @return WriterInterface $this
     * @throws \RuntimeException
     */
    public function writeLog (LogRecord $record): void
    {
        $mode = $this->getMode();
        if ($mode == 1 || $mode == 3) {
            debug($record['data'], 'SQL LOGGER'); // keep this
        }

        unset($record['data']['trace']);

        if ($mode == 2 || $mode == 3) {
            parent::writeLog($record);
        }
    }
}

