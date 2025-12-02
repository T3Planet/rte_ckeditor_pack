<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class ImportWordFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'importWord' => [
                (new Field())
                    ->setName('Formatting')
                    ->setKey('formatting')
                    ->setType(FieldType::ARRAY)  // Array type to indicate nested fields
                    ->setValue([  // Nested fields as the value
                        (new Field())
                            ->setName('Resets')
                            ->setKey('resets')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'Inline' => 'inline',
                                'None' => 'none',
                            ]),

                        (new Field())
                            ->setName('Defaults')
                            ->setKey('defaults')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'Inline' => 'inline',
                                'None' => 'none',
                            ]),

                        (new Field())
                            ->setName('Styles')
                            ->setKey('styles')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'Inline' => 'inline',
                                'None' => 'none',
                            ]),

                        (new Field())
                            ->setName('Comments')
                            ->setKey('comments')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'None' => 'none',
                                'Full' => 'full',
                                'Basic' => 'basic',
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
                'library' => '@ckeditor/ckeditor5-import-word',
                'exports' => 'ImportWord',
            ],
        ];
    }
}
