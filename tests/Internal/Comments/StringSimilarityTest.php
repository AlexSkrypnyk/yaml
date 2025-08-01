<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Internal\Comments;

use AlexSkrypnyk\Yaml\Internal\Comments\StringSimilarity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the StringSimilarity class.
 */
#[CoversClass(StringSimilarity::class)]
class StringSimilarityTest extends TestCase {

  /**
   * Test string similarity calculation with various inputs.
   */
  #[DataProvider('similarityDataProvider')]
  public function testCalculateSimilarity(string $str1, string $str2, float $expected_min, float $expected_max, string $description): void {
    $similarity = new StringSimilarity();
    $actual = $similarity->calculateSimilarity($str1, $str2);

    $this->assertGreaterThanOrEqual($expected_min, $actual, $description . " - minimum threshold");
    $this->assertLessThanOrEqual($expected_max, $actual, $description . " - maximum threshold");
    $this->assertGreaterThanOrEqual(0.0, $actual, "Similarity should be >= 0.0");
    $this->assertLessThanOrEqual(1.0, $actual, "Similarity should be <= 1.0");
  }

  /**
   * Data provider for string similarity tests.
   */
  public static function similarityDataProvider(): array {
    return [
      // Perfect matches.
      ['exact', 'exact', 1.0, 1.0, 'Identical strings'],
      ['', '', 1.0, 1.0, 'Both empty strings'],
      ['single', 'single', 1.0, 1.0, 'Single word match'],
      ['multi word phrase', 'multi word phrase', 1.0, 1.0, 'Multi-word phrase match'],

      // No similarity.
      ['', 'nonempty', 0.0, 0.0, 'Empty vs non-empty'],
      ['nonempty', '', 0.0, 0.0, 'Non-empty vs empty'],
      ['completely', 'different', 0.0, 0.5, 'Completely different words'],
      ['abc', 'xyz', 0.0, 0.4, 'No common characters'],

      // High similarity - single character changes.
      ['test', 'tost', 0.5, 1.0, 'Single character substitution'],
      ['hello', 'hallo', 0.6, 1.0, 'Single vowel change'],
      ['world', 'wprld', 0.6, 1.0, 'Single consonant change'],
      ['cat', 'bat', 0.4, 0.9, 'First character different'],
      ['dog', 'log', 0.4, 0.9, 'First character different short'],

      // High similarity - insertions/deletions.
      ['test', 'tests', 0.7, 1.0, 'Single character addition'],
      ['tests', 'test', 0.7, 1.0, 'Single character deletion'],
      ['hello', 'helo', 0.6, 1.0, 'Single character removal'],
      ['word', 'words', 0.7, 1.0, 'Plural form'],

      // Medium similarity - multiple changes.
      ['testing', 'tasting', 0.4, 1.0, 'Multiple character changes'],
      ['similar', 'similr', 0.6, 1.0, 'Missing middle character'],
      ['compare', 'compar', 0.7, 1.0, 'Missing end character'],
      ['example', 'exampl', 0.7, 1.0, 'Missing last character'],

      // Case sensitivity tests.
      ['Test', 'test', 0.6, 1.0, 'Case difference'],
      ['HELLO', 'hello', 0.0, 0.2, 'All caps vs lowercase'],
      ['MiXeD', 'mixed', 0.4, 1.0, 'Mixed case difference'],

      // Common YAML key scenarios.
      ['database', 'database', 1.0, 1.0, 'YAML key exact match'],
      ['database', 'Database', 0.6, 1.0, 'YAML key case difference'],
      ['database_config', 'databaseconfig', 0.7, 1.0, 'YAML key underscore removed'],
      ['database-config', 'database_config', 0.7, 1.0, 'YAML key dash to underscore'],
      ['api_key', 'apikey', 0.6, 1.0, 'YAML key underscore removed short'],

      // Common YAML value scenarios.
      ['true', 'True', 0.6, 1.0, 'Boolean case difference'],
      ['false', 'False', 0.6, 1.0, 'Boolean case difference'],
      ['null', 'NULL', 0.0, 0.2, 'Null case difference'],
      ['localhost', 'localhost', 1.0, 1.0, 'Hostname exact'],
      ['127.0.0.1', '127.0.0.1', 1.0, 1.0, 'IP address exact'],

      // Prefix/suffix scenarios.
      ['prefix_test', 'prefix', 0.5, 1.0, 'Prefix match'],
      ['test_suffix', 'suffix', 0.4, 0.8, 'Suffix match'],
      ['prefix_middle_suffix', 'middle', 0.3, 0.7, 'Middle substring'],

      // Length difference scenarios.
      ['a', 'ab', 0.3, 1.0, 'Very short with addition'],
      ['ab', 'a', 0.3, 1.0, 'Very short with removal'],
      ['short', 'very_long_string', 0.0, 0.5, 'Large length difference'],
      ['very_long_string', 'short', 0.0, 0.5, 'Large length difference reverse'],

      // Special characters.
      ['test-name', 'test_name', 0.6, 1.0, 'Dash to underscore'],
      ['test.name', 'testname', 0.6, 1.0, 'Dot removal'],
      ['test@name', 'testname', 0.6, 1.0, 'At symbol removal'],
      ['test name', 'testname', 0.6, 1.0, 'Space removal'],

      // Numbers and mixed content.
      ['version1', 'version2', 0.6, 1.0, 'Number difference'],
      ['test123', 'test456', 0.4, 0.9, 'Different numbers'],
      ['item_1', 'item_2', 0.7, 1.0, 'Sequential items'],
      ['config_v1', 'config_v2', 0.7, 1.0, 'Version difference'],

      // Transposition scenarios (good for Jaro-Winkler)
      ['ab', 'ba', 0.3, 0.8, 'Simple transposition'],
      ['abc', 'bac', 0.4, 0.9, 'First two transposed'],
      ['test', 'tset', 0.4, 1.0, 'Middle characters transposed'],
      ['word', 'wrod', 0.4, 1.0, 'Last two transposed'],

      // Common misspellings/typos.
      ['receive', 'recieve', 0.6, 1.0, 'Common misspelling'],
      ['separate', 'seperate', 0.6, 1.0, 'Common misspelling'],
      ['definitely', 'definately', 0.6, 1.0, 'Common misspelling'],

      // Edge cases with very similar strings.
      ['almost_identical', 'almost_identica', 0.8, 1.0, 'Very similar strings'],
      ['nearly_same', 'nearly_sam', 0.8, 1.0, 'Very similar short'],
      ['config_database', 'config_databas', 0.8, 1.0, 'Very similar config key'],

      // Repeated characters.
      ['aaa', 'aa', 0.5, 1.0, 'Repeated character removal'],
      ['hello', 'hellow', 0.7, 1.0, 'Repeated character addition'],
      ['bookkeeper', 'bookeeper', 0.8, 1.0, 'Double letter removal'],

      // Real-world YAML scenarios.
      ['mysql_host', 'mysql_port', 0.4, 1.0, 'Similar config keys'],
      ['redis_url', 'redis_uri', 0.6, 1.0, 'URL vs URI'],
      ['enable_cache', 'enable_caching', 0.7, 1.0, 'Gerund form'],
      ['max_connections', 'max_connection', 0.8, 1.0, 'Singular vs plural'],
    ];
  }

