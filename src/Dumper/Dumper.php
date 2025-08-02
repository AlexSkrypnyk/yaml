<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Dumper;

use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * YAML dumper that preserves comments and formatting.
 */
class Dumper {

  /**
   * Dump AST nodes to YAML string.
   *
   * @param array<Node> $ast_nodes
   *   Array of AST nodes to dump.
   * @param array<string, mixed> $options
   *   Dump options array.
   *
   * @return string
   *   YAML string representation.
   */
  public function dump(array $ast_nodes, array $options = []): string {
    $lines = [];

    foreach ($ast_nodes as $node) {
      $this->dumpNode($node, $lines, $options);
    }

    if (empty($lines)) {
      // Empty arrays should return just a newline.
      return PHP_EOL;
    }

    $result = implode(PHP_EOL, $lines);

    // Only add final newline if the result doesn't already end with one.
    if (!str_ends_with($result, PHP_EOL)) {
      $result .= PHP_EOL;
    }

    return $result;
  }

  /**
   * Dump a single node to lines array.
   *
   * @param \AlexSkrypnyk\Yaml\Ast\Node $node
   *   The node to dump.
   * @param array<string> $lines
   *   Array of output lines (passed by reference).
   * @param array<string, mixed> $options
   *   Dump options array.
   */
  protected function dumpNode(Node $node, array &$lines, array $options = []): void {

    // 1. Handle comment above node
    if ($node->attachedComment !== NULL) {
      foreach (explode(PHP_EOL, $node->attachedComment) as $comment_line) {
        // Use original comment line with preserved indentation.
        $lines[] = $comment_line;
      }
    }

    // 2. Handle node type
    switch ($node->type) {
      case NodeType::Comment:
        $lines[] = $this->indent($node->rawLine ?? '#', $node->indent);
        break;

      case NodeType::BlankLine:
        $lines[] = '';
        break;

      case NodeType::KeyValue:
        // Try to preserve original value formatting from rawLine.
        $original_value = $this->getOriginalValueFormat($node);
        if ($original_value !== NULL) {
          // Parse original value to compare with current value.
          try {
            if ($node->key !== NULL) {
              $parsed_result = SymfonyYaml::parse($node->key . ': ' . $original_value);
              $original_parsed = is_array($parsed_result) ? $parsed_result[$node->key] : NULL;
            }
            else {
              $original_parsed = NULL;
            }
            // For inline format values (arrays/maps), compare semantic content.
            if ($this->isInlineFormat($node->value) && is_string($node->value) && $this->semanticallyEqual($original_parsed, $node->value)) {
              // Values are semantically the same, use original formatting.
              $value = $original_value;
            }
            elseif ($original_parsed === $node->value) {
              // Values are the same, use original formatting.
              $value = $original_value;
            }
            else {
              // Values are different, use standard formatting.
              $value = $this->formatValue($node->value);
            }
          }
          catch (\Exception $e) {
            // If parsing fails, fall back to standard formatting.
            $value = $this->formatValue($node->value);
          }
        }
        else {
          // No original formatting available, use standard formatting.
          $value = $this->formatValue($node->value);
        }
        $lines[] = $this->indent(sprintf('%s: %s', $node->key, $value), $node->indent);
        break;

      case NodeType::MappingStart:
        $lines[] = $this->indent($node->key . ':', $node->indent);
        foreach ($node->children as $child) {
          $this->dumpNode($child, $lines, $options);
        }
        break;

      case NodeType::SequenceItem:
        // Try to preserve original value formatting from rawLine for sequence
        // items too.
        $original_value = $this->getOriginalSequenceValueFormat($node);
        $value = $original_value ?? $this->formatValue($node->value);
        $lines[] = $this->indent('- ' . $value, $node->indent);
        break;

      case NodeType::LiteralBlock:
        $lines[] = $this->indent($node->key . ': |', $node->indent);
        $this->dumpBlockContent($node, $lines, $node->indent + 2, $options);
        break;

      case NodeType::FoldedBlock:
        $lines[] = $this->indent($node->key . ': >', $node->indent);
        $this->dumpBlockContent($node, $lines, $node->indent + 2, $options);
        break;
    }
  }

  /**
   * Indent a line with the specified number of spaces.
   */
  protected function indent(string $line, int $spaces): string {
    return str_repeat(' ', $spaces) . $line;
  }

