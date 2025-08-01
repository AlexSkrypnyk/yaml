<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Internal\Comments;

/**
 * Calculate string similarity using multiple algorithms for maximum accuracy.
 */
class StringSimilarity {

  /**
   * Perfect similarity score.
   */
  public const PERFECT_SIMILARITY = 1.0;

  /**
   * No similarity score.
   */
  public const NO_SIMILARITY = 0.0;

  /**
   * Jaro-Winkler threshold for prefix bonus.
   */
  public const JARO_WINKLER_THRESHOLD = 0.7;

  /**
   * Maximum prefix length for Jaro-Winkler calculation.
   */
  public const JARO_WINKLER_MAX_PREFIX = 4;

  /**
   * Jaro-Winkler prefix weight.
   */
  public const JARO_WINKLER_PREFIX_WEIGHT = 0.1;

  /**
   * Jaro match window divisor.
   */
  public const JARO_MATCH_WINDOW_DIVISOR = 2;

  /**
   * Jaro transposition divisor.
   */
  public const JARO_TRANSPOSITION_DIVISOR = 2;

  /**
   * Jaro formula divisor for final calculation.
   */
  public const JARO_FORMULA_DIVISOR = 3;

  /**
   * Calculate similarity between two strings using hybrid approach.
   *
   * @param string $str1
   *   First string to compare.
   * @param string $str2
   *   Second string to compare.
   *
   * @return float Similarity score between 0.0 and 1.0.
   */
  public function calculateSimilarity(string $str1, string $str2): float {
    // 1. Try exact match first (fastest, 100% accurate)
    if ($str1 === $str2) {
      return self::PERFECT_SIMILARITY;
    }

    // 2. Handle empty strings
    if ($str1 === '' && $str2 === '') {
      return self::PERFECT_SIMILARITY;
    }
    if ($str1 === '' || $str2 === '') {
      return self::NO_SIMILARITY;
    }

    // 3. Calculate multiple similarity scores
    $levenshtein_score = $this->calculateLevenshteinSimilarity($str1, $str2);
    $jaro_winkler_score = $this->calculateJaroWinklerSimilarity($str1, $str2);
    $lcs_score = $this->calculateLCSSimilarity($str1, $str2);

    // 4. Return the HIGHEST score (most optimistic for accuracy)
    return max($levenshtein_score, $jaro_winkler_score, $lcs_score);
  }

  /**
   * Calculate Levenshtein-based similarity.
   */
  protected function calculateLevenshteinSimilarity(string $str1, string $str2): float {
    $distance = levenshtein($str1, $str2);
    $max_length = max(strlen($str1), strlen($str2));

    if ($max_length === 0) {
      return self::PERFECT_SIMILARITY;
    }

    return self::PERFECT_SIMILARITY - ($distance / $max_length);
  }

  /**
   * Calculate Jaro-Winkler similarity.
   * Implementation of Jaro-Winkler algorithm for better prefix matching.
   */
  protected function calculateJaroWinklerSimilarity(string $str1, string $str2): float {
    $jaro = $this->calculateJaro($str1, $str2);

    if ($jaro < self::JARO_WINKLER_THRESHOLD) {
      return $jaro;
    }

    // Calculate common prefix length (up to 4 characters)
    $prefix_length = 0;
    $max_prefix = min(self::JARO_WINKLER_MAX_PREFIX, min(strlen($str1), strlen($str2)));

    for ($i = 0; $i < $max_prefix; $i++) {
      if ($str1[$i] === $str2[$i]) {
        $prefix_length++;
      }
      else {
        break;
      }
    }

    // Jaro-Winkler formula.
    return $jaro + (self::JARO_WINKLER_PREFIX_WEIGHT * $prefix_length * (self::PERFECT_SIMILARITY - $jaro));
  }

  /**
   * Calculate Jaro similarity.
   */
  protected function calculateJaro(string $str1, string $str2): float {
    $len1 = strlen($str1);
    $len2 = strlen($str2);

    if ($len1 === 0 && $len2 === 0) {
      return self::PERFECT_SIMILARITY;
    }
    if ($len1 === 0 || $len2 === 0) {
      return self::NO_SIMILARITY;
    }

    $match_window = intval(max($len1, $len2) / self::JARO_MATCH_WINDOW_DIVISOR) - 1;
    if ($match_window < 0) {
      $match_window = 0;
    }

    $matches = 0;
    $transpositions = 0;
    $str1_matches = array_fill(0, $len1, FALSE);
    $str2_matches = array_fill(0, $len2, FALSE);

    // Find matches.
    for ($i = 0; $i < $len1; $i++) {
      $start = max(0, $i - $match_window);
      $end = min($i + $match_window + 1, $len2);

      for ($j = $start; $j < $end; $j++) {
        if ($str2_matches[$j] || $str1[$i] !== $str2[$j]) {
          continue;
        }
        $str1_matches[$i] = TRUE;
        $str2_matches[$j] = TRUE;
        $matches++;
        break;
      }
    }

    if ($matches === 0) {
      return self::NO_SIMILARITY;
    }

    // Count transpositions.
    $k = 0;
    for ($i = 0; $i < $len1; $i++) {
      if (!$str1_matches[$i]) {
        continue;
      }
      while (!$str2_matches[$k]) {
        $k++;
      }
      if ($str1[$i] !== $str2[$k]) {
        $transpositions++;
      }
      $k++;
    }

    // Jaro formula.
    return ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / self::JARO_TRANSPOSITION_DIVISOR) / $matches) / self::JARO_FORMULA_DIVISOR;
  }

  /**
   * Calculate Longest Common Subsequence similarity.
   */
  protected function calculateLCSSimilarity(string $str1, string $str2): float {
    $len1 = strlen($str1);
    $len2 = strlen($str2);

    if ($len1 === 0 && $len2 === 0) {
      return self::PERFECT_SIMILARITY;
    }
    if ($len1 === 0 || $len2 === 0) {
      return self::NO_SIMILARITY;
    }

    $lcs_length = $this->calculateLCS($str1, $str2);
    $max_length = max($len1, $len2);

    return $lcs_length / $max_length;
  }

  /**
   * Calculate Longest Common Subsequence length.
   */
  protected function calculateLCS(string $str1, string $str2): int {
    $len1 = strlen($str1);
    $len2 = strlen($str2);

    // Create DP table.
    $dp = array_fill(0, $len1 + 1, array_fill(0, $len2 + 1, 0));

    for ($i = 1; $i <= $len1; $i++) {
      for ($j = 1; $j <= $len2; $j++) {
        $dp[$i][$j] = $str1[$i - 1] === $str2[$j - 1] ? $dp[$i - 1][$j - 1] + 1 : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
      }
    }

    return $dp[$len1][$len2];
  }

}
