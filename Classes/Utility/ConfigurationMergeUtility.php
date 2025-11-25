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

        return $merged;
    }

    /**
     * Remove duplicate items from array based on 'model' field
     *
     * @param array $items Array of items to deduplicate
     * @return array Array with duplicates removed
     */
    public function removeDuplicateModels(array $items): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($items as $item) {
            if (isset($item['model'])) {
                if (in_array($item['model'], $seen, true)) {
                    continue; // skip duplicate
                }
                $seen[] = $item['model'];
            }
            if (isset($item['languageCode'])) {
                if (in_array($item['languageCode'], $seen, true)) {
                    continue; // skip duplicate
                }
                $seen[] = $item['languageCode'];
            }
            $unique[] = $item;
        }

        return $unique;
    }
}

