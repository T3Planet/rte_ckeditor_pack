<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Functional\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional test for ViewHelper ArrayToStringViewHelper
 */
class ArrayToStringViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3conf/ext/rte_ckeditor_pack'];
    protected array $coreExtensionsToLoad = ['fluid'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
    }

    #[Test]
    public function viewHelperRendersStringAsIs(): void
    {
        $result = $this->renderViewHelper(['input' => 'test string']);

        self::assertEquals('test string', trim($result));
    }

    #[Test]
    public function viewHelperRendersArrayAsJson(): void
    {
        $testArray = ['key1' => 'value1', 'key2' => 'value2'];
        $result = $this->renderViewHelper(['input' => $testArray]);

        $expected = json_encode($testArray);
        self::assertEquals($expected, trim($result));
    }

    #[Test]
    public function viewHelperRendersNumericArrayAsJson(): void
    {
        $testArray = ['value1', 'value2', 'value3'];
        $result = $this->renderViewHelper(['input' => $testArray]);

        $expected = json_encode($testArray);
        self::assertEquals($expected, trim($result));
    }

    #[Test]
    public function viewHelperRendersEmptyArrayAsEmptyString(): void
    {
        $testArray = [];
        $result = $this->renderViewHelper(['input' => $testArray]);

        // Empty arrays evaluate to false in PHP, so the ViewHelper returns empty string
        self::assertEquals('', trim($result));
    }

    #[Test]
    public function viewHelperRendersNestedArrayAsJson(): void
    {
        $testArray = [
            'level1' => [
                'level2' => 'value',
            ],
        ];
        $result = $this->renderViewHelper(['input' => $testArray]);

        $expected = json_encode($testArray);
        self::assertEquals($expected, trim($result));
    }

    #[Test]
    public function viewHelperRendersEmptyStringWhenInputIsNull(): void
    {
        $result = $this->renderViewHelper(['input' => null]);

        self::assertEquals('', trim($result));
    }

    #[Test]
    public function viewHelperRendersNumberAsIs(): void
    {
        $result = $this->renderViewHelper(['input' => 42]);

        self::assertEquals('42', trim($result));
    }

    /**
     * Render a ViewHelper template
     */
    protected function renderViewHelper(array $variables): string
    {
        $viewFactoryData = new ViewFactoryData(
            templatePathAndFilename: 'EXT:rte_ckeditor_pack/Tests/Functional/Fixtures/ArrayToStringViewHelperFixture.html'
        );

        $standaloneView = GeneralUtility::makeInstance(ViewFactoryInterface::class)->create($viewFactoryData);
        
        foreach ($variables as $key => $value) {
            $standaloneView->assign($key, $value);
        }

        return $standaloneView->render();
    }
}

