<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use T3Planet\RteCkeditorPack\Utility\MathMlFrontendRenderer;

final class MathMlFrontendRendererTest extends TestCase
{
    public function testPrepareForBackendPreviewReplacesMathMlWithWirisImage(): void
    {
        $html = '<p>Math</p><math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<semantics><mrow><mi>x</mi></mrow>'
            . '<annotation encoding="application/vnd.wiris.mtweb-params+json">'
            . '{"language":"en","wiriseditorsavemode":"image"}</annotation>'
            . '</semantics></math>';

        $result = (new MathMlFrontendRenderer())->prepareForBackendPreview($html);

        self::assertStringContainsString('<p>Math</p>', $result);
        self::assertStringContainsString('rte-ckeditor-pack-formula--math', $result);
        self::assertStringContainsString('<img class="Wirisformula"', $result);
        self::assertStringContainsString('https://www.wiris.net/demo/editor/render.svg?', $result);
        self::assertStringNotContainsString('wiriseditorsavemode', $result);
        self::assertStringNotContainsString('<math', $result);
    }

    public function testPrepareForBackendPreviewReplacesSafeXmlMathMl(): void
    {
        $html = '<p>Chem</p>«math class=¨wrs_chemistry¨»«semantics»«mrow»'
            . '«mn»2«/mn»«msub»«mi»H«/mi»«mn»2«/mn»«/msub»'
            . '«/mrow»«annotation encoding=¨application/vnd.wiris.mtweb-params+json¨»'
            . '{"language":"en","wiriseditorsavemode":"image"}«/annotation»«/semantics»«/math»';

        $result = (new MathMlFrontendRenderer())->prepareForBackendPreview($html);

        self::assertStringContainsString('Wirisformula', $result);
        self::assertStringContainsString('rte-ckeditor-pack-formula--chem', $result);
        self::assertStringNotContainsString('wiriseditorsavemode', $result);
    }

    public function testPrepareForBackendPreviewReplacesEntityEncodedMathMl(): void
    {
        $html = '<p>Math</p>&lt;math&gt;&lt;semantics&gt;&lt;mrow&gt;&lt;mi&gt;x&lt;/mi&gt;&lt;/mrow&gt;'
            . '&lt;annotation encoding="application/vnd.wiris.mtweb-params+json"&gt;'
            . '{"language":"en","wiriseditorsavemode":"image"}&lt;/annotation&gt;&lt;/semantics&gt;&lt;/math&gt;';

        $result = (new MathMlFrontendRenderer())->prepareForBackendPreview($html);

        self::assertStringContainsString('Wirisformula', $result);
        self::assertStringNotContainsString('wiriseditorsavemode', $result);
    }

    public function testStripWirisEditorAnnotationsRemovesJsonPayload(): void
    {
        $mathml = '<math><semantics><mrow><mi>a</mi></mrow>'
            . '<annotation encoding="application/vnd.wiris.mtweb-params+json">'
            . '{"language":"en"}</annotation></semantics></math>';

        $result = (new MathMlFrontendRenderer())->stripWirisEditorAnnotations($mathml);

        self::assertStringNotContainsString('annotation', $result);
        self::assertStringNotContainsString('language', $result);
        self::assertStringContainsString('<mi>a</mi>', $result);
    }
}
