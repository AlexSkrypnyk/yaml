<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Internal;

use AlexSkrypnyk\Yaml\Internal\ContentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ContentParser class.
 */
#[CoversClass(ContentParser::class)]
class ContentParserTest extends TestCase {

  #[DataProvider('dataProviderLineEndings')]
  public function testLineEndingDetection(string $content, string $expected_ending, string $reason): void {
    $parser = new ContentParser($content);
    $this->assertSame($expected_ending, $parser->getEol(), $reason);
  }

  #[DataProvider('dataProviderContentParsing')]
  public function testContentParsing(string $content, array $expected_lines): void {
    $parser = new ContentParser($content);
    $this->assertSame($expected_lines, $parser->getLines());
  }

  public function testReconstructContent(): void {
    $original_content = "line1\nline2\nline3";
    $parser = new ContentParser($original_content);

    $lines = $parser->getLines();
    $reconstructed = $parser->reconstructContent($lines);

    $this->assertSame($original_content, $reconstructed);
  }

  public function testReconstructContentWithDifferentLines(): void {
    $parser = new ContentParser("original\ncontent");

    $new_lines = ['modified', 'content', 'here'];
    $reconstructed = $parser->reconstructContent($new_lines);

    // Should use the original line ending but with new content.
    $this->assertSame("modified\ncontent\nhere", $reconstructed);
  }

  #[DataProvider('dataProviderCollapseEmptyLinesInLiteralBlocks')]
  public function testCollapseLiteralBlockEmptyLines(string $input, string $expected): void {
    $parser = new ContentParser($input);
    $actual = $parser->collapseLiteralBlockEmptyLines();
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderLineEndings(): array {
    return [
      ["line1\r\nline2\r\nline3", "\r\n", 'Windows line endings should be detected'],
      ["line1\rline2\rline3", "\r", 'Old Mac line endings should be detected'],
      ["line1\nline2\nline3", "\n", 'Unix/Linux line endings should be detected'],
      ['single line', "\n", 'Single line should default to Unix line ending'],
      ['', "\n", 'Empty content should default to Unix line ending'],
      ["line1\r\nline2\rline3", "\r\n", 'Mixed endings should prioritize Windows (\\r\\n first)'],
    ];
  }

  public static function dataProviderContentParsing(): array {
    return [
      ["line1\nline2\nline3", ['line1', 'line2', 'line3']],
      ["line1\r\nline2\r\nline3", ['line1', 'line2', 'line3']],
      ["line1\rline2\rline3", ['line1', 'line2', 'line3']],
      ['single line', ['single line']],
      ['', ['']],
      ["line1\n\nline3", ['line1', '', 'line3']],
    ];
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
      'literal block with empty lines in middle of content' => [
        <<<YAML
        description: |
          First line


          Second line
          Third line
        YAML,
        <<<YAML
        description: |
          First line
          Second line
          Third line
        YAML,
      ],
      'literal block with mixed empty lines at start and middle' => [
        <<<YAML
        description: |


          Start content

          Middle content
          End content
        YAML,
        <<<YAML
        description: |
          Start content
          Middle content
          End content
        YAML,
      ],
    ];
  }

}
