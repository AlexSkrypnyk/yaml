<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Internal\Comments;

use AlexSkrypnyk\Yaml\Internal\Comments\Line;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Line class.
 */
#[CoversClass(Line::class)]
class LineTest extends TestCase {

  /**
   * Test Line construction with various YAML content.
   */
  #[DataProvider('lineConstructionDataProvider')]
  public function testLineConstruction(
    string $input_line,
    int $expected_level,
    ?string $expected_key,
    ?string $expected_value,
    string $expected_normalized_key,
    string $expected_normalized_value,
    bool $expected_is_comment,
    bool $expected_is_blank,
    string $description,
  ): void {
    $line = new Line($input_line, 5);

    $this->assertSame($input_line, $line->getOriginal(), $description . ' - original');
    $this->assertSame(5, $line->getPosition(), $description . ' - position');
    $this->assertSame($expected_level, $line->getLevel(), $description . ' - level');
    $this->assertSame($expected_key, $line->getKey(), $description . ' - key');
    $this->assertSame($expected_value, $line->getValue(), $description . ' - value');
    $this->assertSame($expected_normalized_key, $line->getNormalizedKey(), $description . ' - normalized key');
    $this->assertSame($expected_normalized_value, $line->getNormalizedValue(), $description . ' - normalized value');
    $this->assertSame($expected_is_comment, $line->isComment(), $description . ' - is comment');
    $this->assertSame($expected_is_blank, $line->isBlank(), $description . ' - is blank');
  }

  /**
   * Test Line construction with empty string input.
   */
  public function testLineConstructionWithEmptyString(): void {
    $line = new Line('', 10);

    $this->assertSame('', $line->getOriginal());
    $this->assertSame(10, $line->getPosition());
    $this->assertSame(0, $line->getLevel());
    $this->assertNull($line->getKey());
    $this->assertNull($line->getValue());
    $this->assertSame('', $line->getNormalizedKey());
    $this->assertSame('', $line->getNormalizedValue());
    $this->assertFalse($line->isComment());
    $this->assertTrue($line->isBlank());
  }

  /**
   * Test Line construction with default position.
   */
  public function testLineConstructionWithDefaultPosition(): void {
    $line = new Line('test: value');

    $this->assertSame(0, $line->getPosition());
  }

  /**
   * Test hasKey method.
   */
  #[DataProvider('hasKeyDataProvider')]
  public function testHasKey(string $input_line, bool $expected, string $description): void {
    $line = new Line($input_line);
    $this->assertSame($expected, $line->hasKey(), $description);
  }

  /**
   * Test hasValue method.
   */
  #[DataProvider('hasValueDataProvider')]
  public function testHasValue(string $input_line, bool $expected, string $description): void {
    $line = new Line($input_line);
    $this->assertSame($expected, $line->hasValue(), $description);
  }

  /**
   * Test __toString method.
   */
  public function testToString(): void {
    $input = 'database: localhost';
    $line = new Line($input);
    $this->assertSame($input, (string) $line);
  }

