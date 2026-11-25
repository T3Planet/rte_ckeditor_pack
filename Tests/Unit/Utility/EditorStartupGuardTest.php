<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use T3Planet\RteCkeditorPack\Utility\MarkdownMathPreserve;
use T3Planet\RteCkeditorPack\Utility\WProofreaderStartupGuard;

final class EditorStartupGuardTest extends TestCase
{
    public function testWProofreaderWithoutSourceUrlIsNotLoaded(): void
    {
        $result = WProofreaderStartupGuard::apply([
            'importModules' => [
                [
                    'module' => '@t3planet/RteCkeditorPack/spell-check',
                    'exports' => ['WProofreader'],
                ],
                ['module' => '@ckeditor/ckeditor5-essentials', 'exports' => ['Essentials']],
            ],
            'wproofreader' => [
                'srcUrl' => '',
                'serviceId' => '',
            ],
        ]);

        self::assertTrue($result['packWproofreaderUnavailable']);
        self::assertArrayNotHasKey('wproofreader', $result);
        self::assertSame(
            [['module' => '@ckeditor/ckeditor5-essentials', 'exports' => ['Essentials']]],
            $result['importModules']
        );
    }

    public function testConfiguredWProofreaderStaysLoaded(): void
    {
        $configuration = [
            'importModules' => [
                [
                    'module' => '@t3planet/RteCkeditorPack/spell-check',
                    'exports' => ['WProofreader'],
                ],
            ],
            'wproofreader' => [
                'srcUrl' => 'https://svc.webspellchecker.net/spellcheck31/wscbundle/wscbundle.js',
                'serviceId' => 'abc',
            ],
        ];

        $result = WProofreaderStartupGuard::apply($configuration);

        self::assertArrayNotHasKey('packWproofreaderUnavailable', $result);
        self::assertSame($configuration['importModules'], $result['importModules']);
        self::assertSame($configuration['wproofreader'], $result['wproofreader']);
    }

    public function testMarkdownMathPluginIsAddedOnlyWhenBothFeaturesAreEnabled(): void
    {
        $both = MarkdownMathPreserve::apply([
            'importModules' => [
                ['module' => '@ckeditor/ckeditor5-markdown-gfm', 'exports' => ['Markdown']],
                ['module' => '@t3planet/RteCkeditorPack/mathtype.js', 'exports' => ['MathType']],
            ],
        ]);
        self::assertSame(
            MarkdownMathPreserve::MODULE,
            $both['importModules'][2]['module']
        );

        $markdownOnly = MarkdownMathPreserve::apply([
            'importModules' => [
                ['module' => '@ckeditor/ckeditor5-markdown-gfm', 'exports' => ['Markdown']],
            ],
        ]);
        self::assertCount(1, $markdownOnly['importModules']);

        $twice = MarkdownMathPreserve::apply($both);
        self::assertCount(3, $twice['importModules']);
    }
}
