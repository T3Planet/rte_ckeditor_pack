<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ExportWordFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'exportWord' => [
                (new Field())
                    ->setName('Converter URL')
                    ->setKey('converterUrl')
                    ->setType(FieldType::INPUT)
                    ->setValue('https://docx-converter.cke-cs.com/v2/convert/html-docx')
                    ->setPlaceholder($this->translateLabel('field.placeholder.converterUrl')),

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
                    ->setPlaceholder($this->translateLabel('field.placeholder.exportWord.fileName')),

                (new Field())
                    ->setName('Converter Options')
                    ->setKey('converterOptions')
                    ->setType(FieldType::ARRAY)  // Array type to indicate nested fields
                    ->setValue([  // Nested fields as the value
                        (new Field())
                            ->setName('document')
                            ->setKey('document')
                            ->setType(FieldType::ARRAY)
                            ->setValue([
                                (new Field())
                                    ->setName('Orientation')
                                    ->setKey('orientation')
                                    ->setType(FieldType::SELECT)
                                    ->setValue([
                                        'Landscape' => 'landscape',
                                        'Portrait' => 'portrait',
                                    ]),
                                (new Field())
                                    ->setName('Size')
                                    ->setKey('size')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('Letter'),

                                (new Field())
                                    ->setName('Language')
                                    ->setKey('language')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('en'),

                                (new Field())
                                    ->setName('Margins')
                                    ->setKey('margins')
                                    ->setType(FieldType::ARRAY)
                                    ->setValue([
                                        (new Field())
                                            ->setName('Margin top (e.g., 10mm, 15px)')
                                            ->setKey('top')
                                            ->setType(FieldType::INPUT)
                                            ->setValue('20mm')
                                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                                        (new Field())
                                            ->setName('Margin bottom (e.g., 10mm, 15px)')
                                            ->setKey('bottom')
                                            ->setType(FieldType::INPUT)
                                            ->setValue('20mm')
                                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                                        (new Field())
                                            ->setName('Margin Right (e.g., 10mm, 15px)')
                                            ->setKey('right')
                                            ->setType(FieldType::INPUT)
                                            ->setValue('20mm')
                                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                                        (new Field())
                                            ->setName('Margin Left (e.g., 10mm, 15px)')
                                            ->setKey('left')
                                            ->setType(FieldType::INPUT)
                                            ->setValue('20mm')
                                            ->setPlaceholder($this->translateLabel('field.placeholder.marginSuffix')),

                                    ]),

                            ]),

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
                'library' => '@ckeditor/ckeditor5-export-word',
                'exports' => 'ExportWord',
            ],
        ];
    }

    private function translateLabel(string $key): string
    {
        return LocalizationUtility::translate($key, 'RteCkeditorPack') ?? '';
    }
}
