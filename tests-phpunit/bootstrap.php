<?php
/**
 * PHPUnit bootstrap — loaded before any test
 */
require_once __DIR__ . '/../vendor/autoload.php';

$root = __DIR__ . '/..';
require_once $root . '/auth/auth.php';
if (file_exists($root . '/admin/admin_auth.php')) require_once $root . '/admin/admin_auth.php';
if (file_exists($root . '/admin/notify.php'))     require_once $root . '/admin/notify.php';

// Each test that touches the DB should call beginTransaction()/rollback() in setUp/tearDown