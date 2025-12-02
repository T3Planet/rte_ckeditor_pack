<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ExportPdfFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'exportPdf' => [
                (new Field())
                    ->setName('Style sheets')
                    ->setKey('stylesheets')
                    ->setType(FieldType::VALUE_LIST)
                    ->setValue([]),

                (new Field())
                    ->setName('File Name')
                    ->setKey('fileName')
                    ->setType(FieldType::INPUT)
                    ->setValue('')
                    ->setPlaceholder($this->translateLabel('field.placeholder.exportPdf.fileName')),

                (new Field())
                    ->setName('Converter URL')
                    ->setKey('converterUrl')
                    ->setType(FieldType::INPUT)
                    ->setValue('https://pdf-converter.cke-cs.com/v1/convert')
                    ->setPlaceholder($this->translateLabel('field.placeholder.converterUrl')),

                (new Field())
                    ->setName('Converter Options')
                    ->setKey('converterOptions')
                    ->setType(FieldType::ARRAY)  // Array type to indicate nested fields
                    ->setValue([  // Nested fields as the value
                        (new Field())
                            ->setName('Format')
                            ->setKey('format')
                            ->setType(FieldType::INPUT)
                            ->setValue('A4'),

                        (new Field())
                            ->setName('Margin top (e.g., 10mm, 15px)')
                            ->setKey('margin_top')
                            ->setType(FieldType::INPUT)
                            ->setValue('0mm')
                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                        (new Field())
                            ->setName('Margin bottom (e.g., 10mm, 15px)')
                            ->setKey('margin_bottom')
                            ->setType(FieldType::INPUT)
                            ->setValue('0mm')
                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                        (new Field())
                            ->setName('Margin Right (e.g., 10mm, 15px)')
                            ->setKey('margin_right')
                            ->setType(FieldType::INPUT)
                            ->setValue('0mm')
                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                        (new Field())
                            ->setName('Margin Left (e.g., 10mm, 15px)')
                            ->setKey('margin_left')
                            ->setType(FieldType::INPUT)
                            ->setValue('0mm')
                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                        (new Field())
                            ->setName('Page Orientation')
                            ->setKey('page_orientation')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'Landscape' => 'landscape',
                                'Portrait' => 'portrait',
                            ]),

                        (new Field())
                            ->setName('Header html')
                            ->setKey('header_html')
                            ->setType(FieldType::TEXTAREA)
                            ->setValue(''),

                        (new Field())
                            ->setName('Footer html')
                            ->setKey('footer_html')
                            ->setType(FieldType::TEXTAREA)
                            ->setValue(''),

                        (new Field())
                            ->setName('Header and Footer CSS')
                            ->setKey('header_and_footer_css')
                            ->setType(FieldType::TEXTAREA)
                            ->setValue(''),
                    ]),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-cloud-services',
                'exports' => 'CloudServices',
            ],
            [
                'library' => '@ckeditor/ckeditor5-export-pdf',
                'exports' => 'ExportPdf',
            ],
        ];
    }

    private function translateLabel(string $key): string
    {
        return LocalizationUtility::translate($key, 'RteCkeditorPack') ?? '';
    }
}
