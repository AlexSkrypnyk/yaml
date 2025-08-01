<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Internal\Comments;

/**
 * Represents a comment block with its associated target line and metadata.
 */
class Comment {

  /**
   * The comment lines as Line objects (can include comments and blank lines).
   *
   * @var Line[]
   */
  public readonly array $lines;

  public function __construct(array $lines) {
    $line_objects = [];
    foreach ($lines as $line) {
      if ($line instanceof Line) {
        $line_objects[] = $line;
      }
      else {
        $line_objects[] = new Line($line);
      }
    }

    $this->lines = $line_objects;
  }

  /**
   * Get only the actual comment lines (starting with #).
   *
   * @return Line[]
   *   Array of comment Line objects.
   */
  public function getCommentLines(): array {
    return array_filter($this->lines, fn($line): bool => $line->isComment());
  }

  /**
   * Get only the blank lines.
   *
   * @return Line[]
   *   Array of blank Line objects.
   */
  public function getBlankLines(): array {
    return array_filter($this->lines, fn($line): bool => $line->isBlank());
  }

  /**
   * Check if this comment block contains actual comments (not just blank lines).
   */
  public function hasRealComments(): bool {
    return !empty($this->getCommentLines());
  }

  /**
   * Check if this comment block contains only blank lines.
   */
  public function isBlankOnly(): bool {
    return empty($this->getCommentLines()) && !empty($this->getBlankLines());
  }

  /**
   * Get the number of lines in this comment block.
   */
  public function getLineCount(): int {
    return count($this->lines);
  }

  /**
   * Get the original string lines for injection back into content.
   *
   * @return string[]
   */
  public function getOriginalLines(): array {
    $original_lines = [];
    foreach ($this->lines as $line) {
      $original_lines[] = $line->getOriginal();
    }
    return $original_lines;
  }

  /**
   * Get the content of this comment block as a single string.
   *
   * @param string $eol
   *   End of line character to use for joining lines.
   *
   * @return string
   *   The comment block content as a single string.
   */
  public function getContent(string $eol = "\n"): string {
    return implode($eol, $this->getOriginalLines());
  }

  /**
   * Get the start line number of this comment block.
   *
   * @return int|null
   *   The start line number, or null if no lines.
   */
  public function getStartLine(): ?int {
    return empty($this->lines) ? NULL : $this->lines[0]->getPosition();
  }

  /**
   * Get the end line number of this comment block.
   *
   * @return int|null
   *   The end line number, or null if no lines.
   */
  public function getEndLine(): ?int {
    return empty($this->lines) ? NULL : $this->lines[count($this->lines) - 1]->getPosition();
  }

  /**
   * Get the target line number for this comment block.
   *
   * This is typically the line immediately after the comment block.
   *
   * @return int|null
   *   The target line number, or null if no lines.
   */
  public function getTargetLineNumber(): ?int {
    $end_line = $this->getEndLine();
    return $end_line !== NULL ? $end_line + 1 : NULL;
  }

  /**
   * Get the original position (same as target line number for compatibility).
   *
   * @return int
   *   The original position.
   */
  public function getOriginalPosition(): int {
    return $this->getTargetLineNumber();
  }

  /**
   * Get the target line that this comment block should be associated with.
   *
   * @param Line[] $original_parsed_lines
   *   The original parsed lines array to look up the target line.
   *
   * @return Line|null
   *   The target line or null if not found.
   */
  public function getTargetLine(array $original_parsed_lines): ?Line {
    $target_line_number = $this->getTargetLineNumber();
    return $original_parsed_lines[$target_line_number] ?? NULL;
  }

  /**
   * Check if this comment block contains a specific comment line.
   */
  public function containsComment(Line $comment_line): bool {
    foreach ($this->getCommentLines() as $existing_comment) {
      if ($existing_comment->getOriginal() === $comment_line->getOriginal()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
