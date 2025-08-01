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
  public function testUpdate(?callable $manipulator = NULL): void {
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

    $actual_content = Yaml::dump($data, 3, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_UNGREEDY_SINGLE_QUOTING);

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
   * @return array<string, array<int, callable(array<mixed>): array<mixed>>>
   *   Test data with optional data manipulator callbacks.
   */
  public static function dataProviderUpdate(): array {
    return [
      'idempotence' => [],
      'update' => [
        function (array $data) {
          $data['commands']['build']['usage'] = 'Updated build description with new functionality.';
          return $data;
        },
      ],
    ];
  }

  #[DataProvider('dataProviderCollapseEmptyLinesInLiteralBlocks')]
  public function testCollapseEmptyLinesInLiteralBlocks(string $input, string $expected): void {
    $actual = Yaml::collapseEmptyLinesInLiteralBlocks($input);
    $this->assertSame($expected, $actual);
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

  public function testToLinesWithWindowsLineEndings(): void {
    $content = "line1\r\nline2\r\nline3";
    $data = Yaml::parse($content);
    $result = Yaml::dump($data);
    $this->assertStringContainsString('line1', $result);
  }

  public function testToLinesWithOldMacLineEndings(): void {
    $content = "line1\rline2\rline3";
    $data = Yaml::parse($content);
    $result = Yaml::dump($data);
    $this->assertStringContainsString('line1', $result);
  }

  public function testUngreedyFlagBehavior(): void {
    $data = ['key' => 'hello world'];

    // Without the flag, should use strict Symfony quoting.
    $strict_result = Yaml::dump($data);
    $this->assertStringContainsString("key: 'hello world'", $strict_result, 'Without flag should quote strings with spaces');

    // With the flag, should use ungreedy quoting.
    // Set up context for unquoting.
    Yaml::parse("key: value\n");
    $ungreedy_result = Yaml::dump($data, 2, 4, Yaml::DUMP_UNGREEDY_SINGLE_QUOTING);
    $this->assertStringContainsString("key: hello world", $ungreedy_result, 'With flag should not quote strings with spaces');
  }

  public static function dataProviderCollapseEmptyLinesInLiteralBlocks(): array {
    return [
      'empty string' => [
        '',
        '',
      ],
      'no literal blocks' => [
        <<<YAML
        key: value
        another: test
        YAML,
        <<<YAML
        key: value
        another: test
        YAML,
      ],
      'literal block immediately after pipe' => [
        <<<YAML
        |


        content
        YAML,
        <<<YAML
        |
        content
        YAML,
      ],
      'literal block with multiple empty lines after pipe' => [
        <<<YAML
        |



        content
        YAML,
        <<<YAML
        |
        content
        YAML,
      ],
      'literal block with whitespace in empty lines' => [
        <<<YAML
        |


        content
        YAML,
        <<<YAML
        |
        content
        YAML,
      ],
      'multiple literal blocks with collapsible lines' => [
        <<<YAML
        first: |


        content1
        second: |


        content2
        YAML,
        <<<YAML
        first: |
        content1
        second: |
        content2
        YAML,
      ],
      'mixed content no effect on non-literal blocks' => [
        <<<YAML
        key: value


        description: |


        content
        another: test
        YAML,
        <<<YAML
        key: value


        description: |
        content
        another: test
        YAML,
      ],
      'literal block with no empty lines to collapse' => [
        <<<YAML
        |
        line1
        line2
        line3
        YAML,
        <<<YAML
        |
        line1
        line2
        line3
        YAML,
      ],
      'literal block with single empty line after pipe' => [
        <<<YAML
        |

        content
        YAML,
        <<<YAML
        |
        content
        YAML,
      ],
      'preg_replace returns null preserves original' => [
        // Create a string with invalid UTF-8 that could cause preg_replace to
        // return null.
        // This simulates the case where the regex engine fails due to encoding
        // issues.
        "|\n\xFF\xFE\n\ncontent",
        "|\n\xFF\xFE\n\ncontent",
      ],
    ];
  }

}
