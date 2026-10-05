<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Form\Element;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
/**
 * Trait for custom asset and styling functionality in RichTextElement
 */
trait CustomAssetTrait
{
    /**
     * Add custom stylesheet files to the result array
     *
     * @param array $resultArray
     * @param array<string, mixed> $ckeditorConfiguration Final configuration of the rendered editor
     * @return array
     */
    protected function addCustomStylesheets(array $resultArray, array $ckeditorConfiguration = []): array
    {
        $resultArray['stylesheetFiles'][] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/editor.css';
        // Load separately: mid-file @import in editor.css is ignored by browsers.
        // Visual Editor already loads this via VisualEditorStylesMiddleware.
        $resultArray['stylesheetFiles'][] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/revision-viewer.css';

        // Decide per rendered editor: the first MathEquations row may belong to another preset.
        if ($this->editorUsesMathType($ckeditorConfiguration)) {
            $resultArray['stylesheetFiles'][] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/mathtype.css';
        }
        
        if ($this->isEditoria11yEnabled()) {
            $extPath = 'EXT:rte_ckeditor_pack/Resources/Public/JavaScript/Plugins/editoria11y/editoria11y.min.css';
            $absoluteWebPath = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($extPath));
            $tempElement = '<div id="editoria11y-config" data-css-path="' . htmlspecialchars($absoluteWebPath) . '" style="display: none;"></div>';
            $resultArray['html'] = $resultArray['html'] . $tempElement;
            $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@t3planet/RteCkeditorPack/editoria11y-integration.js');
        }
        return $resultArray;
    }

    /**
     * @param array<string, mixed> $ckeditorConfiguration
     */
    protected function editorUsesMathType(array $ckeditorConfiguration): bool
    {
        if (!empty($ckeditorConfiguration['mathTypeParameters'])) {
            return true;
        }

        foreach ((array)($ckeditorConfiguration['importModules'] ?? []) as $importModule) {
            $moduleName = is_array($importModule) ? ($importModule['module'] ?? '') : $importModule;
            if (is_string($moduleName) && str_contains($moduleName, 'RteCkeditorPack/mathtype')) {
                return true;
            }
        }

        $toolbar = $ckeditorConfiguration['toolbar'] ?? [];
        if (!is_array($toolbar)) {
            return false;
        }
        $toolbarItems = [];
        array_walk_recursive($toolbar, static function ($value) use (&$toolbarItems): void {
            if (is_string($value)) {
                $toolbarItems[] = $value;
            }
        });

        return in_array('MathType', $toolbarItems, true) || in_array('ChemType', $toolbarItems, true);
    }

    /**
     * Check if Editoria11y is enabled
     *
     * @return bool
     */
    protected function isEditoria11yEnabled(): bool
    {
        return $this->isFeatureEnabled('Editoria11y');
    }

    /**
     * Check if a CKEditor Pack feature is enabled for any preset.
     */
    protected function isFeatureEnabled(string $configKey): bool
    {
        $featureRepository = GeneralUtility::makeInstance(FeatureRepository::class);
        $features = $featureRepository->findByConfigKey($configKey);
        $record = !empty($features) ? $features[0] : null;
        return $record ? $record->isEnable() : false;
    }
}
