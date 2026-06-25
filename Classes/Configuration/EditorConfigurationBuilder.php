<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Configuration;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class EditorConfigurationBuilder
 *
 * Handles the configuration building for CKEditor important settings.
 */
class EditorConfigurationBuilder
{
    /**
     * Add important settings to the editor configuration
     *
     * @param array $configuration
     * @return array
     */
    public function addImportantSettings(array $configuration): array
    {
        $configuration = $this->addProcessingSettings($configuration);
        $configuration = $this->addCommentsEditorConfig($configuration);
        $configuration = $this->addDefaultSettings($configuration);

        return $configuration;
    }

    /**
     * Add processing settings (allowTags and allowTagsOutside)
     *
     * @param array $configuration
     * @return array
     */
    private function addProcessingSettings(array $configuration): array
    {
        $allowTags = ['comment-start', 'comment-end', 'suggestion-start', 'suggestion-end', 'wbr'];

        if (isset($configuration['processing']['allowTags'])) {
            $configuration['processing']['allowTags'] = array_merge($allowTags, $configuration['processing']['allowTags']);
        } else {
            $configuration['processing']['allowTags'] = $allowTags;
        }

        if (isset($configuration['processing']['allowTagsOutside'])) {
            $configuration['processing']['allowTagsOutside'] = array_merge(['img'], $configuration['processing']['allowTagsOutside']);
        } else {
            $configuration['processing']['allowTagsOutside'] = ['img'];
        }

        return $configuration;
    }

    /**
     * Add HTML support settings
     *
     * @param array $configuration
     * @return array
     */
    public function addHtmlSupportSettings(array $configuration, array $htmlConfiguration): array
    {
        $htmlAllow = [];
        if (isset($htmlConfiguration['allow']) && is_array($htmlConfiguration['allow'])) {
            foreach ($htmlConfiguration['allow'] as $item) {
                if (is_array($item)) {
                    $htmlAllow[] = $this->normalizeBooleanStrings($item);
                }
            }
        }

        $normalizedHtmlSupport = $htmlConfiguration;
        if ($htmlAllow !== []) {
            $normalizedHtmlSupport['allow'] = $htmlAllow;
        }

        return $this->mergeHtmlSupportIntoConfiguration($configuration, $normalizedHtmlSupport);
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $htmlSupport
     * @return array<string, mixed>
     */
    private function mergeHtmlSupportIntoConfiguration(array $configuration, array $htmlSupport): array
    {
        if (isset($configuration['editor']['config']) && is_array($configuration['editor']['config'])) {
            $configuration['editor']['config']['htmlSupport'] = $this->mergeHtmlSupportConfig(
                $configuration['editor']['config']['htmlSupport'] ?? [],
                $htmlSupport
            );
        }

        $configuration['htmlSupport'] = $this->mergeHtmlSupportConfig(
            $configuration['htmlSupport'] ?? [],
            $htmlSupport
        );

        return $configuration;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $htmlSupport
     * @return array<string, mixed>
     */
    private function mergeHtmlSupportConfig(array $existing, array $htmlSupport): array
    {
        if (isset($htmlSupport['allow']) && is_array($htmlSupport['allow'])) {
            $existing['allow'] = array_merge(
                $htmlSupport['allow'],
                $existing['allow'] ?? []
            );
        }

        if (isset($htmlSupport['allowEmpty'])) {
            $existing['allowEmpty'] = $htmlSupport['allowEmpty'];
        }

        if (isset($htmlSupport['disallow']) && is_array($htmlSupport['disallow'])) {
            $existing['disallow'] = array_merge(
                $htmlSupport['disallow'],
                $existing['disallow'] ?? []
            );
        }

        return $existing;
    }

    /**
     * Add default comments editor configuration to suppress warning
     *
     * @param array $configuration
     * @return array
     */
    private function addCommentsEditorConfig(array $configuration): array
    {
        if (!isset($configuration['comments']['editorConfig'])) {
            $configuration['comments']['editorConfig']['extraPlugins'] = [];
        }

        return $configuration;
    }

    /**
    * Add default settings if not available in custom preset
    *
    * @param array $configuration
    * @return array
    */
    private function addDefaultSettings(array $configuration): array
    {
        // Set default height if not available
        if (!isset($configuration['height'])) {
            $configuration['height'] = 300;
        }

        // Set default width if not available
        if (!isset($configuration['width'])) {
            $configuration['width'] = 'auto';
        }

        // Set default css
        if (!isset($configuration['contentsCss'])) {
            $configuration['contentsCss'] = ['EXT:rte_ckeditor/Resources/Public/Css/contents.css'];
        }

        return $configuration;
    }

    /**
     * Normalize string boolean values to real booleans
     * @param array $array
     * @return array
     */
    private function normalizeBooleanStrings(array $array): array
    {
        foreach (['classes', 'attributes'] as $key) {
            if (!isset($array[$key]) || !is_string($array[$key])) {
                continue;
            }
            
            $val = trim($array[$key]);
            $lower = strtolower($val);
            
            if ($lower === 'true' || $lower === 'false') {
                $array[$key] = $lower === 'true';
                continue;
            }

            if (strpos($val, ',') !== false && !str_starts_with($val, '{') && !str_starts_with($val, '[')) {
                $array[$key] = array_values(array_filter(array_map('trim', explode(',', $val))));
                continue;
            }

            if ($key === 'attributes') {
                if (str_starts_with($val, '{') || str_starts_with($val, '[')) {
                    $decoded = json_decode($val, true);
                    if ($decoded !== null) {
                        $array[$key] = $decoded;
                    }
                } elseif (preg_match('/^\s*(\w+)\s*:\s*(.+)$/', $val, $m)) {
                    $array[$key] = [trim($m[1]) => trim($m[2], "'\"")];
                }
            }
        }

        array_walk_recursive($array, function (&$value, $key) {
            if (!in_array($key, ['classes', 'attributes'], true) && is_string($value)) {
                $value = match (strtolower(trim($value))) {
                    'true' => true,
                    'false' => false,
                    default => $value,
                };
            }
        });

        return $array;
    }

}
