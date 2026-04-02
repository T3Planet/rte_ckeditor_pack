<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\ViewHelpers\ArrayToStringViewHelper;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for ViewHelper ArrayToStringViewHelper
 */
class ArrayToStringViewHelperTest extends BaseTestCase
{
    /** @var ArrayToStringViewHelper|AccessibleObjectInterface */
    protected $viewHelper;

    protected function setUp(): void
    {
        $this->viewHelper = $this->getAccessibleMock(
            ArrayToStringViewHelper::class,
            ['renderChildren'],
            [],
            '',
            false
        );
    }

    #[Test]
    public function renderReturnsEmptyStringWhenInputIsNull(): void
    {
        $this->viewHelper->_set('arguments', ['input' => null]);
        $result = $this->viewHelper->_call('render');
        self::assertEquals('', $result);
    }

    #[Test]
    public function renderReturnsEmptyStringWhenInputIsFalse(): void
    {
        $this->viewHelper->_set('arguments', ['input' => false]);
        $result = $this->viewHelper->_call('render');
        self::assertEquals('', $result);
    }

    #[Test]
    public function renderReturnsStringWhenInputIsString(): void
    {
        $input = 'test string';
        $this->viewHelper->_set('arguments', ['input' => $input]);
        $result = $this->viewHelper->_call('render');
        self::assertEquals($input, $result);
    }

    #[Test]
    public function renderReturnsJsonEncodedStringWhenInputIsArray(): void
    {
        $input = ['key1' => 'value1', 'key2' => 'value2'];
        $this->viewHelper->_set('arguments', ['input' => $input]);
        $result = $this->viewHelper->_call('render');
        self::assertEquals(json_encode($input), $result);
    }

    #[Test]
    public function renderReturnsJsonEncodedStringWhenInputIsNumericArray(): void
    {
        $input = ['value1', 'value2', 'value3'];
        $this->viewHelper->_set('arguments', ['input' => $input]);
        $result = $this->viewHelper->_call('render');
        self::assertEquals(json_encode($input), $result);
    }

    #[Test]
    public function renderReturnsEmptyStringWhenInputIsEmptyArray(): void
    {
        $input = [];
        $this->viewHelper->_set('arguments', ['input' => $input]);
        $result = $this->viewHelper->_call('render');
        // Empty array evaluates to false in PHP, so returns empty string
        self::assertEquals('', $result);
    }

    #[Test]
    public function renderReturnsJsonEncodedStringWhenInputIsNestedArray(): void
    {
        $input = [
            'level1' => [
                'level2' => 'value',
            ],
        ];
        $this->viewHelper->_set('arguments', ['input' => $input]);
        $result = $this->viewHelper->_call('render');
        self::assertEquals(json_encode($input), $result);
    }

    #[Test]
    public function renderReturnsNumberWhenInputIsInteger(): void
    {
        $input = 42;
        $this->viewHelper->_set('arguments', ['input' => $input]);
        $result = $this->viewHelper->_call('render');
        self::assertEquals($input, $result);
    }
}

