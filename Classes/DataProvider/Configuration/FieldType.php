<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\Configuration;

enum FieldType: int
{
    case INPUT = 1;
    case BOOLEAN = 2;
    case SELECT = 3;
    case ARRAY = 4;
    case TEXTAREA = 5;
    case ITERATIVE = 6;
    case VALUE_LIST = 7;
    case MULTIFIELD = 8;
    case INNERITERATIVE = 9;
    case NUMBER = 10;

}
