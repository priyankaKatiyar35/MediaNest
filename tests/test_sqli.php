<?php
/**
 * SQL injection resistance tests
 * ----------------------------------------------
 * Verifies that prepared statements escape malicious input correctly.
 * If any of these fail, you have an injection bug.
 */

function test_prepared_select_treats_quote_as_literal() {
    global $conn;
    // Insert a benign test user we can query for
    $email = 'sqlitest_' . uniqid() . '@example.com';
    $name  = "O'Brien"; // contains apostrophe — classic injection trigger
    $hash  = password_hash('test123', PASSWORD_DEFAULT);
    $role  = 'user';

    $stmt = mysqli_prepare($conn, "INSERT INTO users (email, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssss', $email, $hash, $name, $role);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    assertTrue($ok, 'insert with apostrophe in name should succeed');

    // Read it back via prepared SELECT
    $stmt = mysqli_prepare($conn, "SELECT full_name FROM users WHERE email=?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    assertEquals("O'Brien", $row['full_name'] ?? null, 'apostrophe should round-trip as literal text');
}

function test_classic_or_1_equals_1_does_not_match_all() {
    global $conn;
    // Try the textbook SQLi payload
    $payload = "anything' OR '1'='1";
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=?");
    mysqli_stmt_bind_param($stmt, 's', $payload);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($stmt);

    assertEquals(0, count($rows), 'OR-1=1 payload bound as a string should match zero rows');
}

function test_drop_table_payload_is_safely_treated_as_text() {
    global $conn;
    $payload = "'; DROP TABLE users; --";
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=?");
    mysqli_stmt_bind_param($stmt, 's', $payload);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    assertTrue($ok, 'malicious payload should run as a no-op SELECT, not execute as SQL');

    // Verify users table still exists (the smoking gun)
    $r = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    assertEquals(1, mysqli_num_rows($r), 'users table should still exist after attack attempt');
}

function test_bound_int_param_rejects_string_payload() {
    global $conn;
    // Bind a malicious string to an integer parameter
    $stmt = mysqli_prepare($conn, "SELECT id FROM video WHERE id=?");
    $payload = "1 OR 1=1";   // string passed where int expected
    mysqli_stmt_bind_param($stmt, 'i', $payload);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($stmt);

    // Cast to int → '1 OR 1=1' becomes 1. Either zero rows (no id=1) or one row (id=1) — but never ALL rows.
    assertTrue(count($rows) <= 1, 'int-bound param should cast to 1, never match every row');
}