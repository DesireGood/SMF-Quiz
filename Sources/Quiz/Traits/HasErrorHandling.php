<?php

declare(strict_types=1);

namespace Quiz\Traits;

/**
 * HasErrorHandling Trait
 *
 * Provides reusable error handling helpers for controllers and services.
 * Integrates with SMF's error-reporting mechanisms.
 *
 * @package Quiz\Traits
 */
trait HasErrorHandling
{
    /**
     * Errors collected during processing
     *
     * @var array<string, string>
     */
    private array $errors = [];

    /**
     * Add an error to the error list
     *
     * @param string $key Error key/identifier
     * @param string $message Human-readable message
     * @return void
     */
    protected function addError(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    /**
     * Check whether any errors have been collected
     *
     * @return bool True if there are errors
     */
    protected function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get all collected errors
     *
     * @return array<string, string>
     */
    protected function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Clear all collected errors
     *
     * @return void
     */
    protected function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Log an error message using SMF's log_error() if available
     *
     * @param string $message Error message
     * @param string $type SMF error type ('critical', 'minor', 'template', etc.)
     * @param string|null $file File in which the error occurred
     * @param int|null $line Line number of the error
     * @return void
     */
    protected function logError(string $message, string $type = 'minor', ?string $file = null, ?int $line = null): void
    {
        if (function_exists('log_error')) {
            log_error($message, $type, $file, $line);
        }
    }

    /**
     * Fatal error — triggers SMF's fatal_lang_error() to display an error page
     *
     * Use only when processing cannot continue and the user must be informed.
     *
     * @param string $langKey Language string key for the error message
     * @param bool $allowGuest Whether guests can see the message
     * @param array<mixed> $sprintf Optional printf-style arguments for the language string
     * @return never
     */
    protected function fatalError(string $langKey, bool $allowGuest = false, array $sprintf = []): never
    {
        fatal_lang_error($langKey, $allowGuest, $sprintf);
    }

    /**
     * Handle a caught exception: log it and optionally add a user-facing error
     *
     * @param \Throwable $e Exception to handle
     * @param string $userKey Error key to expose to the user (empty = silent)
     * @param string $userMessage Human-readable message for the user (empty = use exception message)
     * @return void
     */
    protected function handleException(\Throwable $e, string $userKey = '', string $userMessage = ''): void
    {
        $this->logError($e->getMessage(), 'minor', $e->getFile(), $e->getLine());

        if ($userKey !== '') {
            $this->addError($userKey, $userMessage !== '' ? $userMessage : $e->getMessage());
        }
    }
}
