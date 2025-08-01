<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests;

use AlexSkrypnyk\Yaml\Yaml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Yaml class.
 */
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

    $data = Yaml::parseFile($before);

    if (is_callable($manipulator)) {
      $data = $manipulator($data);
    }

    $flags = Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | $additional_flags;
    // @phpstan-ignore-next-line
    $actual_content = Yaml::dump($data, 3, 2, $flags);

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
   * @return array<string, array<int, callable(array<mixed>): array<mixed>|int|null>>
   *   Test data with optional data manipulator callbacks and flags.
   */
  public static function dataProviderUpdate(): array {
    return [
      'idempotence' => [NULL],
      'update' => [
        function (array $data) {
          $data['commands']['build']['usage'] = 'Updated build description with new functionality.';
          return $data;
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
    Yaml::parseFile('/nonexistent/file.yml');
  }

  public function testDumpWithoutOriginalLines(): void {
    $data = ['key' => 'value'];
    $result = Yaml::dump($data);
    $this->assertStringContainsString('key: value', $result);
  }

  public function testDumpWithNonArrayInput(): void {
    Yaml::parse("key: value\n");
    $result = Yaml::dump('simple string');
    $this->assertSame("'simple string'", $result);
  }

  public function testUngreedyFlagBehavior(): void {
    $data = ['key' => 'hello world'];

    // By default, should use ungreedy quoting.
    // Set up context for unquoting.
    Yaml::parse("key: value\n");
    $ungreedy_result = Yaml::dump($data);
    $this->assertStringContainsString("key: hello world", $ungreedy_result, 'Default behavior should not quote strings with spaces');

    // With strict flag, should use strict Symfony quoting.
    $strict_result = Yaml::dump($data, 2, 4, Yaml::DUMP_STRICT_SINGLE_QUOTING);
    $this->assertStringContainsString("key: 'hello world'", $strict_result, 'With strict flag should quote strings with spaces');
  }

}
