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
        
        $extPath = 'EXT:rte_ckeditor_pack/Resources/Public/JavaScript/Plugins/editoria11y/editoria11y.min.css';
        $absoluteWebPath = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($extPath));
        $tempElement = '<div id="editoria11y-config" data-css-path="' . htmlspecialchars($absoluteWebPath) . '" style="display: none;"></div>';
        $resultArray['html'] = $resultArray['html'] . $tempElement;
        if ($this->isEditoria11yEnabled()) {
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
        $featureRepository = GeneralUtility::makeInstance(FeatureRepository::class);
        $features = $featureRepository->findByConfigKey('Editoria11y');
        $record = !empty($features) ? $features[0] : null;
        return $record ? $record->isEnable() : false;
    }
}
