<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Dumper;

use AlexSkrypnyk\Yaml\Dumper\Escaper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Escaper class.
 */
#[CoversClass(Escaper::class)]
class EscaperTest extends TestCase {

  #[DataProvider('dataProviderRequiresSingleQuotingValueString')]
  public function testRequiresSingleQuotingValueString(string $input, bool $expected, string $reason): void {
    $actual = Escaper::requiresSingleQuotingValueString($input);
    $this->assertSame($expected, $actual, $reason);
  }

  public static function dataProviderRequiresSingleQuotingValueString(): array {
    // @see https://www.yaml.info/learn/quote.html
    return [
      // Strings that should NOT require quoting (FALSE cases).
      ['', FALSE, 'Empty string should not require quoting'],
      ['simple', FALSE, 'Simple word should not require quoting'],
      ['hello world', FALSE, 'String with spaces should not require quoting'],
      ['docker compose restart', FALSE, 'Command with spaces should not require quoting'],
      ['Custom local commands. See `ahoy local help`.', FALSE, 'String with backticks should not require quoting'],
      ['Stop and remove containers, images, volumes and networks.', FALSE, 'String with punctuation should not require quoting'],
      ['ahoy cli "cd ${WEBROOT}/themes/custom/${DRUPAL_THEME} && yarn run build"', FALSE, 'Complex command should not require quoting'],
      ['\\[ -n "${VORTEX_DB_IMAGE}" \\] && docker pull ${VORTEX_DB_IMAGE} || true', FALSE, 'Shell command with brackets should not require quoting'],
      ['-c', FALSE, 'Command line option should not require quoting'],
      ['-e', FALSE, 'Another command line option should not require quoting'],
      ['test-with-dashes', FALSE, 'String with dashes should not require quoting'],
      ['file.txt', FALSE, 'Filename should not require quoting'],
      ['path/to/file', FALSE, 'File path should not require quoting'],

      // Strings that SHOULD require quoting (TRUE cases).
      ['null', TRUE, 'YAML reserved value null should require quoting'],
      ['~', TRUE, 'YAML null symbol should require quoting'],
      ['true', TRUE, 'YAML boolean true should require quoting'],
      ['false', TRUE, 'YAML boolean false should require quoting'],
      ['y', TRUE, 'YAML boolean y should require quoting'],
      ['n', TRUE, 'YAML boolean n should require quoting'],
      ['yes', TRUE, 'YAML boolean yes should require quoting'],
      ['no', TRUE, 'YAML boolean no should require quoting'],
      ['on', TRUE, 'YAML boolean on should require quoting'],
      ['off', TRUE, 'YAML boolean off should require quoting'],
      ['True', TRUE, 'Mixed case boolean should require quoting'],
      ['FALSE', TRUE, 'Uppercase boolean should require quoting'],

      // Numeric values.
      ['123', TRUE, 'Integer should require quoting'],
      ['3.14', TRUE, 'Float should require quoting'],
      ['-42', TRUE, 'Negative integer should require quoting'],
      ['+7.5', TRUE, 'Positive float should require quoting'],
      ['1e5', TRUE, 'Scientific notation should require quoting'],
      ['2.5e-3', TRUE, 'Scientific notation with negative exponent should require quoting'],

      // Strings starting with YAML special characters.
      ['!tag', TRUE, 'String starting with ! should require quoting'],
      ['&anchor', TRUE, 'String starting with & should require quoting'],
      ['*alias', TRUE, 'String starting with * should require quoting'],
      [':key', TRUE, 'String starting with : should require quoting'],
      ['?explicit', TRUE, 'String starting with ? should require quoting'],
      ['#comment', TRUE, 'String starting with # should require quoting'],
      ['|literal', TRUE, 'String starting with | should require quoting'],
      ['>folded', TRUE, 'String starting with > should require quoting'],
      ['@reserved', TRUE, 'String starting with @ should require quoting'],
      ['`reserved', TRUE, 'String starting with ` should require quoting'],

      // Strings with leading/trailing whitespace.
      [' test', TRUE, 'String with leading whitespace should require quoting'],
      ['test ', TRUE, 'String with trailing whitespace should require quoting'],
      [' test ', TRUE, 'String with leading and trailing whitespace should require quoting'],
      ["\ttest", TRUE, 'String with leading tab should require quoting'],
      ["test\n", TRUE, 'String with trailing newline should require quoting'],

      // Strings starting with flow collection markers.
      ['[array]', TRUE, 'String starting with [ should require quoting'],
      [']end', TRUE, 'String starting with ] should require quoting'],
      ['{object}', TRUE, 'String starting with { should require quoting'],
      ['}end', TRUE, 'String starting with } should require quoting'],

      // Strings starting with single quotes.
      ["'quoted", TRUE, 'String starting with single quote should require quoting'],
      ["'test string'", TRUE, 'String starting with single quote should require quoting even if properly closed'],
    ];
  }

}
