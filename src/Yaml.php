<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml;

use Consolidation\Comments\Comments;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Yaml class provides a drop-in replacement for Symfony's Yaml component.
 *
 * Adds comment preservation functionality to standard YAML operations.
 */
class Yaml {

  public const PARSE_EXCEPTION_ON_INVALID_TYPE = SymfonyYaml::PARSE_EXCEPTION_ON_INVALID_TYPE;

  public const PARSE_OBJECT = SymfonyYaml::PARSE_OBJECT;

  public const PARSE_OBJECT_FOR_MAP = SymfonyYaml::PARSE_OBJECT_FOR_MAP;

  public const PARSE_DATETIME = SymfonyYaml::PARSE_DATETIME;

  public const PARSE_CONSTANT = SymfonyYaml::PARSE_CONSTANT;

  public const PARSE_CUSTOM_TAGS = SymfonyYaml::PARSE_CUSTOM_TAGS;

  public const DUMP_OBJECT = SymfonyYaml::DUMP_OBJECT;

  public const DUMP_EXCEPTION_ON_INVALID_TYPE = SymfonyYaml::DUMP_EXCEPTION_ON_INVALID_TYPE;

  public const DUMP_OBJECT_AS_MAP = SymfonyYaml::DUMP_OBJECT_AS_MAP;

  public const DUMP_MULTI_LINE_LITERAL_BLOCK = SymfonyYaml::DUMP_MULTI_LINE_LITERAL_BLOCK;

  public const DUMP_EMPTY_ARRAY_AS_SEQUENCE = SymfonyYaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE;

  public const DUMP_NULL_AS_TILDE = SymfonyYaml::DUMP_NULL_AS_TILDE;

  /**
   * Holds the original YAML content split into lines.
   *
   * @var array<string>|null
   */
  protected static ?array $lines = NULL;

  /**
   * Holds the detected line ending character(s) from the original content.
   */
  protected static string $lineEnding = "\n";

  /**
   * Parses a YAML file into a PHP value.
   *
   * Usage:
   * @code
   *
   *     $array = Yaml::parseFile('config.yml');
   *     print_r($array);
   *
   * @endcode
   *
   * @param string $filename
   *   The path to the YAML file to be parsed.
   * @param int-mask-of<static::PARSE_*> $flags
   *   A bit field of PARSE_* constants to customize the YAML parser behavior.
   *
   * @throws \Symfony\Component\Yaml\Exception\ParseException
   *   If the file could not be read or the YAML is not valid.
   */
  public static function parseFile(string $filename, int $flags = 0): mixed {
    $content = file_get_contents($filename);

    if ($content === FALSE) {
      throw new \RuntimeException('Unable to read file: ' . $filename);
    }

    return static::parse($content, $flags);
  }

  /**
   * Parses YAML into a PHP value.
   *
   *  Usage:
   *  <code>
   *   $array = Yaml::parse(file_get_contents('config.yml'));
   *   print_r($array);
   *  </code>
   *
   * @param string $input
   *   A string containing YAML.
   * @param int-mask-of<static::PARSE_*> $flags
   *   A bit field of PARSE_* constants to customize the YAML parser behavior.
   *
   * @throws \Symfony\Component\Yaml\Exception\ParseException
   *   If the YAML is not valid.
   */
  public static function parse(string $input, int $flags = 0): mixed {
    static::$lines = static::toLines($input);

    return SymfonyYaml::parse($input, $flags);
  }

