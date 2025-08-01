<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Internal\Comments;

/**
 * Manage comments in YAML content using fuzzy matching.
 *
 * This is our own implementation to replace consolidation/comments
 * with better control over empty line handling and format-change resilience.
 */
class CommentManager {

  /**
   * Parsed lines from original content.
   *
   * @var Line[]
   */
  protected array $collectedLines = [];

  /**
   * Comments found at the beginning of the file.
   *
   * @var Comment[]
   */
  protected array $headerComments = [];

  /**
   * Comments associated with specific content lines using fuzzy matching.
   *
   * @var Comment[]
   */
  protected array $comments = [];

  /**
   * Comments found at the end of the file.
   *
   * @var Comment[]
   */
  protected array $footerComments = [];

  /**
   * Fuzzy matcher for finding matches despite format changes.
   */
  protected FuzzyMatcher $fuzzyMatcher;

  public function __construct(string|array|null $content = NULL, string $eol = "\n") {
    $this->fuzzyMatcher = new FuzzyMatcher();

    if ($content !== NULL) {
      $this->collect($content, $eol);
    }
  }

  /**
   * Collect all comments from content.
   *
   * @param string|array $content
   *   Content as string or array of lines.
   * @param string $eol
   *   End of line delimiter when content is a string.
   */
  public function collect(string|array $content, string $eol = "\n"): void {
    $content_lines = is_string($content) ? explode($eol, $content) : $content;

    $content_lines = $this->removeTrailingBlankLines($content_lines);

    $this->headerComments = [];
    $this->comments = [];
    $this->footerComments = [];

    // Parse all lines into structured format.
    $this->collectedLines = [];
    foreach ($content_lines as $line_number => $line) {
      $this->collectedLines[$line_number] = new Line($line, $line_number);
    }

    // Separately, parse all comments and content lines.
    $this->collectComments();
  }

  /**
   * Inject comments back into content lines using fuzzy matching.
   */
  public function inject(string|array $content, string $eol = "\n"): array {
    $content_lines = is_string($content) ? explode($eol, $content) : $content;

    // Only remove trailing blank lines if we're actually injecting comments.
    // If content structure is unchanged, preserve original structure.
    $original_had_trailing_newline = !empty($content_lines) && end($content_lines) === '';
    $content_lines = $this->removeTrailingBlankLines($content_lines);

    // Parse candidate lines.
    $lines = [];
    foreach ($content_lines as $content_line_number => $content_line) {
      $lines[$content_line_number] = new Line($content_line, $content_line_number);
    }

    // Find all comment matches using fuzzy matching.
    $comment_matches = $this->findAllCommentMatches($lines);

    // Build result by iterating through candidate lines and injecting comments
    // at the right positions.
    // Convert Line objects to original strings for result.
    $result = [];
    
    // Check if header comments are already present in the content lines
    $content_has_header_comments = false;
    if (!empty($this->headerComments) && !empty($lines)) {
      $first_content_line = reset($lines);
      foreach ($this->headerComments as $header_comment) {
        if ($header_comment->isComment() && $first_content_line->getOriginal() === $header_comment->getOriginal()) {
          $content_has_header_comments = true;
          break;
        }
      }
    }
    
    // Only inject header comments if they're not already in the content
    if (!$content_has_header_comments) {
      foreach ($this->headerComments as $header_comment) {
        $result[] = $header_comment->getOriginal();
      }
    }

    $match_index = 0;

    foreach ($lines as $line) {
      // Check if we need to inject comments before this line.
      while ($match_index < count($comment_matches) &&
        $comment_matches[$match_index]['position'] === $line->getPosition()) {

        $match = $comment_matches[$match_index];
        $current_comment = $match['comment'];

        // Check if the comments are already present before this line.
        $should_inject = $this->shouldInjectComment($current_comment, $line->getPosition(), $lines);

        if ($should_inject) {
          $result = array_merge($result, $current_comment->getOriginalLines());
          Debug::log("\n--- INJECTING COMMENTS before line [%d] %s ---\n%s", $line->getPosition(), $line->getOriginal(), implode("\n", $current_comment->lines));
        }
        else {
          Debug::log("\n--- SKIPPING INJECTION (comments already present) before line [%d] %s ---", $line->getPosition(), $line->getOriginal());
        }

        $match_index++;
      }

      // Add the line itself.
      $result[] = $line->getOriginal();
    }

    // Add footer comments.
    foreach ($this->footerComments as $footer_comment) {
      $result[] = $footer_comment->getOriginal();
    }

    // Restore trailing newline if original had one and no comments were actually injected.
    if ($original_had_trailing_newline && count($result) === count($content_lines)) {
      // Check if we actually modified anything.
      $content_modified = FALSE;
      $counter = count($result);
      for ($i = 0; $i < $counter; $i++) {
        if ($result[$i] !== $content_lines[$i]) {
          $content_modified = TRUE;
          break;
        }
      }

      if (!$content_modified) {
        $result[] = '';
      }
    }

    return $result;
  }

