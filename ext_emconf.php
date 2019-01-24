<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Debug Mysql DB',
    'description' => 'Extends \\TYPO3\\CMS\\Core\\Database\\DatabaseConnection (former t3lib_db) to show Errors and Debug-Messages. Useful for viewing and debugging of sql-queries. Shows error messages if they occur. (For TYPO3 before 6.1 use the versions 0.5.x)',
    'category' => 'misc',
    'version' => '0.6.4',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearcacheonload' => 0,
    'author' => 'Stefan Geith / Franz Holzinger',
    'author_email' => 'franz@ttproducts.de',
    'author_company' => '',
    'constraints' => array(
        'depends' => array(
            'php' => '5.3.0-7.99.99',
            'typo3' => '6.1.0-8.99.99',
        ),
        'conflicts' => array(
            'dbal' => '0.0.0-0.0.0',
        ),
        'suggests' => array(
        ),
    )
);

