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
                                    ->setName('Default Model ID')
                                    ->setKey('defaultModelId')
                                    ->setType(FieldType::SELECT)
                                    ->setValue([
                                        'Use Cloud Services Default' => '',
                                        'GPT-5' => 'gpt-5',
                                        'GPT-5 Mini' => 'gpt-5-mini',
                                        'GPT-4.1' => 'gpt-4.1',
                                        'GPT-4.1 Mini' => 'gpt-4.1-mini',
                                        'Claude 4.5 Sonnet' => 'claude-4-5-sonnet',
                                        'Claude 4.5 Haiku' => 'claude-4-5-haiku',
                                    ]),
                                (new Field())
                                    ->setName('Displayed Models')
                                    ->setKey('displayedModels')
                                    ->setType(FieldType::VALUE_LIST)
                                    ->setValue(['gpt', 'claude']),
                                (new Field())
                                    ->setName('Model Selector Always Visible')
                                    ->setKey('modelSelectorAlwaysVisible')
                                    ->setType(FieldType::BOOLEAN)
                                    ->setValue(false),
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
