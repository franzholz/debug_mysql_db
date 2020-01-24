<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Debug Mysql DB',
    'description' => 'Extends \\TYPO3\\CMS\\Core\\Database\\DatabaseConnection (former t3lib_db) to show Errors and Debug-Messages. Useful for viewing and debugging of sql-queries. Shows error messages if they occur. (For TYPO3 before 9 use the versions 0.6.x)',
    'category' => 'misc',
    'version' => '0.8.0',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearcacheonload' => 0,
    'author' => 'Franz Holzinger / Stefan Geith',
    'author_email' => 'franz@ttproducts.de',
	'author_company' => 'jambage.com',
    'constraints' => array(
        'depends' => array(
            'typo3' => '8.7.0-9.5.99',
        ),
        'suggests' => array(
            'typo3db_legacy' => '1.0.0-1.1.99',
            'fh_debug' => '0.8.0-0.9.99',
        ),
    )
);

