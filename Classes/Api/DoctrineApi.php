<?php

namespace Geithware\DebugMysqlDb\Api;

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

use Doctrine\DBAL\Connection;



class DoctrineApi implements \TYPO3\CMS\Core\SingletonInterface {

    // mit QueryBuilder Methode ergänzen
    public function getExpandedQuery ($query, $params) {
        $expandedQuery = $query;
        foreach ($params as $paramName => $value) {
            $type = $types[$paramName];
            switch ($type) {
                case Connection::PARAM_INT_ARRAY:
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    } else {
                        continue 2;
                    }
                    break;
                case Connection::PARAM_STR_ARRAY:
                    if (is_array($value)) {
                        $newValueArray = [];
                        foreach ($value as $subValue) {
                            $newValueArray[] = '\'' . $value . '\'';
                        }
                        $value = implode(',', $newValueArray);
                    } else {
                        continue 2;
                    }
                    break;
                case \TYPO3\CMS\Core\Database\Connection::PARAM_INT:
                    $value = intval($value);
                    break;
                case \TYPO3\CMS\Core\Database\Connection::PARAM_STR:
                    $value = '\'' . $value . '\'';
                    break;
            }
            $expandedQuery = str_replace(':' . $paramName, $value, $expandedQuery);
        }
        return $expandedQuery;
    }
}

