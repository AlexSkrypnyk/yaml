<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Parser;

use AlexSkrypnyk\Yaml\Parser\Parser;
use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use AlexSkrypnyk\Yaml\Ast\AstTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Parser class.
 */
#[CoversClass(Parser::class)]
class ParserTest extends TestCase {

  public function testParse(): void {
    $parser = new Parser();
    $content = "# Configuration\nname: test\nversion: 1.0";

    $result = $parser->parse($content);

    $this->assertInstanceOf(AstTree::class, $result);
    $nodes = $result->getNodes();
    $this->assertNotEmpty($nodes);
  }

  #[DataProvider('dataProviderCommentMapping')]
  public function testMapComments(array $inputNodes, array $expectedNodes): void {
    $parser = new Parser();

    // Use reflection to access the protected method.
    $reflection = new \ReflectionClass($parser);
    $method = $reflection->getMethod('mapComments');
    $method->setAccessible(TRUE);

    $result = $method->invoke($parser, $inputNodes);

    $expected_json = json_encode($expectedNodes, JSON_PRETTY_PRINT);
    $result_json = json_encode($result, JSON_PRETTY_PRINT);

    $this->assertNotFalse($expected_json, 'Expected nodes should be JSON encodable');
    $this->assertNotFalse($result_json, 'Result should be JSON encodable');

    $this->assertJsonStringEqualsJsonString(
      $expected_json,
      $result_json,
      'Mapped result should match expected nodes'
    );
  }

  public static function dataProviderCommentMapping(): array {
    return [
      'single comment before key-value' => [
        [
          new Node(NodeType::Comment, ['rawLine' => '# Database config']),
          new Node(NodeType::KeyValue, ['key' => 'host', 'value' => 'localhost', 'rawLine' => 'host: localhost']),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
            'attachedComment' => '# Database config',
          ]),
        ],
      ],

