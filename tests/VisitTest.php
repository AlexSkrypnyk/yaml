<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Tests;

use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use AlexSkrypnyk\Yaml\Yaml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the visit method functionality.
 */
#[CoversClass(Yaml::class)]
class VisitTest extends TestCase {

  /**
   * Directory where fixture YAML files are stored.
   */
  protected string $fixturesDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fixturesDir = __DIR__ . '/fixtures';
  }

  public function testVisitBasicTraversal(): void {
    $yaml_content = <<<YAML
name: test-app
version: 1.0
commands:
  build:
    usage: Build the app
    cmd: make build
  test:
    usage: Run tests
    cmd: make test
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    $visited_nodes = [];
    $yaml->visit(function (Node $node, array $parent_path) use (&$visited_nodes): Node {
      if ($node->key !== NULL) {
        $visited_nodes[] = [
          'key' => $node->key,
          'value' => $node->value,
          'type' => $node->type,
          'parent_path' => $parent_path,
        ];
      }
      // Don't remove any nodes.
      return $node;
    });

    // Verify we visited the expected nodes.
    $this->assertGreaterThan(0, count($visited_nodes));

    // Check that we have the root level keys.
    $root_keys = array_filter($visited_nodes, function ($item): bool {
      return empty($item['parent_path']);
    });

    $root_key_names = array_column($root_keys, 'key');
    $this->assertContains('name', $root_key_names);
    $this->assertContains('version', $root_key_names);
    $this->assertContains('commands', $root_key_names);
  }

  public function testVisitNodeModification(): void {
    $yaml_content = <<<YAML
name: old-name
version: "1.0"
description: Old description
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    // Modify all string values to add a prefix.
    $yaml->visit(function (Node $node, array $parent_path): Node {
      if ($node->type === NodeType::KeyValue && is_string($node->value)) {
        $node->value = 'modified-' . $node->value;
      }
      // Keep the node.
      return $node;
    });

    // Verify modifications were applied.
    $this->assertEquals('modified-old-name', $yaml->getValue(['name']));
    $this->assertEquals('modified-1.0', $yaml->getValue(['version']));
    $this->assertEquals('modified-Old description', $yaml->getValue(['description']));
  }

  public function testVisitNodeRemoval(): void {
    $yaml_content = <<<YAML
name: test-app
version: 1.0
debug: true
commands:
  build:
    usage: Build the app
    cmd: make build
  test:
    usage: Run tests
    cmd: make test
  debug-only:
    usage: Debug command
    cmd: make debug
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    // Remove all nodes that contain "debug" in the key or value.
    $yaml->visit(function (Node $node, array $parent_path): ?Node {
      if ($node->key !== NULL && str_contains($node->key, 'debug')) {
        // Remove this node.
        return NULL;
      }
      if (is_string($node->value) && str_contains($node->value, 'debug')) {
        // Remove this node.
        return NULL;
      }
      // Keep the node.
      return $node;
    });

    // Verify nodes were removed.
    $this->assertFalse($yaml->has(['debug']));
    $this->assertFalse($yaml->has(['commands', 'debug-only']));

    // Verify other nodes remain.
    $this->assertTrue($yaml->has(['name']));
    $this->assertTrue($yaml->has(['version']));
    $this->assertTrue($yaml->has(['commands', 'build']));
    $this->assertTrue($yaml->has(['commands', 'test']));
  }

  public function testVisitNestedStructures(): void {
    $yaml_content = <<<YAML
app:
  name: my-app
  config:
    database:
      host: localhost
      port: 5432
    cache:
      enabled: true
      ttl: 3600
services:
  - web
  - api
  - worker
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    $node_paths = [];
    $yaml->visit(function (Node $node, array $parent_path) use (&$node_paths): Node {
      if ($node->key !== NULL) {
        $full_path = array_merge($parent_path, [$node->key]);
        $node_paths[] = implode('.', $full_path);
      }
      return $node;
    });

    // Verify we captured nested paths correctly.
    $this->assertContains('app', $node_paths);
    $this->assertContains('app.name', $node_paths);
    $this->assertContains('app.config', $node_paths);
    $this->assertContains('app.config.database', $node_paths);
    $this->assertContains('app.config.database.host', $node_paths);
    $this->assertContains('app.config.database.port', $node_paths);
    $this->assertContains('services', $node_paths);
  }

  public function testVisitSequenceItems(): void {
    $yaml_content = <<<YAML
items:
  - first
  - second
  - third
nested:
  - name: item1
    value: 100
  - name: item2
    value: 200
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    $sequence_values = [];
    $yaml->visit(function (Node $node, array $parent_path) use (&$sequence_values): Node {
      if ($node->type === NodeType::SequenceItem) {
        $sequence_values[] = [
          'value' => $node->value,
          'parent_path' => $parent_path,
        ];
      }
      return $node;
    });

    $this->assertGreaterThan(0, count($sequence_values));

    // Check for sequence values.
    $values = array_column($sequence_values, 'value');
    $this->assertContains('first', $values);
    $this->assertContains('second', $values);
    $this->assertContains('third', $values);
  }

  public function testVisitWithCommentsPreservation(): void {
    $yaml_content = <<<YAML
# Main configuration
name: test-app
version: 1.0

# Commands section
commands:
  # Build command
  build:
    usage: Build the app
    cmd: make build
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    $nodes_with_comments = [];
    $yaml->visit(function (Node $node, array $parent_path) use (&$nodes_with_comments): Node {
      if ($node->attachedComment !== NULL) {
        $nodes_with_comments[] = [
          'key' => $node->key,
          'comment' => $node->attachedComment,
        ];
      }
      return $node;
    });

    // Verify we found nodes with comments.
    $this->assertGreaterThan(0, count($nodes_with_comments));
  }

  public function testVisitEmptyCallback(): void {
    $yaml_content = <<<YAML
name: test
value: 123
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    // Visit with empty callback that does nothing.
    $yaml->visit(function (Node $node, array $parent_path): Node {
      // Just return the node unchanged.
      return $node;
    });

    // Verify structure is unchanged.
    $this->assertEquals('test', $yaml->getValue(['name']));
    $this->assertEquals(123, $yaml->getValue(['value']));
  }

  public function testVisitConditionalRemoval(): void {
    $yaml_content = <<<YAML
environments:
  development:
    debug: true
    logging: verbose
  production:
    debug: false
    logging: error
  staging:
    debug: true
    logging: info
YAML;

    $yaml = new Yaml();
    $yaml->parse($yaml_content);

    // Remove all environments where debug is true.
    $yaml->visit(function (Node $node, array $parent_path): ?Node {
      // If this is a mapping under 'environments' with debug: true, remove it
      // We need to check if this environment has debug: true
      // For simplicity, we'll remove 'development' and 'staging' by name.
      if (count($parent_path) === 1 && $parent_path[0] === 'environments' &&
          $node->type === NodeType::MappingStart &&
          in_array($node->key, ['development', 'staging'])) {
          return NULL;
          // Remove this environment.
      }
      return $node;
    });

    // Verify removals.
    $this->assertFalse($yaml->has(['environments', 'development']));
    $this->assertFalse($yaml->has(['environments', 'staging']));
    $this->assertTrue($yaml->has(['environments', 'production']));
  }

  public function testVisitRemoveBlocksFixture(): void {
    $before = $this->fixturesDir . '/visit-remove-blocks/before.yml';
    $after = $this->fixturesDir . '/visit-remove-blocks/after.yml';

    // Ensure fixture files exist.
    $this->assertFileExists($before, 'Before fixture file should exist');
    $this->assertFileExists($after, 'After fixture file should exist');

    // Load the YAML file.
    $yaml = new Yaml();
    $yaml->load($before);

    // Apply visitor to remove specific line from block content.
    $yaml->visit(function (Node $node, array $parent_path): Node {
      // Remove the "ahoy reset" line from cmd blocks in the build command.
      if (count($parent_path) === 2 && $parent_path[0] === 'commands' &&
          $parent_path[1] === 'build' && $node->key === 'cmd' &&
          is_string($node->value)) {
        // Remove the specific "ahoy reset" line from the literal block content.
        $lines = explode("\n", $node->value);
        $filtered_lines = array_filter($lines, function ($line): bool {
          return trim($line) !== 'ahoy reset';
        });
        $node->value = implode("\n", $filtered_lines);
      }
      // Keep all nodes.
      return $node;
    });

    $actual_content = $yaml->dump();
    $expected_content = file_get_contents($after);

    $this->assertEquals(
      $expected_content,
      $actual_content,
      'Output should match expected fixture after removing specific line from block content'
    );
  }

}
