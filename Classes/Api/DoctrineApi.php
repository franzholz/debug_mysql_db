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

    public function getExpandedQuery ($query, $params, $types)
    {
        $questionmarkMode =
        strpos((string) $query, '(?') ||
        strpos((string) $query, '= ?') ||
        strpos((string) $query, '=?');

        $parts = [];
        if ($questionmarkMode) {
            $parts = explode('?', (string) $query);
        }
        $expandedQuery = $query;
        $preparedValues = [];

        foreach ($params as $paramName => $value) {
            // 1. Sichere Typ-Ermittlung
            if (is_array($types) && isset($types[$paramName])) {
                $type = $types[$paramName];
            } else if (is_int($value)) {
                $type = Connection::PARAM_INT;
            } else if (is_array($value)) {
                $type = Connection::PARAM_STR_ARRAY; // Falls Array übergeben wird, nimm an es ist ein String-Array
            } else {
                $type = Connection::PARAM_STR; // Standard für Skalare ist String
            }

            // 2. Werte-Formatierung basierend auf Typ
            switch ($type) {
                case Connection::PARAM_INT_ARRAY:
                    if (is_array($value)) {
                        $value = implode(',', array_map('intval', $value));
                    } else {
                        $value = intval($value);
                    }
                    break;

                case Connection::PARAM_STR_ARRAY:
                    if (is_array($value)) {
                        $newValueArray = [];
                        foreach ($value as $subValue) {
                            $newValueArray[] = '\'' . addslashes((string)$subValue) . '\'';
                        }
                        $value = implode(',', $newValueArray);
                    } else {
                        $value = '\'' . addslashes((string)$value) . '\'';
                    }
                    break;

                case Connection::PARAM_INT:
                    $value = intval($value);
                    break;

                case Connection::PARAM_STR:
                default:
                    if (is_array($value)) {
                        $value = current($value);
                    }
                    $value = '\'' . addslashes((string)$value) . '\'';
                    break;
            }

            // 3. Zuweisung je nach Platzhalter-Modus
            if ($questionmarkMode) {
                $preparedValues[] = (string)$value;
            } else {
                $expandedQuery = str_replace(':' . $paramName, (string)$value, (string) $expandedQuery);
            }
        }

        // 4. Reißverschluss-Verfahren für Fragezeichen-Queries
        if ($questionmarkMode && !empty($parts)) {
            $expandedQuery = '';
            foreach ($parts as $index => $part) {
                $expandedQuery .= $part;
                if (isset($preparedValues[$index])) {
                    $expandedQuery .= $preparedValues[$index];
                }
            }
        }

        return $expandedQuery;
    }
}