      'multiple comments before key-value' => [
        [
          new Node(NodeType::Comment, ['rawLine' => '# Primary database']),
          new Node(NodeType::Comment, ['rawLine' => '# Connection settings']),
          new Node(NodeType::KeyValue, ['key' => 'host', 'value' => 'localhost', 'rawLine' => 'host: localhost']),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
            'attachedComment' => "# Primary database\n# Connection settings",
          ]),
        ],
      ],

      'comment before mapping start' => [
        [
          new Node(NodeType::Comment, ['rawLine' => '# Database section']),
          new Node(NodeType::MappingStart, ['key' => 'database', 'rawLine' => 'database:']),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'database',
            'rawLine' => 'database:',
            'attachedComment' => '# Database section',
          ]),
        ],
      ],

      'comment before sequence item' => [
        [
          new Node(NodeType::Comment, ['rawLine' => '# First item']),
          new Node(NodeType::SequenceItem, ['value' => 'item1', 'rawLine' => '- item1']),
        ],
        [
          new Node(NodeType::SequenceItem, [
            'value' => 'item1',
            'rawLine' => '- item1',
            'attachedComment' => '# First item',
          ]),
        ],
      ],

      'floating comment at end' => [
        [
          new Node(NodeType::KeyValue, ['key' => 'host', 'value' => 'localhost', 'rawLine' => 'host: localhost']),
          new Node(NodeType::Comment, ['rawLine' => '# Floating comment']),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
          ]),
          new Node(NodeType::Comment, [
            'rawLine' => '# Floating comment',
          ]),
        ],
      ],

      'fully floating comment with blank lines' => [
        [
          new Node(NodeType::KeyValue, ['key' => 'name', 'value' => 'test', 'rawLine' => 'name: test']),
          new Node(NodeType::BlankLine, ['rawLine' => '']),
          new Node(NodeType::Comment, ['rawLine' => '# Information command']),
          new Node(NodeType::Comment, ['rawLine' => '# This is a multi-line']),
          new Node(NodeType::Comment, ['rawLine' => '# floating comment block']),
          new Node(NodeType::BlankLine, ['rawLine' => '']),
          new Node(NodeType::KeyValue, ['key' => 'version', 'value' => '1.0', 'rawLine' => 'version: 1.0']),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'name',
            'value' => 'test',
            'rawLine' => 'name: test',
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
          ]),
          new Node(NodeType::Comment, [
            'rawLine' => '# Information command',
          ]),
          new Node(NodeType::Comment, [
            'rawLine' => '# This is a multi-line',
          ]),
          new Node(NodeType::Comment, [
            'rawLine' => '# floating comment block',
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'version',
            'value' => '1.0',
            'rawLine' => 'version: 1.0',
          ]),
        ],
      ],

      'blank lines preserved' => [
        [
          new Node(NodeType::KeyValue, ['key' => 'host', 'value' => 'localhost', 'rawLine' => 'host: localhost']),
          new Node(NodeType::BlankLine, ['rawLine' => '']),
          new Node(NodeType::Comment, ['rawLine' => '# Next section']),
          new Node(NodeType::KeyValue, ['key' => 'port', 'value' => '5432', 'rawLine' => 'port: 5432']),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'port',
            'value' => '5432',
            'rawLine' => 'port: 5432',
            'attachedComment' => '# Next section',
          ]),
        ],
      ],

      'literal block with comment' => [
        [
          new Node(NodeType::Comment, ['rawLine' => '# Build script']),
          new Node(NodeType::LiteralBlock, ['key' => 'cmd', 'value' => "echo hello\necho world\n", 'rawLine' => "cmd: |\n  echo hello\n  echo world"]),
        ],
        [
          new Node(NodeType::LiteralBlock, [
            'key' => 'cmd',
            'value' => "echo hello\necho world\n",
            'rawLine' => "cmd: |\n  echo hello\n  echo world",
            'attachedComment' => '# Build script',
          ]),
        ],
      ],

      'complex mixed structure' => [
        [
          new Node(NodeType::Comment, ['rawLine' => '# Configuration']),
          new Node(NodeType::MappingStart, ['key' => 'database', 'rawLine' => 'database:']),
          new Node(NodeType::Comment, ['rawLine' => '# Host setting']),
          new Node(NodeType::KeyValue, ['key' => 'host', 'value' => 'localhost', 'rawLine' => '  host: localhost']),
          new Node(NodeType::BlankLine, ['rawLine' => '']),
          new Node(NodeType::Comment, ['rawLine' => '# List of users']),
          new Node(NodeType::MappingStart, ['key' => 'users', 'rawLine' => 'users:']),
          new Node(NodeType::SequenceItem, ['value' => 'admin', 'rawLine' => '  - admin']),
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'database',
            'rawLine' => 'database:',
            'attachedComment' => '# Configuration',
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => '  host: localhost',
            'attachedComment' => '# Host setting',
          ]),
          new Node(NodeType::BlankLine, [
            'rawLine' => '',
          ]),
          new Node(NodeType::MappingStart, [
            'key' => 'users',
            'rawLine' => 'users:',
            'attachedComment' => '# List of users',
          ]),
          new Node(NodeType::SequenceItem, [
            'value' => 'admin',
            'rawLine' => '  - admin',
          ]),
        ],
      ],

      'no comments' => [
        [
          new Node(NodeType::KeyValue, ['key' => 'host', 'value' => 'localhost', 'rawLine' => 'host: localhost']),
          new Node(NodeType::KeyValue, ['key' => 'port', 'value' => '5432', 'rawLine' => 'port: 5432']),
        ],
        [
          new Node(NodeType::KeyValue, [
            'key' => 'host',
            'value' => 'localhost',
            'rawLine' => 'host: localhost',
          ]),
          new Node(NodeType::KeyValue, [
            'key' => 'port',
            'value' => '5432',
            'rawLine' => 'port: 5432',
          ]),
        ],
      ],
    ];
  }

  public function testMapCommentsEdgeCases(): void {
    $parser = new Parser();

    // Use reflection to access the protected method.
    $reflection = new \ReflectionClass($parser);
    $method = $reflection->getMethod('mapComments');
    $method->setAccessible(TRUE);

    // Test empty input.
    $result = $method->invoke($parser, []);
    $this->assertEmpty($result, 'Empty input should produce empty result');

    // Test only comments.
    $onlyComments = [
      new Node(NodeType::Comment, ['rawLine' => '# Comment 1']),
      new Node(NodeType::Comment, ['rawLine' => '# Comment 2']),
    ];
    $result = $method->invoke($parser, $onlyComments);
    $this->assertIsArray($result);
    $this->assertCount(2, $result, 'Only comments should be preserved as floating comments');
    $this->assertInstanceOf(Node::class, $result[0]);
    $this->assertInstanceOf(Node::class, $result[1]);
    $this->assertEquals(NodeType::Comment, $result[0]->type);
    $this->assertEquals(NodeType::Comment, $result[1]->type);

    // Test only blank lines.
    $onlyBlanks = [
      new Node(NodeType::BlankLine, ['rawLine' => '']),
      new Node(NodeType::BlankLine, ['rawLine' => '']),
    ];
    $result = $method->invoke($parser, $onlyBlanks);
    $this->assertIsArray($result);
    $this->assertCount(2, $result, 'Blank lines should be preserved');
    $this->assertInstanceOf(Node::class, $result[0]);
    $this->assertInstanceOf(Node::class, $result[1]);
    $this->assertEquals(NodeType::BlankLine, $result[0]->type);
    $this->assertEquals(NodeType::BlankLine, $result[1]->type);
  }

  #[DataProvider('dataProviderDataToAst')]
  public function testDataToAst(array $inputData, array $expectedNodes): void {
    $parser = new Parser();

    // Use reflection to access the protected method.
    $reflection = new \ReflectionClass($parser);
    $method = $reflection->getMethod('dataToAst');
    $method->setAccessible(TRUE);

    $result = $method->invoke($parser, $inputData);

    $expected_json = json_encode($expectedNodes, JSON_PRETTY_PRINT);
    $result_json = json_encode($result, JSON_PRETTY_PRINT);

    $this->assertNotFalse($expected_json, 'Expected nodes should be JSON encodable');
    $this->assertNotFalse($result_json, 'Result should be JSON encodable');

    $this->assertJsonStringEqualsJsonString(
      $expected_json,
      $result_json,
      'Built AST should match expected nodes'
    );
  }

  public static function dataProviderDataToAst(): array {
    return [
      'simple key-value pairs' => [
        [
          'name' => 'test',
          'version' => '1.0',
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
      ],

      'nested mapping' => [
        [
          'database' => [
            'host' => 'localhost',
            'port' => 5432,
          ],
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'database',
            'children' => [
              new Node(NodeType::KeyValue, [
                'key' => 'host',
                'value' => 'localhost',
                'indent' => 2,
              ]),
              new Node(NodeType::KeyValue, [
                'key' => 'port',
                'value' => 5432,
                'indent' => 2,
              ]),
            ],
            'indent' => 0,
          ]),
        ],
      ],

      'list/sequence' => [
        [
          'items' => ['first', 'second', 'third'],
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'items',
            'children' => [
              new Node(NodeType::SequenceItem, [
                'value' => 'first',
                'indent' => 2,
              ]),
              new Node(NodeType::SequenceItem, [
                'value' => 'second',
                'indent' => 2,
              ]),
              new Node(NodeType::SequenceItem, [
                'value' => 'third',
                'indent' => 2,
              ]),
            ],
            'indent' => 0,
          ]),
        ],
      ],

      'complex nested structure' => [
        [
          'app' => [
            'name' => 'myapp',
            'config' => [
              'debug' => TRUE,
              'features' => ['auth', 'logging'],
            ],
          ],
        ],
        [
          new Node(NodeType::MappingStart, [
            'key' => 'app',
            'children' => [
              new Node(NodeType::KeyValue, [
                'key' => 'name',
                'value' => 'myapp',
                'indent' => 2,
              ]),
              new Node(NodeType::MappingStart, [
                'key' => 'config',
                'children' => [
                  new Node(NodeType::KeyValue, [
                    'key' => 'debug',
                    'value' => TRUE,
                    'indent' => 4,
                  ]),
                  new Node(NodeType::MappingStart, [
                    'key' => 'features',
                    'children' => [
                      new Node(NodeType::SequenceItem, [
                        'value' => 'auth',
                        'indent' => 6,
                      ]),
                      new Node(NodeType::SequenceItem, [
                        'value' => 'logging',
                        'indent' => 6,
                      ]),
                    ],
                    'indent' => 4,
                  ]),
                ],
                'indent' => 2,
              ]),
            ],
            'indent' => 0,
          ]),
        ],
      ],

      'empty array' => [
        [],
        [],
      ],
    ];
  }

  public function testDataToAstWithIndentation(): void {
    $parser = new Parser();

    // Use reflection to access the protected method.
    $reflection = new \ReflectionClass($parser);
    $method = $reflection->getMethod('dataToAst');
    $method->setAccessible(TRUE);

    $inputData = ['key' => 'value'];
    $result = $method->invoke($parser, $inputData, 4);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertInstanceOf(Node::class, $result[0]);
    $this->assertEquals(4, $result[0]->indent);
  }

}
