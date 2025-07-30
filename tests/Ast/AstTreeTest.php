<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use AlexSkrypnyk\Yaml\Ast\AstException;
use AlexSkrypnyk\Yaml\Ast\AstTree;
use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the AstTree class.
 */
#[CoversClass(AstTree::class)]
class AstTreeTest extends TestCase {

  /**
   * Test finding nodes by path.
   */
  public function testFindNodeByPath(): void {
    $nodes = [
      new Node(NodeType::KeyValue, [
        'key' => 'root',
        'value' => 'root value',
        'indent' => 0,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'nested',
        'value' => 'nested value',
        'indent' => 2,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'deep',
        'value' => 'deep value',
        'indent' => 4,
      ]),
    ];

    $tree = new AstTree($nodes);

    // Test finding root level node.
    $rootNode = $tree->findNodeByPath(['root']);
    $this->assertNotNull($rootNode);
    $this->assertEquals('root', $rootNode->key);

    // Test finding nested node.
    $nestedNode = $tree->findNodeByPath(['root', 'nested']);
    $this->assertNotNull($nestedNode);
    $this->assertEquals('nested', $nestedNode->key);

    // Test finding deep nested node.
    $deepNode = $tree->findNodeByPath(['root', 'nested', 'deep']);
    $this->assertNotNull($deepNode);
    $this->assertEquals('deep', $deepNode->key);

    // Test non-existent path.
    $nonExistent = $tree->findNodeByPath(['nonexistent']);
    $this->assertNull($nonExistent);
  }

  /**
   * Test checking if path exists.
   */
  public function testHas(): void {
    $nodes = [
      new Node(NodeType::KeyValue, [
        'key' => 'existing',
        'value' => 'value',
        'indent' => 0,
      ]),
    ];

    $tree = new AstTree($nodes);

    $this->assertTrue($tree->has(['existing']));
    $this->assertFalse($tree->has(['nonexistent']));
  }

  /**
   * Test getting values by path.
   */
  public function testGetValue(): void {
    $nodes = [
      new Node(NodeType::KeyValue, [
        'key' => 'test',
        'value' => 'test value',
        'indent' => 0,
      ]),
    ];

    $tree = new AstTree($nodes);

    $this->assertEquals('test value', $tree->getValue(['test']));

    // Test exception for non-existent path.
    $this->expectException(AstException::class);
    $tree->getValue(['nonexistent']);
  }

  /**
   * Test setting values for existing paths.
   */
  public function testSetValueExisting(): void {
    $nodes = [
      new Node(NodeType::KeyValue, [
        'key' => 'test',
        'value' => 'original value',
        'indent' => 0,
      ]),
    ];

    $tree = new AstTree($nodes);

    $tree->setValue(['test'], 'updated value');
    $this->assertEquals('updated value', $tree->getValue(['test']));
  }

  /**
   * Test setting values creates new keys when path doesn't exist.
   */
  public function testSetValueCreatesNewKey(): void {
    $tree = new AstTree([]);

    $tree->setValue(['newkey'], 'new value');

    $this->assertTrue($tree->has(['newkey']));
    $this->assertEquals('new value', $tree->getValue(['newkey']));
  }

  /**
   * Test adding simple scalar keys.
   */
  public function testAddKeyScalar(): void {
    $tree = new AstTree([]);

    $tree->addKey([], 'root', 'root value');

    $this->assertTrue($tree->has(['root']));
    $this->assertEquals('root value', $tree->getValue(['root']));

    $nodes = $tree->getNodes();
    $this->assertCount(1, $nodes);
    $this->assertEquals('root', $nodes[0]->key);
    $this->assertEquals('root value', $nodes[0]->value);
    $this->assertEquals(0, $nodes[0]->indent);
  }

  /**
   * Test adding array values creates proper structure.
   */
  public function testAddKeyArray(): void {
    $tree = new AstTree([]);

    $arrayValue = [
      'usage' => 'Test command',
      'cmd' => 'echo test',
    ];

    $tree->addKey([], 'command', $arrayValue);

    $this->assertTrue($tree->has(['command']));

    $nodes = $tree->getNodes();
    $this->assertGreaterThan(0, count($nodes));

    // The first node should be a MAPPING_START for complex structures.
    $rootNode = $nodes[0];
    $this->assertEquals('command', $rootNode->key);
    $this->assertEquals(NodeType::MappingStart, $rootNode->type);
  }

