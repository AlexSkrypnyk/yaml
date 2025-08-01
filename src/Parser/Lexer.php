<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Parser;

use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;

/**
 * YAML lexer that tokenizes YAML content into AST nodes.
 */
class Lexer {

  /**
   * Tokenize a YAML file.
   *
   * @param string $file_path
   *   The path to the YAML file.
   *
   * @return array<Node>
   *   Array of tokenized nodes.
   */
  public function tokenizeFile(string $file_path): array {
    $content = file_get_contents($file_path);
    if ($content === FALSE) {
      return [];
    }
    return $this->tokenize($content);
  }

  /**
   * Tokenize YAML content into an array of nodes.
   *
   * @param string $content
   *   The YAML content to tokenize.
   *
   * @return array<Node>
   *   Array of tokenized nodes.
   */
  public function tokenize(string $content): array {
    $tokens = [];

    // Split content into lines, preserving line endings.
    $lines = explode("\n", $content);

    $i = 0;
    while ($i < count($lines)) {
      $line = $lines[$i];
      $token = $this->tokenizeLine($line, $i);

      // Check if this starts a multiline block.
      if ($token->type === NodeType::KeyValue && is_string($token->value) && $this->isMultilineBlockStart($token->value)) {
        $block_type = $this->getBlockType($token->value);

        // Process the block content.
        $i++;
        $base_indent = $this->getBaseIndentForBlock($lines, $i, $token->indent);
        $block_content = [];
        $block_lines = [];

        while ($i < count($lines)) {
          $block_line = $lines[$i];

          // Empty line - check if it's within the block or ends the block.
          if (trim($block_line) === '') {
            // For empty lines, we need to determine if they're part of the
            // block
            // content
            // or if they come after the block and should be separate
            // tokens.
            // Look ahead to see if there's more block content coming.
            $has_more_block_content = FALSE;
            $counter = count($lines);
            for ($j = $i + 1; $j < $counter; $j++) {
              $next_line = $lines[$j];
              if (trim($next_line) === '') {
                // Skip blank lines when looking ahead.
                continue;
              }
              $next_indent = strlen($next_line) - strlen(ltrim($next_line));
              if ($next_indent >= $base_indent) {
                $has_more_block_content = TRUE;
              }
              break;
            }

            if ($has_more_block_content) {
              // This blank line is within the block content.
              $block_content[] = '';
              $block_lines[] = $block_line;
              $i++;
              continue;
            }
            else {
              // This blank line comes after the block - end the block and let
              // it be processed separately.
              $i--;
              break;
            }
          }

          $block_indent = strlen($block_line) - strlen(ltrim($block_line));

          // Check if we're still in the block (proper indentation)
          if ($block_indent < $base_indent) {
            // End of block, backtrack.
            $i--;
            break;
          }

          // Add content line, preserving relative indentation beyond base.
          // Remove only the base indentation, keep additional indentation.
          if (strlen($block_line) >= $base_indent) {
            $content_line = substr($block_line, $base_indent);
          }
          else {
            // Line has less indentation than base, keep as-is after ltrim.
            $content_line = ltrim($block_line);
          }
          $block_content[] = $content_line;
          $block_lines[] = $block_line;
          $i++;
        }

        // Create single block node with full content.
        $block_value = implode("\n", $block_content);
        if (!empty($block_content)) {
          // YAML literal blocks end with newline.
          $block_value .= "\n";
        }

        $token->type = $block_type === '|' ? NodeType::LiteralBlock : NodeType::FoldedBlock;
        $token->value = $block_value;
        // Store original block lines for potential reconstruction.
        $token->rawLine = $token->rawLine . "\n" . implode("\n", $block_lines);

        $tokens[] = $token;
      }
      else {
        $tokens[] = $token;
      }

      $i++;
    }

    return $tokens;
  }

  /**
   * Check if a value indicates the start of a multiline block.
   */
  protected function isMultilineBlockStart(string $value): bool {
    return in_array(trim($value), ['|', '>']);
  }

  /**
   * Get the block type character (| or >).
   */
  protected function getBlockType(string $value): string {
    return trim($value);
  }

  /**
   * Determine the base indentation level for a multiline block.
   *
   * @param array<string> $lines
   *   Array of YAML lines.
   * @param int $start_index
   *   Starting index to look from.
   * @param int $key_indent
   *   Indentation level of the key.
   *
   * @return int
   *   The base indentation level.
   */
  protected function getBaseIndentForBlock(array $lines, int $start_index, int $key_indent): int {
    // Look for the first non-empty line after the block start.
    $counter = count($lines);
    // Look for the first non-empty line after the block start.
    for ($i = $start_index; $i < $counter; $i++) {
      $line = $lines[$i];
      if (trim($line) !== '') {
        return strlen($line) - strlen(ltrim($line));
      }
    }

    // Default to key indentation + 2 if no content found.
    return $key_indent + 2;
  }

  /**
   * Tokenize a single line into a Node.
   */
  public function tokenizeLine(string $line, int $line_number): Node {
    $trimmed = ltrim($line);
    $indent = strlen($line) - strlen($trimmed);

    if (trim($line) === '') {
      return new Node(NodeType::BlankLine, [
        'rawLine' => '',
        'indent' => $indent,
      ]);
    }

    if (str_starts_with($trimmed, '#')) {
      return new Node(NodeType::Comment, [
        'rawLine' => $line,
        'indent' => $indent,
      ]);
    }

    if (preg_match('/^- (.+)$/', $trimmed, $m)) {
      return new Node(NodeType::SequenceItem, [
        'value' => trim($m[1]),
        'rawLine' => $line,
        'indent' => $indent,
      ]);
    }

    if (preg_match('/^([^\s:#]+):\s*(.*?)$/', $trimmed, $m)) {
      $key = $m[1];
      $value = $m[2];

      // Detect mapping start: key: (with no value)
      if ($value === '') {
        return new Node(NodeType::MappingStart, [
          'key' => $key,
          'rawLine' => $line,
          'indent' => $indent,
        ]);
      }
      // Detect literal/folded block start: key: | or key: >.
      elseif (in_array(trim($value), ['|', '>'])) {
        // This will be converted to block node in tokenize() method.
        return new Node(NodeType::KeyValue, [
          'key' => $key,
          'value' => $value,
          'rawLine' => $line,
          'indent' => $indent,
        ]);
      }
      else {
        return new Node(NodeType::KeyValue, [
          'key' => $key,
          'value' => $value,
          'rawLine' => $line,
          'indent' => $indent,
        ]);
      }
    }

    // Fallback as unknown content — could be multiline or edge case.
    // Fallback to COMMENT for safety.
    return new Node(NodeType::Comment, [
      'rawLine' => $line,
      'indent' => $indent,
    ]);
  }

}
