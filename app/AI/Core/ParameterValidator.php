<?php

namespace App\AI\Core;

class ParameterValidator
{
    /**
     * Validate parameters against a schema
     */
    public static function validate(array $parameters, array $schema): array
    {
        $errors = [];

        // Check required fields
        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (!isset($parameters[$field])) {
                $errors[$field] = "Field '{$field}' is required";
            }
        }

        // Validate property types
        $properties = $schema['properties'] ?? [];
        foreach ($parameters as $key => $value) {
            if (!isset($properties[$key])) {
                continue; // Allow extra fields, ignore them
            }

            $property = $properties[$key];
            $type = $property['type'] ?? null;

            if ($type && !self::validateType($value, $type)) {
                $errors[$key] = "Field '{$key}' must be of type {$type}";
            }

            // Validate enum values
            if (isset($property['enum']) && !in_array($value, $property['enum'])) {
                $errors[$key] = "Field '{$key}' must be one of: " . implode(', ', $property['enum']);
            }

            // Validate min/max length
            if ($type === 'string') {
                if (isset($property['minLength']) && strlen($value) < $property['minLength']) {
                    $errors[$key] = "Field '{$key}' must be at least {$property['minLength']} characters";
                }
                if (isset($property['maxLength']) && strlen($value) > $property['maxLength']) {
                    $errors[$key] = "Field '{$key}' must be at most {$property['maxLength']} characters";
                }
            }
        }

        return $errors;
    }

    /**
     * Check if value matches the specified type
     */
    private static function validateType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value), // In PHP, objects are represented as arrays in JSON
            default => true,
        };
    }
}
