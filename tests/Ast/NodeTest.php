<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Ast;

use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Node class.
 */
#[CoversClass(Node::class)]
class NodeTest extends TestCase {

  public function testBasicNodeCreation(): void {
    $node = new Node(NodeType::KeyValue);

    $this->assertEquals(NodeType::KeyValue, $node->type);
    $this->assertNull($node->key);
    $this->assertNull($node->value);
    $this->assertEquals(0, $node->indent);
    $this->assertNull($node->rawLine);
    $this->assertNull($node->attachedComment);
    $this->assertEmpty($node->children);
  }

  public function testNodeCreationWithData(): void {
    $data = [
      'key' => 'test_key',
      'value' => 'test_value',
      'indent' => 4,
      'rawLine' => 'test_key: test_value',
      'attachedComment' => '# Test comment',
      'children' => [
        new Node(NodeType::Comment, ['rawLine' => '# Child comment']),
      ],
    ];

    $node = new Node(NodeType::KeyValue, $data);

    $this->assertEquals(NodeType::KeyValue, $node->type);
    $this->assertEquals('test_key', $node->key);
    $this->assertEquals('test_value', $node->value);
    $this->assertEquals(4, $node->indent);
    $this->assertEquals('test_key: test_value', $node->rawLine);
    $this->assertEquals('# Test comment', $node->attachedComment);
    $this->assertCount(1, $node->children);
    $this->assertEquals(NodeType::Comment, $node->children[0]->type);
  }

  #[DataProvider('dataProviderNodeTypes')]
  public function testDifferentNodeTypes(NodeType $nodeType, array $expectedData): void {
    $node = new Node($nodeType, $expectedData);

    $this->assertEquals($nodeType, $node->type);

    foreach ($expectedData as $property => $expectedValue) {
      $this->assertEquals($expectedValue, $node->$property, sprintf("Property '%s' should match expected value", $property));
    }
  }

  public static function dataProviderNodeTypes(): array {
    return [
      'KeyValue node' => [
        NodeType::KeyValue,
        [
          'key' => 'database_host',
          'value' => 'localhost',
          'indent' => 0,
          'rawLine' => 'database_host: localhost',
        ],
      ],
      'MappingStart node' => [
        NodeType::MappingStart,
        [
          'key' => 'database',
          'indent' => 0,
          'rawLine' => 'database:',
        ],
      ],
      'SequenceItem node' => [
        NodeType::SequenceItem,
        [
          'value' => 'item1',
          'indent' => 2,
          'rawLine' => '  - item1',
        ],
      ],
      'Comment node' => [
        NodeType::Comment,
        [
          'rawLine' => '# This is a comment',
          'indent' => 0,
        ],
      ],
      'BlankLine node' => [
        NodeType::BlankLine,
        [
          'rawLine' => '',
          'indent' => 4,
        ],
      ],
      'LiteralBlock node' => [
        NodeType::LiteralBlock,
        [
          'key' => 'script',
          'value' => "echo hello\necho world\n",
          'rawLine' => "script: |\n  echo hello\n  echo world",
          'indent' => 0,
        ],
      ],
      'FoldedBlock node' => [
        NodeType::FoldedBlock,
        [
          'key' => 'description',
          'value' => "This is a long description\nthat spans multiple lines\n",
          'rawLine' => "description: >\n  This is a long description\n  that spans multiple lines",
          'indent' => 0,
        ],
      ],
    ];
  }

  public function testNodeWithChildren(): void {
    $childNode1 = new Node(NodeType::KeyValue, [
      'key' => 'host',
      'value' => 'localhost',
      'indent' => 2,
    ]);

    $childNode2 = new Node(NodeType::KeyValue, [
      'key' => 'port',
      'value' => '5432',
      'indent' => 2,
    ]);

    $parentNode = new Node(NodeType::MappingStart, [
      'key' => 'database',
      'indent' => 0,
      'children' => [$childNode1, $childNode2],
    ]);

    $this->assertEquals(NodeType::MappingStart, $parentNode->type);
    $this->assertEquals('database', $parentNode->key);
    $this->assertCount(2, $parentNode->children);
    $this->assertEquals('host', $parentNode->children[0]->key);
    $this->assertEquals('port', $parentNode->children[1]->key);
  }

  public function testNodeValueModification(): void {
    $node = new Node(NodeType::KeyValue, [
      'key' => 'test',
      'value' => 'original',
    ]);

    // Test value modification.
    $node->value = 'updated';
    $this->assertEquals('updated', $node->value);
  }

  public function testNodeWithComplexChildren(): void {
    // Create a complex nested structure.
    $sequenceItem1 = new Node(NodeType::SequenceItem, [
      'value' => 'admin',
      'indent' => 4,
    ]);

    $sequenceItem2 = new Node(NodeType::SequenceItem, [
      'value' => 'user',
      'indent' => 4,
    ]);

    $usersMapping = new Node(NodeType::MappingStart, [
      'key' => 'users',
      'indent' => 2,
      'children' => [$sequenceItem1, $sequenceItem2],
    ]);

    $configMapping = new Node(NodeType::MappingStart, [
      'key' => 'config',
      'indent' => 0,
      'children' => [$usersMapping],
    ]);

    $this->assertEquals(NodeType::MappingStart, $configMapping->type);
    $this->assertEquals('config', $configMapping->key);
    $this->assertCount(1, $configMapping->children);

    $users = $configMapping->children[0];
    $this->assertEquals(NodeType::MappingStart, $users->type);
    $this->assertEquals('users', $users->key);
    $this->assertCount(2, $users->children);

    $this->assertEquals('admin', $users->children[0]->value);
    $this->assertEquals('user', $users->children[1]->value);
  }

  public function testNodeIndentationLevels(): void {
    $levels = [0, 2, 4, 6, 8];

    foreach ($levels as $level) {
      $node = new Node(NodeType::KeyValue, [
        'key' => 'test',
        'value' => 'value',
        'indent' => $level,
      ]);

      $this->assertEquals($level, $node->indent, sprintf('Indentation level %s should be preserved', $level));
    }
  }

  public function testNodeCommentAttachment(): void {
    $comments = [
      '# Simple comment',
      '# Multi-line comment\n# Second line',
      '### Section header ###',
      '# Comment with special chars: @#$%^&*()',
    ];

    foreach ($comments as $comment) {
      $node = new Node(NodeType::KeyValue, [
        'key' => 'test',
        'value' => 'value',
        'attachedComment' => $comment,
      ]);

      $this->assertEquals($comment, $node->attachedComment, 'Comment should be preserved exactly');
    }
  }

  public function testEmptyAndNullValues(): void {
    $node = new Node(NodeType::KeyValue, [
      'key' => '',
      'value' => NULL,
      'rawLine' => '',
      'attachedComment' => NULL,
    ]);

    $this->assertEquals('', $node->key);
    $this->assertNull($node->value);
    $this->assertEquals('', $node->rawLine);
    $this->assertNull($node->attachedComment);
  }

}
