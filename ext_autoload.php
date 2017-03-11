<?php

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('debug_mysql_db');
return array(
	'DatabaseConnection' => $extensionPath . 'Classes/Database/DatabaseConnection.php'
);

