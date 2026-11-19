<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Validator
{
    public const STUDENT_NO_PATTERN = '/^\d{4}-\d{5}-[A-Z]{2}-\d$/';
    public const PHONE_PATTERN = '/^[0-9+() -]{7,20}$/';

    /**
     * Returns the first error for each field. Rules: required, email, max:n, password, same:field,
     * accepted and regex:pattern. Empty optional values skip the other rules.
     *
     * @param array<string, string> $data
     * @param array<string, array{label: string, rules: list<string>, message?: string}> $fields
     *        "message" replaces the error text of a regex rule.
     * @return array<string, string>
     */
    public static function check(array $data, array $fields): array
    {
        $errors = [];

        foreach ($fields as $name => $field) {
            $value = $data[$name] ?? '';

            foreach ($field['rules'] as $rule) {
                $error = self::error($rule, $value, $data, $field);

                if ($error !== null) {
                    $errors[$name] = $error;
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $data
     * @param array{label: string, rules: list<string>, message?: string} $field
     */
    private static function error(string $rule, string $value, array $data, array $field): ?string
    {
        [$name, $argument] = array_pad(explode(':', $rule, 2), 2, '');
        $label = $field['label'];

        if ($name === 'required') {
            return $value === '' ? "{$label} is required." : null;
        }

        if ($name === 'accepted') {
            return $value === '' ? "{$label} is required." : null;
        }

        if ($value === '') {
            return null;
        }

        return match ($name) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'Enter a valid email address.' : null,
            'max' => mb_strlen($value) > (int) $argument ? "{$label} must be {$argument} characters or fewer." : null,
            'password' => self::passwordError($value),
            'same' => $value !== ($data[$argument] ?? null) ? "{$label} does not match." : null,
            'regex' => preg_match($argument, $value) !== 1 ? ($field['message'] ?? "{$label} is not valid.") : null,
            default => throw new InvalidArgumentException("Unknown validation rule: {$name}"),
        };
    }

    private static function passwordError(string $value): ?string
    {
        if (strlen($value) < 8 || preg_match('/\pL/u', $value) !== 1 || preg_match('/\d/', $value) !== 1) {
            return 'Password must be at least 8 characters and include letters and numbers.';
        }

        // bcrypt ignores everything after 72 bytes.
        return strlen($value) > 72 ? 'Password must be 72 characters or fewer.' : null;
    }
}
