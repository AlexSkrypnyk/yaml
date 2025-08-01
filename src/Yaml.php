<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml;

use AlexSkrypnyk\Yaml\Internal\Comments\CommentManager;
use AlexSkrypnyk\Yaml\Internal\ContentParser;
use AlexSkrypnyk\Yaml\Internal\Unescaper;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Yaml class provides a drop-in replacement for Symfony's Yaml component.
 *
 * Adds several enhancements:
 * - Comment preservation during parsing and dumping.
 * - Ungreedy quoting for strings by default, reducing unnecessary quotes.
 *   Use DUMP_STRICT_SINGLE_QUOTING flag to revert to Symfony's strict quoting.
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

  public const DUMP_STRICT_SINGLE_QUOTING = 1 << 10;

  public const DUMP_COLLAPSE_LITERAL_BLOCK_EMPTY_LINES = 1 << 11;

  /**
   * The content parser for the original YAML content.
   */
  protected static ?ContentParser $contentParser = NULL;

  protected static ?string $content = NULL;

  /**
   * Parses a YAML file into a PHP value.
   *
   * Usage:
   *
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
    if (!file_exists($filename)) {
      throw new \RuntimeException('File does not exist: ' . $filename);
    }

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
    static::$content = $input;

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
    $dump = SymfonyYaml::dump($input, $inline, $indent, $flags);

    // If static::$content is NULL, it means that this was called just to dump
    // a value without prior parsing. In this case, we don't have any knowledge
    // about comments or original content, so we skip comment injection.
    // We also want this to run as early as possible to increase our chances of
    // comment position matching.
    if (!is_null(static::$content)) {
      $content_parser_for_comments = new ContentParser($dump);
      $comment_manager = new CommentManager();
      $comment_manager->collect(static::$content, $content_parser_for_comments->getEol());
      $lines_with_comments = $comment_manager->inject($content_parser_for_comments->getLines());
      $dump = implode($content_parser_for_comments->getEol(), $lines_with_comments);
    }

    // Collapse literal block empty lines if flag is set.
    if (($flags & static::DUMP_COLLAPSE_LITERAL_BLOCK_EMPTY_LINES) !== 0) {
      $content_parser_for_collapse = new ContentParser($dump);
      $dump = $content_parser_for_collapse->collapseLiteralBlockEmptyLines();
    }

    // Unescape greedily single-quoted strings if not in strict mode.
    if (($flags & static::DUMP_STRICT_SINGLE_QUOTING) === 0) {
      $content_parser_for_unescaper = new ContentParser(static::$content ?? $dump);
      $dump = Unescaper::unescapeSingleQuotedValueString($dump, $content_parser_for_unescaper->getLines());
    }

    // @todo Only do this if the appropriate flag is set.
    // Also prevent duplicated empty EOFs.
    // Only add newline for array inputs (complex YAML structures)
    if (is_array($input)) {
      $content_parser_for_eof = new ContentParser(static::$content ?? $dump);
      if (!str_ends_with($dump, $content_parser_for_eof->getEol())) {
        $dump .= $content_parser_for_eof->getEol();
      }
    }

    return $dump;
  }

}