  /**
   * Test that similarity is symmetric.
   */
  #[DataProvider('symmetryDataProvider')]
  public function testSimilaritySymmetry(string $str1, string $str2): void {
    $similarity = new StringSimilarity();
    $score1 = $similarity->calculateSimilarity($str1, $str2);
    $score2 = $similarity->calculateSimilarity($str2, $str1);

    $this->assertEquals($score1, $score2, sprintf("Similarity should be symmetric for '%s' and '%s'", $str1, $str2));
  }

  /**
   * Data provider for symmetry tests.
   */
  public static function symmetryDataProvider(): array {
    return [
      ['hello', 'world'],
      ['test', 'testing'],
      ['', 'nonempty'],
      ['abc', 'xyz'],
      ['similar', 'similr'],
      ['database', 'Database'],
      ['prefix_test', 'test_suffix'],
      ['very_long_string', 'short'],
      ['test-name', 'test_name'],
      ['version1', 'version2'],
    ];
  }

  /**
   * Test boundary values and edge cases.
   */
  #[DataProvider('boundaryDataProvider')]
  public function testBoundaryValues(string $str1, string $str2, float $expected, string $description): void {
    $similarity = new StringSimilarity();
    $actual = $similarity->calculateSimilarity($str1, $str2);

    $this->assertEquals($expected, $actual, $description);
  }

  /**
   * Data provider for boundary value tests.
   */
  public static function boundaryDataProvider(): array {
    return [
      ['', '', 1.0, 'Both empty should be perfect match'],
      ['a', '', 0.0, 'Empty vs single char should be no match'],
      ['', 'a', 0.0, 'Single char vs empty should be no match'],
      ['identical', 'identical', 1.0, 'Identical strings should be perfect match'],
    ];
  }

}