  /**
   * Collect comments from parsed lines.
   */
  protected function collectComments(): void {
    $accumulated_comment_lines = [];
    $is_header_section = TRUE;

    foreach ($this->collectedLines as $line_number => $line) {
      if ($line->isComment() || $line->isBlank()) {
        $accumulated_comment_lines[] = $line;
      }
      else {
        // We hit a content line - process any accumulated comments
        if (!empty($accumulated_comment_lines)) {
          if ($is_header_section) {
            // First content line - separate header comments from line comments
            $header_lines = [];
            $line_comment_lines = [];
            
            // Find the last real comment to determine the split point
            $last_real_comment_index = -1;
            for ($i = count($accumulated_comment_lines) - 1; $i >= 0; $i--) {
              if ($accumulated_comment_lines[$i]->isComment()) {
                $last_real_comment_index = $i;
                break;
              }
            }
            
            if ($last_real_comment_index >= 0) {
              // Better splitting: the last real comment should be a line comment for the current content line
              // Only comments before the last real comment block should be headers
              
              // Find where the last comment block starts (comments + blanks that go together)
              $last_comment_block_start = $last_real_comment_index;
              
              // Look backwards to find any blank lines that should go with the last comment
              for ($i = $last_real_comment_index - 1; $i >= 0; $i--) {
                if ($accumulated_comment_lines[$i]->isBlank()) {
                  $last_comment_block_start = $i;
                } else if ($accumulated_comment_lines[$i]->isComment()) {
                  // Hit another comment, this is a separate block
                  break;
                } else {
                  break;
                }
              }
              
              // Everything before the last comment block goes to header
              for ($i = 0; $i < $last_comment_block_start; $i++) {
                $header_lines[] = $accumulated_comment_lines[$i];
              }
              
              // The last comment block goes to line comments
              for ($i = $last_comment_block_start; $i < count($accumulated_comment_lines); $i++) {
                $line_comment_lines[] = $accumulated_comment_lines[$i];
              }
            } else {
              // No real comments, all are blank lines - these are line comments, not header
              $line_comment_lines = $accumulated_comment_lines;
            }
            
            $this->headerComments = $header_lines;
            
            if (!empty($line_comment_lines)) {
              $this->comments[] = new Comment($line_comment_lines);
            }
            
            $is_header_section = FALSE;
          }
          else {
            // Subsequent content lines - all accumulated comments are line comments
            $this->comments[] = new Comment($accumulated_comment_lines);
          }
        } else {
          // No accumulated comments, but if this is the first content line, we're no longer in header section
          if ($is_header_section) {
            $is_header_section = FALSE;
          }
        }
        
        $accumulated_comment_lines = [];
      }
    }

    // Handle any remaining comments at the end
    if (!empty($accumulated_comment_lines)) {
      if ($is_header_section) {
        // Never found content, everything is header
        $this->headerComments = $accumulated_comment_lines;
      }
      else {
        // Footer comments
        $this->footerComments = $accumulated_comment_lines;
      }
    }
  }

  /**
   * Find all comments and their best match positions using fuzzy matching.
   */
  protected function findAllCommentMatches(array $candidate_parsed_lines): array {
    $comment_matches = [];

    // Find best match for each comment block.
    foreach ($this->comments as $comment_index => $comment) {
      $target_line = $comment->getTargetLine($this->collectedLines);
      if ($target_line === NULL) {
        // Skip if target line not found.
        continue;
      }

      $best_match_line = $this->fuzzyMatcher->findBestMatch(
        $target_line,
        $candidate_parsed_lines,
        $this->collectedLines,
        $comment->getOriginalPosition()
      );

      if ($best_match_line instanceof Line) {
        $comment_matches[] = [
          'position' => $best_match_line->getPosition(),
          'comment' => $comment,
          'comment_index' => $comment_index,
        ];

        Debug::log("\nCOMMENT MATCH: target=[%d] '%s' -> candidate=[%d] '%s'",
          $comment->getOriginalPosition(),
          $target_line->getOriginal(),
          $best_match_line->getPosition(),
          $best_match_line->getOriginal()
        );
      }
    }

    // Sort by position to inject in correct order.
    usort($comment_matches, fn($a, $b): int => $a['position'] <=> $b['position']);

    return $comment_matches;
  }

