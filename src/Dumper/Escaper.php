<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Dumper;

use Symfony\Component\Yaml\Escaper as SymfonyEscaper;

/**
 * Extended YAML Escaper with ungreedy quoting options.
 *
 * Provides less aggressive quoting behavior compared to Symfony's default
 * escaper, only requiring quotes when absolutely necessary for correct
 * YAML parsing.
 */
class Escaper extends SymfonyEscaper {

  /**
   * Determines if a string requires single quoting in YAML (ungreedy version).
   *
   * This function is less aggressive than Symfony's version, only requiring
   * quotes when absolutely necessary for correct YAML parsing.
   *
   * @param string $value
   *   The string value to check.
   *
   * @return bool
   *   TRUE if the string requires quoting, FALSE otherwise.
   *
   * @see https://www.yaml.info/learn/quote.html
   */
  public static function requiresSingleQuotingValueString(string $value): bool {
    // Empty strings don't need quoting.
    if ($value === '') {
      return FALSE;
    }

    // Check if the entire value matches YAML reserved values
    // (case-insensitive).
    $lower_value = \strtolower($value);
    if (\in_array($lower_value, [
      'null', '~', 'true', 'false', 'y', 'n', 'yes', 'no', 'on', 'off',
    ])) {
      return TRUE;
    }

    // Check if the value looks like a number (int, float, scientific
    // notation).
    $number_pattern = '/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/';
    if (\is_numeric($value) || \preg_match($number_pattern, $value)) {
      return TRUE;
    }

    // Check if starts with problematic characters that have special meaning
    // in YAML.
    if (\preg_match('/^[!&*:?#|>@`]/', $value)) {
      return TRUE;
    }

    // Check if starts with whitespace or ends with whitespace.
    if (\preg_match('/^\s|\s$/', $value)) {
      return TRUE;
    }

    // Only require quoting for very specific structural characters that
    // would break YAML parsing in key-value contexts.
    // Most content, even with commas, quotes, brackets, etc. can be
    // unquoted.
    if (\preg_match('/^[\[\]{}]/', $value)) {
      return TRUE;
    }
    // Special case: if string starts with a single quote, it needs escaping.
    // If none of the above conditions are met, the string can be safely
    // unquoted.
    return \str_starts_with($value, "'");
  }

}
