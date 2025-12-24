<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Configuration;

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
        $configuration = $this->addHtmlSupportSettings($configuration);
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
    private function addHtmlSupportSettings(array $configuration): array
    {
        $htmlSupport = [
            [
                'name' => '/^.*$/',
                'styles' => true,
                'attributes' => true,
                'classes' => true,
            ],
            [
                'name' => 'span',
                'styles' => true,
                'attributes' => true,
                'classes' => true,
            ],
            [
                'name' => 'p',
                'classes' => true,
                'attributes' => [
                    'pattern' => 'data-.+',
                ],
            ],
        ];

        if (isset($configuration['htmlSupport']['allow'])) {
            $configuration['htmlSupport']['allow'] = array_merge($htmlSupport, $configuration['htmlSupport']['allow']);
        } else {
            $configuration['htmlSupport']['allow'] = $htmlSupport;
        }

        return $configuration;
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
}
