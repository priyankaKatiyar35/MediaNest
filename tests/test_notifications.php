<?php
/**
 * Notification helper tests
 * ----------------------------------------------
 * Skipped automatically if notify.php is not loaded.
 */

function test_notify_helpers_loaded() {
    $exists = function_exists('notifyUser') && function_exists('countMyUnread');
    assertTrue($exists, 'notify.php should be loaded by tests/run.php bootstrap');
}

function test_notify_single_user_creates_row() {
    global $conn;
    if (!function_exists('notifyUser')) { assertTrue(true, 'skipped'); return; }

    // Grab any existing user
    $r = mysqli_query($conn, "SELECT id FROM users LIMIT 1");
    if (!$r || !mysqli_num_rows($r)) { assertTrue(true, 'no users — skipped'); return; }
    $uid = (int) mysqli_fetch_row($r)[0];

    $ok = notifyUser($uid, 'test_kind', 'Unit test notification', 'body text', '/some/link');
    assertTrue($ok, 'notifyUser should return truthy on success');

    // Count rows added
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='test_kind'");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $cnt = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    assertTrue($cnt >= 1, 'at least one row should exist for the test notification');
}

function test_notify_rejects_zero_user_id() {
    if (!function_exists('notifyUser')) { assertTrue(true, 'skipped'); return; }
    $r = notifyUser(0, 'x', 'y');
    assertFalse($r, 'should refuse user_id 0');
}