  /**
   * Dumps a PHP value to a YAML string.
   *
   * The dump method, when supplied with an array, will do its best
   * to convert the array into friendly YAML.
   *
   * @param mixed $input
   *   The PHP value.
   * @param int $inline
   *   The level where you switch to inline YAML.
   * @param int $indent
   *   The amount of spaces to use for indentation of nested nodes.
   * @param int-mask-of<static::DUMP_*> $flags
   *   A bit field of DUMP_* constants to customize the dumped YAML string.
   */
  public static function dump(mixed $input, int $inline = 2, int $indent = 4, int $flags = 0): string {
    $content = SymfonyYaml::dump($input, $inline, $indent, $flags);

    // If static::$lines is NULL, it means that there are no original lines to
    // preserve comments.
    if (static::$lines === NULL || !is_array($input)) {
      return $content;
    }

    $comments = new Comments();
    $comments->collect(static::$lines);

    $lines = static::toLines($content);
    $lines_with_comments = $comments->inject($lines);
    $content = implode(static::$lineEnding, $lines_with_comments);

    $content = static::unquote($content);
    $content = static::deduplicateLines($content);

    if (!str_ends_with($content, static::$lineEnding)) {
      $content .= static::$lineEnding;
    }

    return $content;
  }

  /**
   * Parse and store YAML content with line ending detection.
   *
   * @return array<string>
   *   Array of lines from the content.
   */
  protected static function toLines(string $content): array {
    // Detect line endings: \r\n (Windows), \r (old Mac), or \n (Unix/Linux)
    if (str_contains($content, "\r\n")) {
      static::$lineEnding = "\r\n";
    }
    elseif (str_contains($content, "\r")) {
      static::$lineEnding = "\r";
    }
    else {
      static::$lineEnding = "\n";
    }

    return explode(static::$lineEnding, $content);
  }

  /**
   * Remove unnecessary single quotes from string values.
   *
   * @param string $content
   *   The YAML content to process.
   *
   * @return string
   *   The processed YAML content with unnecessary quotes removed.
   */
  protected static function unquote(string $content): string {
    if (static::$lines === NULL) {
      return $content;
    }

    // Build a map of originally quoted strings to preserve them.
    $originally_quoted = static::buildOriginallyQuotedMap(static::$lines);

    $pattern1 = "/^(\s*\w+:\s)'((?:[^']|'')+)'$/m";
    $result = preg_replace_callback($pattern1, function (array $matches) use ($originally_quoted): string {
      $unquoted_value = str_replace("''", "'", $matches[2]);
      if (isset($originally_quoted[$unquoted_value])) {
        return $matches[0];
      }
      return $matches[1] . $unquoted_value;
    }, $content);

    if ($result === NULL) {
      return $content;
    }

    $pattern2 = "/^(\s*-\s)'([a-zA-Z0-9_-]+|{{[^}]+}})'$/m";
    $final_result = preg_replace_callback($pattern2, function (array $matches) use ($originally_quoted): string {
      $unquoted_value = $matches[2];
      if (isset($originally_quoted[$unquoted_value])) {
        return $matches[0];
      }
      return $matches[1] . $matches[2];
    }, $result);

    return $final_result ?? $result;
  }

  /**
   * Build a map of strings that were originally quoted in the source YAML.
   *
   * @param array<string> $lines
   *   Lines of the original YAML content.
   *
   * @return array<string, bool>
   *   Map of unquoted strings to TRUE if they were originally quoted.
   */
  protected static function buildOriginallyQuotedMap(array $lines): array {
    $quoted_strings = [];

    foreach ($lines as $line) {
      if (preg_match("/^\s*\w+:\s'((?:[^']|'')+)'$/", $line, $matches)) {
        $quoted_strings[str_replace("''", "'", $matches[1])] = TRUE;
      }

      if (preg_match("/^\s*-\s'([a-zA-Z0-9_-]+|{{[^}]+}})'$/", $line, $matches)) {
        $quoted_strings[$matches[1]] = TRUE;
      }
    }

    return $quoted_strings;
  }

  /**
   * Remove consecutive duplicate lines (fixes consolidation/comments issues).
   *
   * @param string $content
   *   The YAML content to process.
   *
   * @return string
   *   The processed YAML content with duplicate lines removed.
   */
  protected static function deduplicateLines(string $content): string {
    $lines = static::toLines($content);

    $deduplicated_lines = [];
    $previous_line = NULL;

    foreach ($lines as $line) {
      if ($line !== $previous_line) {
        $deduplicated_lines[] = $line;
      }
      $previous_line = $line;
    }

    return implode(static::$lineEnding, $deduplicated_lines);
  }

}
