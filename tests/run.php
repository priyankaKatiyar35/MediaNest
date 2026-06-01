<?php
/**
 * MediaNest — Lightweight Test Runner
 * --------------------------------------------------------------
 * No Composer, no PHPUnit. Just `php tests/run.php` from project root.
 *
 * Each test file in tests/ that starts with `test_` is loaded.
 * Inside each file, functions named test_* are auto-discovered and run.
 * Use the global helpers below:
 *
 *   assertEquals($expected, $actual, $message = '')
 *   assertTrue($cond, $message = '')
 *   assertFalse($cond, $message = '')
 *   assertNotEmpty($value, $message = '')
 *   assertContains($needle, $haystack, $message = '')
 *
 * Tests run in a transaction that's rolled back at the end so the
 * database is never permanently modified.
 */

// ─── State ───────────────────────────────────────────────
$GLOBALS['__tests']  = ['pass' => 0, 'fail' => 0, 'errors' => []];
$GLOBALS['__current'] = '';

// ─── Assertions ──────────────────────────────────────────
function assertEquals($expected, $actual, $msg = '') {
    if ($expected === $actual) return _pass();
    return _fail($msg ?: 'Expected ' . _show($expected) . ', got ' . _show($actual));
}
function assertTrue($cond, $msg = '') {
    if ($cond) return _pass();
    return _fail($msg ?: 'Expected true, got ' . _show($cond));
}
function assertFalse($cond, $msg = '') {
    if (!$cond) return _pass();
    return _fail($msg ?: 'Expected false, got ' . _show($cond));
}
function assertNotEmpty($value, $msg = '') {
    if (!empty($value)) return _pass();
    return _fail($msg ?: 'Expected non-empty, got ' . _show($value));
}
function assertContains($needle, $haystack, $msg = '') {
    if (is_array($haystack) && in_array($needle, $haystack, true)) return _pass();
    if (is_string($haystack) && strpos($haystack, $needle) !== false) return _pass();
    return _fail($msg ?: _show($needle) . ' not found in ' . _show($haystack));
}
function _pass() { $GLOBALS['__tests']['pass']++; return true; }
function _fail($msg) {
    $GLOBALS['__tests']['fail']++;
    $GLOBALS['__tests']['errors'][] = $GLOBALS['__current'] . ' — ' . $msg;
    return false;
}
function _show($v) {
    if (is_string($v))  return '"' . mb_substr($v, 0, 50) . (strlen($v) > 50 ? '…' : '') . '"';
    if (is_array($v))   return '[' . count($v) . ' items]';
    if (is_bool($v))    return $v ? 'true' : 'false';
    if (is_null($v))    return 'null';
    return (string) $v;
}

// ─── Coloured terminal output ────────────────────────────
function _c($txt, $col) {
    $codes = ['red'=>"\033[31m",'green'=>"\033[32m",'yellow'=>"\033[33m",'blue'=>"\033[34m",'bold'=>"\033[1m",'reset'=>"\033[0m"];
    return ($codes[$col] ?? '') . $txt . $codes['reset'];
}

// ─── Bootstrap ───────────────────────────────────────────
// Pick up auth/admin helpers (paths assume tests/ sits in project root)
$root = __DIR__ . '/..';
require_once $root . '/auth/auth.php';
if (file_exists($root . '/admin/admin_auth.php')) require_once $root . '/admin/admin_auth.php';
if (file_exists($root . '/admin/notify.php'))     require_once $root . '/admin/notify.php';

// Test DB connection
global $conn;
if (!$conn) {
    echo _c("✗ No \$conn — check admin/config.php is included via auth/auth.php\n", 'red');
    exit(1);
}

// Wrap everything in a transaction so test data doesn't pollute the DB
mysqli_autocommit($conn, false);

echo "\n" . _c("MediaNest test suite", 'bold') . "\n";
echo str_repeat('─', 50) . "\n";

// ─── Discover and run ────────────────────────────────────
$test_files = glob(__DIR__ . '/test_*.php');
if (!$test_files) { echo _c("No test files found in tests/\n", 'yellow'); exit(0); }

foreach ($test_files as $tf) {
    echo "\n" . _c('▸ ' . basename($tf), 'blue') . "\n";

    // Capture currently-defined functions, load the file, diff to find new ones
    $before = get_defined_functions()['user'];
    require_once $tf;
    $after  = get_defined_functions()['user'];
    $new    = array_diff($after, $before);

    foreach ($new as $fn) {
        if (strpos($fn, 'test_') !== 0) continue;
        $GLOBALS['__current'] = $fn;
        $start_pass = $GLOBALS['__tests']['pass'];
        $start_fail = $GLOBALS['__tests']['fail'];
        try {
            $fn();
            $delta_pass = $GLOBALS['__tests']['pass'] - $start_pass;
            $delta_fail = $GLOBALS['__tests']['fail'] - $start_fail;
            if ($delta_fail === 0) {
                echo "  " . _c('✓', 'green') . " $fn ($delta_pass assertion" . ($delta_pass === 1 ? '' : 's') . ")\n";
            } else {
                echo "  " . _c('✗', 'red') . " $fn ($delta_fail failed)\n";
            }
        } catch (Throwable $e) {
            $GLOBALS['__tests']['fail']++;
            $GLOBALS['__tests']['errors'][] = "$fn — exception: " . $e->getMessage();
            echo "  " . _c('✗', 'red') . " $fn — exception: " . $e->getMessage() . "\n";
        }
    }
}

// Rollback any DB writes from tests
mysqli_rollback($conn);

// ─── Summary ─────────────────────────────────────────────
echo "\n" . str_repeat('─', 50) . "\n";
$p = $GLOBALS['__tests']['pass'];
$f = $GLOBALS['__tests']['fail'];
$total = $p + $f;

if ($f === 0) {
    echo _c(_c("✓ All $total assertions passed", 'green'), 'bold') . "\n\n";
    exit(0);
} else {
    echo _c("✗ $f failed, $p passed (of $total)", 'red') . "\n\n";
    foreach ($GLOBALS['__tests']['errors'] as $err) echo "  • $err\n";
    echo "\n";
    exit(1);
}