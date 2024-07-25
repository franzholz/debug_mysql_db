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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Database\Connection;



class DoctrineApi implements SingletonInterface {

    // mit QueryBuilder Methode ergänzen
    public function getExpandedQuery ($query, $params, $types)
    {
        $questionmarkMode =
            strpos((string) $query, '(?') ||
            strpos((string) $query, '= ?');
        $parts = [];
        $partsIndex = 0;
        if ($questionmarkMode) {
            $parts = explode('?', (string) $query);
        }
        $expandedQuery = $query;

        foreach ($params as $paramName => $value) {
            if (
                is_array($types) &&
                isset($types[$paramName])
            ) {
                $type = $types[$paramName];
            } else if (is_int($value)) {
                $type = Connection::PARAM_INT;
            } else if (is_string($value)) {
                $type = Connection::PARAM_STR;
            } else {
                $type = Connection::PARAM_STR_ARRAY;
            }

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
                            $newValueArray[] = '\'' . $subValue . '\'';
                        }
                        $value = implode(',', $newValueArray);
                    } else {
                        continue 2;
                    }
                    break;
                case Connection::PARAM_INT:
                    $value = intval($value);
                    break;
                case Connection::PARAM_STR:
                    if (is_array($value)) {
                        $value = current($value);
                    }
                    $value = '\'' . (string) $value . '\'';
                    break;
            }

            if ($questionmarkMode) {
                $parts[$partsIndex++] .= $value;
            } else {
                $expandedQuery = str_replace(':' . $paramName, $value, (string) $expandedQuery);
            }
        }

        if ($questionmarkMode) {
            $expandedQuery = implode('', $parts);
        }

        return $expandedQuery;
    }
}

