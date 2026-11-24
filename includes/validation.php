<?php
/**
 * Server-side input validation. Browser (HTML5) checks are only for convenience;
 * every rule is enforced again here because attackers can bypass the browser.
 */
class Validator
{
    private array $errors = [];

    public function __construct(private array $data) {}

    public function value(string $field): string
    {
        $v = $this->data[$field] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    public function required(string $field, string $label): static
    {
        if ($this->value($field) === '') {
            $this->errors[$field] ??= "$label is required.";
        }
        return $this;
    }

    public function length(string $field, string $label, int $min, int $max): static
    {
        $len = mb_strlen($this->value($field));
        if ($this->value($field) !== '' && ($len < $min || $len > $max)) {
            $this->errors[$field] ??= "$label must be between $min and $max characters.";
        }
        return $this;
    }

    public function email(string $field): static
    {
        $v = $this->value($field);
        if ($v !== '' && (!filter_var($v, FILTER_VALIDATE_EMAIL) || mb_strlen($v) > 150)) {
            $this->errors[$field] ??= 'Enter a valid email address.';
        }
        return $this;
    }

    public function name(string $field, string $label): static
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match("/^[\p{L} .'-]+$/u", $v)) {
            $this->errors[$field] ??= "$label may contain only letters, spaces, hyphens and apostrophes.";
        }
        return $this;
    }

    public function phone(string $field): static
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^\+?[0-9]{10,15}$/', $v)) {
            $this->errors[$field] ??= 'Enter a valid phone number (10-15 digits, e.g. 08012345678).';
        }
        return $this;
    }

    /** At least 8 characters with upper case, lower case, a number and a symbol. */
    public function strongPassword(string $field): static
    {
        $v = $this->data[$field] ?? '';
        if (!is_string($v) || strlen($v) < 8 || strlen($v) > 72
            || !preg_match('/[A-Z]/', $v) || !preg_match('/[a-z]/', $v)
            || !preg_match('/[0-9]/', $v) || !preg_match('/[^A-Za-z0-9]/', $v)) {
            $this->errors[$field] ??= 'Password must be 8-72 characters and include upper case, lower case, a number and a symbol.';
        }
        return $this;
    }

    public function matches(string $field, string $other, string $message): static
    {
        if (($this->data[$field] ?? '') !== ($this->data[$other] ?? '')) {
            $this->errors[$field] ??= $message;
        }
        return $this;
    }

    public function integerBetween(string $field, string $label, int $min, int $max): static
    {
        $v = filter_var($this->value($field), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($v === false) {
            $this->errors[$field] ??= "$label must be a whole number from $min to $max.";
        }
        return $this;
    }

    public function in(string $field, string $label, array $allowed): static
    {
        if (!in_array($this->value($field), array_map('strval', $allowed), true)) {
            $this->errors[$field] ??= "Select a valid $label.";
        }
        return $this;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

/** Render a field's error message (escaped). */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="field-error">' . e($errors[$field]) . '</p>' : '';
}
