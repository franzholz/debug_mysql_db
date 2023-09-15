<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Debug Mysql or other DB',
    'description' => 'Extends \\TYPO3\\CMS\\Core\\Database\\DatabaseConnection and \\TYPO3\\CMS\\Core\\Database\\Connection to show Errors and Debug-Messages. Debugging of sql-queries by debug and FileWriter.',
    'category' => 'misc',
    'version' => '1.3.2',
    'state' => 'stable',
    'uploadfolder' => 0,
    'clearcacheonload' => 0,
    'author' => 'Franz Holzinger, formerly Stefan Geith',
    'author_email' => 'franz@ttproducts.de',
	'author_company' => 'jambage.com',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
        ],
        'suggests' => [
            'typo3db_legacy' => '1.0.0-1.1.99',
            'fh_debug' => '0.12.0-0.14.99',
        ],
    ]
];

