<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Debug Mysql or other DB',
    'description' => 'Extends \\TYPO3\\CMS\\Core\\Database\\DatabaseConnection and \\TYPO3\\CMS\\Core\\Database\\Connection to show Errors and Debug-Messages. Debugging of sql-queries by debug and FileWriter.',
    'category' => 'misc',
    'version' => '1.9.0',
    'state' => 'stable',
    'author' => 'Franz Holzinger, formerly Stefan Geith',
    'author_email' => 'franz@ttproducts.de',
	'author_company' => 'jambage.com',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.4.99',
            'php' => '8.1.0-8.4.99',
        ],
        'suggests' => [
            'typo3db_legacy' => '1.2.0-1.2.99',
            'fh_debug' => '0.17.0-0.20.99',
        ],
    ]
];

