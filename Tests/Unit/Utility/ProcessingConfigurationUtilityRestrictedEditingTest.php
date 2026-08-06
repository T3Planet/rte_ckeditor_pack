<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Utility\ProcessingConfigurationUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit tests for restricted-editing HTML processing helpers.
 */
class ProcessingConfigurationUtilityRestrictedEditingTest extends BaseTestCase
{
    #[Test]
    public function applyRestrictedEditingProcessingReturnsUnchangedWhenFeatureNotDetected(): void
    {
        $configuration = [
            'toolbar' => ['items' => ['bold']],
            'processing' => ['allowTags' => ['p']],
        ];

        $result = ProcessingConfigurationUtility::applyRestrictedEditingProcessing($configuration);

        self::assertSame($configuration, $result);
    }

    #[Test]
    public function applyRestrictedEditingProcessingAllowsSpanDivAndClassWhenToolbarHasRestrictedItem(): void
    {
        $configuration = [
            'toolbar' => [
                'items' => ['restrictedEditingException'],
            ],
            'processing' => [
                'allowTags' => ['p'],
                'allowAttributes' => ['href'],
            ],
        ];

        $result = ProcessingConfigurationUtility::applyRestrictedEditingProcessing($configuration);

        self::assertContains('span', $result['processing']['allowTags']);
        self::assertContains('div', $result['processing']['allowTags']);
        self::assertContains('p', $result['processing']['allowTags']);
        self::assertContains('class', $result['processing']['allowAttributes']);
        self::assertContains('href', $result['processing']['allowAttributes']);
        self::assertArrayHasKey('proc.', $result);
    }

    #[Test]
    public function applyRestrictedEditingProcessingDetectsPluginExports(): void
    {
        $configuration = [
            'importModules' => [
                [
                    'module' => '@ckeditor/ckeditor5-restricted-editing',
                    'exports' => ['RestrictedEditingMode'],
                ],
            ],
        ];

        $result = ProcessingConfigurationUtility::applyRestrictedEditingProcessing($configuration);

        self::assertContains('span', $result['processing']['allowTags']);
        self::assertContains('div', $result['processing']['allowTags']);
        self::assertContains('class', $result['processing']['allowAttributes']);
    }
}
