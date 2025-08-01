<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Internal\Comments;

use AlexSkrypnyk\Yaml\Internal\Comments\CommentManager;
use AlexSkrypnyk\Yaml\Internal\Comments\Debug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CommentManager class.
 */
#[CoversClass(CommentManager::class)]
class CommentManagerTest extends TestCase {

  /**
   * Directory where fixture YAML files are stored.
   */
  protected string $fixturesDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fixturesDir = dirname(__DIR__, 2) . '/fixtures';
  }

  /**
   * Test comment collection and injection using fixtures.
   */
  #[DataProvider('commentsDataProvider')]
  public function testComments(callable $manipulator): void {
    $dir = $this->fixturesDir . '/' . $this->dataName();
    if (!is_dir($dir)) {
      throw new \RuntimeException('Directory "' . $dir . '" does not exist');
    }

    $before_file = $dir . '/before.yml';
    $after_file = $dir . '/after.yml';

    // Ensure fixture files exist.
    $this->assertFileExists($before_file, 'Before fixture file should exist');
    $this->assertFileExists($after_file, 'After fixture file should exist');

    // Read fixture files.
    $original_content = file_get_contents($before_file);
    $expected_content = file_get_contents($after_file);

    // Process with CommentManager class.
    $comment_manager = new CommentManager();
    $comment_manager->collect($original_content);

    // Apply manipulator to simulate content changes.
    $original_lines = explode("\n", $original_content);
    $altered_lines = $manipulator($original_lines);

    Debug::log("\n=== MANIPULATOR OUTPUT ===");
    foreach ($altered_lines as $i => $line) {
      Debug::log("[%d] %s", $i, $line);
    }
    Debug::log("=== END MANIPULATOR ===");

    // Inject comments back.
    $result_lines = $comment_manager->inject($altered_lines);
    $actual_content = implode("\n", $result_lines);

    $this->assertEquals(
      $expected_content,
      $actual_content,
      'Comments should be preserved correctly according to fixture'
    );
  }

  /**
   * Data provider for comment tests.
   */
  public static function commentsDataProvider(): array {
    return [
      'comments-simple-reordering' => [
        function (array $lines): array {
          // Simulate reordering by swapping content lines only.
          return ['another: thing', 'some: thing'];
        },
      ],
      'comments-indented-comments' => [
        function (array $lines): array {
          // Remove comments but keep structure.
          return array_filter($lines, fn($line): bool => !str_starts_with(trim($line), '#'));
        },
      ],
      'comments-edge-cases' => [
        function (array $lines): array {
          // Add new content to comment-only file.
          return ['key: value'];
        },
      ],
      'comments-literal-blocks' => [
        function (array $lines): array {
          // Keep the content as-is to test comment preservation.
          return $lines;
        },
      ],
      'comments-travis-yaml' => [
        function (array $lines): array {
          // Remove comments to simulate parsing/dumping cycle.
          return array_filter($lines, fn($line): bool => !str_starts_with(trim($line), '#') && trim($line) !== '');
        },
      ],
    ];
  }

}
