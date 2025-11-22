<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Form\Element;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\RichTextElement as CoreElem;

class RichTextElementV12 extends CoreElem
{
    use RevisionHistoryTrait;
    use CustomAssetTrait;
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $resultArray['labelHasBeenHandled'] = true;
        $parameterArray = $this->data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];

        $fieldId = $this->sanitizeFieldId($parameterArray['itemFormElName']);
        $itemFormElementName = $this->data['parameterArray']['itemFormElName'];

        $value = $this->data['parameterArray']['itemFormElValue'] ?? '';

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $fieldControlResult = $this->renderFieldControl();
        $fieldControlHtml = $fieldControlResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        $this->rteConfiguration = $config['richtextConfiguration']['editor'] ?? [];
        $ckeditorConfiguration = $this->resolveCkEditorConfiguration();

        $ckeditorAttributes = GeneralUtility::implodeAttributes([
            'id' => $fieldId . 'ckeditor5',
            'options' => GeneralUtility::jsonEncodeForHtmlAttribute($ckeditorConfiguration, false),
        ], true);

        $textareaAttributes = GeneralUtility::implodeAttributes([
            'slot' => 'textarea',
            'id' => $fieldId,
            'name' => $itemFormElementName,
            'rows' => '18',
            'class' => 'form-control',
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
        ], true);

        $html = [];
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] =   $fieldInformationHtml;
        $html[] =   '<div class="form-control-wrap">';
        $html[] =       '<div class="form-wizards-wrap">';
        $html[] =           '<div class="form-wizards-element form-wizards-item-element">';
        $html[] =               '<typo3-rte-ckeditor-ckeditor5 ' . $ckeditorAttributes . '>';
        $html[] =                 '<textarea ' . $textareaAttributes . '>';
        $html[] =                   htmlspecialchars((string)$value);
        $html[] =                 '</textarea>';
        $html[] =               '</typo3-rte-ckeditor-ckeditor5>';

        if ($this->isRevisionHistoryEnabled($ckeditorConfiguration)) {
            $html[] = $this->renderRevisionHistoryTextarea($fieldId, $ckeditorConfiguration);
        }

        $html[] =           '</div>';
        if (!empty($fieldControlHtml)) {
            $html[] =           '<div class="form-wizards-item-aside form-wizards-item-aside--field-control">';
            $html[] =               '<div class="btn-group">';
            $html[] =                   $fieldControlHtml;
            $html[] =               '</div>';
            $html[] =           '</div>';
        }
        if (!empty($fieldWizardHtml)) {
            $html[] = '<div class="form-wizards-items-bottom">';
            $html[] = $fieldWizardHtml;
            $html[] = '</div>';
        }
        $html[] =       '</div>';
        $html[] =   '</div>';
        $html[] = '</div>';

        $resultArray['html'] = $this->wrapWithFieldsetAndLegend(implode(LF, $html));
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@typo3/rte-ckeditor/ckeditor5.js');

        $uiLanguage = $ckeditorConfiguration['language']['ui'];
        if ($this->translationExists($uiLanguage)) {
            $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@typo3/ckeditor5/translations/' . $uiLanguage . '.js');
        }

        $contentLanguage = $ckeditorConfiguration['language']['content'];
        if ($this->translationExists($contentLanguage)) {
            $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@typo3/ckeditor5/translations/' . $contentLanguage . '.js');
        }

        $resultArray = $this->addCustomStylesheets($resultArray);
        return $resultArray;
    }
}
