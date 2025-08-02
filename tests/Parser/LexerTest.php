<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Parser;

use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use AlexSkrypnyk\Yaml\Parser\Lexer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Lexer class.
 */
#[CoversClass(Lexer::class)]
class LexerTest extends TestCase {

  #[DataProvider('dataProviderTokenizeLine')]
  public function testTokenizeLine(string $line, int $lineNumber, NodeType $expectedType, array $expectedData): void {
    $lexer = new Lexer();
    $result = $lexer->tokenizeLine($line, $lineNumber);

    $this->assertEquals($expectedType, $result->type);

    foreach ($expectedData as $property => $expectedValue) {
      $this->assertEquals($expectedValue, $result->$property, sprintf("Property '%s' should match expected value", $property));
    }
  }

  public static function dataProviderTokenizeLine(): array {
    return [
      // Blank lines.
      'empty line' => [
        'line' => '',
        'lineNumber' => 0,
        'expectedType' => NodeType::BlankLine,
        'expectedData' => [
          'rawLine' => '',
          'indent' => 0,
        ],
      ],
      'whitespace only line' => [
        'line' => '    ',
        'lineNumber' => 1,
        'expectedType' => NodeType::BlankLine,
        'expectedData' => [
          'rawLine' => '',
          'indent' => 4,
        ],
      ],

      // Comments.
      'simple comment' => [
        'line' => '# This is a comment',
        'lineNumber' => 2,
        'expectedType' => NodeType::Comment,
        'expectedData' => [
          'rawLine' => '# This is a comment',
          'indent' => 0,
        ],
      ],
      'indented comment' => [
        'line' => '  # Indented comment',
        'lineNumber' => 3,
        'expectedType' => NodeType::Comment,
        'expectedData' => [
          'rawLine' => '  # Indented comment',
          'indent' => 2,
        ],
      ],
      'comment with multiple hashes' => [
        'line' => '### Section header ###',
        'lineNumber' => 4,
        'expectedType' => NodeType::Comment,
        'expectedData' => [
          'rawLine' => '### Section header ###',
          'indent' => 0,
        ],
      ],

      // Key-value pairs.
      'simple key-value' => [
        'line' => 'key: value',
        'lineNumber' => 5,
        'expectedType' => NodeType::KeyValue,
        'expectedData' => [
          'key' => 'key',
          'value' => 'value',
          'rawLine' => 'key: value',
          'indent' => 0,
        ],
      ],
      'indented key-value' => [
        'line' => '  nested: item',
        'lineNumber' => 6,
        'expectedType' => NodeType::KeyValue,
        'expectedData' => [
          'key' => 'nested',
          'value' => 'item',
          'rawLine' => '  nested: item',
          'indent' => 2,
        ],
      ],
      'key-value with quoted string' => [
        'line' => 'message: "Hello world"',
        'lineNumber' => 7,
        'expectedType' => NodeType::KeyValue,
        'expectedData' => [
          'key' => 'message',
          'value' => '"Hello world"',
          'rawLine' => 'message: "Hello world"',
          'indent' => 0,
        ],
      ],
      'key-value with number' => [
        'line' => 'port: 8080',
        'lineNumber' => 8,
        'expectedType' => NodeType::KeyValue,
        'expectedData' => [
          'key' => 'port',
          'value' => '8080',
          'rawLine' => 'port: 8080',
          'indent' => 0,
        ],
      ],
      'key-value with boolean' => [
        'line' => 'enabled: true',
        'lineNumber' => 9,
        'expectedType' => NodeType::KeyValue,
        'expectedData' => [
          'key' => 'enabled',
          'value' => 'true',
          'rawLine' => 'enabled: true',
          'indent' => 0,
        ],
      ],

      // Mapping starts.
      'mapping start' => [
        'line' => 'section:',
        'lineNumber' => 10,
        'expectedType' => NodeType::MappingStart,
        'expectedData' => [
          'key' => 'section',
          'rawLine' => 'section:',
          'indent' => 0,
        ],
      ],
      'indented mapping start' => [
        'line' => '    subsection:',
        'lineNumber' => 11,
        'expectedType' => NodeType::MappingStart,
        'expectedData' => [
          'key' => 'subsection',
          'rawLine' => '    subsection:',
          'indent' => 4,
        ],
      ],

      // Sequence items.
      'simple sequence item' => [
        'line' => '- item1',
        'lineNumber' => 12,
        'expectedType' => NodeType::SequenceItem,
        'expectedData' => [
          'value' => 'item1',
          'rawLine' => '- item1',
          'indent' => 0,
        ],
      ],
      'indented sequence item' => [
        'line' => '  - nested_item',
        'lineNumber' => 13,
        'expectedType' => NodeType::SequenceItem,
        'expectedData' => [
          'value' => 'nested_item',
          'rawLine' => '  - nested_item',
          'indent' => 2,
        ],
      ],
      'sequence item with quoted value' => [
        'line' => '- "quoted item"',
        'lineNumber' => 14,
        'expectedType' => NodeType::SequenceItem,
        'expectedData' => [
          'value' => '"quoted item"',
          'rawLine' => '- "quoted item"',
          'indent' => 0,
        ],
      ],

      // Edge cases and fallbacks.
      'complex content fallback' => [
        'line' => 'some complex | multiline > content',
        'lineNumber' => 15,
        'expectedType' => NodeType::Comment,
        'expectedData' => [
          'rawLine' => 'some complex | multiline > content',
          'indent' => 0,
        ],
      ],
      'malformed yaml fallback' => [
        'line' => 'key without colon value',
        'lineNumber' => 16,
        'expectedType' => NodeType::Comment,
        'expectedData' => [
          'rawLine' => 'key without colon value',
          'indent' => 0,
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderTokenize')]
  public function testTokenize(string $content, array $expectedNodes): void {
    $lexer = new Lexer();
    $result = $lexer->tokenize($content);

    $this->assertCount(count($expectedNodes), $result);

    foreach ($expectedNodes as $index => $expected) {
      $node = $result[$index];
      $this->assertEquals($expected['type'], $node->type, sprintf('Node %s type should match', $index));

      foreach ($expected['data'] as $property => $expectedValue) {
        $this->assertEquals($expectedValue, $node->$property, sprintf("Node %s property '%s' should match", $index, $property));
      }
    }
  }

  public static function dataProviderTokenize(): array {
    return [
      'simple yaml document' => [
        'content' => "# Configuration\nname: test\nversion: 1.0",
        'expectedNodes' => [
          [
            'type' => NodeType::Comment,
            'data' => ['rawLine' => '# Configuration', 'indent' => 0],
          ],
          [
            'type' => NodeType::KeyValue,
            'data' => ['key' => 'name', 'value' => 'test', 'rawLine' => 'name: test', 'indent' => 0],
          ],
          [
            'type' => NodeType::KeyValue,
            'data' => ['key' => 'version', 'value' => '1.0', 'rawLine' => 'version: 1.0', 'indent' => 0],
          ],
        ],
      ],
      'nested structure' => [
        'content' => "database:\n  host: localhost\n  port: 5432",
        'expectedNodes' => [
          [
            'type' => NodeType::MappingStart,
            'data' => ['key' => 'database', 'rawLine' => 'database:', 'indent' => 0],
          ],
          [
            'type' => NodeType::KeyValue,
            'data' => ['key' => 'host', 'value' => 'localhost', 'rawLine' => '  host: localhost', 'indent' => 2],
          ],
          [
            'type' => NodeType::KeyValue,
            'data' => ['key' => 'port', 'value' => '5432', 'rawLine' => '  port: 5432', 'indent' => 2],
          ],
        ],
      ],
      'list with comments' => [
        'content' => "# Items\nitems:\n  - first\n  - second",
        'expectedNodes' => [
          [
            'type' => NodeType::Comment,
            'data' => ['rawLine' => '# Items', 'indent' => 0],
          ],
          [
            'type' => NodeType::MappingStart,
            'data' => ['key' => 'items', 'rawLine' => 'items:', 'indent' => 0],
          ],
          [
            'type' => NodeType::SequenceItem,
            'data' => ['value' => 'first', 'rawLine' => '  - first', 'indent' => 2],
          ],
          [
            'type' => NodeType::SequenceItem,
            'data' => ['value' => 'second', 'rawLine' => '  - second', 'indent' => 2],
          ],
        ],
      ],
      'mixed content with blank lines' => [
        'content' => "key1: value1\n\n# Comment\nkey2: value2",
        'expectedNodes' => [
          [
            'type' => NodeType::KeyValue,
            'data' => ['key' => 'key1', 'value' => 'value1', 'rawLine' => 'key1: value1', 'indent' => 0],
          ],
          [
            'type' => NodeType::BlankLine,
            'data' => ['rawLine' => '', 'indent' => 0],
          ],
          [
            'type' => NodeType::Comment,
            'data' => ['rawLine' => '# Comment', 'indent' => 0],
          ],
          [
            'type' => NodeType::KeyValue,
            'data' => ['key' => 'key2', 'value' => 'value2', 'rawLine' => 'key2: value2', 'indent' => 0],
          ],
        ],
      ],
      'literal block' => [
        'content' => <<<YAML
cmd: |
  echo hello
  echo world
YAML,
        'expectedNodes' => [
          [
            'type' => NodeType::LiteralBlock,
            'data' => [
              'key' => 'cmd',
              'value' => <<<VALUE
echo hello
echo world

VALUE,
              'rawLine' => <<<RAW
cmd: |
  echo hello
  echo world
RAW,
              'indent' => 0,
            ],
          ],
        ],
      ],
      'folded block' => [
        'content' => <<<YAML
description: >
  This is a long
  folded description
YAML,
        'expectedNodes' => [
          [
            'type' => NodeType::FoldedBlock,
            'data' => [
              'key' => 'description',
              'value' => <<<VALUE
This is a long
folded description

VALUE,
              'rawLine' => <<<RAW
description: >
  This is a long
  folded description
RAW,
              'indent' => 0,
            ],
          ],
        ],
      ],
    ];
  }

  public function testTokenizeFile(): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'yaml_test');
    $content = "# Test file\ntest: true";
    file_put_contents($tempFile, $content);

    $lexer = new Lexer();
    $result = $lexer->tokenizeFile($tempFile);

    $this->assertCount(2, $result);
    $this->assertEquals(NodeType::Comment, $result[0]->type);
    $this->assertEquals(NodeType::KeyValue, $result[1]->type);

    unlink($tempFile);
  }

  #[DataProvider('dataProviderIndentationEdgeCases')]
  public function testIndentationEdgeCases(string $line, int $expectedIndentLevel): void {
    $lexer = new Lexer();
    $result = $lexer->tokenizeLine($line, 0);

    $this->assertEquals($expectedIndentLevel, $result->indent);
  }

  public static function dataProviderIndentationEdgeCases(): array {
    return [
      'no indentation' => ['key: value', 0],
      'two spaces' => ['  key: value', 2],
      'four spaces' => ['    key: value', 4],
      'tab character' => ["\tkey: value", 1],
      'mixed tabs and spaces' => ["\t  key: value", 3],
      'eight spaces' => ['        key: value', 8],
    ];
  }

  public function testTokenizeWithFixture(): void {
    $yaml_content = <<<YAML
#
# Ahoy configuration file.
# http://www.ahoycli.com/
#
# Test fixture for YAML updating with comment preservation
#
ahoyapi: v2

commands:
  # Build command
  build:
    usage: Build or rebuild the project.
    cmd: |
      # Reset the project
      ahoy reset
      # Start the stack
      ahoy up --build --force-recreate

  # Information command
  info:
    usage: Show information about this project.
    cmd: |
      COMPOSE_PROJECT_NAME=\${COMPOSE_PROJECT_NAME:-\${PWD##*/}} \\
      ahoy cli ./scripts/vortex/info.sh "\$@"

  # Database operations
  down:
    usage: 'Stop and remove containers, images, volumes and networks.'
    cmd: docker compose down --remove-orphans --volumes

  restart:
    usage: Restart stopped or running containers.
    cmd: docker compose restart "\$@"

  local:
    usage: Custom local commands. See `ahoy local help`.
    optional: true
    imports: [.ahoy.local.yml]

  local2:
    usage: 'Custom local commands. See `ahoy local help`.'
    optional: false
    imports:
      - .ahoy.local.yml
      - .ahoy.local2.yml

  pull-db:
    usage: Download database image with the latest nightly dump. Run "ahoy reload-db" to reload DB in the running stack.
    cmd: \\[ -n "\${VORTEX_DB_IMAGE}" \\] && docker pull \${VORTEX_DB_IMAGE} || true

  fe:
    usage: Build front-end assets.
    cmd: ahoy cli "cd \${WEBROOT}/themes/custom/\${DRUPAL_THEME} && yarn run build"

# Override entrypoint to alter default behavior of Ahoy.
entrypoint:
  - bash
  # Same as `bash -c -e`
  - '-c'
  - -e
  # Exit the script if any statement returns a non-true return value.
  - |
    bash -e -c "\$0" "\$@"
  - '{cmd}'
  # Exit the script if any statement returns a non-true return value.
  - '{name}'

YAML;

    $lexer = new Lexer();
    $result = $lexer->tokenize($yaml_content);

    // Basic expectations for the fixture.
    $this->assertNotEmpty($result);

    // Should have comment nodes.
    $comment_nodes = array_filter($result, fn($node): bool => $node->type === NodeType::Comment);
    $this->assertNotEmpty($comment_nodes, 'Should have comment nodes');

    // Should have key-value nodes.
    $key_value_nodes = array_filter($result, fn($node): bool => $node->type === NodeType::KeyValue);
    $this->assertNotEmpty($key_value_nodes, 'Should have key-value nodes');

    // Should have mapping start nodes.
    $mapping_nodes = array_filter($result, fn($node): bool => $node->type === NodeType::MappingStart);
    $this->assertNotEmpty($mapping_nodes, 'Should have mapping nodes');

    // Should have literal block nodes.
    $literal_block_nodes = array_filter($result, fn($node): bool => $node->type === NodeType::LiteralBlock);
    $this->assertNotEmpty($literal_block_nodes, 'Should have literal block nodes');

    // Should find the main 'ahoyapi' key-value pair.
    $ahoyapi_node = NULL;
    foreach ($key_value_nodes as $node) {
      if ($node->key === 'ahoyapi') {
        $ahoyapi_node = $node;
        break;
      }
    }
    $this->assertNotNull($ahoyapi_node, 'Should find ahoyapi key-value node');
    $this->assertEquals('v2', $ahoyapi_node->value);

    // Should find the 'commands' mapping start.
    $commands_node = NULL;
    foreach ($mapping_nodes as $node) {
      if ($node->key === 'commands') {
        $commands_node = $node;
        break;
      }
    }
    $this->assertNotNull($commands_node, 'Should find commands mapping node');

    // Check for proper indentation tracking.
    $indent_levels = array_map(fn($node): int => $node->indent, $result);
    $this->assertContains(0, $indent_levels, 'Should have root level nodes');
    $this->assertContains(2, $indent_levels, 'Should have indented nodes');

    // Verify raw lines are preserved.
    foreach ($result as $node) {
      if ($node->type !== NodeType::BlankLine) {
        $this->assertNotNull($node->rawLine, 'Non-blank nodes should have rawLine preserved');
      }
    }
  }

  public function testTokenizeIdempotenceFixtureStructure(): void {
    $yaml_content = <<<YAML
#
# Ahoy configuration file.
# http://www.ahoycli.com/
#
# Test fixture for YAML updating with comment preservation
#
ahoyapi: v2

commands:
  # Build command
  build:
    usage: Build or rebuild the project.
    cmd: |
      # Reset the project
      ahoy reset
      # Start the stack
      ahoy up --build --force-recreate

  # Information command
  info:
    usage: Show information about this project.
    cmd: |
      COMPOSE_PROJECT_NAME=\${COMPOSE_PROJECT_NAME:-\${PWD##*/}} \\
      ahoy cli ./scripts/vortex/info.sh "\$@"

  # Database operations
  down:
    usage: 'Stop and remove containers, images, volumes and networks.'
    cmd: docker compose down --remove-orphans --volumes

  restart:
    usage: Restart stopped or running containers.
    cmd: docker compose restart "\$@"

  local:
    usage: Custom local commands. See `ahoy local help`.
    optional: true
    imports: [.ahoy.local.yml]

  local2:
    usage: 'Custom local commands. See `ahoy local help`.'
    optional: false
    imports:
      - .ahoy.local.yml
      - .ahoy.local2.yml

  pull-db:
    usage: Download database image with the latest nightly dump. Run "ahoy reload-db" to reload DB in the running stack.
    cmd: \\[ -n "\${VORTEX_DB_IMAGE}" \\] && docker pull \${VORTEX_DB_IMAGE} || true

  fe:
    usage: Build front-end assets.
    cmd: ahoy cli "cd \${WEBROOT}/themes/custom/\${DRUPAL_THEME} && yarn run build"

# Override entrypoint to alter default behavior of Ahoy.
entrypoint:
  - bash
  # Same as `bash -c -e`
  - '-c'
  - -e
  # Exit the script if any statement returns a non-true return value.
  - |
    bash -e -c "\$0" "\$@"
  - '{cmd}'
  # Exit the script if any statement returns a non-true return value.
  - '{name}'

YAML;

    $lexer = new Lexer();
    $result = $lexer->tokenize($yaml_content);

    $expected = [
      new Node(NodeType::Comment, ['rawLine' => '#', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '# Ahoy configuration file.', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '# http://www.ahoycli.com/', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '#', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '# Test fixture for YAML updating with comment preservation', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '#', 'indent' => 0]),
      new Node(NodeType::KeyValue, ['key' => 'ahoyapi', 'value' => 'v2', 'rawLine' => 'ahoyapi: v2', 'indent' => 0]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::MappingStart, ['key' => 'commands', 'rawLine' => 'commands:', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '  # Build command', 'indent' => 2]),
      new Node(NodeType::MappingStart, ['key' => 'build', 'rawLine' => '  build:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => 'Build or rebuild the project.', 'rawLine' => '    usage: Build or rebuild the project.', 'indent' => 4]),
      new Node(NodeType::LiteralBlock, ['key' => 'cmd', 'value' => "# Reset the project\nahoy reset\n# Start the stack\nahoy up --build --force-recreate\n", 'rawLine' => "    cmd: |\n      # Reset the project\n      ahoy reset\n      # Start the stack\n      ahoy up --build --force-recreate", 'indent' => 4]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '  # Information command', 'indent' => 2]),
      new Node(NodeType::MappingStart, ['key' => 'info', 'rawLine' => '  info:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => 'Show information about this project.', 'rawLine' => '    usage: Show information about this project.', 'indent' => 4]),
      new Node(NodeType::LiteralBlock, ['key' => 'cmd', 'value' => "COMPOSE_PROJECT_NAME=\${COMPOSE_PROJECT_NAME:-\${PWD##*/}} \\\nahoy cli ./scripts/vortex/info.sh \"\$@\"\n", 'rawLine' => "    cmd: |\n      COMPOSE_PROJECT_NAME=\${COMPOSE_PROJECT_NAME:-\${PWD##*/}} \\\n      ahoy cli ./scripts/vortex/info.sh \"\$@\"", 'indent' => 4]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '  # Database operations', 'indent' => 2]),
      new Node(NodeType::MappingStart, ['key' => 'down', 'rawLine' => '  down:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => "'Stop and remove containers, images, volumes and networks.'", 'rawLine' => "    usage: 'Stop and remove containers, images, volumes and networks.'", 'indent' => 4]),
      new Node(NodeType::KeyValue, ['key' => 'cmd', 'value' => 'docker compose down --remove-orphans --volumes', 'rawLine' => '    cmd: docker compose down --remove-orphans --volumes', 'indent' => 4]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::MappingStart, ['key' => 'restart', 'rawLine' => '  restart:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => 'Restart stopped or running containers.', 'rawLine' => '    usage: Restart stopped or running containers.', 'indent' => 4]),
      new Node(NodeType::KeyValue, ['key' => 'cmd', 'value' => 'docker compose restart "$@"', 'rawLine' => '    cmd: docker compose restart "$@"', 'indent' => 4]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::MappingStart, ['key' => 'local', 'rawLine' => '  local:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => 'Custom local commands. See `ahoy local help`.', 'rawLine' => '    usage: Custom local commands. See `ahoy local help`.', 'indent' => 4]),
      new Node(NodeType::KeyValue, ['key' => 'optional', 'value' => 'true', 'rawLine' => '    optional: true', 'indent' => 4]),
      new Node(NodeType::KeyValue, ['key' => 'imports', 'value' => '[.ahoy.local.yml]', 'rawLine' => '    imports: [.ahoy.local.yml]', 'indent' => 4]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::MappingStart, ['key' => 'local2', 'rawLine' => '  local2:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => "'Custom local commands. See `ahoy local help`.'", 'rawLine' => "    usage: 'Custom local commands. See `ahoy local help`.'", 'indent' => 4]),
      new Node(NodeType::KeyValue, ['key' => 'optional', 'value' => 'false', 'rawLine' => '    optional: false', 'indent' => 4]),
      new Node(NodeType::MappingStart, ['key' => 'imports', 'rawLine' => '    imports:', 'indent' => 4]),
      new Node(NodeType::SequenceItem, ['value' => '.ahoy.local.yml', 'rawLine' => '      - .ahoy.local.yml', 'indent' => 6]),
      new Node(NodeType::SequenceItem, ['value' => '.ahoy.local2.yml', 'rawLine' => '      - .ahoy.local2.yml', 'indent' => 6]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::MappingStart, ['key' => 'pull-db', 'rawLine' => '  pull-db:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => 'Download database image with the latest nightly dump. Run "ahoy reload-db" to reload DB in the running stack.', 'rawLine' => '    usage: Download database image with the latest nightly dump. Run "ahoy reload-db" to reload DB in the running stack.', 'indent' => 4]),
      new Node(NodeType::KeyValue, ['key' => 'cmd', 'value' => '\\[ -n "${VORTEX_DB_IMAGE}" \\] && docker pull ${VORTEX_DB_IMAGE} || true', 'rawLine' => '    cmd: \\[ -n "${VORTEX_DB_IMAGE}" \\] && docker pull ${VORTEX_DB_IMAGE} || true', 'indent' => 4]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::MappingStart, ['key' => 'fe', 'rawLine' => '  fe:', 'indent' => 2]),
      new Node(NodeType::KeyValue, ['key' => 'usage', 'value' => 'Build front-end assets.', 'rawLine' => '    usage: Build front-end assets.', 'indent' => 4]),
      new Node(NodeType::KeyValue, ['key' => 'cmd', 'value' => 'ahoy cli "cd ${WEBROOT}/themes/custom/${DRUPAL_THEME} && yarn run build"', 'rawLine' => '    cmd: ahoy cli "cd ${WEBROOT}/themes/custom/${DRUPAL_THEME} && yarn run build"', 'indent' => 4]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
      new Node(NodeType::Comment, ['rawLine' => '# Override entrypoint to alter default behavior of Ahoy.', 'indent' => 0]),
      new Node(NodeType::MappingStart, ['key' => 'entrypoint', 'rawLine' => 'entrypoint:', 'indent' => 0]),
      new Node(NodeType::SequenceItem, ['value' => 'bash', 'rawLine' => '  - bash', 'indent' => 2]),
      new Node(NodeType::Comment, ['rawLine' => '  # Same as `bash -c -e`', 'indent' => 2]),
      new Node(NodeType::SequenceItem, ['value' => "'-c'", 'rawLine' => "  - '-c'", 'indent' => 2]),
      new Node(NodeType::SequenceItem, ['value' => '-e', 'rawLine' => '  - -e', 'indent' => 2]),
      new Node(NodeType::Comment, ['rawLine' => '  # Exit the script if any statement returns a non-true return value.', 'indent' => 2]),
      new Node(NodeType::SequenceItem, ['value' => '|', 'rawLine' => '  - |', 'indent' => 2]),
      new Node(NodeType::Comment, ['rawLine' => '    bash -e -c "$0" "$@"', 'indent' => 4]),
      new Node(NodeType::SequenceItem, ['value' => "'{cmd}'", 'rawLine' => "  - '{cmd}'", 'indent' => 2]),
      new Node(NodeType::Comment, ['rawLine' => '  # Exit the script if any statement returns a non-true return value.', 'indent' => 2]),
      new Node(NodeType::SequenceItem, ['value' => "'{name}'", 'rawLine' => "  - '{name}'", 'indent' => 2]),
      new Node(NodeType::BlankLine, ['rawLine' => '', 'indent' => 0]),
    ];

    $this->assertCount(count($expected), $result, 'Should have expected number of tokens');

    $json_result = json_encode($result, JSON_PRETTY_PRINT);
    $json_expected = json_encode($expected, JSON_PRETTY_PRINT);

    $this->assertNotFalse($json_result, 'Result should be JSON encodable');
    $this->assertNotFalse($json_expected, 'Expected should be JSON encodable');

    $this->assertJsonStringEqualsJsonString($json_expected, $json_result, 'Tokenized structure should match expected structure');
  }

  public function testLiteralBlockIndentationPreservation(): void {
    $yaml_content = <<<YAML
cli:
  cmd: |
    if [ "\${#}" -ne 0 ]; then
      docker compose exec cli bash -c "\$*"
    else
      docker compose exec cli bash
    fi
YAML;

    $lexer = new Lexer();
    $result = $lexer->tokenize($yaml_content);

    // Find the literal block node.
    $literal_block_node = NULL;
    foreach ($result as $node) {
      if ($node->type === NodeType::LiteralBlock && $node->key === 'cmd') {
        $literal_block_node = $node;
        break;
      }
    }

    $this->assertNotNull($literal_block_node, 'Should find the cmd literal block node');

    // Verify the literal block content preserves relative indentation.
    $expected_value = <<<VALUE
if [ "\${#}" -ne 0 ]; then
  docker compose exec cli bash -c "\$*"
else
  docker compose exec cli bash
fi

VALUE;

    $this->assertEquals($expected_value, $literal_block_node->value, 'Literal block should preserve relative indentation within the block content');

    // Verify that the docker commands have 2 spaces of relative indentation.
    $this->assertIsString($literal_block_node->value, 'Literal block value should be a string');
    $lines = explode("\n", trim($literal_block_node->value));
    // "  docker compose exec cli bash -c "$*""
    $docker_line_1 = $lines[1];
    // "  docker compose exec cli bash"
    $docker_line_2 = $lines[3];

    $this->assertStringStartsWith('  docker compose exec', $docker_line_1, 'First docker command should have 2 spaces of relative indentation');
    $this->assertStringStartsWith('  docker compose exec', $docker_line_2, 'Second docker command should have 2 spaces of relative indentation');
  }

}
