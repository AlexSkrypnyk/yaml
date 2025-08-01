<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Internal\Comments;

/**
 * Represents a parsed YAML line with structured components.
 */
class Line {

  /**
   * Indentation level (0, 1, 2, etc.).
   */
  protected readonly int $level;

  /**
   * Key name (if exists).
   */
  protected readonly ?string $key;

  /**
   * Value (if exists).
   */
  protected readonly ?string $value;

  /**
   * Normalized key using key normalization algorithm.
   */
  protected readonly string $normalizedKey;

  /**
   * Normalized value using value normalization algorithm.
   */
  protected readonly string $normalizedValue;

  /**
   * Original line content for reference.
   */
  protected readonly string $original;

  /**
   * Line position in the file.
   */
  protected readonly int $position;

  public function __construct(string $line, int $position = 0) {
    assert($position >= 0, 'Line position must be zero or greater');

    $this->original = $line;
    $this->position = $position;

    $this->level = self::calculateLevel($line);

    // Extract key and value for content lines.
    $key = NULL;
    $value = NULL;
    if (!$this->isComment() && !$this->isBlank()) {
      self::extractKeyValue($line, $key, $value);
    }

    $this->key = $key;
    $this->value = $value;
    $this->normalizedKey = $key !== NULL ? self::normalizeKey($key) : '';
    $this->normalizedValue = $value !== NULL ? self::normalizeValue($value) : '';
  }

  /**
   * Check if this line has content (not comment or blank).
   */
  public function hasContent(): bool {
    return !$this->isComment() && !$this->isBlank();
  }

  /**
   * Check if this line has a key.
   */
  public function hasKey(): bool {
    return $this->key !== NULL && $this->key !== '';
  }

  /**
   * Check if this line has a value.
   */
  public function hasValue(): bool {
    return $this->value !== NULL && $this->value !== '';
  }

  /**
   * Check if a line is a comment.
   */
  public function isComment(): bool {
    return str_starts_with(trim($this->original), '#');
  }

  /**
   * Check if a line is blank or whitespace only.
   */
  public function isBlank(): bool {
    return trim($this->original) === '';
  }

  /**
   * Get the original line content.
   */
  public function getOriginal(): string {
    return $this->original;
  }

  /**
   * Get the line position in the file.
   */
  public function getPosition(): int {
    return $this->position;
  }

  /**
   * Get the normalized key.
   */
  public function getNormalizedKey(): string {
    return $this->normalizedKey;
  }

  /**
   * Get the normalized value.
   */
  public function getNormalizedValue(): string {
    return $this->normalizedValue;
  }

  /**
   * Get the indentation level.
   */
  public function getLevel(): int {
    return $this->level;
  }

  /**
   * Get the key (if exists).
   */
  public function getKey(): ?string {
    return $this->key;
  }

  /**
   * Get the value (if exists).
   */
  public function getValue(): ?string {
    return $this->value;
  }

  /**
   * Get a human-readable string representation of this line.
   */
  public function __toString(): string {
    return $this->original;
  }

  /**
   * Calculate indentation level of a line.
   */
  protected static function calculateLevel(string $line): int {
    $trimmed = ltrim($line);
    if ($trimmed === '') {
      // Blank lines have no meaningful level.
      return 0;
    }

    $leading_spaces = strlen($line) - strlen($trimmed);
    // Assume 2 spaces per level (standard YAML)
    return intval($leading_spaces / 2);
  }

  /**
   * Extract key and value from a YAML line.
   */
  protected static function extractKeyValue(string $line, ?string &$key, ?string &$value): void {
    $trimmed = trim($line);

    // Handle array items (- item)
    if (str_starts_with($trimmed, '- ')) {
      $key = '-';
      $value = trim(substr($trimmed, 2));
      return;
    }

    // Handle key-value pairs (key: value)
    $colon_pos = strpos($trimmed, ':');
    if ($colon_pos !== FALSE) {
      $key = trim(substr($trimmed, 0, $colon_pos));
      $remainder = trim(substr($trimmed, $colon_pos + 1));

      // Handle cases like "key:" (no value) vs "key: value".
      if ($remainder !== '') {
        $value = $remainder;
      }
    }
    else {
      // Lines without colons might be standalone values or malformed.
      $value = $trimmed;
    }
  }

  /**
   * Normalize a key for fuzzy matching (conservative approach).
   */
  protected static function normalizeKey(string $key): string {
    // Remove quotes around keys.
    $key = trim($key, '"\'');

    // Remove underscores and hyphens, keep alphanumeric.
    $normalized = preg_replace('/[^a-zA-Z0-9]/', '', $key);

    // Convert to lowercase for case-insensitive matching.
    return strtolower($normalized ?? '');
  }

  /**
   * Normalize a value for fuzzy matching (aggressive approach).
   */
  protected static function normalizeValue(string $value): string {
    // Remove quotes, brackets, and other formatting.
    $value = trim($value, '"\'[]{}');

    // Handle common YAML values.
    $value = trim($value);

    // Special handling for YAML literal/folded block indicators
    if ($value === '|' || $value === '>') {
      return 'literalblock';
    }

    // Remove all non-alphanumeric characters except for important content.
    $normalized = preg_replace('/[^a-zA-Z0-9]/', '', $value);

    // Convert to lowercase.
    return strtolower($normalized ?? '');
  }

}
