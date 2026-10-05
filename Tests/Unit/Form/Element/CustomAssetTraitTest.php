<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Form\Element;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Form\Element\CustomAssetTrait;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class CustomAssetTraitTest extends BaseTestCase
{
    private const MATHTYPE_CSS = 'EXT:rte_ckeditor_pack/Resources/Public/Css/mathtype.css';

    private function createSubject(): object
    {
        return new class () {
            use CustomAssetTrait;

            // The first MathEquations row in the DB may belong to another (disabled) preset.
            protected function isFeatureEnabled(string $configKey): bool
            {
                return false;
            }

            public function callAddCustomStylesheets(array $resultArray, array $ckeditorConfiguration): array
            {
                return $this->addCustomStylesheets($resultArray, $ckeditorConfiguration);
            }
        };
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function editorConfigurationProvider(): array
    {
        return [
            'mathTypeParameters present' => [
                ['mathTypeParameters' => ['editorParameters' => ['language' => 'en']]],
                true,
            ],
            'mathtype import module' => [
                ['importModules' => [['module' => '@t3planet/RteCkeditorPack/mathtype.js', 'exports' => ['MathType']]]],
                true,
            ],
            'MathType toolbar item' => [
                ['toolbar' => ['items' => ['bold', 'MathType']]],
                true,
            ],
            'ChemType in toolbar group' => [
                ['toolbar' => ['items' => ['bold', ['label' => 'Math', 'items' => ['ChemType']]]]],
                true,
            ],
            'no MathType' => [
                ['toolbar' => ['items' => ['bold', 'italic']], 'importModules' => [['module' => '@ckeditor/ckeditor5-basic-styles']]],
                false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('editorConfigurationProvider')]
    public function mathTypeStylesheetFollowsRenderedEditorConfiguration(array $ckeditorConfiguration, bool $expected): void
    {
        $result = $this->createSubject()->callAddCustomStylesheets(['stylesheetFiles' => [], 'html' => ''], $ckeditorConfiguration);

        self::assertSame($expected, in_array(self::MATHTYPE_CSS, $result['stylesheetFiles'], true));
    }
}
