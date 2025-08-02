<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml;

use AlexSkrypnyk\Yaml\Ast\AstTree;
use AlexSkrypnyk\Yaml\Dumper\Unescaper;
use AlexSkrypnyk\Yaml\Dumper\Dumper;
use AlexSkrypnyk\Yaml\Parser\Parser;

/**
 * Yaml class provides a drop-in replacement for Symfony's Yaml component.
 *
 * Adds several enhancements:
 * - Comment preservation during parsing and dumping.
 * - Original formatting preservation when possible.
 */
class Yaml implements YamlInterface {

  public const DUMP_COLLAPSE_LITERAL_BLOCK_EMPTY_LINES = 1 << 11;

  /**
   * The raw YAML content.
   */
  protected ?string $content = NULL;

  /**
   * AST tree for managing YAML manipulation.
   */
  protected AstTree $tree;

  /**
   * {@inheritdoc}
   */
  public function load(string $file_path): void {
    if (!file_exists($file_path)) {
      throw new \RuntimeException('File does not exist: ' . $file_path);
    }

    $content = file_get_contents($file_path);

    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Unable to read file: ' . $file_path);
      // @codeCoverageIgnoreEnd
    }

    $this->parse($content);
  }

  /**
   * {@inheritdoc}
   */
  public function parse(string $content): void {
    $this->content = $content;

    // Use the Parser class to build the AST tree.
    $parser = new Parser();
    $this->tree = $parser->parse($content);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(array $path): mixed {
    return $this->tree->getValue($path);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(array $path, mixed $value): void {
    $this->tree->setValue($path, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getComment(array $path): ?string {
    return $this->tree->getComment($path);
  }

  /**
   * {@inheritdoc}
   */
  public function setComment(array $path, string $comment): void {
    $this->tree->setComment($path, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function addKey(array $parent_path, string $key, mixed $value, ?string $comment = NULL): void {
    // Delegate to AST tree for manipulation.
    $this->tree->addKey($parent_path, $key, $value, $comment);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteKey(array $path): void {
    // Delegate to AST tree for manipulation.
    $this->tree->deleteKey($path);
  }

  /**
   * {@inheritdoc}
   */
  public function has(array $path): bool {
    return $this->tree->has($path);
  }

  /**
   * {@inheritdoc}
   */
  public function visit(callable $visitor): void {
    $this->tree->visit($visitor);
  }

  /**
   * {@inheritdoc}
   */
  public function dump(int $flags = 0): string {
    $options = [];

    // Handle legacy flag parameter for backward compatibility.
    if (($flags & self::DUMP_COLLAPSE_LITERAL_BLOCK_EMPTY_LINES) !== 0) {
      $options['collapseEmptyLines'] = TRUE;
    }

    $dumper = new Dumper();
    $result = $dumper->dump($this->tree->getNodes(), $options);

    // Apply unescaping to remove unnecessary quotes.
    // @todo Move this to Dumper class if needed.
    $original_lines = $this->content ? explode("\n", $this->content) : NULL;
    $dump = Unescaper::unescapeSingleQuotedValueString($result, $original_lines, TRUE);

    return $dump;
  }

  /**
   * {@inheritdoc}
   */
  public function save(string $file_path, int $flags = 0): void {
    $content = $this->dump($flags);

    if (file_put_contents($file_path, $content) === FALSE) {
      throw new \RuntimeException('Unable to write file: ' . $file_path);
    }
  }

}