  /**
   * Test adding nested keys.
   */
  public function testAddKeyNested(): void {
    // Start with a parent structure.
    $nodes = [
      new Node(NodeType::MappingStart, [
        'key' => 'parent',
        'indent' => 0,
      ]),
    ];

    $tree = new AstTree($nodes);

    $tree->addKey(['parent'], 'child', 'child value');

    $this->assertTrue($tree->has(['parent', 'child']));
    $this->assertEquals('child value', $tree->getValue(['parent', 'child']));

    $allNodes = $tree->getNodes();
    $this->assertGreaterThan(1, count($allNodes));

    // Find the child node.
    $childNode = $tree->findNodeByPath(['parent', 'child']);
    $this->assertNotNull($childNode);
    // Should be indented under parent.
    $this->assertEquals(2, $childNode->indent);
  }

  /**
   * Test adding keys with comments.
   */
  public function testAddKeyWithComment(): void {
    $tree = new AstTree([]);

    $tree->addKey([], 'commented', 'value', 'This is a comment');

    $this->assertEquals('This is a comment', $tree->getComment(['commented']));
  }

  /**
   * Test deleting existing keys.
   */
  public function testDeleteKey(): void {
    $nodes = [
      new Node(NodeType::KeyValue, [
        'key' => 'toDelete',
        'value' => 'delete me',
        'indent' => 0,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'toKeep',
        'value' => 'keep me',
        'indent' => 0,
      ]),
    ];

    $tree = new AstTree($nodes);

    $this->assertTrue($tree->has(['toDelete']));
    $this->assertTrue($tree->has(['toKeep']));

    $tree->deleteKey(['toDelete']);

    $this->assertFalse($tree->has(['toDelete']));
    $this->assertTrue($tree->has(['toKeep']));

    // Verify node was actually removed from array.
    $remainingNodes = $tree->getNodes();
    $this->assertCount(1, $remainingNodes);
    $this->assertEquals('toKeep', $remainingNodes[0]->key);
  }

  /**
   * Test deleting non-existent keys throws exception.
   */
  public function testDeleteKeyNonExistent(): void {
    $tree = new AstTree([]);

    $this->expectException(AstException::class);
    $this->expectExceptionMessage('Path not found: nonexistent');

    $tree->deleteKey(['nonexistent']);
  }

  /**
   * Test deleting with empty path throws exception.
   */
  public function testDeleteKeyEmptyPath(): void {
    $tree = new AstTree([]);

    $this->expectException(AstException::class);
    $this->expectExceptionMessage('Path cannot be empty');

    $tree->deleteKey([]);
  }

  /**
   * Test getting comments.
   */
  public function testGetComment(): void {
    $node = new Node(NodeType::KeyValue, [
      'key' => 'test',
      'value' => 'value',
      'indent' => 0,
    ]);
    $node->attachedComment = 'Test comment';

    $tree = new AstTree([$node]);

    $this->assertEquals('Test comment', $tree->getComment(['test']));
  }

  /**
   * Test setting comments.
   */
  public function testSetComment(): void {
    $nodes = [
      new Node(NodeType::KeyValue, [
        'key' => 'test',
        'value' => 'value',
        'indent' => 0,
      ]),
    ];

    $tree = new AstTree($nodes);

    $tree->setComment(['test'], 'New comment');
    $this->assertEquals('New comment', $tree->getComment(['test']));
  }

