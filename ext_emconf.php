<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Debug Mysql or other DB',
    'description' => 'Extends \\TYPO3\\CMS\\Core\\Database\\Connection and \\TYPO3\\CMS\\Typo3DbLegacy\\Database\\DatabaseConnection to show Errors and Debug-Messages. Debugging of sql-queries by debug and FileWriter.',
    'category' => 'misc',
    'version' => '1.9.6',
    'state' => 'stable',
    'author' => 'Franz Holzinger, formerly Stefan Geith',
    'author_email' => 'franz@ttproducts.de',
	'author_company' => 'jambage.com',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-14.3.99',
        ],
        'suggests' => [
            'typo3db_legacy' => '1.2.0-1.4.99',
            'fh_debug' => '0.18.0-0.20.99',
        ],
    ]
];

