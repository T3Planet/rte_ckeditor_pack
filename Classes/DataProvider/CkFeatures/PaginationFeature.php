<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaginationFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'pagination' => [
                (new Field())
                    ->setName('Page Height')
                    ->setKey('pageHeight')
                    ->setType(FieldType::INPUT)
                    ->setValue('29.7cm'),

                (new Field())
                    ->setName('Page Width')
                    ->setKey('pageWidth')
                    ->setType(FieldType::INPUT)
                    ->setValue('21cm'),

                (new Field())
                    ->setName('Margins')
                    ->setKey('pageMargins')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Margin top')
                            ->setKey('top')
                            ->setType(FieldType::INPUT)
                            ->setValue('20mm'),

                        (new Field())
                            ->setName('Margin bottom')
                            ->setKey('bottom')
                            ->setType(FieldType::INPUT)
                            ->setValue('20mm'),

                        (new Field())
                            ->setName('Margin Right')
                            ->setKey('right')
                            ->setType(FieldType::INPUT)
                            ->setValue('20mm'),

                        (new Field())
                            ->setName('Margin Left')
                            ->setKey('left')
                            ->setType(FieldType::INPUT)
                            ->setValue('20mm'),
                    ]),

            ],
        ];
    }

    private function translateLabel(string $key): string
    {
        return LocalizationUtility::translate($key, 'RteCkeditorPack') ?? '';
    }
}