  /**
   * Data provider for line construction tests.
   */
  public static function lineConstructionDataProvider(): array {
    return [
      // Basic key-value pairs.
      [
        'database: localhost',
        0, 'database', 'localhost', 'database', 'localhost',
        FALSE, FALSE, 'Simple key-value pair',
      ],
      [
        'port: 3306',
        0, 'port', '3306', 'port', '3306',
        FALSE, FALSE, 'Numeric value',
      ],
      [
        'enabled: true',
        0, 'enabled', 'true', 'enabled', 'true',
        FALSE, FALSE, 'Boolean value',
      ],

      // Indented lines.
      [
        '  database: localhost',
        1, 'database', 'localhost', 'database', 'localhost',
        FALSE, FALSE, '2-space indented line',
      ],
      [
        '    port: 3306',
        2, 'port', '3306', 'port', '3306',
        FALSE, FALSE, '4-space indented line',
      ],
      [
        '      nested: value',
        3, 'nested', 'value', 'nested', 'value',
        FALSE, FALSE, '6-space indented line',
      ],

      // Keys without values.
      [
        'database:',
        0, 'database', NULL, 'database', '',
        FALSE, FALSE, 'Key without value',
      ],
      [
        '  config:',
        1, 'config', NULL, 'config', '',
        FALSE, FALSE, 'Indented key without value',
      ],

      // Array items.
      [
        '- item1',
        0, '-', 'item1', '', 'item1',
        FALSE, FALSE, 'Array item',
      ],
      [
        '  - nested_item',
        1, '-', 'nested_item', '', 'nesteditem',
        FALSE, FALSE, 'Indented array item',
      ],
      [
        '-   spaced_item',
        0, '-', 'spaced_item', '', 'spaceditem',
        FALSE, FALSE, 'Array item with extra spaces',
      ],

      // Quoted keys and values.
      [
        '"quoted_key": "quoted_value"',
        0, '"quoted_key"', '"quoted_value"', 'quotedkey', 'quotedvalue',
        FALSE, FALSE, 'Quoted key and value',
      ],
      [
        "'single_key': 'single_value'",
        0, "'single_key'", "'single_value'", 'singlekey', 'singlevalue',
        FALSE, FALSE, 'Single quoted key and value',
      ],

      // Complex values.
      [
        'url: https://example.com:8080/path',
        0, 'url', 'https://example.com:8080/path', 'url', 'httpsexamplecom8080path',
        FALSE, FALSE, 'URL value',
      ],
      [
        'version: 1.2.3-beta',
        0, 'version', '1.2.3-beta', 'version', '123beta',
        FALSE, FALSE, 'Version string',
      ],

      // Keys with special characters.
      [
        'database-host: localhost',
        0, 'database-host', 'localhost', 'databasehost', 'localhost',
        FALSE, FALSE, 'Key with hyphen',
      ],
      [
        'database_port: 3306',
        0, 'database_port', '3306', 'databaseport', '3306',
        FALSE, FALSE, 'Key with underscore',
      ],
      [
        'api.key: secret123',
        0, 'api.key', 'secret123', 'apikey', 'secret123',
        FALSE, FALSE, 'Key with dot',
      ],

      // Comments.
      [
        '# This is a comment',
        0, NULL, NULL, '', '',
        TRUE, FALSE, 'Comment line',
      ],
      [
        '  # Indented comment',
        1, NULL, NULL, '', '',
        TRUE, FALSE, 'Indented comment',
      ],
      [
        '#No space comment',
        0, NULL, NULL, '', '',
        TRUE, FALSE, 'Comment without space',
      ],

      // Blank lines.
      [
        '',
        0, NULL, NULL, '', '',
        FALSE, TRUE, 'Empty line',
      ],
      [
        '   ',
        0, NULL, NULL, '', '',
        FALSE, TRUE, 'Whitespace only line',
      ],
      [
        "\t\t",
        0, NULL, NULL, '', '',
        FALSE, TRUE, 'Tab only line',
      ],

      // Lines without colons (standalone values)
      [
        'standalone_value',
        0, NULL, 'standalone_value', '', 'standalonevalue',
        FALSE, FALSE, 'Standalone value',
      ],
      [
        '  indented_standalone',
        1, NULL, 'indented_standalone', '', 'indentedstandalone',
        FALSE, FALSE, 'Indented standalone value',
      ],

      // Complex YAML structures.
      [
        'database: { host: localhost, port: 3306 }',
        0, 'database', '{ host: localhost, port: 3306 }', 'database', 'hostlocalhostport3306',
        FALSE, FALSE, 'Inline object value',
      ],
      [
        'items: [one, two, three]',
        0, 'items', '[one, two, three]', 'items', 'onetwothree',
        FALSE, FALSE, 'Inline array value',
      ],

      // Edge cases with normalization.
      [
        'CamelCase: MixedCASE',
        0, 'CamelCase', 'MixedCASE', 'camelcase', 'mixedcase',
        FALSE, FALSE, 'Mixed case key and value',
      ],
      [
        'key_with-symbols.here: value@with#symbols!',
        0, 'key_with-symbols.here', 'value@with#symbols!', 'keywithsymbolshere', 'valuewithsymbols',
        FALSE, FALSE, 'Key and value with symbols',
      ],

      // YAML literal and folded blocks.
      [
        'description: |',
        0, 'description', '|', 'description', 'literalblock',
        FALSE, FALSE, 'Literal block indicator',
      ],
      [
        'text: >',
        0, 'text', '>', 'text', 'literalblock',
        FALSE, FALSE, 'Folded block indicator',
      ],

      // Odd spacing.
      [
        '   key   :   value   ',
        1, 'key', 'value', 'key', 'value',
        FALSE, FALSE, 'Key-value with extra spaces',
      ],
      [
        'key:value',
        0, 'key', 'value', 'key', 'value',
        FALSE, FALSE, 'Key-value without spaces',
      ],
    ];
  }

  /**
   * Data provider for hasContent tests.
   */
  public static function hasContentDataProvider(): array {
    return [
      ['database: localhost', TRUE, 'Key-value pair has content'],
      ['# Comment', FALSE, 'Comment does not have content'],
      ['', FALSE, 'Empty line does not have content'],
      ['   ', FALSE, 'Whitespace line does not have content'],
      ['- item', TRUE, 'Array item has content'],
      ['standalone', TRUE, 'Standalone value has content'],
      ['key:', TRUE, 'Key without value has content'],
    ];
  }

  /**
   * Data provider for hasKey tests.
   */
  public static function hasKeyDataProvider(): array {
    return [
      ['database: localhost', TRUE, 'Key-value pair has key'],
      ['key:', TRUE, 'Key without value has key'],
      ['- item', TRUE, 'Array item has key (-)'],
      ['standalone', FALSE, 'Standalone value has no key'],
      ['# Comment', FALSE, 'Comment has no key'],
      ['', FALSE, 'Empty line has no key'],
      ['   ', FALSE, 'Whitespace line has no key'],
    ];
  }

  /**
   * Data provider for hasValue tests.
   */
  public static function hasValueDataProvider(): array {
    return [
      ['database: localhost', TRUE, 'Key-value pair has value'],
      ['key:', FALSE, 'Key without value has no value'],
      ['- item', TRUE, 'Array item has value'],
      ['standalone', TRUE, 'Standalone value has value'],
      ['# Comment', FALSE, 'Comment has no value'],
      ['', FALSE, 'Empty line has no value'],
      ['   ', FALSE, 'Whitespace line has no value'],
      ['key: ""', TRUE, 'Empty quoted string is still a value'],
      ["key: ''", TRUE, 'Empty single quoted string is still a value'],
    ];
  }

}
