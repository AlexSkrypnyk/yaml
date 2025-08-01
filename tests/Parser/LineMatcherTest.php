<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Parser;

use AlexSkrypnyk\Yaml\Parser\LineMatcher;
use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the LineMatcher class.
 */
#[CoversClass(LineMatcher::class)]
class LineMatcherTest extends TestCase {

  #[DataProvider('dataProviderAlign')]
  public function testAlign(array $lineTokens, array $astTree, array $expectedResult): void {
    $matcher = new LineMatcher();
    $result = $matcher->align($lineTokens, $astTree);

    $expected_json = json_encode($expectedResult, JSON_PRETTY_PRINT);
    $result_json = json_encode($result, JSON_PRETTY_PRINT);

    $this->assertNotFalse($expected_json, 'Expected result should be JSON encodable');
    $this->assertNotFalse($result_json, 'Result should be JSON encodable');

    $this->assertJsonStringEqualsJsonString(
      $expected_json,
      $result_json,
      'Line matched result should match expected enhanced tokens'
    );
  }

  public static function dataProviderAlign(): array {
    return [
      'simple key-value enhancement' => [
        // Line tokens (from Tokenizer/CommentMapper) - raw format.
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
    // Raw string value.
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
            'indent' => 0,
          ]),
        ],
        // AST tree (from Editor/Symfony) - parsed format.
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
        // Parsed value (same in this case)
            'value' => 'localhost',
            'indent' => 0,
          ]),
        ],
        // Expected result - tokens enhanced with parsed values.
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
        // Enhanced with parsed value.
            'value' => 'localhost',
        // Original rawLine preserved.
            'rawLine' => 'host: localhost',
            'indent' => 0,
          ]),
        ],
      ],

      'preserve comments and blank lines' => [
        [
          new Node(NodeType::Comment, [
            'rawLine' => '# Database config',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
            'indent' => 0,
            'attachedComment' => '# Database config',
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
            'indent' => 0,
          ]),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'indent' => 0,
          ]),
        ],
        [
          new Node(NodeType::Comment, [
            'rawLine' => '# Database config',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'host',
    // Enhanced value.
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
            'indent' => 0,
            'attachedComment' => '# Database config',
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
            'indent' => 0,
          ]),
        ],
      ],

      'literal block enhancement' => [
        [
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
      // Raw multiline string.
            'value' => "echo hello\necho world\n",
            'rawLine' => "cmd: |\n  echo hello\n  echo world",
            'indent' => 0,
          ]),
        ],
        [
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
        // Parsed multiline value.
            'value' => "echo hello\necho world\n",
            'indent' => 0,
          ]),
        ],
        [
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
        // Enhanced value.
            'value' => "echo hello\necho world\n",
        // Preserved raw format.
            'rawLine' => "cmd: |\n  echo hello\n  echo world",
            'indent' => 0,
          ]),
        ],
      ],

      'mapping with children enhancement' => [
        [
          new Node(NodeType::MappingStart, [
            'key' => 'database',
            'rawLine' => 'database:',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => '  host: localhost',
            'indent' => 2,
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'database',
            'indent' => 0,
            'children' => [
              new Node(NodeType::KeyValue, [
                'key' => 'host',
                'value' => 'localhost',
                'indent' => 2,
              ]),
            ],
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'database',
            'rawLine' => 'database:',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => '  host: localhost',
            'indent' => 2,
          ]),
        ],
      ],

      'sequence items enhancement' => [
        [
          new Node(NodeType::MappingStart, [
            'key' => 'items',
            'rawLine' => 'items:',
            'indent' => 0,
          ]),
          new Node(NodeType::SequenceItem, [
            'value' => 'first',
            'rawLine' => '  - first',
            'indent' => 2,
          ]),
          new Node(NodeType::SequenceItem, [
            'value' => 'second',
            'rawLine' => '  - second',
            'indent' => 2,
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'items',
            'indent' => 0,
            'children' => [
              new Node(NodeType::SequenceItem, [
                'value' => 'first',
                'indent' => 2,
              ]),
              new Node(NodeType::SequenceItem, [
                'value' => 'second',
                'indent' => 2,
              ]),
            ],
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'items',
            'rawLine' => 'items:',
            'indent' => 0,
          ]),
          new Node(NodeType::SequenceItem, [
            'value' => 'first',
            'rawLine' => '  - first',
            'indent' => 2,
          ]),
          new Node(NodeType::SequenceItem, [
            'value' => 'second',
            'rawLine' => '  - second',
            'indent' => 2,
          ]),
        ],
      ],

      'floating comments preserved' => [
        [
          new Node(NodeType::KeyValue, [
            'key' => 'name',
            'value' => 'test',
            'rawLine' => 'name: test',
            'indent' => 0,
          ]),
          new Node(NodeType::Comment, [
            'rawLine' => '# Floating comment',
            'indent' => 0,
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'version',
            'value' => '1.0',
            'rawLine' => 'version: 1.0',
            'indent' => 0,
          ]),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'name',
            'value' => 'test',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'version',
            'value' => '1.0',
            'indent' => 0,
          ]),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'name',
        // Enhanced.
            'value' => 'test',
            'rawLine' => 'name: test',
            'indent' => 0,
          ]),
          new Node(NodeType::Comment, [
    // Preserved.
            'rawLine' => '# Floating comment',
            'indent' => 0,
          ]),
          new Node(NodeType::BlankLine, [
    // Preserved.
            'rawLine' => '',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'version',
    // Enhanced.
            'value' => '1.0',
            'rawLine' => 'version: 1.0',
            'indent' => 0,
          ]),
        ],
      ],

      'no matching AST nodes' => [
        [
          new Node(NodeType::Comment, [
            'rawLine' => '# Only comment',
            'indent' => 0,
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
            'indent' => 0,
          ]),
        ],
        // Empty AST.
        [],
        [
          new Node(NodeType::Comment, [
        // Preserved as-is.
            'rawLine' => '# Only comment',
            'indent' => 0,
          ]),
          new Node(NodeType::BlankLine, [
    // Preserved as-is.
            'rawLine' => '',
            'indent' => 0,
          ]),
        ],
      ],

      'partial matching' => [
        [
          new Node(NodeType::KeyValue, [
            'key' => 'matched',
            'value' => 'old_value',
            'rawLine' => 'matched: old_value',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'unmatched',
            'value' => 'token_value',
            'rawLine' => 'unmatched: token_value',
            'indent' => 0,
          ]),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'matched',
        // This will enhance the token.
            'value' => 'new_value',
            'indent' => 0,
          ]),
          // Note: no matching node for 'unmatched'.
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'matched',
        // Enhanced with AST value.
            'value' => 'new_value',
            'rawLine' => 'matched: old_value',
            'indent' => 0,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'unmatched',
    // Unchanged (no matching AST)
            'value' => 'token_value',
            'rawLine' => 'unmatched: token_value',
            'indent' => 0,
          ]),
        ],
      ],

      'empty inputs' => [
        [],
        [],
        [],
      ],

      'tokens only' => [
        [
          new Node(NodeType::Comment, [
            'rawLine' => '# Header',
            'indent' => 0,
          ]),
        ],
        [],
        [
          new Node(NodeType::Comment, [
            'rawLine' => '# Header',
            'indent' => 0,
          ]),
        ],
      ],

      'ast only (no enhancement possible)' => [
        [],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'orphaned',
            'value' => 'value',
            'indent' => 0,
          ]),
        ],
        [],
      ],

      'literal block preserves formatting over ast children' => [
        [
          new Node(NodeType::MappingStart, [
            'key' => 'build',
            'rawLine' => '  build:',
            'indent' => 2,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'usage',
            'value' => 'Build project',
            'rawLine' => '    usage: Build project',
            'indent' => 4,
          ]),
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
            'value' => "echo hello\necho world\n",
            'rawLine' => "    cmd: |\n      echo hello\n      echo world",
            'indent' => 4,
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'build',
            'indent' => 2,
            'children' => [
              new Node(NodeType::KeyValue, [
                'key' => 'usage',
                'value' => 'Build project',
                'indent' => 4,
              ]),
          // AST has this as KEY_VALUE, not LITERAL_BLOCK.
              new Node(NodeType::KeyValue, [
                'key' => 'cmd',
                'value' => "echo hello\necho world",
                'indent' => 4,
              ]),
            ],
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'build',
            'rawLine' => '  build:',
            'indent' => 2,
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'usage',
            'value' => 'Build project',
            'rawLine' => '    usage: Build project',
            'indent' => 4,
          ]),
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
            'value' => "echo hello\necho world\n",
            'rawLine' => "    cmd: |\n      echo hello\n      echo world",
            'indent' => 4,
          ]),
        ],
      ],

      'multiple literal blocks in hierarchy' => [
        [
          new Node(NodeType::MappingStart, [
            'key' => 'commands',
            'rawLine' => 'commands:',
            'indent' => 0,
          ]),
          new Node(NodeType::MappingStart, [
            'key' => 'build',
            'rawLine' => '  build:',
            'indent' => 2,
          ]),
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
            'value' => "make build\n",
            'rawLine' => "    cmd: |\n      make build",
            'indent' => 4,
          ]),
          new Node(NodeType::MappingStart, [
            'key' => 'test',
            'rawLine' => '  test:',
            'indent' => 2,
          ]),
          new Node(NodeType::FoldedBlock, [
            'key' => 'description',
            'value' => "This is a long test description\n",
            'rawLine' => "    description: >\n      This is a long test description",
            'indent' => 4,
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'commands',
            'indent' => 0,
            'children' => [
              new Node(NodeType::MappingStart, [
                'key' => 'build',
                'indent' => 2,
                'children' => [
        // AST loses literal format.
                  new Node(NodeType::KeyValue, [
                    'key' => 'cmd',
                    'value' => "make build",
                    'indent' => 4,
                  ]),
                ],
              ]),
              new Node(NodeType::MappingStart, [
                'key' => 'test',
                'indent' => 2,
                'children' => [
          // AST loses folded format.
                  new Node(NodeType::KeyValue, [
                    'key' => 'description',
                    'value' => "This is a long test description",
                    'indent' => 4,
                  ]),
                ],
              ]),
            ],
          ]),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'commands',
            'rawLine' => 'commands:',
            'indent' => 0,
          ]),
          new Node(NodeType::MappingStart, [
            'key' => 'build',
            'rawLine' => '  build:',
            'indent' => 2,
          ]),
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
            'value' => "make build\n",
            'rawLine' => "    cmd: |\n      make build",
            'indent' => 4,
          ]),
          new Node(NodeType::MappingStart, [
            'key' => 'test',
            'rawLine' => '  test:',
            'indent' => 2,
          ]),
          new Node(NodeType::FoldedBlock, [
            'key' => 'description',
            'value' => "This is a long test description\n",
            'rawLine' => "    description: >\n      This is a long test description",
            'indent' => 4,
          ]),
        ],
      ],
    ];
  }

}
