<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\TestingFramework\Core\Testbase;

/**
 * Boilerplate for a functional test phpunit boostrap file.
 *
 * This file is loosely maintained within TYPO3 testing-framework, extensions
 * are encouraged to not use it directly, but to copy it to an own place,
 * usually in parallel to a FunctionalTests.xml file.
 *
 * This file is defined in FunctionalTests.xml and called by phpunit
 * before instantiating the test suites.
 */
(static function () {
    $setEnvVar = function (string $name, string $value): void {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    };
    
    if (!getenv('typo3DatabaseName')) {
        $dbname = getenv('TYPO3_DB_DBNAME') ?: 'db';
        $setEnvVar('typo3DatabaseName', $dbname);
    }
    if (!getenv('typo3DatabaseHost')) {
        $host = getenv('TYPO3_DB_HOST') ?: 'db';
        $setEnvVar('typo3DatabaseHost', $host);
    }
    if (!getenv('typo3DatabasePort')) {
        $port = getenv('TYPO3_DB_PORT') ?: '3306';
        $setEnvVar('typo3DatabasePort', $port);
    }
    if (!getenv('typo3DatabaseUsername')) {
        $username = getenv('TYPO3_DB_USERNAME') ?: 'db';
        $setEnvVar('typo3DatabaseUsername', $username);
    }
    if (!getenv('typo3DatabasePassword')) {
        $password = getenv('TYPO3_DB_PASSWORD') ?: 'db';
        $setEnvVar('typo3DatabasePassword', $password);
    }
    if (!getenv('typo3DatabaseDriver')) {
        $driver = getenv('TYPO3_DB_DRIVER') ?: 'mysqli';
        $setEnvVar('typo3DatabaseDriver', $driver);
    }

    $testbase = new Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();

