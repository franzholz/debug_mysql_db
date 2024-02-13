<?php

namespace Geithware\DebugMysqlDb\Middleware;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use TYPO3\CMS\Core\Utility\GeneralUtility;

use Geithware\DebugMysqlDb\Api\BootstrapApi;

/**
 * Initializes the global ERROR object before processing a request for the TYPO3 Frontend.
 *
 */
class Bootstrap implements MiddlewareInterface
{
    /**
     * Hook to initialze the error object
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $api = GeneralUtility::makeInstance(BootstrapApi::class);
        $api->init($request);

        return $handler->handle($request);
    }
}
