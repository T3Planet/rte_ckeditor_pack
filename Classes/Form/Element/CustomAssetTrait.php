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
     * @return array
     */
    protected function addCustomStylesheets(array $resultArray): array
    {
        $resultArray['stylesheetFiles'][] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/editor.css';
        // Load separately: mid-file @import in editor.css is ignored by browsers.
        // Visual Editor already loads this via VisualEditorStylesMiddleware.
        $resultArray['stylesheetFiles'][] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/revision-viewer.css';

        if ($this->isFeatureEnabled('MathEquations')) {
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
