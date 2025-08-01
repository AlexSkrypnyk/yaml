<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml;

use AlexSkrypnyk\Yaml\Escaper;
use Symfony\Component\Yaml\Escaper as SymfonyEscaper;
use Symfony\Component\Yaml\Unescaper as SymfonyUnescaper;

/**
 * YAML Unescaper for single quoted value strings.
 *
 * Provides functionality to remove unnecessary single quotes from YAML
 * string values based on context and flags.
 */
class Unescaper extends SymfonyUnescaper {

  /**
   * Remove unnecessary single quotes from string values.
   *
   * @param string $content
   *   The YAML content to process.
   * @param array<string>|null $original_lines
   *   Lines of the original YAML content for context.
   * @param int $flags
   *   A bit field of DUMP_* constants to customize behavior.
   *
   * @return string
   *   The processed YAML content with unnecessary quotes removed.
   */
  public static function unescapeSingleQuotedValueString(string $content, ?array $original_lines = NULL, int $flags = 0): string {
    if ($original_lines === NULL) {
      return $content;
    }

    // Build a map of originally quoted strings to preserve them.
    $originally_quoted = static::buildOriginallyQuotedMap($original_lines);

    // Parse the content to build context-aware unquoting.
    $lines = explode("\n", $content);
    $context_stack = [];
    $processed_lines = [];

    foreach ($lines as $line) {
      // Check if this is a key-value line with quoted value.
      if (preg_match('/^(\s*)(\w+):\s\'([^\']*)\'$/', $line, $matches)) {
        $current_indent = strlen($matches[1]);
        $key = $matches[2];
        $quoted_value = $matches[3];

        // Adjust context stack based on indentation.
        while (count($context_stack) > 0 && end($context_stack)['indent'] >= $current_indent) {
          array_pop($context_stack);
        }

        // Add current key to context.
        $context_stack[] = ['key' => $key, 'indent' => $current_indent];

        // Build full context path.
        $context_path = implode(':', array_column($context_stack, 'key'));
        $full_context = $context_path . ':' . $quoted_value;

        // Check if this should remain quoted.
        if (isset($originally_quoted[$full_context])) {
          // Keep the quotes.
          $processed_lines[] = $line;
        }
        else {
          // Check if ungreedy quoting is enabled.
          // DUMP_UNGREEDY_SINGLE_QUOTING.
          $use_ungreedy = ($flags & (1 << 10)) !== 0;
          $requires = $use_ungreedy
            ? Escaper::requiresSingleQuotingUngreedy($matches[3])
            : SymfonyEscaper::requiresSingleQuoting($matches[3]);

          if ($requires) {
            $matches[3] = SymfonyEscaper::escapeWithSingleQuotes($matches[3]);
          }
          // Remove the quotes.
          $processed_lines[] = $matches[1] . $matches[2] . ': ' . $matches[3];
        }
      }
      elseif (preg_match('/^(\s*)(\w+):\s*(.*)$/', $line, $matches)) {
        // Non-quoted key-value line - update context but don't modify.
        $current_indent = strlen($matches[1]);
        $key = $matches[2];

        // Adjust context stack based on indentation.
        while (count($context_stack) > 0 && end($context_stack)['indent'] >= $current_indent) {
          array_pop($context_stack);
        }

        // Add current key to context.
        $context_stack[] = ['key' => $key, 'indent' => $current_indent];
        $processed_lines[] = $line;
      }
      elseif (preg_match("/^(\s*-\s)'([a-zA-Z0-9_-]+|{{[^}]+}})'$/", $line, $matches)) {
        $value = $matches[2];

        // Check if ungreedy quoting is enabled.
        // DUMP_UNGREEDY_SINGLE_QUOTING.
        $use_ungreedy = ($flags & (1 << 10)) !== 0;
        $requires = $use_ungreedy
          ? Escaper::requiresSingleQuotingUngreedy($value)
          : SymfonyEscaper::requiresSingleQuoting($value);

        if ($requires) {
          $value = SymfonyEscaper::escapeWithSingleQuotes($value);
        }

        $processed_lines[] = isset($originally_quoted[$value]) ? $line : $matches[1] . $value;
      }
      else {
        // Line doesn't match any patterns - keep as-is.
        $processed_lines[] = $line;
      }
    }

    return implode("\n", $processed_lines);
  }

  /**
   * Build a map of strings that were originally quoted in the source YAML.
   *
   * @param array<string> $lines
   *   Lines of the original YAML content.
   *
   * @return array<string, bool>
   *   Map of context paths to TRUE if they were originally quoted.
   */
  protected static function buildOriginallyQuotedMap(array $lines): array {
    $quoted_strings = [];
    $context_stack = [];

    foreach ($lines as $line) {
      // Skip empty lines and comments.
      $trimmed = trim($line);
      if ($trimmed === '' || $trimmed[0] === '#') {
        continue;
      }

      // Check if this is a key-value line.
      if (preg_match('/^(\s*)(\w+):\s*(.*)$/', $line, $matches)) {
        $current_indent = strlen($matches[1]);
        $key = $matches[2];
        $value = $matches[3];

        // Adjust context stack based on indentation.
        while (count($context_stack) > 0 && end($context_stack)['indent'] >= $current_indent) {
          array_pop($context_stack);
        }

        // Add current key to context.
        $context_stack[] = ['key' => $key, 'indent' => $current_indent];

        // Build full context path.
        $context_path = implode(':', array_column($context_stack, 'key'));

        // Check if value is quoted and extract content.
        if (preg_match('/^\'(.*)\'$/', $value, $value_match)) {
          $unquoted_value = $value_match[1];
          $full_context = $context_path . ':' . $unquoted_value;
          $quoted_strings[$full_context] = TRUE;
        }
      }

      // Handle array items.
      if (preg_match("/^\s*-\s'([a-zA-Z0-9_-]+|{{[^}]+}})'$/", $line, $matches)) {
        $quoted_strings[$matches[1]] = TRUE;
      }
    }

    return $quoted_strings;
  }

}