<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Internal\Comments;

/**
 * Fuzzy matcher using multi-layer context window matching.
 */
class FuzzyMatcher {

  /**
   * Minimum similarity threshold for target line matching.
   */
  public const TARGET_LINE_MINIMUM_THRESHOLD = 0.5;

  /**
   * Weight for target line similarity in combined score.
   */
  public const TARGET_SIMILARITY_WEIGHT = 0.6;

  /**
   * Weight for context score in combined score.
   */
  public const CONTEXT_SCORE_WEIGHT = 0.3;

  /**
   * Weight for context position score in combined score.
   */
  public const CONTEXT_POSITION_WEIGHT = 0.1;

  /**
   * Weight for key similarity when comparing lines.
   */
  public const KEY_SIMILARITY_WEIGHT = 0.7;

  /**
   * Weight for value similarity when comparing lines.
   */
  public const VALUE_SIMILARITY_WEIGHT = 0.3;

  /**
   * Divisor for splitting context scores (before and after).
   */
  public const CONTEXT_DIRECTION_DIVISOR = 2;

  /**
   * Step increment for direction loop (-1 to 1, step 2).
   */
  public const DIRECTION_STEP = 2;

  /**
   * Fallback score when no context found.
   */
  public const NO_CONTEXT_FALLBACK_SCORE = 1.0;

  protected StringSimilarity $similarityCalculator;

  /**
   * Configuration thresholds.
   */
  protected float $contextThreshold = 0.7;
  protected float $finalThreshold = 0.6;
  protected int $positionWindow = 30;
  protected int $contextWindowSize = 2;

  public function __construct(?StringSimilarity $similarity_calculator = NULL) {
    $this->similarityCalculator = $similarity_calculator ?? new StringSimilarity();
  }

  /**
   * Find the best match for a target line in candidate lines.
   *
   * @param Line $parsed_target
   *   Target line from original content.
   * @param Line[] $parsed_candidates
   *   Array of candidate Line objects.
   * @param Line[] $original_parsed_lines
   *   All original parsed lines for context.
   * @param int $target_original_position
   *   Original position of target line.
   *
   * @return Line|null Best match Line or null if no match found.
   */
  public function findBestMatch(
    Line $parsed_target,
    array $parsed_candidates,
    array $original_parsed_lines,
    int $target_original_position,
  ): ?Line {
    $best_match = NULL;
    $best_score = 0.0;

    foreach ($parsed_candidates as $candidate_index => $candidate) {
      $score = $this->calculateMatchScore(
        $parsed_target,
        $candidate,
        $original_parsed_lines,
        $parsed_candidates,
        $target_original_position
      );

      if ($score > $best_score && $score >= $this->finalThreshold) {
        $best_score = $score;
        $best_match = $candidate;
      }
    }

    return $best_match;
  }

  /**
   * Calculate match score using three-layer context algorithm.
   */
  public function calculateMatchScore(
    Line $parsed_target,
    Line $candidate,
    array $original_parsed_lines,
    array $candidate_lines,
    int $target_original_position,
  ): float {
    // Layer 1: Positional Constraint.
    $position_drift = abs($candidate->getPosition() - $target_original_position);
    if ($position_drift > $this->positionWindow) {
      // Reject if too far from expected position.
      return 0.0;
    }

    // Layer 2: Target Line Similarity.
    $target_similarity = $this->calculateLineSimilarity($parsed_target, $candidate);
    if ($target_similarity < self::TARGET_LINE_MINIMUM_THRESHOLD) {
      // Reject if target line doesn't match well enough.
      return 0.0;
    }

    // Layer 3: Context Validation.
    $context_score = $this->calculateContextScore(
      $original_parsed_lines,
      $candidate_lines,
      $target_original_position,
      $candidate->getPosition()
    );

    // Layer 4: Recursive Context Position Validation.
    $context_position_score = $this->validateContextPositions(
      $original_parsed_lines,
      $candidate_lines,
      $target_original_position,
      $candidate->getPosition()
    );

    // Combine scores: target line weighted higher, context provides confirmation.
    $combined_score = ($target_similarity * self::TARGET_SIMILARITY_WEIGHT) + ($context_score * self::CONTEXT_SCORE_WEIGHT) + ($context_position_score * self::CONTEXT_POSITION_WEIGHT);

    // Debug output if environment variable is set.
    $target_weighted = $target_similarity * self::TARGET_SIMILARITY_WEIGHT;
    $context_weighted = $context_score * self::CONTEXT_SCORE_WEIGHT;
    $context_position_weighted = $context_position_score * self::CONTEXT_POSITION_WEIGHT;

    Debug::log("\nFUZZY MATCH DEBUG::calculateMatchScore");
    Debug::log("Target:     [%d] %s", $target_original_position, trim($parsed_target->getOriginal()));
    Debug::log("Candidate:  [%d] %s", $candidate->getPosition(), trim($candidate->getOriginal()));
    Debug::log("Normalized: target_key='%s' target_value='%s'", $parsed_target->getNormalizedKey(), $parsed_target->getNormalizedValue());
    Debug::log("Normalized: candidate_key='%s' candidate_value='%s'", $candidate->getNormalizedKey(), $candidate->getNormalizedValue());
    Debug::log("Scores:");
    Debug::log("  Target similarity:     %.4f (weighted: %.4f)", $target_similarity, $target_weighted);
    Debug::log("  Context score:         %.4f (weighted: %.4f)", $context_score, $context_weighted);
    Debug::log("  Context position:      %.4f (weighted: %.4f)", $context_position_score, $context_position_weighted);
    Debug::log("  COMBINED SCORE:        %.4f", $combined_score);
    Debug::log("  Position drift:        %d", abs($candidate->getPosition() - $target_original_position));
    Debug::log("----------------------------------------");

    return $combined_score;
  }

