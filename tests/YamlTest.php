<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests;

use AlexSkrypnyk\Yaml\Ast\AstException;
use AlexSkrypnyk\Yaml\Ast\AstTree;
use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Dumper\Dumper;
use AlexSkrypnyk\Yaml\Parser\Lexer;
use AlexSkrypnyk\Yaml\Parser\LineMatcher;
use AlexSkrypnyk\Yaml\Parser\Parser;
use AlexSkrypnyk\Yaml\Yaml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Yaml class.
 */
#[CoversClass(AstTree::class)]
#[CoversClass(Dumper::class)]
#[CoversClass(Lexer::class)]
#[CoversClass(LineMatcher::class)]
#[CoversClass(Node::class)]
#[CoversClass(Parser::class)]
#[CoversClass(Yaml::class)]
class YamlTest extends TestCase {

  /**
   * Directory where fixture YAML files are stored.
   */
  protected string $fixturesDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fixturesDir = __DIR__ . '/fixtures';
  }

  #[DataProvider('dataProviderUpdate')]
  public function testUpdate(?callable $manipulator = NULL, int $additional_flags = 0): void {
    $dir = $this->fixturesDir . '/' . $this->dataName();
    if (!is_dir($dir)) {
      throw new \RuntimeException('Directory "' . $dir . '" does not exist');
    }
    $before = $dir . '/before.yml';
    $after = $dir . '/after.yml';

    // Ensure fixture files exist.
    $this->assertFileExists($before, 'Before fixture file should exist');
    $this->assertFileExists($after, 'After fixture file should exist');

    // Use instance-based API for all tests.
    $yaml = new Yaml();
    $yaml->load($before);

    if (is_callable($manipulator)) {
      $manipulator($yaml);
    }

    $actual_content = $yaml->dump($additional_flags);

    $expected_content = file_get_contents($after);

    $this->assertEquals(
      $expected_content,
      $actual_content,
      'Output should match expected fixture with preserved comments'
    );
  }

  /**
   * Data provider for testUpdate method.
   *
   * @return array<string, array<int, callable(Yaml): void|int|null>>
   *   Test data with optional YAML manipulator callbacks and flags.
   */
  public static function dataProviderUpdate(): array {
    return [
      'idempotence' => [NULL],
      'update' => [
        function (Yaml $yaml): void {
          $yaml->setValue(['commands', 'build', 'usage'], 'Updated build description with new functionality.');
        },
      ],
      'set-value-simple' => [
        function (Yaml $yaml): void {
          $yaml->setValue(['ahoyapi'], 'v3');
        },
      ],
      'set-value-nested' => [
        function (Yaml $yaml): void {
          $yaml->setValue(['commands', 'down', 'usage'], 'Updated stop command description');
        },
      ],
      'set-value-new-key' => [
        function (Yaml $yaml): void {
          $yaml->setValue(['version'], '1.2.0');
        },
      ],
      'add-key-command' => [
        function (Yaml $yaml): void {
          $yaml->addKey(['commands'], 'deploy', [
            'usage' => 'Deploy the application',
            'cmd' => 'make deploy',
          ]);
        },
      ],
      'delete-key-command' => [
        function (Yaml $yaml): void {
          $yaml->deleteKey(['commands', 'local2']);
        },
      ],
      'delete-key-nested' => [
        function (Yaml $yaml): void {
          $yaml->deleteKey(['commands', 'build', 'usage']);
        },
      ],
      'multiple-operations' => [
        function (Yaml $yaml): void {
          $yaml->setValue(['ahoyapi'], 'v3');
          $yaml->setValue(['commands', 'build', 'usage'], 'Enhanced build process');
          $yaml->deleteKey(['commands', 'local']);
        },
      ],
      'collapse-empty-lines-without-flag' => [NULL],
      'collapse-empty-lines-with-flag' => [
        NULL,
        Yaml::DUMP_COLLAPSE_LITERAL_BLOCK_EMPTY_LINES,
      ],
    ];
  }

  public function testParseFileThrowsExceptionOnInvalidFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('File does not exist: /nonexistent/file.yml');
    $yaml = new Yaml();
    $yaml->load('/nonexistent/file.yml');
  }

  public function testDumpWithoutOriginalLines(): void {
    $yaml = new Yaml();
    $yaml->parse("key: value\n");
    $result = $yaml->dump();
    $this->assertStringContainsString('key: value', $result);
  }

  public function testDumpWithNonArrayInput(): void {
    $yaml = new Yaml();
    $yaml->parse("value: 'simple string'");
    $result = $yaml->dump();
    $this->assertStringContainsString("'simple string'", $result);
  }

  /**
   * Test error conditions for manipulator methods.
   */
  public function testGetValueErrorConditions(): void {
    $yaml = new Yaml();
    $yaml->load($this->fixturesDir . '/idempotence/before.yml');

    // Test non-existent path throws exception.
    $this->expectException(AstException::class);
    $this->expectExceptionMessage('Path not found: nonexistent');
    $yaml->getValue(['nonexistent']);
  }

  public function testSetValueErrorConditions(): void {
    $yaml = new Yaml();
    $yaml->load($this->fixturesDir . '/idempotence/before.yml');

    // Test empty path throws exception.
    $this->expectException(AstException::class);
    $this->expectExceptionMessage('Path cannot be empty');
    $yaml->setValue([], 'value');
  }

  public function testDeleteKeyErrorConditions(): void {
    $yaml = new Yaml();
    $yaml->load($this->fixturesDir . '/idempotence/before.yml');

    // Test empty path throws exception.
    $this->expectException(AstException::class);
    $this->expectExceptionMessage('Path cannot be empty');
    $yaml->deleteKey([]);
  }

  public function testSaveInvalidPath(): void {
    $yaml = new Yaml();
    $yaml->load($this->fixturesDir . '/idempotence/before.yml');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to write file: /invalid/path/file.yml');

    // Suppress the PHP warning for this test.
    @$yaml->save('/invalid/path/file.yml');
  }

  public function testCommentManipulation(): void {
    $yaml = new Yaml();
    $yaml->load($this->fixturesDir . '/idempotence/before.yml');

    // Test getting existing comment.
    $comment = $yaml->getComment(['ahoyapi']);
    $this->assertIsString($comment);

    // Test setting a new comment.
    $yaml->setComment(['ahoyapi'], '# Custom API version');
    $updatedComment = $yaml->getComment(['ahoyapi']);
    $this->assertEquals('# Custom API version', $updatedComment);
  }

  public function testCommentManipulationInvalidPath(): void {
    $yaml = new Yaml();
    $yaml->load($this->fixturesDir . '/idempotence/before.yml');

    // Test invalid path throws exception for getComment.
    $this->expectException(AstException::class);
    $this->expectExceptionMessage('Path not found: nonexistent.path');
    $yaml->getComment(['nonexistent', 'path']);
  }

}
