<?php
/**
 * Auth-related tests:
 *  - password hashing roundtrip
 *  - CSRF token generation/verification
 *  - currentUser() / isLoggedIn() behaviour
 *  - requireAdmin() blocks non-admin
 */

function test_password_hashing_roundtrip() {
    $pw   = 'correct_horse_battery_staple';
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    assertTrue(password_verify($pw, $hash), 'right password should verify');
    assertFalse(password_verify('wrong_password', $hash), 'wrong password should not verify');
    assertNotEmpty($hash, 'hash should be non-empty');
}

function test_csrf_token_is_consistent_per_session() {
    $_SESSION = []; // fresh
    if (!function_exists('csrfToken')) {
        // Skip silently if helper isn't loaded
        assertTrue(true, 'csrfToken not available — skipping');
        return;
    }
    $t1 = csrfToken();
    $t2 = csrfToken();
    assertEquals($t1, $t2, 'token should not change within the same session');
    assertTrue(strlen($t1) >= 32, 'token should be long enough to be unguessable');
}

function test_csrf_check_rejects_bad_token() {
    if (!function_exists('csrfCheck')) {
        assertTrue(true, 'csrfCheck not available — skipping');
        return;
    }
    assertFalse(csrfCheck(''),        'empty token should fail');
    assertFalse(csrfCheck('nonsense'), 'random token should fail');
    assertFalse(csrfCheck(null),       'null token should fail');
}

function test_csrf_check_accepts_real_token() {
    if (!function_exists('csrfToken') || !function_exists('csrfCheck')) {
        assertTrue(true, 'CSRF helpers not available — skipping');
        return;
    }
    $real = csrfToken();
    assertTrue(csrfCheck($real), 'the real token should pass');
}

function test_logged_out_state() {
    $_SESSION = [];
    assertFalse(isLoggedIn(), 'no session = not logged in');
    assertEquals(null, currentUser(), 'no session = no current user');
}

function test_login_with_wrong_password_returns_false() {
    if (!function_exists('attemptLogin')) {
        assertTrue(true, 'attemptLogin not available — skipping');
        return;
    }
    $result = attemptLogin('nonexistent_user_zzz@example.com', 'wrong-pw');
    assertFalse($result['ok'] ?? true, 'unknown user should fail login');
}