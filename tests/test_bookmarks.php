<?php
/**
 * Bookmark toggle tests
 * ----------------------------------------------
 * Tests the DB-level toggle behaviour directly (doesn't simulate HTTP).
 */

function test_bookmark_insert_then_remove() {
    global $conn;

    // Find any user + any video to bookmark
    $u = mysqli_query($conn, "SELECT id FROM users LIMIT 1");
    $v = mysqli_query($conn, "SELECT id FROM video LIMIT 1");
    if (!$u || !$v || !mysqli_num_rows($u) || !mysqli_num_rows($v)) {
        assertTrue(true, 'no users or videos — skipped');
        return;
    }
    $uid = (int) mysqli_fetch_row($u)[0];
    $vid = (int) mysqli_fetch_row($v)[0];
    $type = 'video';

    // Clean slate
    $stmt = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE user_id=? AND item_type=? AND item_id=?");
    mysqli_stmt_bind_param($stmt, 'isi', $uid, $type, $vid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Insert
    $stmt = mysqli_prepare($conn, "INSERT INTO bookmarks (user_id, item_type, item_id) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isi', $uid, $type, $vid);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    assertTrue($ok, 'inserting a bookmark should succeed');

    // Verify
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookmarks WHERE user_id=? AND item_type=? AND item_id=?");
    mysqli_stmt_bind_param($stmt, 'isi', $uid, $type, $vid);
    mysqli_stmt_execute($stmt);
    $n = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    assertEquals(1, $n, 'exactly one bookmark row should now exist');

    // Remove
    $stmt = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE user_id=? AND item_type=? AND item_id=?");
    mysqli_stmt_bind_param($stmt, 'isi', $uid, $type, $vid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Verify gone
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookmarks WHERE user_id=? AND item_type=? AND item_id=?");
    mysqli_stmt_bind_param($stmt, 'isi', $uid, $type, $vid);
    mysqli_stmt_execute($stmt);
    $n = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    assertEquals(0, $n, 'bookmark should be gone after delete');
}

function test_bookmark_unique_constraint_prevents_duplicates() {
    global $conn;
    $u = mysqli_query($conn, "SELECT id FROM users LIMIT 1");
    $v = mysqli_query($conn, "SELECT id FROM video LIMIT 1");
    if (!$u || !$v || !mysqli_num_rows($u) || !mysqli_num_rows($v)) {
        assertTrue(true, 'skipped'); return;
    }
    $uid = (int) mysqli_fetch_row($u)[0];
    $vid = (int) mysqli_fetch_row($v)[0];
    $type = 'video';

    // Clean
    $stmt = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE user_id=? AND item_type=? AND item_id=?");
    mysqli_stmt_bind_param($stmt, 'isi', $uid, $type, $vid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);

    // Try to insert twice
    $stmt = mysqli_prepare($conn, "INSERT INTO bookmarks (user_id, item_type, item_id) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isi', $uid, $type, $vid);
    mysqli_stmt_execute($stmt);
    $second = @mysqli_stmt_execute($stmt); // duplicate — should fail
    mysqli_stmt_close($stmt);

    assertFalse($second, 'second insert with same (user, type, id) should be blocked by UNIQUE constraint');
}