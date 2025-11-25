<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class AIFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'ai' => [
                (new Field())
                    ->setName('Container Type')
                    ->setKey('container')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Type')
                            ->setKey('type')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'Overlay' => 'overlay',
                                'Sidebar' => 'sidebar'
                            ]),
                        (new Field())
                            ->setName('Side')
                            ->setKey('side')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'Left' => 'left',
                                'Right' => 'right'
                            ]),
                    ]),
                (new Field())
                    ->setName('Chat Configuration')
                    ->setKey('chat')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Models')
                            ->setKey('models')
                            ->setType(FieldType::ARRAY)
                            ->setValue([
                                (new Field())
                                    ->setName('Model Selector Always Visible')
                                    ->setKey('modelSelectorAlwaysVisible')
                                    ->setType(FieldType::BOOLEAN)
                                    ->setValue(false),
                            ]),
                        (new Field())
                            ->setName('Context')
                            ->setKey('context')
                            ->setType(FieldType::ARRAY)
                            ->setValue([
                                (new Field())
                                    ->setName('Document Context Enabled')
                                    ->setKey('document')
                                    ->setType(FieldType::ARRAY)
                                    ->setValue([
                                        (new Field())
                                            ->setName('Document Context Enabled')
                                            ->setKey('enabled')
                                            ->setType(FieldType::BOOLEAN)
                                            ->setValue(true),
                                    ]),
                                (new Field())
                                    ->setName('URLs Context Enabled')
                                    ->setKey('urls')
                                    ->setType(FieldType::ARRAY)
                                    ->setValue([
                                        (new Field())
                                            ->setName('URLs Context Enabled')
                                            ->setKey('enabled')
                                            ->setType(FieldType::BOOLEAN)
                                            ->setValue(true),
                                    ]),
                                (new Field())
                                    ->setName('Files Context Enabled')
                                    ->setKey('files')
                                    ->setType(FieldType::ARRAY)
                                    ->setValue([
                                        (new Field())
                                            ->setName('Files Context Enabled')
                                            ->setKey('enabled')
                                            ->setType(FieldType::BOOLEAN)
                                            ->setValue(true),
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
                'library' => '@t3planet/RteCkeditorPack/ai-sidebar',
                'exports' => 'AISidebar'
            ],
            [
                'library' => '@ckeditor/ckeditor5-cloud-services',
                'exports' => 'CloudServices'
            ],
            [
                'library' => '@ckeditor/ckeditor5-ai',
                'exports' => 'AIChat,AIEditorIntegration,AIQuickActions,AIReviewMode'
            ],
        ];
    }
}
