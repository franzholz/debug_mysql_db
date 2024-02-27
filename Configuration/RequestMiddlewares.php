<?php

use Geithware\DebugMysqlDb\Middleware\Bootstrap;
return [
    'backend' => [
        'geithware/debug-mysql-db/preprocessing' => [
            'target' => Bootstrap::class,
            'description' => 'The global error object ($GLOBALS[\'TYPO3_DB\']) or the ConnectionPool wrapperClass must be set and initialized.',
            'before' => [
                'typo3/cms-backend/locked-backend'
            ],
        ]
    ],
    'frontend' => [
        'geithware/debug-mysql-db/preprocessing' => [
            'target' => Bootstrap::class,
            'description' => 'The global error object ($GLOBALS[\'error\']) must be set and initialized.',
            'before' => [
                'typo3/cms-frontend/maintenance-mode'
            ],
        ]
    ]
];

