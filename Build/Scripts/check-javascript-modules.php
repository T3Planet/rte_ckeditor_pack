<?php

/**
 * Pre-PR gate for Configuration/JavaScriptModules.php.
 *
 * TYPO3 core (ImportMap / JavaScriptModules loading) includes this file inside its own method
 * scope while it loops over packages with a `$package` variable. A file that defines top-level
 * variables (e.g. `foreach (... as $package)`) overwrites core's locals and crashes the backend
 * with "Call to a member function getPackageKey() on string".
 *
 * This script includes the file the same way (inside a function with preset locals) and checks:
 *  - it returns an array with an 'imports' array
 *  - core's locals ($package, $configurationFile, $packageConfiguration, $importMaps, $extensionVersions)
 *    are unchanged (other leaked top-level variables are warnings)
 *  - every EXT:rte_ckeditor_pack/Resources/Public/Contrib/... file it references exists (the rebuild
 *    output); other missing EXT: references are reported as warnings (errors with --strict-files)
 *
 * Usage: php Build/Scripts/check-javascript-modules.php [file] [--ext-root=<dir>] [--typo3=<x.y.z>] [--skip-files] [--strict-files]
 * Runs without TYPO3: VersionNumberUtility is stubbed when it does not exist (--typo3 selects the version, default 13.4.0).
 * Exit code 0 = ok, 1 = check failed, 2 = usage error.
 */

$args = array_slice($argv, 1);
$opts = ['typo3' => '13.4.0', 'ext-root' => null, 'skip-files' => false, 'strict-files' => false];
$file = null;
foreach ($args as $arg) {
    if (preg_match('/^--(typo3|ext-root)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif ($arg === '--skip-files' || $arg === '--strict-files') {
        $opts[substr($arg, 2)] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php check-javascript-modules.php [file] [--ext-root=<dir>] [--typo3=<x.y.z>] [--skip-files] [--strict-files]\n");
        exit(0);
    } elseif ($file === null) {
        $file = $arg;
    } else {
        fwrite(STDERR, "Unexpected argument: $arg\n");
        exit(2);
    }
}
$extRoot = rtrim($opts['ext-root'] ?? dirname(__DIR__, 2), '/');
$file ??= $extRoot . '/Configuration/JavaScriptModules.php';
if (!is_file($file)) {
    fwrite(STDERR, "File not found: $file\n");
    exit(2);
}

if (!class_exists(\TYPO3\CMS\Core\Utility\VersionNumberUtility::class)) {
    $GLOBALS['__CHECK_TYPO3_VERSION'] = $opts['typo3'];
    eval('namespace TYPO3\CMS\Core\Utility; final class VersionNumberUtility {
        public static function getCurrentTypo3Version(): string { return $GLOBALS["__CHECK_TYPO3_VERSION"]; }
        public static function convertVersionStringToArray(string $v): array {
            $p = array_map("intval", explode(".", $v) + [0, 0, 0]);
            return ["version" => $p[0] * 1000000 + $p[1] * 1000 + $p[2], "version_main" => $p[0], "version_sub" => $p[1], "version_patch" => $p[2]];
        }
    }');
}

/** Mimics core: include inside a method scope that has its own $package loop variable. */
function checkJavaScriptModulesInclude(string $file): array
{
    // the locals TYPO3\CMS\Core\Page\ImportMap::getImportMaps() has while it includes the file
    $package = new \ArrayObject(['packageKey' => 'core']);
    $marker = $package;
    $configurationFile = '/core/Configuration/JavaScriptModules.php';
    $packageConfiguration = ['imports' => []];
    $importMaps = ['core' => []];
    $extensionVersions = ['core' => 'core:13.4.0'];
    $coreLocals = compact('configurationFile', 'packageConfiguration', 'importMaps', 'extensionVersions');
    // other common names: overwriting them is harmless for core today, but reported
    $contrib = 'outer-contrib';
    $imports = ['outer' => true];
    $otherLocals = compact('contrib', 'imports');

    $result = include $file;

    $errors = [];
    if ($package !== $marker) {
        $errors[] = 'the file overwrote the caller variable $package (got ' . get_debug_type($package) . ') - wrap its body in `return (static function (): array { ... })();`';
    }
    foreach ($coreLocals as $name => $value) {
        if ($$name !== $value) {
            $errors[] = "the file overwrote the caller variable \$$name";
        }
    }
    foreach ($otherLocals as $name => $value) {
        if ($$name !== $value) {
            fwrite(STDERR, "warning: the file leaks the top-level variable \$$name into TYPO3's scope (wrap it in a static closure)\n");
        }
    }
    return [$result, $errors];
}

[$result, $errors] = checkJavaScriptModulesInclude($file);
if (!is_array($result)) {
    $errors[] = 'the file must return an array, got ' . get_debug_type($result);
} elseif (!is_array($result['imports'] ?? null) || $result['imports'] === []) {
    $errors[] = "the returned array has no non-empty 'imports' array";
} elseif (!$opts['skip-files']) {
    $refs = [];
    array_walk_recursive($result['imports'], static function ($v) use (&$refs): void {
        if (is_string($v) && str_starts_with($v, 'EXT:rte_ckeditor_pack/')) {
            $refs[] = $v;
        }
    });
    foreach (array_unique($refs) as $ref) {
        if (file_exists($extRoot . '/' . substr($ref, strlen('EXT:rte_ckeditor_pack/')))) {
            continue;
        }
        if ($opts['strict-files'] || str_starts_with($ref, 'EXT:rte_ckeditor_pack/Resources/Public/Contrib/')) {
            $errors[] = "missing file referenced in imports: $ref";
        } else {
            fwrite(STDERR, "warning: missing file referenced in imports (not part of the Contrib build): $ref\n");
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "JavaScriptModules check FAILED for $file (TYPO3 {$opts['typo3']}):\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}
printf("JavaScriptModules check OK for %s (TYPO3 %s, %d imports)\n", $file, $opts['typo3'], count($result['imports']));
exit(0);
