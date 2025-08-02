<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml;

/**
 * Interface for YAML manipulation with comment preservation.
 */
interface YamlInterface {

  /**
   * Load and parse a YAML file into a formatting-aware AST.
   *
   * Example:
   * @code
   * $editor->parseFile('ahoy.yml');
   * @endcode
   */
  public function load(string $file_path): void;

  /**
   * Parse a YAML string a formatting-aware AST.
   *
   * Example:
   * @code
   * $editor->parse('YAML');
   * @endcode
   */
  public function parse(string $content): void;

  /**
   * Get the value at a dot-path.
   *
   * @param array<string> $path
   *   The path to the value.
   *
   * @return mixed
   *   The value at the path.
   *
   *   Example:
   *
   * @code
   * $editor->getValue(['project']);
   * // Returns: "my-app"
   *
   * $editor->getValue(['commands', 'build', 'cmd']);
   * // Returns: "make build"
   * @endcode
   */
  public function getValue(array $path): mixed;

  /**
   * Set or update a value at the given path.
   *
   * @param array<string> $path
   *   The path to set the value at.
   * @param mixed $value
   *   The value to set.
   *
   *   Example:
   *
   * @code
   *   $editor->setValue(['commands', 'build', 'cmd'], 'docker build .');
   *   // Updates the 'cmd' under 'build'
   *
   *   $editor->setValue(['commands', 'test', 'cmd'], 'make test');
   *   // Adds a new 'test' command
   * @endcode
   */
  public function setValue(array $path, mixed $value): void;

  /**
   * Get the block comment associated with a given path (if any).
   *
   * @param array<string> $path
   *   The path to get the comment for.
   *
   * @return string|null
   *   The comment, or NULL if no comment exists.
   *
   *   Example:
   *
   * @code
   * $comment = $editor->getComment(['commands', 'build']);
   * echo $comment;
   * @endcode
   */
  public function getComment(array $path): ?string;

  /**
   * Attach or update a block comment on a specific path.
   *
   * @param array<string> $path
   *   The path to set the comment for.
   * @param string $comment
   *   The comment to set.
   *
   *   Example:
   *
   * @code
   *   $editor->setComment(['commands', 'build'], '# Build-related actions');
   * @endcode
   */
  public function setComment(array $path, string $comment): void;

  /**
   * Add a new key under a parent path, with optional block comment.
   *
   * @param array<string> $parent_path
   *   The path to the parent.
   * @param string $key
   *   The key to add.
   * @param mixed $value
   *   The value to set.
   * @param string|null $comment
   *   Optional comment for the key.
   *
   *   Example:
   *
   * @code
   *   $editor->addKey(['commands'], 'deploy', [
   *     'usage' => 'Deploy the app',
   *     'cmd' => 'make deploy'
   *   ], '# Deployment command');
   * @endcode
   */
  public function addKey(array $parent_path, string $key, mixed $value, ?string $comment = NULL): void;

  /**
   * Delete a key (or node) at the specified path.
   *
   * @param array<string> $path
   *   The path to the key to delete.
   *
   *   Example:
   *
   * @code
   *   $editor->deleteKey(['commands', 'build']);
   * @endcode
   */
  public function deleteKey(array $path): void;

  /**
   * Check if a key exists at the specified path.
   *
   * @param array<string> $path
   *   The path to check.
   *
   * @return bool
   *   TRUE if the path exists, FALSE otherwise.
   *
   *   Example:
   *
   * @code
   * if ($editor->has(['commands', 'test'])) {
   *     echo 'Test command is defined';
   * }
   * @endcode
   */
  public function has(array $path): bool;

  /**
   * Recursively walk the YAML tree, applying a callback to each node.
   *
   * @param callable $visitor
   *   A callback that receives a Node and its parents as array (root element
   *   has empty array).
   */
  public function visit(callable $visitor): void;

  /**
   * Dump the YAML AST as a string with formatting preserved.
   *
   * Example:
   * @code
   * $yaml = $editor->dump();
   * echo $yaml;
   * @endcode
   */
  public function dump(int $flags = 0): string;

  /**
   * Save the current YAML AST to a file with formatting preserved.
   *
   * @param string $file_path
   *   The file path to save to.
   * @param int $flags
   *   Optional flags for dumping (same as dump() method).
   *
   *   Example:
   *
   * @code
   *   $editor->save('ahoy-updated.yml');
   *   $editor->save('ahoy-collapsed.yml', Yaml::DUMP_COLLAPSE_LITERAL_BLOCK_EMPTY_LINES);
   * @endcode
   */
  public function save(string $file_path, int $flags = 0): void;

}
