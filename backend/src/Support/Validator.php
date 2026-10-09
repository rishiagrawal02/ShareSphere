<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Exceptions\ValidationFailedException;

class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;

            $isRequired = in_array('required', $ruleList, true);

            if (($value === null || $value === '' || $value === []) && !$isRequired) {
                continue;
            }

            if ($isRequired && ($value === null || $value === '' || (is_array($value) && empty($value)))) {
                $errors[$field] = "The {$field} field is required.";
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                $params = [];
                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $rule;
                }

                $error = self::applyRule($field, $value, $ruleName, $params);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = is_string($value) ? trim($value) : $value;
            }
        }

        if (!empty($errors)) {
            throw new ValidationFailedException($errors);
        }

        return $validated;
    }

    private static function applyRule(string $field, mixed $value, string $rule, array $params): ?string
    {
        switch ($rule) {
            case 'string':
                if (!is_string($value)) {
                    return "The {$field} must be a string.";
                }
                break;

            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (is_string($value) && mb_strlen($value, 'UTF-8') < $min) {
                    return "The {$field} must be at least {$min} characters.";
                }
                if (is_array($value) && count($value) < $min) {
                    return "The {$field} must contain at least {$min} items.";
                }
                break;

            case 'max':
                $max = (int) ($params[0] ?? 255);
                if (is_string($value) && mb_strlen($value, 'UTF-8') > $max) {
                    return "The {$field} must not exceed {$max} characters.";
                }
                if (is_array($value) && count($value) > $max) {
                    return "The {$field} must not contain more than {$max} items.";
                }
                break;

            case 'email':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    return "The {$field} must be a valid email address.";
                }
                break;

            case 'int':
            case 'integer':
                if (!is_int($value) && (!is_string($value) || !preg_match('/^-?\d+$/', $value))) {
                    return "The {$field} must be an integer.";
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    return "The {$field} must be a number.";
                }
                break;

            case 'min_value':
                $minVal = (float) ($params[0] ?? 0);
                if (!is_numeric($value) || (float)$value < $minVal) {
                    return "The {$field} must be at least {$minVal}.";
                }
                break;

            case 'max_value':
                $maxVal = (float) ($params[0] ?? 0);
                if (!is_numeric($value) || (float)$value > $maxVal) {
                    return "The {$field} must not exceed {$maxVal}.";
                }
                break;

            case 'in':
                if (!in_array((string)$value, $params, true)) {
                    $allowed = implode(', ', $params);
                    return "The {$field} must be one of: {$allowed}.";
                }
                break;

            case 'boolean':
                $acceptable = [true, false, 1, 0, '1', '0', 'true', 'false'];
                if (!in_array($value, $acceptable, true)) {
                    return "The {$field} must be true or false.";
                }
                break;

            case 'date':
                if (!is_string($value) || strtotime($value) === false) {
                    return "The {$field} must be a valid date.";
                }
                break;

            case 'lat':
            case 'latitude':
                if (!is_numeric($value) || (float)$value < -90 || (float)$value > 90) {
                    return "The {$field} must be a valid latitude between -90 and 90.";
                }
                break;

            case 'lng':
            case 'longitude':
                if (!is_numeric($value) || (float)$value < -180 || (float)$value > 180) {
                    return "The {$field} must be a valid longitude between -180 and 180.";
                }
                break;

            case 'regex':
                $pattern = $params[0] ?? '';
                if (!is_string($value) || !preg_match($pattern, $value)) {
                    return "The {$field} format is invalid.";
                }
                break;
        }

        return null;
    }
}
