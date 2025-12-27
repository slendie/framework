<?php

declare(strict_types=1);

namespace Slendie\Framework;

/**
 * CSRF Protection
 *
 * Provides methods to generate and validate CSRF tokens for form protection.
 */
final class CSRF
{
    /**
     * Generate a CSRF token and store it in session
     *
     * @return string The generated CSRF token
     */
    public static function token()
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Generate HTML input field for CSRF token
     *
     * @return string HTML input field with CSRF token
     */
    public static function field()
    {
        $token = self::token();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate CSRF token from request
     *
     * @param string|null $token The token to validate (if null, reads from POST data)
     * @return bool True if token is valid, false otherwise
     */
    public static function validate($token = null)
    {
        if ($token === null) {
            $token = $_POST['_token'] ?? null;
        }

        if (empty($token) || !isset($_SESSION['_csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Regenerate CSRF token (useful after successful validation)
     *
     * @return string The new CSRF token
     */
    public static function regenerate()
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf_token'];
    }
}