  /**
   * Dump block content with proper indentation.
   *
   * @param \AlexSkrypnyk\Yaml\Ast\Node $node
   *   The block node to dump.
   * @param array<string> $lines
   *   Array of output lines (passed by reference).
   * @param int $base_indent
   *   Base indentation level.
   * @param array<string, mixed> $options
   *   Dump options array.
   */
  protected function dumpBlockContent(Node $node, array &$lines, int $base_indent, array $options = []): void {
    if ($node->value === NULL || $node->value === '') {
      return;
    }

    // Split the block content into lines.
    $content_string = is_string($node->value) ? $node->value :
      (is_scalar($node->value) ? (string) $node->value : '');
    $content_lines = explode("\n", rtrim($content_string, "\n"));

    // Apply collapse empty lines option if specified.
    if (!empty($options['collapseEmptyLines'])) {
      $content_lines = $this->collapseEmptyLines($content_lines);
    }

    foreach ($content_lines as $content_line) {
      // For literal blocks, empty lines should remain empty (no indentation)
      // to match the original YAML format.
      $lines[] = $content_line === '' ? '' : $this->indent($content_line, $base_indent);
    }
  }

  /**
   * Format a value for YAML output.
   */
  protected function formatValue(mixed $value): string {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if (is_null($value)) {
      return 'null';
    }

    if (is_numeric($value)) {
      return (string) $value;
    }

    if (is_string($value) && preg_match('/[:#\\-]|\\s/', $value)) {
      // Simple quoting rule - use single quotes to match Symfony's behavior.
      return "'" . str_replace("'", "''", $value) . "'";
    }

    return is_scalar($value) ? $value : '';
  }

  /**
   * Extract the original value format from the rawLine if available.
   */
  protected function getOriginalValueFormat(Node $node): ?string {
    if ($node->rawLine === NULL || !$node->key) {
      return NULL;
    }

    // Parse the rawLine to extract the value part after "key: ".
    $pattern = '/^\s*' . preg_quote($node->key, '/') . ':\s*(.*)$/';
    if (preg_match($pattern, $node->rawLine, $matches)) {
      return $matches[1];
    }

    return NULL;
  }

  /**
   * Extract the original value format from the rawLine for sequence items.
   */
  protected function getOriginalSequenceValueFormat(Node $node): ?string {
    if ($node->rawLine === NULL) {
      return NULL;
    }

    // Parse the rawLine to extract the value part after "- ".
    $pattern = '/^\s*-\s*(.*)$/';
    if (preg_match($pattern, $node->rawLine, $matches)) {
      return $matches[1];
    }

    return NULL;
  }

  /**
   * Collapse consecutive empty lines in literal blocks.
   *
   * @param array<string> $lines
   *   Array of content lines.
   *
   * @return array<string>
   *   Lines with empty lines removed.
   */
  protected function collapseEmptyLines(array $lines): array {
    $result = [];

    foreach ($lines as $line) {
      // Skip all empty lines when collapse flag is set.
      if (trim($line) === '') {
        continue;
      }

      $result[] = $line;
    }

    return $result;
  }

  /**
   * Check if a value represents inline array or map format.
   *
   * @param mixed $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if the value is in inline format.
   */
  protected function isInlineFormat($value): bool {
    if (!is_string($value)) {
      return FALSE;
    }

    $trimmed = trim($value);

    // Check for inline array: [item1, item2] or [item].
    if (preg_match('/^\[.*\]$/', $trimmed)) {
      return TRUE;
    }
    // Check for inline map: {key1: value1, key2: value2} or {key: value}.
    return (bool) preg_match('/^\{.*\}$/', $trimmed);
  }

  /**
   * Check if two values are semantically equal for inline format comparison.
   *
   * @param mixed $parsed_value
   *   The parsed value (e.g., PHP array).
   * @param string $inline_string
   *   The inline format string.
   *
   * @return bool
   *   TRUE if values are semantically equal.
   */
  protected function semanticallyEqual($parsed_value, string $inline_string): bool {
    try {
      // Parse the inline string as YAML to get its semantic value.
      $inline_parsed = SymfonyYaml::parse($inline_string);
      return $parsed_value === $inline_parsed;
    }
    catch (\Exception $exception) {
      return FALSE;
    }
  }

}
