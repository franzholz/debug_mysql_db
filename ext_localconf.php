<?php

if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$_EXTCONF = unserialize($_EXTCONF);    // unserializing the configuration so we can use it here:

if (isset($_EXTCONF) && is_array($_EXTCONF)) {

	$dbgMode = $_EXTCONF['TYPO3_MODE'] ? trim(strtoupper($_EXTCONF['TYPO3_MODE'])) : 'OFF';

	if (TYPO3_MODE == $dbgMode || $dbgMode == 'ALL') {

		$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\DatabaseConnection'] = array(
			'className' => 'Geithware\\DebugMysqlDb\\Database\\DatabaseConnection',
		);
	}
}