  /**
   * Calculate match score considering both content and position.
   */
  protected function calculateLineMatchScore(
    Line $target_line,
    Line $candidate_line,
    int $candidate_position,
    int $original_position,
  ): float {
    // Position score - closer positions get higher scores.
    $position_drift = abs($candidate_position - $original_position);
    // Too far apart.
    if ($position_drift > 15) {
      return 0.0;
    }
    $position_score = max(0, 1.0 - ($position_drift / 15));

    // Content score.
    $content_score = $this->calculateSimpleScore($target_line, $candidate_line);

    // Combine scores: content is more important than position.
    return ($content_score * 0.8) + ($position_score * 0.2);
  }

  /**
   * Calculate a simple score for ranking matches.
   */
  protected function calculateSimpleScore(Line $target_line, Line $candidate_line): float {
    $key_score = 0.0;
    $value_score = 0.0;

    // Compare normalized keys with fuzzy matching.
    if ($target_line->getNormalizedKey() !== '' && $candidate_line->getNormalizedKey() !== '') {
      if ($target_line->getNormalizedKey() === $candidate_line->getNormalizedKey()) {
        // Perfect match.
        $key_score = 1.0;
      }
      else {
        // Use string similarity for fuzzy key matching.
        $similarity_calculator = new StringSimilarity();
        $key_score = $similarity_calculator->calculateSimilarity(
          $target_line->getNormalizedKey(),
          $candidate_line->getNormalizedKey()
        );
      }
    }

    // Compare normalized values with fuzzy matching.
    if ($target_line->getNormalizedValue() !== '' && $candidate_line->getNormalizedValue() !== '') {
      if ($target_line->getNormalizedValue() === $candidate_line->getNormalizedValue()) {
        // Perfect match.
        $value_score = 1.0;
      }
      else {
        // Use string similarity for fuzzy value matching.
        $similarity_calculator = new StringSimilarity();
        $value_score = $similarity_calculator->calculateSimilarity(
          $target_line->getNormalizedValue(),
          $candidate_line->getNormalizedValue()
        );
      }
    }

    // If only key or only value exists, use that score.
    if ($key_score > 0.0 && $value_score === 0.0) {
      return $key_score;
    }
    if ($value_score > 0.0 && $key_score === 0.0) {
      // Value-only matches are slightly less reliable.
      return $value_score * 0.8;
    }

    // Weight keys more heavily than values (keys are more stable)
    if ($key_score > 0.0 && $value_score > 0.0) {
      return ($key_score * 0.7) + ($value_score * 0.3);
    }

    return 0.0;
  }

  /**
   * Remove trailing blank lines from content.
   */
  protected function removeTrailingBlankLines(array $lines): array {
    while (!empty($lines) && trim(end($lines)) === '') {
      array_pop($lines);
    }
    return $lines;
  }

  /**
   * Check if a comment block should be injected before a given position.
   *
   * Only inject if the comments are not already present before the target line.
   */
  protected function shouldInjectComment(Comment $comment_block, int $target_position, array $candidate_parsed_lines): bool {
    // For blocks with real comments, check if they already exist.
    if ($comment_block->hasRealComments()) {
      $check_range = min(10, $target_position);

      for ($i = max(0, $target_position - $check_range); $i < $target_position; $i++) {
        if (isset($candidate_parsed_lines[$i])) {
          $candidate_line = $candidate_parsed_lines[$i];

          if ($candidate_line->isComment()) {
            foreach ($comment_block->getCommentLines() as $comment_line) {
              if ($candidate_line->getOriginal() === $comment_line->getOriginal()) {
                Debug::log("    Found existing comment at position %d, skipping injection", $i);
                // This comment already exists nearby, don't inject.
                return FALSE;
              }
            }
          }
        }
      }
    }

    // For blank-line-only blocks, check if blank lines already exist right before target.
    if ($comment_block->isBlankOnly()) {
      $blank_count = count($comment_block->getBlankLines());
      $blank_exists_count = 0;

      // Check the exact positions where blank lines would be injected.
      for ($i = $target_position - $blank_count; $i < $target_position; $i++) {
        if ($i >= 0 && isset($candidate_parsed_lines[$i]) && $candidate_parsed_lines[$i]->isBlank()) {
          $blank_exists_count++;
        }
      }

      // If we already have the same number of blank lines in the right place, don't inject.
      if ($blank_exists_count >= $blank_count) {
        Debug::log("    Found existing blank lines before position %d, skipping injection", $target_position);
        return FALSE;
      }
    }

    // Should inject.
    return TRUE;
  }

  /**
   * Configure fuzzy matching thresholds.
   */
  public function setFuzzyThresholds(
    float $context_threshold = 0.7,
    float $final_threshold = 0.8,
    int $position_window = 10,
    int $context_window_size = 2,
  ): void {
    $this->fuzzyMatcher->setThresholds($context_threshold, $final_threshold, $position_window, $context_window_size);
  }

}
