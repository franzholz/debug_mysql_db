<?php

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

namespace Geithware\DebugMysqlDb\Api;

use Psr\Http\Message\ServerRequestInterface;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

use Geithware\DebugMysqlDb\Database\Typo3DbLegacyConnection;
use Geithware\DebugMysqlDb\Log\Writer\FileWriter;



/**
 * Components for the Debug
 */
class BootstrapApi
{
    /**
     * initialize the global debug object
     * @param ServerRequestInterface $request
     * @param it $requestEmpty
     * @return ResponseInterface
     */
    public function init(ServerRequestInterface $request, $requestEmpty = false): void
    {
        $extensionKey = 'debug_mysql_db';
        if (!isset($GLOBALS['TYPO3_REQUEST'])) {
                $GLOBALS['TYPO3_REQUEST'] = $request;
        }

        $extensionConfiguration = GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
        )->get($extensionKey);

        if (class_exists(\TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection::class)) {
            if (is_object($GLOBALS['TYPO3_DB'])) {
                $GLOBALS['TYPO3_DB']->__sleep();
            }

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection::class] = [
                'className' => Typo3DbLegacyConnection::class
            ];

            //**********************************************
            //*** copied from extension typo3db_legacy:
            //**********************************************

            require_once(ExtensionManagementUtility::extPath($extensionKey) . 'Classes/Api/DebugApi.php');
            require_once(ExtensionManagementUtility::extPath($extensionKey) . 'Classes/Database/Typo3DbLegacyConnection.php');
            
            // Initialize database connection in $GLOBALS and connect
            $databaseConnection = GeneralUtility::makeInstance(Typo3DbLegacyConnection::class);
            
            $databaseConnection->setDatabaseName(
                $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] ?? ''
            );
            $databaseConnection->setDatabaseUsername(
                $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] ?? ''
            );
            $databaseConnection->setDatabasePassword(
                $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] ?? ''
            );
            
            $databaseHost = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] ?? '';
            if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'])) {
                $databaseConnection->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']);
            } elseif (strpos((string) $databaseHost, ':') > 0) {
                // @TODO: Find a way to handle this case in the install tool and drop this
                [$databaseHost, $databasePort] = explode(':', (string) $databaseHost);
                $databaseConnection->setDatabasePort($databasePort);
            }
            
            if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['unix_socket'])) {
                $databaseConnection->setDatabaseSocket(
                    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['unix_socket']
                );
            }
            $databaseConnection->setDatabaseHost($databaseHost);
            $databaseConnection->debugOutput = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sqlDebug'] ?? false;
            
            if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['persistentConnection'])
                && $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['persistentConnection']
            ) {
                $databaseConnection->setPersistentDatabaseConnection(true);
            }
            
            $isDatabaseHostLocalHost = in_array($databaseHost, ['localhost', '127.0.0.1', '::1'], true);
            if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverOptions'])
                && $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverOptions'] & MYSQLI_CLIENT_COMPRESS
                && !$isDatabaseHostLocalHost
            ) {
                $databaseConnection->setConnectionCompression(true);
            }
            
            if (!empty($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['initCommands'])) {
                $commandsAfterConnect = GeneralUtility::trimExplode(
                    LF,
                    str_replace(
                        '\' . LF . \'',
                        LF,
                        (string) $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['initCommands']
                    ),
                    true
                );
                $databaseConnection->setInitializeCommandsAfterConnect($commandsAfterConnect);
            }

            $GLOBALS['TYPO3_DB'] = $databaseConnection;
            $GLOBALS['TYPO3_DB']->initialize();
        }
        
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Database\Connection::class] = [
            'className' => \Geithware\DebugMysqlDb\Database\DoctrineConnection::class
        ];
        
        $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][\TYPO3\CMS\Core\Database\ConnectionPool::DEFAULT_CONNECTION_NAME]['wrapperClass'] = \Geithware\DebugMysqlDb\Database\DoctrineConnection::class;
        
        if ($extensionConfiguration['FILEWRITER']) {
            $GLOBALS['TYPO3_CONF_VARS']['LOG']['Geithware']['DebugMysqlDb'] = [
                'writerConfiguration' => [
                    LogLevel::DEBUG => [
                        \Geithware\DebugMysqlDb\Log\Writer\FileWriter::class => [
                            'mode' => $extensionConfiguration['FILEWRITER']
                        ]
                    ]
                ],
            ];
        }
    }
}
