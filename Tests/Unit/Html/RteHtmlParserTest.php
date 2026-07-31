<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Html;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Html\RteHtmlParser;
use TYPO3\CMS\Core\Html\RteHtmlParser as CoreRteHtmlParser;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class RteHtmlParserTest extends BaseTestCase
{
    #[Test]
    public function extendsCoreRteHtmlParser(): void
    {
        self::assertTrue(is_subclass_of(RteHtmlParser::class, CoreRteHtmlParser::class));
    }
}
