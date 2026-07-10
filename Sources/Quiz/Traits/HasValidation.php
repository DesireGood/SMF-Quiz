<?php

declare(strict_types=1);

namespace Quiz\Traits;

/**
 * HasValidation Trait
 *
 * Provides reusable input validation helpers for controllers and services.
 *
 * @package Quiz\Traits
 */
trait HasValidation
{
    /**
     * Validation errors collected during a validation pass
     *
     * @var array<string, string>
     */
    private array $validationErrors = [];

    /**
     * Validate that a value is a positive integer
     *
     * @param mixed $value Value to check
     * @param string $field Field name for error messages
     * @return bool
     */
    protected function validatePositiveInt(mixed $value, string $field): bool
    {
        if (!is_numeric($value) || (int)$value <= 0) {
            $this->validationErrors[$field] = "Field '$field' must be a positive integer.";
            return false;
        }
        return true;
    }

    /**
     * Validate that a value is a non-negative integer
     *
     * @param mixed $value Value to check
     * @param string $field Field name for error messages
     * @return bool
     */
    protected function validateNonNegativeInt(mixed $value, string $field): bool
    {
        if (!is_numeric($value) || (int)$value < 0) {
            $this->validationErrors[$field] = "Field '$field' must be a non-negative integer.";
            return false;
        }
        return true;
    }

    /**
     * Validate that a string is not empty after trimming
     *
     * @param mixed $value Value to check
     * @param string $field Field name for error messages
     * @param int $maxLength Maximum allowed length (0 = no limit)
     * @return bool
     */
    protected function validateRequiredString(mixed $value, string $field, int $maxLength = 0): bool
    {
        if (!is_string($value) || trim($value) === '') {
            $this->validationErrors[$field] = "Field '$field' is required.";
            return false;
        }
        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            $this->validationErrors[$field] = "Field '$field' exceeds maximum length of $maxLength characters.";
            return false;
        }
        return true;
    }

    /**
     * Validate that a value is in an allowed set
     *
     * @param mixed $value Value to check
     * @param array<mixed> $allowed Allowed values
     * @param string $field Field name for error messages
     * @return bool
     */
    protected function validateInList(mixed $value, array $allowed, string $field): bool
    {
        if (!in_array($value, $allowed, true)) {
            $this->validationErrors[$field] = "Field '$field' contains an invalid value.";
            return false;
        }
        return true;
    }

    /**
     * Validate that a value is a valid URL
     *
     * @param mixed $value Value to check
     * @param string $field Field name for error messages
     * @return bool
     */
    protected function validateUrl(mixed $value, string $field): bool
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->validationErrors[$field] = "Field '$field' must be a valid URL.";
            return false;
        }
        return true;
    }

    /**
     * Validate a boolean-like value (0/1/true/false)
     *
     * @param mixed $value Value to check
     * @param string $field Field name for error messages
     * @return bool
     */
    protected function validateBool(mixed $value, string $field): bool
    {
        if (!in_array($value, [0, 1, '0', '1', true, false], true)) {
            $this->validationErrors[$field] = "Field '$field' must be a boolean value.";
            return false;
        }
        return true;
    }

    /**
     * Check whether validation has passed (no errors collected)
     *
     * @return bool True if no errors
     */
    protected function isValid(): bool
    {
        return empty($this->validationErrors);
    }

    /**
     * Get all collected validation errors
     *
     * @return array<string, string>
     */
    protected function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Reset validation errors
     *
     * @return void
     */
    protected function resetValidation(): void
    {
        $this->validationErrors = [];
    }
}
