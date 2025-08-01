<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Internal\Comments;

/**
 * Debug utility for comment processing.
 */
class Debug {

  /**
   * Log debug message if YAML_DEBUG_FUZZY_MATCHING environment variable is set.
   *
   * @param string $message
   *   The message string, potentially with sprintf placeholders.
   * @param mixed ...$args
   *   Additional arguments for sprintf formatting.
   */
  public static function log(string $message, mixed ...$args): void {
    if (!getenv('YAML_DEBUG_FUZZY_MATCHING')) {
      return;
    }

    // Check if the message contains sprintf-style placeholders.
    if (preg_match('/%[sdcoxXeEfFgG%]/', $message) && !empty($args)) {
      // Use sprintf formatting with error handling.
      try {
        $formatted_message = sprintf($message, ...$args);
        fprintf(STDERR, "%s\n", $formatted_message);
      }
      catch (\ValueError | \TypeError $e) {
        // Fallback if sprintf fails.
        fprintf(STDERR, "%s\n", $message);
        foreach ($args as $i => $arg) {
          fprintf(STDERR, "  Arg[%d]: %s\n", $i, print_r($arg, TRUE));
        }
      }
    }
    elseif (!empty($args)) {
      // No sprintf placeholders but we have additional args - use print_r for args.
      fprintf(STDERR, "%s\n", $message);
      foreach ($args as $arg) {
        fprintf(STDERR, "%s\n", print_r($arg, TRUE));
      }
    }
    else {
      // Just the message.
      fprintf(STDERR, "%s\n", $message);
    }
  }

}
