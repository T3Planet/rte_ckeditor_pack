<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

/**
 * Utility class for merging configuration arrays with duplicate removal
 */
class ConfigurationMergeUtility
{
    /**
     * Merge two configuration arrays recursively, removing duplicates
     *
     * @param array $yamlFeatureConfig Configuration from YAML
     * @param array $moduleConfiguration Configuration from database
     * @return array Merged configuration
     */
    public function mergeRecursiveDistinct(array $yamlFeatureConfig, array $moduleConfiguration): array
    {
        $merged = $yamlFeatureConfig;

        foreach ($moduleConfiguration as $key => $value) {
            if (isset($merged[$key]) && is_array($merged[$key]) && is_array($value)) {
                $isAssoc1 = array_keys($merged[$key]) !== range(0, count($merged[$key]) - 1);
                $isAssoc2 = array_keys($value) !== range(0, count($value) - 1);

                if ($isAssoc1 || $isAssoc2) {
                    // Recursive merge for associative arrays
                    $merged[$key] = $this->mergeRecursiveDistinct($merged[$key], $value);
                } else {
                    // Merge numeric arrays and remove duplicates
                    $merged[$key] = array_merge($merged[$key], $value);
                    $merged[$key] = $this->removeDuplicateModels($merged[$key]);
                }
            } else {
                $merged[$key] = $value;
            }
        }
        
        // Apply deduplication to nested numeric arrays in the final merged result
        $merged = $this->deduplicateNestedArrays($merged);
        
        return $merged;
    }

    /**
     * Recursively deduplicate numeric arrays in nested structures
     *
     * @param array $data Data to process
     * @return array Processed data with duplicates removed
     */
    private function deduplicateNestedArrays(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if it's a numeric array
                $isNumeric = array_keys($value) === range(0, count($value) - 1);
                if ($isNumeric && !empty($value)) {
                    // Deduplicate numeric arrays
                    $data[$key] = $this->removeDuplicateModels($value);
                } else {
                    // Recursively process nested associative arrays
                    $data[$key] = $this->deduplicateNestedArrays($value);
                }
            }
        }
        
        return $data;
    }

    /**
     * Remove duplicate items from array by comparing all fields (handles multidimensional arrays)
     *
     * @param array $items Array of items to deduplicate
     * @return array Array with duplicates removed
     */
    public function removeDuplicateModels(array $items): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($items as $item) {
            // Normalize the item by sorting keys recursively to handle different key orders
            $normalized = $this->normalizeArray($item);
            // Create a unique signature by serializing the normalized item
            // Use md5 hash for faster comparison and to handle large arrays
            $signature = md5(serialize($normalized));
            
            // Check if we've seen this exact item before using hash
            if (isset($seen[$signature])) {
                continue; // skip duplicate
            }
            
            // Track this signature and add item to unique array
            $seen[$signature] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * Normalize array by recursively sorting keys to handle different key orders
     * Also normalizes string values that should be arrays (like 'classes' field)
     *
     * @param mixed $data Data to normalize (array or other type)
     * @return mixed Normalized data
     */
    private function normalizeArray($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        
        // Check if it's a numeric array (preserve order) or associative (sort keys)
        $isNumeric = array_keys($data) === range(0, count($data) - 1);
        
        if (!$isNumeric) {
            // Sort keys for associative arrays only
            ksort($data);
        }
        
        // Recursively normalize nested arrays and normalize string values
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Special handling for 'classes' and 'options' arrays
                if ($key === 'classes' || $key === 'options') {
                    $data[$key] = $this->normalizeClassesOrOptions($value);
                } else {
                    $data[$key] = $this->normalizeArray($value);
                }
            } elseif (is_string($value) && ($key === 'classes' || $key === 'options')) {
                // Normalize string values for 'classes' and 'options' fields to arrays
                // Convert comma-separated strings to arrays
                $normalized = array_filter(array_map('trim', explode(',', $value)));
                $data[$key] = array_values($normalized);
            } elseif (is_string($value) && $value === '') {
                // Normalize empty strings to empty arrays for consistency
                $data[$key] = [];
            }
        }
        
        return $data;
    }

    /**
     * Normalize classes or options array - handle mixed string/array values
     *
     * @param array $value Array that may contain strings with commas
     * @return array Normalized array
     */
    private function normalizeClassesOrOptions(array $value): array
    {
        $normalized = [];
        
        foreach ($value as $item) {
            if (is_string($item)) {
                // If string contains comma, split it
                if (strpos($item, ',') !== false) {
                    $split = array_filter(array_map('trim', explode(',', $item)));
                    $normalized = array_merge($normalized, $split);
                } else {
                    $trimmed = trim($item);
                    if ($trimmed !== '') {
                        $normalized[] = $trimmed;
                    }
                }
            } elseif (is_array($item)) {
                // Recursively normalize nested arrays
                $normalized = array_merge($normalized, $this->normalizeClassesOrOptions($item));
            } elseif ($item !== null && $item !== '') {
                $normalized[] = $item;
            }
        }
        
        // Remove duplicates and reindex
        return array_values(array_unique($normalized));
    }

    /**
     * Sync toolbar items: add new items from YAML that are available but not in preset
     *
     * @param array $yamlToolbarItems Array of toolbar items from YAML configuration
     * @param string $presetToolbarItems Comma-separated string of toolbar items from preset
     * @return string Comma-separated string of merged toolbar items
     */
    public function syncToolBar(array $yamlToolbarItems, string $presetToolbarItems): string
    {
        // Convert preset toolbar items string to array
        $presetItems = !empty($presetToolbarItems) 
            ? array_map('trim', explode(',', $presetToolbarItems))
            : [];
        $yamlItems = array_filter($yamlToolbarItems, 'is_string');
        $newItems = array_diff($yamlItems, $presetItems);
        $mergedItems = array_merge($presetItems, $newItems);
        $mergedItems = array_filter($mergedItems, function($item) {
            return !empty(trim($item));
        });
        return implode(',', $mergedItems);
    }

    public function parseOptions($options): array {
        if (is_array($options)) {
            return array_values($options); // REMOVE original keys
        }

        // Convert string → array and reindex
        return array_values(array_filter(array_map('trim', explode(',', (string)$options))));
    }

    public function mergeOptionArrays(array ...$arrays): array {
        $merged = [];

        foreach ($arrays as $source) {
            foreach ($source as $key => $value) {

                $options = $this->parseOptions($value['options'] ?? []);

                if (!isset($merged[$key])) {
                    $merged[$key] = ['options' => $options];
                } else {
                    // merge + remove duplicates
                    $merged[$key]['options'] = array_unique(
                        array_merge($merged[$key]['options'], $options),
                        SORT_REGULAR
                    );
                }

                // ALWAYS remove all keys (0,1,2,…)
                $merged[$key]['options'] = array_values($merged[$key]['options']);
            }
        }

        return $merged;
    }

}

