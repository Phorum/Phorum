<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\CsrfGuard;
use PHPUnit\Framework\TestCase;

/**
 * CsrfGuard uses $_SESSION, so tests must manage session state manually.
 */
class CsrfGuardTest extends TestCase
{
    protected function setUp(): void
    {
        // Start session if not active; clear CSRF token between tests
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['phorum_csrf_token']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['phorum_csrf_token']);
    }

    // -------------------------------------------------------------------------
    // fieldName()
    // -------------------------------------------------------------------------

    public function testFieldNameReturnsExpectedString(): void
    {
        $this->assertSame('csrf_token', CsrfGuard::fieldName());
    }

    // -------------------------------------------------------------------------
    // token()
    // -------------------------------------------------------------------------

    public function testTokenReturnsNonEmptyString(): void
    {
        $token = CsrfGuard::token();
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testTokenIsStableAcrossCallsWithinSameSession(): void
    {
        $t1 = CsrfGuard::token();
        $t2 = CsrfGuard::token();
        $this->assertSame($t1, $t2);
    }

    public function testTokenIsHexEncoded(): void
    {
        $token = CsrfGuard::token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function testValidateReturnsTrueForCorrectToken(): void
    {
        $token = CsrfGuard::token();
        $this->assertTrue(CsrfGuard::validate($token));
    }

    public function testValidateReturnsFalseForWrongToken(): void
    {
        CsrfGuard::token(); // ensure session token is set
        $this->assertFalse(CsrfGuard::validate('wrongtoken'));
    }

    public function testValidateReturnsFalseWhenNoTokenInSession(): void
    {
        unset($_SESSION['phorum_csrf_token']);
        $this->assertFalse(CsrfGuard::validate('anything'));
    }

    // -------------------------------------------------------------------------
    // field()
    // -------------------------------------------------------------------------

    public function testFieldRendersHiddenInput(): void
    {
        $html = CsrfGuard::field();
        $this->assertStringContainsString('<input type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString(CsrfGuard::token(), $html);
    }
}
