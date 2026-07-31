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
        $configuration = $this->ensureCollaborationMarkerProcessing($configuration);
        $configuration = $this->addCommentsEditorConfig($configuration);
        $configuration = $this->addDefaultSettings($configuration);

        return $configuration;
    }

    /**
     * Ensure comment/suggestion markers survive DataHandler processing and are not
     * owned by General HTML Support. Safe to call from richtext applyAll (no editor defaults).
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function ensureCollaborationMarkerProcessing(array $configuration): array
    {
        $configuration = $this->addProcessingSettings($configuration);

        return $this->disallowCollaborationMarkersInHtmlSupport($configuration);
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
        // `name` links comment/suggestion markers to stored threads (required for Non-RTC).
        $allowAttributes = ['name'];

        if (isset($configuration['processing']['allowTags'])) {
            $configuration['processing']['allowTags'] = array_merge($allowTags, $configuration['processing']['allowTags']);
        } else {
            $configuration['processing']['allowTags'] = $allowTags;
        }

        if (isset($configuration['processing']['allowAttributes'])) {
            $configuration['processing']['allowAttributes'] = array_values(array_unique(array_merge(
                $allowAttributes,
                (array)$configuration['processing']['allowAttributes']
            )));
        } else {
            $configuration['processing']['allowAttributes'] = $allowAttributes;
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
                if (!is_array($item)) {
                    continue;
                }
                $normalized = $this->normalizeBooleanStrings($item);
                $name = $normalized['name'] ?? null;
                // Skip empty / invalid names and collaboration markers (owned by Comments/TC).
                if ($name === null || $name === '' || $name === false) {
                    continue;
                }
                if (is_string($name) && in_array($name, ['comment-start', 'comment-end', 'suggestion-start', 'suggestion-end'], true)) {
                    continue;
                }
                $htmlAllow[] = $normalized;
            }
        }

        $normalizedHtmlSupport = $htmlConfiguration;
        if ($htmlAllow !== []) {
            $normalizedHtmlSupport['allow'] = $htmlAllow;
        } elseif (isset($normalizedHtmlSupport['allow'])) {
            // Drop empty/invalid allow entries (including collaboration markers).
            unset($normalizedHtmlSupport['allow']);
        }

        if (isset($normalizedHtmlSupport['allowEmpty']) && is_string($normalizedHtmlSupport['allowEmpty'])) {
            $normalizedHtmlSupport['allowEmpty'] = array_values(array_filter(
                array_map('trim', explode(',', $normalizedHtmlSupport['allowEmpty'])),
                static fn (string $name): bool => $name !== ''
            ));
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
     * Keep comment/suggestion markers owned by Comments / Track Changes plugins.
     * If General HTML Support is allowed to handle these tags, CKEditor stores them as
     * generic HTML and the field value shows raw <comment-start>/<comment-end> markup.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function disallowCollaborationMarkersInHtmlSupport(array $configuration): array
    {
        $disallow = [
            ['name' => 'comment-start'],
            ['name' => 'comment-end'],
            ['name' => 'suggestion-start'],
            ['name' => 'suggestion-end'],
        ];
        $blockedNames = array_column($disallow, 'name');

        if (!isset($configuration['htmlSupport']) || !is_array($configuration['htmlSupport'])) {
            $configuration['htmlSupport'] = [];
        }
        if (!isset($configuration['editor']['config']) || !is_array($configuration['editor']['config'])) {
            $configuration['editor']['config'] = [];
        }
        if (
            !isset($configuration['editor']['config']['htmlSupport'])
            || !is_array($configuration['editor']['config']['htmlSupport'])
        ) {
            $configuration['editor']['config']['htmlSupport'] = [];
        }

        $targets = [
            &$configuration['htmlSupport'],
            &$configuration['editor']['config']['htmlSupport'],
        ];

        foreach ($targets as &$htmlSupport) {
            $existing = [];
            if (isset($htmlSupport['disallow']) && is_array($htmlSupport['disallow'])) {
                $existing = $htmlSupport['disallow'];
            }
            $names = [];
            foreach ($existing as $rule) {
                if (is_array($rule) && isset($rule['name'])) {
                    $names[(string)$rule['name']] = true;
                }
            }
            foreach ($disallow as $rule) {
                if (!isset($names[$rule['name']])) {
                    $existing[] = $rule;
                }
            }
            $htmlSupport['disallow'] = $existing;

            if (isset($htmlSupport['allow']) && is_array($htmlSupport['allow'])) {
                $htmlSupport['allow'] = array_values(array_filter(
                    $htmlSupport['allow'],
                    static function ($rule) use ($blockedNames): bool {
                        if (!is_array($rule) || !isset($rule['name'])) {
                            return true;
                        }

                        return !in_array((string)$rule['name'], $blockedNames, true);
                    }
                ));
            }
            if (isset($htmlSupport['allowEmpty']) && is_array($htmlSupport['allowEmpty'])) {
                $htmlSupport['allowEmpty'] = array_values(array_filter(
                    $htmlSupport['allowEmpty'],
                    static fn ($name): bool => !in_array((string)$name, $blockedNames, true)
                ));
            }
        }
        unset($htmlSupport);

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
