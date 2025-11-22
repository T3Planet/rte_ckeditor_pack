<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class MergeFieldsFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'mergeFields' => [
                (new Field())
                    ->setName('Definitions')
                    ->setKey('definitions')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue(
                        [
                            [
                                (new Field())
                                    ->setName('Group ID (must be unique value)')
                                    ->setKey('groupId')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('guest'),
                                (new Field())
                                    ->setName('Label')
                                    ->setKey('groupLabel')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('Guest'),
                                (new Field())
                                    ->setName('Definitions')
                                    ->setKey('definitions')
                                    ->setType(FieldType::INNERITERATIVE)
                                    ->setValue(
                                        [
                                            [
                                                (new Field())
                                                    ->setName('Group ID (must be unique value)')
                                                    ->setKey('id')
                                                    ->setType(FieldType::INPUT)
                                                    ->setValue('guestName'),
                                                (new Field())
                                                    ->setName('Label')
                                                    ->setKey('label')
                                                    ->setType(FieldType::INPUT)
                                                    ->setValue('Guest name'),
                                                (new Field())
                                                    ->setName('DefaultValue')
                                                    ->setKey('defaultValue')
                                                    ->setType(FieldType::INPUT)
                                                    ->setValue('Guest'),
                                            ],
                                            [
                                                (new Field())
                                                    ->setName('Group ID (must be unique value)')
                                                    ->setKey('id')
                                                    ->setType(FieldType::INPUT)
                                                    ->setValue('guestTitle'),
                                                (new Field())
                                                    ->setName('Label')
                                                    ->setKey('label')
                                                    ->setType(FieldType::INPUT)
                                                    ->setValue('Guest title'),
                                                (new Field())
                                                    ->setName('DefaultValue')
                                                    ->setKey('defaultValue')
                                                    ->setType(FieldType::INPUT)
                                                    ->setValue('Ms./Mr'),
                                            ],
                                        ]
                                    ),

                            ],

                        ]
                    ),
            ],
        ];
    }
}