  /**
   * Test complex nested structure operations.
   */
  public function testComplexNestedOperations(): void {
    // Create a structure like:
    // @code
    // commands:
    //   build:
    //     usage: Build command
    //     cmd: make build
    //   test:
    //     usage: Test command
    // @endcode
    $nodes = [
      new Node(NodeType::MappingStart, [
        'key' => 'commands',
        'indent' => 0,
      ]),
      new Node(NodeType::MappingStart, [
        'key' => 'build',
        'indent' => 2,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'usage',
        'value' => 'Build command',
        'indent' => 4,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'cmd',
        'value' => 'make build',
        'indent' => 4,
      ]),
      new Node(NodeType::MappingStart, [
        'key' => 'test',
        'indent' => 2,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'usage',
        'value' => 'Test command',
        'indent' => 4,
      ]),
    ];

    $tree = new AstTree($nodes);

    // Test we can find nested paths.
    $this->assertTrue($tree->has(['commands', 'build', 'usage']));
    $this->assertTrue($tree->has(['commands', 'build', 'cmd']));
    $this->assertTrue($tree->has(['commands', 'test', 'usage']));

    // Test getting nested values.
    $this->assertEquals('Build command', $tree->getValue(['commands', 'build', 'usage']));
    $this->assertEquals('make build', $tree->getValue(['commands', 'build', 'cmd']));
    $this->assertEquals('Test command', $tree->getValue(['commands', 'test', 'usage']));

    // Test updating nested value.
    $tree->setValue(['commands', 'build', 'usage'], 'Updated build command');
    $this->assertEquals('Updated build command', $tree->getValue(['commands', 'build', 'usage']));

    // Test deleting nested key - should only remove usage, not the whole
    // build command.
    $tree->deleteKey(['commands', 'build', 'usage']);
    $this->assertFalse($tree->has(['commands', 'build', 'usage']));
    // Cmd should still exist.
    $this->assertTrue($tree->has(['commands', 'build', 'cmd']));
    // Other commands unaffected.
    $this->assertTrue($tree->has(['commands', 'test', 'usage']));

    // Verify the structure integrity.
    $nodes = $tree->getNodes();
    $this->assertNotEmpty($nodes);
    $buildCmdNode = $tree->findNodeByPath(['commands', 'build', 'cmd']);
    $this->assertNotNull($buildCmdNode);
    $testUsageNode = $tree->findNodeByPath(['commands', 'test', 'usage']);
    $this->assertNotNull($testUsageNode);
  }

  /**
   * Test deleting complex structures with children.
   */
  public function testDeleteComplexStructure(): void {
    // Create a structure like:
    // @code
    // parent:
    //   child1: value1
    //   child2: value2
    // sibling: sibling_value
    // @endcode
    $nodes = [
      new Node(NodeType::MappingStart, [
        'key' => 'parent',
        'indent' => 0,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'child1',
        'value' => 'value1',
        'indent' => 2,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'child2',
        'value' => 'value2',
        'indent' => 2,
      ]),
      new Node(NodeType::KeyValue, [
        'key' => 'sibling',
        'value' => 'sibling_value',
        'indent' => 0,
      ]),
    ];

    $tree = new AstTree($nodes);

    // Verify initial structure.
    $this->assertTrue($tree->has(['parent']));
    $this->assertTrue($tree->has(['parent', 'child1']));
    $this->assertTrue($tree->has(['parent', 'child2']));
    $this->assertTrue($tree->has(['sibling']));

    // Delete the parent - should remove parent and all its children.
    $tree->deleteKey(['parent']);

    // Verify parent and children are gone, but sibling remains.
    $this->assertFalse($tree->has(['parent']));
    $this->assertFalse($tree->has(['parent', 'child1']));
    $this->assertFalse($tree->has(['parent', 'child2']));
    // Sibling should remain.
    $this->assertTrue($tree->has(['sibling']));

    // Verify the correct number of nodes remain.
    $remainingNodes = $tree->getNodes();
    $this->assertCount(1, $remainingNodes);
    $this->assertEquals('sibling', $remainingNodes[0]->key);
  }

  /**
   * Test error conditions.
   */
  public function testErrorConditions(): void {
    $tree = new AstTree([]);

    // Test empty path for setValue.
    $this->expectException(AstException::class);
    $tree->setValue([], 'value');
  }

  /**
   * Test adding key to non-existent parent throws exception.
   */
  public function testAddKeyNonExistentParent(): void {
    $tree = new AstTree([]);

    $this->expectException(AstException::class);
    $this->expectExceptionMessage('Parent path not found: nonexistent');

    $tree->addKey(['nonexistent'], 'child', 'value');
  }

}
