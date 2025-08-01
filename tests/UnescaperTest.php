<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests;

use AlexSkrypnyk\Yaml\Unescaper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Unescaper class.
 */
#[CoversClass(Unescaper::class)]
class UnescaperTest extends TestCase {

  public function testUnescapeSingleQuotedValueStringWithoutOriginalLines(): void {
    // Test unescapeSingleQuotedValueString method when original_lines is NULL.
    $content = "key: 'value'";
    $result = Unescaper::unescapeSingleQuotedValueString($content, NULL);
    $this->assertSame($content, $result);
  }

  public function testUnescapeSingleQuotedValueStringWithValidContent(): void {
    // Test unescapeSingleQuotedValueString method with proper original lines.
    $content = "key: 'hello world'";
    $original_lines = ["key: value"];
    // DUMP_UNGREEDY_SINGLE_QUOTING.
    $result = Unescaper::unescapeSingleQuotedValueString($content, $original_lines, 1 << 10);
    $this->assertStringContainsString('key: hello world', $result);
  }

  public function testBuildOriginallyQuotedMap(): void {
    // Test the buildOriginallyQuotedMap method indirectly through
    // unescapeSingleQuotedValueString.
    $content = "key: 'quoted value'";
    $original_lines = ["key: 'quoted value'"];
    $result = Unescaper::unescapeSingleQuotedValueString($content, $original_lines);
    // Should keep the quotes since it was originally quoted.
    $this->assertStringContainsString("key: 'quoted value'", $result);
  }

}