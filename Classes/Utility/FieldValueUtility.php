<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class FieldValueUtility
{
    /**
     * Extract field values from Field objects to build configuration structure
     *
     * @param array $fields Array of Field objects organized by main key
     * @return array Configuration structure with extracted values
     */
    public static function extractFieldValues(array $fields): array
    {
        $result = [];
        
        foreach ($fields as $mainKey => $fieldGroup) {
            if (!is_array($fieldGroup)) {
                continue;
            }
            
            $result[$mainKey] = [];
            
            foreach ($fieldGroup as $field) {
                if (!is_object($field)) {
                    continue;
                }
                
                // Check if it's a Field object
                if ($field instanceof Field) {
                    $fieldKey = $field->getKey();
                    $fieldValue = $field->getValue();
                    $fieldType = $field->getType();
                    
                    // Handle ITERATIVE fields (arrays of Field objects)
                    if ($fieldType instanceof FieldType 
                        && $fieldType === FieldType::ITERATIVE
                        && is_array($fieldValue)
                    ) {
                        // Convert array of Field objects to array of plain arrays
                        $iterativeResult = [];
                        foreach ($fieldValue as $item) {
                            if (is_array($item)) {
                                $itemResult = [];
                                foreach ($item as $nestedField) {
                                    if ($nestedField instanceof Field) {
                                        $nestedKey = $nestedField->getKey();
                                        $nestedValue = $nestedField->getValue();
                                        // Recursively extract nested Field objects
                                        $itemResult[$nestedKey] = self::extractFieldValue($nestedValue);
                                    }
                                }
                                $iterativeResult[] = $itemResult;
                            }
                        }
                        $result[$mainKey][$fieldKey] = $iterativeResult;
                    } else {
                        // Handle simple field values
                        $result[$mainKey][$fieldKey] = self::extractFieldValue($fieldValue);
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Recursively extract values from Field objects or nested structures
     *
     * @param mixed $value
     * @return mixed
     */
    public static function extractFieldValue($value)
    {
        if (is_object($value)) {
            if ($value instanceof Field) {
                return self::extractFieldValue($value->getValue());
            }
            try {
                $reflection = new \ReflectionClass($value);
                // ReflectionClass::isEnum() is available in PHP 8.1+
                if (method_exists($reflection, 'isEnum') && $reflection->isEnum()) {
                    // For backed enums, return the value; for pure enums, return the name
                    try {
                        return $value->value;
                    } catch (\Error $e) {
                        return $value->name;
                    }
                }
            } catch (\ReflectionException $e) {}
            
            if (property_exists($value, 'value')) {
                return self::extractFieldValue($value->value);
            }
        }
        if (is_array($value)) {
            return array_map([self::class, 'extractFieldValue'], $value);
        }
        return $value;
    }
}

