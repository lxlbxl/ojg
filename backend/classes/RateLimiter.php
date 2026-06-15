<?php
/**
 * RateLimiter Class
 * Handles authentication brute-force protection and generic API rate limiting.
 */

class RateLimiter
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check if a login attempt is allowed or if the identifier/IP is locked out.
     * Lockout triggers after 5 failures in 15 minutes.
     * Backoff is exponential: 900 * 2^min(5, F-5) seconds.
     */
    public function checkLoginAttempt($identifier, $ip)
    {
        if ($this->db->isFileStorage()) {
            return ['allowed' => true, 'remaining' => 0, 'failures' => 0];
        }

        try {
            // Count failed attempts in the last 24 hours for identifier OR IP
            $time24h = date('Y-m-d H:i:s', time() - 86400);
            
            // Note: SQLite and MySQL both support passing string timestamps in comparisons
            $sql = "SELECT COUNT(*) as count FROM login_attempts WHERE (identifier = ? OR ip_address = ?) AND attempted_at > ?";
            $res = $this->db->fetch($sql, [$identifier, $ip, $time24h]);
            $failures = (int)($res['count'] ?? 0);

            if ($failures >= 5) {
                // Fetch the most recent failure to calculate elapsed time
                $lastAttempt = $this->db->fetch(
                    "SELECT attempted_at FROM login_attempts WHERE (identifier = ? OR ip_address = ?) ORDER BY attempted_at DESC LIMIT 1",
                    [$identifier, $ip]
                );

                if ($lastAttempt) {
                    $lastTime = strtotime($lastAttempt['attempted_at']);
                    $elapsed = time() - $lastTime;
                    // Exponential backoff: 15 min * 2^min(5, F-5)
                    $backoff = 900 * pow(2, min(5, $failures - 5));

                    if ($elapsed < $backoff) {
                        return [
                            'allowed' => false,
                            'remaining' => $backoff - $elapsed,
                            'failures' => $failures
                        ];
                    }
                }
            }

            return ['allowed' => true, 'remaining' => 0, 'failures' => $failures];
        } catch (Exception $e) {
            error_log("RateLimiter checkLoginAttempt error: " . $e->getMessage());
            return ['allowed' => true, 'remaining' => 0, 'failures' => 0];
        }
    }

    /**
     * Record a failed login attempt.
     */
    public function recordLoginFailure($identifier, $ip)
    {
        if ($this->db->isFileStorage()) {
            return false;
        }

        try {
            return $this->db->insert('login_attempts', [
                'identifier' => $identifier,
                'ip_address' => $ip,
                'attempted_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("RateLimiter recordLoginFailure error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record a successful login attempt, clearing previous failures.
     */
    public function recordLoginSuccess($identifier, $ip)
    {
        if ($this->db->isFileStorage()) {
            return true;
        }

        try {
            // Delete with parameters array
            return $this->db->delete('login_attempts', "identifier = ? OR ip_address = ?", [$identifier, $ip]);
        } catch (Exception $e) {
            error_log("RateLimiter recordLoginSuccess error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * API Rate Limiting using Token Bucket algorithm.
     * Default limit is 60 requests per minute.
     */
    public function checkApiRateLimit($ip, $ratePerMinute = 60, $capacity = 60)
    {
        if ($this->db->isFileStorage()) {
            return true;
        }

        try {
            $now = time();
            $refillRate = $ratePerMinute / 60.0; // tokens per second

            $bucket = $this->db->fetch("SELECT tokens, last_updated FROM rate_limits_bucket WHERE ip_address = ?", [$ip]);

            if ($bucket) {
                $elapsed = max(0, $now - (int)$bucket['last_updated']);
                $newTokens = min((float)$capacity, (float)$bucket['tokens'] + ($elapsed * $refillRate));
            } else {
                $newTokens = (float)$capacity;
            }

            if ($newTokens >= 1.0) {
                $newTokens -= 1.0;
                
                if ($bucket) {
                    $this->db->update('rate_limits_bucket', [
                        'tokens' => $newTokens,
                        'last_updated' => $now
                    ], "ip_address = :ip", [':ip' => $ip]);
                } else {
                    $this->db->insert('rate_limits_bucket', [
                        'ip_address' => $ip,
                        'tokens' => $newTokens,
                        'last_updated' => $now
                    ]);
                }
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("RateLimiter checkApiRateLimit error: " . $e->getMessage());
            return true; // fail open on rate limit DB error to avoid breaking API
        }
    }
}
