<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function () {
    $extensionConfiguration = array();

    if (
        defined('TYPO3_version') &&
        version_compare(TYPO3_version, '9.0.0', '>=')
    ) {
        $extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
        )->get('debug_mysql_db');
    } else { // before TYPO3 9
        $extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['debug_mysql_db']);
    }

    if (class_exists('TYPO3\\CMS\\Typo3DbLegacy\\Database\\DatabaseConnection')) {
        if (is_object($GLOBALS['TYPO3_DB'])) {
            $GLOBALS['TYPO3_DB']->__sleep();
        }

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Typo3DbLegacy\\Database\\DatabaseConnection'] = array(
            'className' => \Geithware\DebugMysqlDb\Database\Typo3DbLegacyDatabaseConnection::class
        );

        //**********************************************
        //*** copied from extension typo3db_legacy:
        //**********************************************

        require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('debug_mysql_db') . 'Classes/Api/DebugApi.php');
        require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('debug_mysql_db') . 'Classes/Database/Typo3DbLegacyDatabaseConnection.php');

        // Initialize database connection in $GLOBALS and connect
        $databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Geithware\DebugMysqlDb\Database\Typo3DbLegacyDatabaseConnection::class);
    
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
        } elseif (strpos($databaseHost, ':') > 0) {
            // @TODO: Find a way to handle this case in the install tool and drop this
            list($databaseHost, $databasePort) = explode(':', $databaseHost);
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
            $commandsAfterConnect = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
                LF,
                str_replace(
                    '\' . LF . \'',
                    LF,
                    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['initCommands']
                ),
                true
            );
            $databaseConnection->setInitializeCommandsAfterConnect($commandsAfterConnect);
        }

        $GLOBALS['TYPO3_DB'] = $databaseConnection;
        $GLOBALS['TYPO3_DB']->initialize();
    } else if (
        defined('TYPO3_version') &&
        version_compare(TYPO3_version, '9.0.0', '<')
    ) {
        $dbgMode = $extensionConfiguration['TYPO3_MODE'] ? trim(strtoupper($extensionConfiguration['TYPO3_MODE'])) : 'OFF';

        if (TYPO3_MODE == $dbgMode || $dbgMode == 'ALL') {

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\DatabaseConnection'] = array(
                'className' => \Geithware\DebugMysqlDb\Database\DatabaseConnection::class
            );
        }
    }

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\Connection'] = array(
        'className' => \Geithware\DebugMysqlDb\Database\DoctrineConnection::class
    );
    
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][\TYPO3\CMS\Core\Database\ConnectionPool::DEFAULT_CONNECTION_NAME]['wrapperClass'] = \Geithware\DebugMysqlDb\Database\DoctrineConnection::class;
});


