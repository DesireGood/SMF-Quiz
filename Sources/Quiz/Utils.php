<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Sanitise a quiz session token from user input.
 *
 * Strips every character that is not a hexadecimal digit (0-9, a-f, case-insensitive).
 * Returns an empty string when the input is absent or contains no valid characters.
 *
 * @param string $raw Raw token value from user input (e.g. from $_GET)
 * @return string Sanitised hex string (may be empty)
 */
function quiz_sanitize_session_token(string $raw): string
{
    return preg_replace('/[^0-9a-f]/i', '', $raw) ?? '';
}
