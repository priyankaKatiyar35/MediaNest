<?php
use PHPUnit\Framework\TestCase;

/**
 * Auth + security tests — PHPUnit version.
 * Each test runs in a transaction that's rolled back automatically.
 */
class AuthTest extends TestCase
{
    protected function setUp(): void {
        global $conn;
        mysqli_autocommit($conn, false);
        $_SESSION = [];
    }
    protected function tearDown(): void {
        global $conn;
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
    }

    public function testPasswordHashRoundtrips(): void {
        $pw = 'correct horse battery staple';
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $this->assertTrue(password_verify($pw, $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }

    public function testCsrfTokenIsStableWithinSession(): void {
        if (!function_exists('csrfToken')) $this->markTestSkipped('csrfToken not loaded');
        $a = csrfToken();
        $b = csrfToken();
        $this->assertSame($a, $b);
        $this->assertGreaterThanOrEqual(32, strlen($a));
    }

    public function testCsrfCheckRejectsBadTokens(): void {
        if (!function_exists('csrfCheck')) $this->markTestSkipped('csrfCheck not loaded');
        $this->assertFalse(csrfCheck(''));
        $this->assertFalse(csrfCheck('nonsense'));
    }

    public function testLoggedOutStateIsClean(): void {
        $this->assertFalse(isLoggedIn());
        $this->assertNull(currentUser());
    }

    public function testSqlInjectionPayloadIsTreatedAsLiteral(): void {
        global $conn;
        $payload = "anything' OR '1'='1";
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=?");
        mysqli_stmt_bind_param($stmt, 's', $payload);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
        mysqli_stmt_close($stmt);
        $this->assertCount(0, $rows);
    }

    public function testApostropheInTextIsLiteral(): void {
        global $conn;
        $email = 'test_' . uniqid() . '@example.com';
        $name  = "O'Brien";
        $hash  = password_hash('x', PASSWORD_DEFAULT);
        $role  = 'user';
        $stmt = mysqli_prepare($conn,
            "INSERT INTO users (email, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $email, $hash, $name, $role);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT full_name FROM users WHERE email=?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $this->assertSame("O'Brien", $row['full_name']);
    }
}