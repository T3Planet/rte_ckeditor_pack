<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Form\Element;

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
        return $resultArray;
    }
}
