<?php
/**
 * Input Validation Helper
 */

class Validator {
    private $errors = [];

    /**
     * Validate required field
     */
    public function required($field, $value, $fieldName) {
        if (empty($value) && $value !== '0') {
            $this->errors[$field] = "{$fieldName} is required";
            return false;
        }
        return true;
    }

    /**
     * Validate email
     */
    public function email($field, $value, $fieldName) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$fieldName} must be a valid email address";
            return false;
        }
        return true;
    }

    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $min, $fieldName) {
        if (strlen($value) < $min) {
            $this->errors[$field] = "{$fieldName} must be at least {$min} characters";
            return false;
        }
        return true;
    }

    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $max, $fieldName) {
        if (strlen($value) > $max) {
            $this->errors[$field] = "{$fieldName} must not exceed {$max} characters";
            return false;
        }
        return true;
    }

    /**
     * Validate numeric
     */
    public function numeric($field, $value, $fieldName) {
        if (!is_numeric($value)) {
            $this->errors[$field] = "{$fieldName} must be a number";
            return false;
        }
        return true;
    }

    /**
     * Validate range
     */
    public function range($field, $value, $min, $max, $fieldName) {
        if ($value < $min || $value > $max) {
            $this->errors[$field] = "{$fieldName} must be between {$min} and {$max}";
            return false;
        }
        return true;
    }

    /**
     * Validate enum
     */
    public function enum($field, $value, $allowedValues, $fieldName) {
        if (!in_array($value, $allowedValues)) {
            $this->errors[$field] = "{$fieldName} must be one of: " . implode(', ', $allowedValues);
            return false;
        }
        return true;
    }

    /**
     * Get all validation errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Check if validation has errors
     */
    public function hasErrors() {
        return !empty($this->errors);
    }

    /**
     * Clear all errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
}
?>
