<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Utility\RteMarkupTransformationUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class RteMarkupTransformationUtilityTest extends BaseTestCase
{
    #[Test]
    #[DataProvider('collaborationMarkupProvider')]
    public function stripCollaborationMarkupRemovesMarkers(string $input, string $expected): void
    {
        self::assertSame(
            $expected,
            RteMarkupTransformationUtility::stripCollaborationMarkup($input)
        );
    }

    #[Test]
    #[DataProvider('restoreEscapedCollaborationMarkersProvider')]
    public function restoreEscapedCollaborationMarkersRestoresRealTags(string $input, string $expected): void
    {
        self::assertSame(
            $expected,
            RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($input)
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function collaborationMarkupProvider(): array
    {
        return [
            'raw comment markers' => [
                'this <comment-start name="eb44985ac1ebd511323130b78af83e819:ce4fe"></comment-start>is'
                . '<comment-end name="eb44985ac1ebd511323130b78af83e819:ce4fe"></comment-end> testing '
                . '<comment-start name="ef0e583a4b76c29e031ccd5f69b2aec3b:64f69"></comment-start>content'
                . '<comment-end name="ef0e583a4b76c29e031ccd5f69b2aec3b:64f69"></comment-end>',
                'this is testing content',
            ],
            'preview-sanitizer encoded comment markers' => [
                'this &lt;comment-start name="eb44985ac1ebd511323130b78af83e819:ce4fe"&gt;&lt;/comment-start&gt;is'
                . '&lt;comment-end name="eb44985ac1ebd511323130b78af83e819:ce4fe"&gt;&lt;/comment-end&gt; testing '
                . '&lt;comment-start name="ef0e583a4b76c29e031ccd5f69b2aec3b:64f69"&gt;&lt;/comment-start&gt;content'
                . '&lt;comment-end name="ef0e583a4b76c29e031ccd5f69b2aec3b:64f69"&gt;&lt;/comment-end&gt;',
                'this is testing content',
            ],
            'suggestion markers keep insertion text' => [
                'Hello <suggestion-start name="insertion:abc"></suggestion-start>world'
                . '<suggestion-end name="insertion:abc"></suggestion-end>!',
                'Hello world!',
            ],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function restoreEscapedCollaborationMarkersProvider(): array
    {
        return [
            'entity-encoded comment markers' => [
                '<p>&lt;comment-start name="edd90745e80fe42e0fbf615f8123e2305:dcf38"&gt;&lt;/comment-start&gt;testing'
                . '&lt;comment-end name="edd90745e80fe42e0fbf615f8123e2305:dcf38"&gt;&lt;/comment-end&gt; is test</p>',
                '<p><comment-start name="edd90745e80fe42e0fbf615f8123e2305:dcf38"></comment-start>testing'
                . '<comment-end name="edd90745e80fe42e0fbf615f8123e2305:dcf38"></comment-end> is test</p>',
            ],
            'already real markers stay unchanged' => [
                '<p><comment-start name="abc:1"></comment-start>x<comment-end name="abc:1"></comment-end></p>',
                '<p><comment-start name="abc:1"></comment-start>x<comment-end name="abc:1"></comment-end></p>',
            ],
        ];
    }
}
