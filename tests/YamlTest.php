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

    $actual_content = Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

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

}
