<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Internal;

/**
 * Content parser for YAML content.
 *
 * Handles parsing content into lines and detecting line endings.
 */
class ContentParser {

  protected string $content;

  /**
   * The original YAML content split into lines.
   *
   * @var array<string>
   */
  private array $lines;

  /**
   * The detected line ending character(s) from the original content.
   */
  private string $eol;

  /**
   * Constructor.
   *
   * @param string $content
   *   The content to parse.
   */
  public function __construct(string $content) {
    $this->content = $content;
    $this->parseContent($content);
  }

  /**
   * Get the parsed lines.
   *
   * @return array<string>
   *   Array of lines from the content.
   */
  public function getLines(): array {
    return $this->lines;
  }

  /**
   * Get the detected line ending.
   *
   * @return string
   *   The line ending character(s).
   */
  public function getEol(): string {
    return $this->eol;
  }

  /**
   * Reconstruct content from lines using the original line ending.
   *
   * @param array<string> $lines
   *   Array of lines to join.
   *
   * @return string
   *   The reconstructed content.
   */
  public function reconstructContent(array $lines): string {
    return implode($this->eol, $lines);
  }

  /**
   * Collapse repeated empty lines within literal blocks.
   *
   * This function specifically targets YAML literal blocks indicated by the
   * pipe character (|) and collapses multiple consecutive empty lines that
   * occur anywhere within the literal block content (start or middle, but not
   * at the end). Empty lines outside of literal blocks are left unchanged.
   *
   * @return string
   *   The content with collapsed empty lines only within YAML literal blocks.
   */
  public function collapseLiteralBlockEmptyLines(): string {
    $lines = $this->lines;
    $result_lines = [];
    $in_literal_block = FALSE;
    $literal_block_key_indent = 0;
    $literal_block_content_indent = 0;
    $i = 0;

    while ($i < count($lines)) {
      $line = $lines[$i];

      // Check if this line starts a literal block (with optional modifiers
      // like |- or |+).
      if (preg_match('/^(\s*).*\|[-+]?\s*$/', $line, $matches)) {
        $in_literal_block = TRUE;
        $literal_block_key_indent = strlen($matches[1]);
        $literal_block_content_indent = $literal_block_key_indent + 2;
        $result_lines[] = $line;
        $i++;
        continue;
      }

      // Check if we're exiting a literal block: non-empty line with
      // indentation <= the YAML key that started the block.
      if ($in_literal_block && trim($line) !== '') {
        $current_indent = strlen($line) - strlen(ltrim($line));
        if ($current_indent <= $literal_block_key_indent) {
          $in_literal_block = FALSE;
        }
      }

      // If we're in a literal block, handle empty line collapsing.
      if ($in_literal_block && trim($line) === '') {
        // Count consecutive empty lines.
        $empty_line_count = 0;
        $j = $i;
        while ($j < count($lines) && (trim($lines[$j]) === '' || preg_match('/^\s*$/', $lines[$j]))) {
          $empty_line_count++;
          $j++;
        }

        // Check what comes after the empty lines.
        if ($j < count($lines)) {
          $next_line_indent = strlen($lines[$j]) - strlen(ltrim($lines[$j]));

          // If the next line has <= key indentation AND looks like a YAML key
          // (contains a colon), these empty lines are between YAML sections.
          if ($next_line_indent <= $literal_block_key_indent && strpos($lines[$j], ':') !== FALSE) {
            $in_literal_block = FALSE;
            $result_lines[] = $line;
            $i++;
            continue;
          }
        }

        // These empty lines are within the literal block content, so collapse
        // them.
        if ($empty_line_count > 0) {
          $i = $j;
          continue;
        }
      }

      $result_lines[] = $line;
      $i++;
    }

    return $this->reconstructContent($result_lines);
  }

  /**
   * Parse content into lines and detect line endings.
   *
   * @param string $content
   *   The content to parse.
   */
  private function parseContent(string $content): void {
    // Detect line endings: \r\n (Windows), \r (old Mac), or \n (Unix/Linux).
    if (str_contains($content, "\r\n")) {
      $this->eol = "\r\n";
    }
    elseif (str_contains($content, "\r")) {
      $this->eol = "\r";
    }
    else {
      $this->eol = "\n";
    }

    $this->lines = explode($this->eol, $content);
  }

  /**
   *
   */
  public function getContent(): string {
    return $this->content;
  }

}