  /**
   * Calculate similarity between two parsed lines.
   */
  protected function calculateLineSimilarity(Line $line1, Line $line2): float {
    // Skip non-content lines.
    if ($line1->isComment() || $line1->isBlank() || $line2->isComment() || $line2->isBlank()) {
      return 0.0;
    }

    $key_score = 0.0;
    $value_score = 0.0;

    // Compare normalized keys.
    if ($line1->getNormalizedKey() !== '' && $line2->getNormalizedKey() !== '') {
      $key_score = $this->similarityCalculator->calculateSimilarity(
        $line1->getNormalizedKey(),
        $line2->getNormalizedKey()
      );
    }

    // Compare normalized values.
    if ($line1->getNormalizedValue() !== '' && $line2->getNormalizedValue() !== '') {
      $value_score = $this->similarityCalculator->calculateSimilarity(
        $line1->getNormalizedValue(),
        $line2->getNormalizedValue()
      );
    }

    // If only key or only value exists, use that score.
    if ($key_score > 0.0 && $value_score === 0.0) {
      return $key_score;
    }
    if ($value_score > 0.0 && $key_score === 0.0) {
      return $value_score;
    }

    // Weight keys more heavily than values (keys are more stable)
    return ($key_score * self::KEY_SIMILARITY_WEIGHT) + ($value_score * self::VALUE_SIMILARITY_WEIGHT);
  }

  /**
   * Calculate context score using non-linear context window.
   */
  protected function calculateContextScore(
    array $original_lines,
    array $candidate_lines,
    int $original_position,
    int $candidate_position,
  ): float {
    $before_score = $this->calculateDirectionalContextScore(
      $original_lines,
      $candidate_lines,
      $original_position,
      $candidate_position,
    // Before (upward)
      -1
    );

    $after_score = $this->calculateDirectionalContextScore(
      $original_lines,
      $candidate_lines,
      $original_position,
      $candidate_position,
    // After (downward)
      1
    );

    // Both directions must contribute to context validation.
    return ($before_score + $after_score) / self::CONTEXT_DIRECTION_DIVISOR;
  }

  /**
   * Calculate context score in one direction (before or after).
   */
  protected function calculateDirectionalContextScore(
    array $original_lines,
    array $candidate_lines,
    int $original_position,
    int $candidate_position,
    int $direction,
  ): float {
    $context_matches = 0;
    $context_checks = 0;

    for ($i = 1; $i <= $this->contextWindowSize; $i++) {
      $original_context_pos = $original_position + ($direction * $i);
      $candidate_context_pos = $candidate_position + ($direction * $i);

      // Check bounds.
      if (!isset($original_lines[$original_context_pos]) || !isset($candidate_lines[$candidate_context_pos])) {
        continue;
      }

      $context_checks++;
      $context_similarity = $this->calculateLineSimilarity(
        $original_lines[$original_context_pos],
        $candidate_lines[$candidate_context_pos]
      );

      if ($context_similarity >= $this->contextThreshold) {
        $context_matches++;
        // Found good context, can stop (non-linear approach)
        break;
      }

      // If first line doesn't match, try second line (skip potentially updated line)
      if ($i === 1 && $context_similarity < $this->contextThreshold) {
        // Try next line.
        continue;
      }

      // If second line also doesn't match, context is broken.
      if ($i === 2 && $context_similarity < $this->contextThreshold) {
        break;
      }
    }

    // Return ratio of context matches to checks.
    return $context_checks > 0 ? $context_matches / $context_checks : 0.0;
  }

  /**
   * Validate that context lines themselves are within reasonable positions.
   */
  protected function validateContextPositions(
    array $original_lines,
    array $candidate_lines,
    int $original_position,
    int $candidate_position,
  ): float {
    $valid_contexts = 0;
    $total_contexts = 0;

    // Check context lines in both directions.
    for ($direction = -1; $direction <= 1; $direction += self::DIRECTION_STEP) {
      for ($i = 1; $i <= $this->contextWindowSize; $i++) {
        $original_context_pos = $original_position + ($direction * $i);
        $candidate_context_pos = $candidate_position + ($direction * $i);

        // Check bounds.
        if (!isset($original_lines[$original_context_pos]) || !isset($candidate_lines[$candidate_context_pos])) {
          continue;
        }

        $total_contexts++;
        $expected_candidate_pos = $original_context_pos + ($candidate_position - $original_position);
        $context_drift = abs($candidate_context_pos - $expected_candidate_pos);

        if ($context_drift <= $this->positionWindow) {
          $valid_contexts++;
        }
      }
    }

    return $total_contexts > 0 ? $valid_contexts / $total_contexts : self::NO_CONTEXT_FALLBACK_SCORE;
  }

  /**
   * Set configuration thresholds.
   */
  public function setThresholds(
    float $context_threshold = 0.7,
    float $final_threshold = 0.8,
    int $position_window = 10,
    int $context_window_size = 2,
  ): void {
    $this->contextThreshold = $context_threshold;
    $this->finalThreshold = $final_threshold;
    $this->positionWindow = $position_window;
    $this->contextWindowSize = $context_window_size;
  }

}
