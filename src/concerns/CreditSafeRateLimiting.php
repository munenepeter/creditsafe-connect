<?php
/**
 * Abstract CreditSafeRateLimiting
 * 
 * attempt to avoid being blocked by creditsafe by tracking the requests we've made
 * 
 * @see - https://doc.creditsafe.com/tag/Authentication/operation/Authenticate#:~:text=Rate%20Limiting%20Authenticate
 */

 abstract class CreditSafeRateLimiting {

    private const int REQUEST_THRESHOLD = 240; // 4 mins - Creditsafe requires 5 but for our use we will have 4

    // Maximum number of invalid authentication attempts allowed within the rate limit window
    private const int MAX_INVALID_ATTEMPTS = 4;

    // Rate limit window in seconds (2 minutes)
    private const int RATE_LIMIT_WINDOW_SECONDS = 120;

    // Maximum number of global authentication requests allowed within the global rate limit window
    private const int MAX_GLOBAL_REQUESTS = 10_000;

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    private function isGloballyLocked(): int {
        $last_lockout = $_SESSION['global_lockout_time'] ?? 0;
        return (time() - $last_lockout) < self::REQUEST_THRESHOLD; // 4 minutes
    }

    private function incrementGlobalRequestCounter(): void {
        $current_time = time();
        $five_minutes_ago = $current_time - self::REQUEST_THRESHOLD; // 4 minutes
        
        if (!isset($_SESSION['global_requests']) || $_SESSION['global_requests_time'] < $five_minutes_ago) {
            $_SESSION['global_requests'] = 1;
            $_SESSION['global_requests_time'] = $current_time;
        } else {
            $_SESSION['global_requests']++;
        }
    }

    private function hasExceededGlobalThreshold(): bool {
        return ($_SESSION['global_requests'] ?? 0) > self::MAX_GLOBAL_REQUESTS;
    }

    private function setGlobalLockout(): void {
        $_SESSION['global_lockout_time'] = time();
    }

    private function isRequestRateLimited(): bool {
        $invalid_attempts = $this->getInvalidAttempts();
        $last_attempt_time = $this->getLastAttemptTime();

        return $invalid_attempts >= MAX_INVALID_ATTEMPTS && (time() - $last_attempt_time) < self::RATE_LIMIT_WINDOW_SECONDS;
    }

    private function recordInvalidAttempt(): void {
        $_SESSION['invalid_attempts'] = ($this->getInvalidAttempts()) + 1;
        $_SESSION['last_attempt_time'] = time();
    }

    private function resetInvalidAttempts(): void {
        $_SESSION['invalid_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;
    }

    private function handleRateLimitResponse(string $message) {
        echo $message;
        http_response_code(429);
        exit;
    }

    private function getInvalidAttempts(): int {
        return $_SESSION['invalid_attempts'] ?? 0;
    }
}