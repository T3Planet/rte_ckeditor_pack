<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Form\Element;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\RichTextElement as CoreElem;

class RichTextElement extends CoreElem
{
    use RevisionHistoryTrait;
    use CustomAssetTrait;
    public function render(): array
    {
        $languageService = $this->getLanguageService();

        $resultArray = $this->initializeResultArray();
        $parameterArray = $this->data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];

        $fieldId = $this->sanitizeFieldId($parameterArray['itemFormElName']);
        $itemFormElementName = $this->data['parameterArray']['itemFormElName'];

        $value = $this->data['parameterArray']['itemFormElValue'] ?? null;

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
        $html[] =   $fieldInformationHtml;
        $html[] =   '<div class="form-control-wrap">';
        $html[] =       '<div class="form-wizards-wrap">';
        $html[] =           '<div class="form-wizards-item-element">';
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
            $html[] = '<div class="form-wizards-item-bottom">';
            $html[] = $fieldWizardHtml;
            $html[] = '</div>';
        }
        $html[] =       '</div>';
        $html[] =   '</div>';

        $nullControlNameEscaped = htmlspecialchars('control[active][' . $this->data['tableName'] . '][' . $this->data['databaseRow']['uid'] . '][' . $this->data['fieldName'] . ']');

        $fullElement = $html;

        if ($this->hasNullCheckboxButNoPlaceholder()) {
            $checked = $value !== null ? ' checked="checked"' : '';
            $fullElement = [];
            $fullElement[] = '<div class="t3-form-field-disable"></div>';
            $fullElement[] = '<div class="form-check t3-form-field-eval-null-checkbox">';
            $fullElement[] =     '<input type="hidden" name="' . $nullControlNameEscaped . '" value="0" />';
            $fullElement[] =     '<input type="checkbox" class="form-check-input" name="' . $nullControlNameEscaped . '" id="' . $nullControlNameEscaped . '" value="1"' . $checked . ' />';
            $fullElement[] =     '<label class="form-check-label" for="' . $nullControlNameEscaped . '">';
            $fullElement[] =         $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.nullCheckbox');
            $fullElement[] =     '</label>';
            $fullElement[] = '</div>';
            $fullElement[] = implode(LF, $html);

        } elseif ($this->hasNullCheckboxWithPlaceholder()) {

            $checked = $value !== null ? ' checked="checked"' : '';
            // Note that we draw the raw placeholder from $config instead of $ckeditorConfiguration so it
            // contains the full HTML markup. $ckeditorConfiguration['placeholder'] has strip_tags() applied.
            // The full HTML is only emitted with htmlspecialchars(), and later parsed by CKEditor.
            // The HTML-stripped placeholder is used for the label of the nullable checkbox.

            $placeholder = trim((string)($ckeditorConfiguration['placeholder'] ?? ''));
            $defaultValue = '';
            $rawPlaceholder = trim((string)($config['placeholder'] ?? ''));
            if ($rawPlaceholder !== '') {
                $defaultValue = $rawPlaceholder;
            }
            if ($placeholder !== '') {
                $shortenedPlaceholder = GeneralUtility::fixed_lgd_cs($placeholder, 20);
                if ($placeholder !== $shortenedPlaceholder) {
                    $overrideLabel = sprintf(
                        $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.placeholder.override'),
                        '<span title="' . htmlspecialchars($placeholder) . '">' . htmlspecialchars($shortenedPlaceholder) . '</span>'
                    );
                } else {
                    $overrideLabel = sprintf(
                        $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.placeholder.override'),
                        htmlspecialchars($placeholder)
                    );
                }
            } else {
                $overrideLabel = $languageService->sL(
                    'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.placeholder.override_not_available'
                );
            }
            $placeholderCkeditorAttributes = GeneralUtility::implodeAttributes([
                'id' => $fieldId . '-placeholder-ckeditor5',
                'options' => GeneralUtility::jsonEncodeForHtmlAttribute([
                    ...$ckeditorConfiguration,
                    'readOnly' => true,
                ], false),
            ], true);

            $placeholderTextareaAttributes = GeneralUtility::implodeAttributes([
                'slot' => 'textarea',
                'id' => $fieldId . '-placeholder',
                'rows' => '18',
                'class' => 'form-control',
            ], true);

            $fullElement = [];
            $fullElement[] = '<div class="form-check t3js-form-field-eval-null-placeholder-checkbox">';
            $fullElement[] =     '<input type="hidden" name="' . $nullControlNameEscaped . '" value="0" />';
            $fullElement[] =     '<input type="checkbox" class="form-check-input" name="' . $nullControlNameEscaped . '" id="' . $nullControlNameEscaped . '" value="1"' . $checked . ' />';
            $fullElement[] =     '<label class="form-check-label" for="' . $nullControlNameEscaped . '">';
            $fullElement[] =         $overrideLabel;
            $fullElement[] =     '</label>';
            $fullElement[] = '</div>';
            $fullElement[] = '<div class="t3js-formengine-placeholder-placeholder">';
            $fullElement[] =    '<div class="form-control-wrap">';
            $fullElement[] =        '<typo3-rte-ckeditor-ckeditor5 ' . $placeholderCkeditorAttributes . '>';
            $fullElement[] =            '<textarea ' . $placeholderTextareaAttributes . '>';
            $fullElement[] =                htmlspecialchars($defaultValue);
            $fullElement[] =            '</textarea>';
            $fullElement[] =        '</typo3-rte-ckeditor-ckeditor5>';
            $fullElement[] =    '</div>';
            $fullElement[] = '</div>';
            $fullElement[] = '<div class="t3js-formengine-placeholder-formfield">';
            $fullElement[] =    implode(LF, $html);
            $fullElement[] = '</div>';
        }

        $fullElement = '<div class="formengine-field-item t3js-formengine-field-item">' . implode(LF, $fullElement) . '</div>';
        $resultArray['html'] = $this->wrapWithFieldsetAndLegend($fullElement);

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
