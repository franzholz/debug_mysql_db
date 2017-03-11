<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Debug Mysql DB',
	'description' => 'Extends \\TYPO3\\CMS\\Core\\Database\\DatabaseConnection (former t3lib_db) to show Errors and Debug-Messages. Useful for viewing and debugging of sql-queries. Shows error messages if they occur. (For TYPO3 before 6.1 see versions 0.5.x)',
	'category' => 'misc',
	'shy' => 0,
	'version' => '0.6.1',
	'dependencies' => '',
	'conflicts' => 'dbal',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Stefan Geith / Franz Holzinger',
	'author_email' => 'franz@ttproducts.de',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'php' => '5.3.0-7.99.99',
			'typo3' => '6.1.0-7.99.99',
		),
		'conflicts' => array(
			'dbal' => '0.0.0-0.0.0',
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:7:{s:9:"ChangeLog";s:4:"dae2";s:16:"ext_autoload.php";s:4:"55c0";s:21:"ext_conf_template.txt";s:4:"b1f6";s:12:"ext_icon.gif";s:4:"8ea6";s:17:"ext_localconf.php";s:4:"c1c4";s:39:"Classes/Database/DatabaseConnection.php";s:4:"8887";s:14:"doc/manual.sxw";s:4:"70df";}',
	'suggests' => array(
	),
);

