<?php

namespace App\Exceptions;

use Exception;

class ConfigurationException extends Exception
{
    /**
     * Create a new configuration exception for a missing field.
     */
    public static function missingField(string $field): self
    {
        return new self("Configuration field '{$field}' is required but missing.");
    }

    /**
     * Create a new configuration exception for an invalid type.
     */
    public static function invalidType(string $field, string $expected, string $actual): self
    {
        return new self("Configuration field '{$field}' must be of type {$expected}, {$actual} given.");
    }

    /**
     * Create a new configuration exception for an invalid value.
     */
    public static function invalidValue(string $field, string $message): self
    {
        return new self("Configuration field '{$field}' has an invalid value: {$message}");
    }

    /**
     * Create a new configuration exception for an invalid file.
     */
    public static function invalidFile(string $path, string $reason): self
    {
        return new self("Configuration file '{$path}' is invalid: {$reason}");
    }
}
