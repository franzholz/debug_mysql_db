<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Debug Mysql or other DB',
    'description' => 'Extends \\TYPO3\\CMS\\Core\\Database\\DatabaseConnection (former t3lib_db) and \\TYPO3\\CMS\\Core\\Database\\Connection to show Errors and Debug-Messages. Useful for viewing and debugging of sql-queries. Shows error messages if they occur.',
    'category' => 'misc',
    'version' => '0.8.3',
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

