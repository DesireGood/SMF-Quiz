<?php

declare(strict_types=1);

namespace Quiz\Service;

use Quiz\Traits\HasValidation;

/**
 * ValidationService
 *
 * Centralised input validation for the Quiz modification.
 * Sanitises and validates all user-supplied input before it reaches
 * the database or business-logic layer.
 *
 * @package Quiz\Service
 */
final class ValidationService
{
    use HasValidation;

    /**
     * Sanitise an integer from user input
     *
     * Returns 0 when the value is absent or non-numeric.
     *
     * @param array<string, mixed> $input Source array (e.g. $_GET, $_POST)
     * @param string $key Array key to read
     * @param int $min Minimum acceptable value (inclusive)
     * @param int $max Maximum acceptable value (0 = no upper limit)
     * @return int
     */
    public function sanitizeInt(array $input, string $key, int $min = 0, int $max = 0): int
    {
        $value = isset($input[$key]) ? (int)$input[$key] : 0;

        if ($value < $min) {
            return $min;
        }

        if ($max > 0 && $value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * Sanitise a string from user input
     *
     * Uses SMF's htmlspecialchars wrapper when available, otherwise falls
     * back to PHP's htmlspecialchars with ENT_QUOTES.
     *
     * @param array<string, mixed> $input Source array
     * @param string $key Array key to read
     * @param int $maxLength Maximum allowed length (0 = no truncation)
     * @return string
     */
    public function sanitizeString(array $input, string $key, int $maxLength = 0): string
    {
        if (!isset($input[$key])) {
            return '';
        }

        $value = (string)$input[$key];

        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        if ($maxLength > 0) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Sanitise a boolean flag from user input
     *
     * Treats '1', 'true', 'yes', 'on' (case-insensitive) as true.
     *
     * @param array<string, mixed> $input Source array
     * @param string $key Array key to read
     * @return bool
     */
    public function sanitizeBool(array $input, string $key): bool
    {
        if (!isset($input[$key])) {
            return false;
        }

        return in_array(strtolower((string)$input[$key]), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Sanitise an array of integer IDs from a comma-separated string
     *
     * @param array<string, mixed> $input Source array
     * @param string $key Array key to read
     * @return array<int>
     */
    public function sanitizeIntList(array $input, string $key): array
    {
        if (empty($input[$key])) {
            return [];
        }

        $parts = explode(',', (string)$input[$key]);
        $ids = array_map(static fn(string $p) => (int)$p, $parts);
        $ids = array_filter($ids, static fn(int $id) => $id > 0);

        return array_unique(array_values($ids));
    }

    /**
     * Validate a quiz ID and return its sanitised integer form
     *
     * @param array<string, mixed> $input Source array
     * @param string $key Array key to read (default 'id_quiz')
     * @return int Positive quiz ID
     * @throws \InvalidArgumentException When the ID is missing or invalid
     */
    public function requireQuizId(array $input, string $key = 'id_quiz'): int
    {
        $id = $this->sanitizeInt($input, $key, 1);
        if ($id < 1) {
            throw new \InvalidArgumentException('A valid quiz ID is required.');
        }
        return $id;
    }

    /**
     * Validate a user ID and return its sanitised integer form
     *
     * @param array<string, mixed> $input Source array
     * @param string $key Array key to read (default 'id_user')
     * @return int Positive user ID
     * @throws \InvalidArgumentException When the ID is missing or invalid
     */
    public function requireUserId(array $input, string $key = 'id_user'): int
    {
        $id = $this->sanitizeInt($input, $key, 1);
        if ($id < 1) {
            throw new \InvalidArgumentException('A valid user ID is required.');
        }
        return $id;
    }

    /**
     * Validate a session token (md5 hex string)
     *
     * @param array<string, mixed> $input Source array
     * @param string $key Array key to read (default 'id_session')
     * @return string Validated 32-character hex string
     * @throws \InvalidArgumentException When the token is invalid
     */
    public function requireSessionId(array $input, string $key = 'id_session'): string
    {
        $token = trim((string)($input[$key] ?? ''));
        if (!preg_match('/^[0-9a-f]{32}$/i', $token)) {
            throw new \InvalidArgumentException('Invalid session token.');
        }
        return $token;
    }

    /**
     * Sanitise a session token from user input.
     *
     * Strips all characters except hexadecimal digits. Returns an empty string
     * when no valid token is present — callers that need a non-empty token should
     * use requireSessionId() instead.
     *
     * @param array<string, mixed> $input Source array
     * @param string $key Array key to read (default 'id_session')
     * @return string Sanitised hex token (may be empty)
     */
    public function sanitizeSessionToken(array $input, string $key = 'id_session'): string
    {
        return preg_replace('/[^0-9a-f]/i', '', (string)($input[$key] ?? '')) ?? '';
    }
}
