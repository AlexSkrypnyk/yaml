<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Ast;

/**
 * AST Tree class that handles all tree manipulation and traversal operations.
 *
 * This class encapsulates the enhanced tokens and provides methods to:
 * - Traverse the tree to find nodes by path
 * - Manipulate values in the tree
 * - Add new nodes to the tree
 * - Delete nodes from the tree
 * - Get and set comments.
 */
class AstTree {

  /**
   * The enhanced tokens that form the AST tree.
   *
   * @var Node[]
   */
  protected array $nodes = [];

  /**
   * Constructor.
   *
   * @param Node[] $nodes
   *   The initial enhanced tokens/nodes.
   */
  public function __construct(array $nodes = []) {
    $this->nodes = $nodes;
  }

  /**
   * Get all nodes in the tree.
   *
   * @return Node[]
   *   The nodes array.
   */
  public function getNodes(): array {
    return $this->nodes;
  }

  /**
   * Find a node by path in the tree.
   *
   * @param array<string> $path
   *   The path to search for.
   *
   * @return Node|null
   *   The node if found, NULL otherwise.
   */
  public function findNodeByPath(array $path): ?Node {
    if (empty($this->nodes)) {
      return NULL;
    }

    return $this->searchNodesRecursively($this->nodes, $path);
  }

  /**
   * Search for a node recursively in the tree.
   *
   * @param array<Node> $nodes
   *   Array of nodes to search in.
   * @param array<string> $path
   *   The remaining path to search for.
   * @param int $current_depth
   *   Current indentation depth for tracking nested structure.
   *
   * @return Node|null
   *   The node if found, NULL otherwise.
   */
  protected function searchNodesRecursively(array $nodes, array $path, int $current_depth = 0): ?Node {
    if (empty($path)) {
      return NULL;
    }

    $target_key = $path[0];
    $target_depth = $current_depth * 2;
    // YAML uses 2-space indentation.
    // YAML uses 2-space indentation.
    $counter = count($nodes);

    for ($i = 0; $i < $counter; $i++) {
      $node = $nodes[$i];

      if ($node->key !== NULL && $node->key === $target_key && $node->indent >= $target_depth) {
        // If this is the final key in the path, return this node.
        if (count($path) === 1) {
          return $node;
        }

        // Otherwise, look for the next key in the path at the next
        // indentation level.
        $remaining_path = array_slice($path, 1);
        $next_target_key = $remaining_path[0];
        $next_target_depth = ($current_depth + 1) * 2;

        // Search subsequent nodes for the next key at the correct depth.
        for ($j = $i + 1; $j < count($nodes); $j++) {
          $next_node = $nodes[$j];

          // Stop if we've gone back to a shallower depth (end of this mapping)
          if ($next_node->key !== NULL && $next_node->indent <= $node->indent) {
            break;
          }

          if ($next_node->key !== NULL && $next_node->key === $next_target_key && $next_node->indent >= $next_target_depth) {
            if (count($remaining_path) === 1) {
              return $next_node;
            }
            // Continue recursively.
            return $this->searchNodesRecursively(array_slice($nodes, $j), $remaining_path, $current_depth + 1);
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Set a value at the specified path in the tree.
   *
   * @param array<string> $path
   *   The path to the value.
   * @param mixed $value
   *   The value to set.
   *
   * @throws AstException
   *   If the path is invalid.
   */
  public function setValue(array $path, mixed $value): void {
    if (empty($path)) {
      throw new AstException('Path cannot be empty');
    }

    $node = $this->findNodeByPath($path);
    if (!$node instanceof Node) {
      // Create new key if path doesn't exist.
      $parent_path = array_slice($path, 0, -1);
      $key = end($path);
      $this->addKey($parent_path, $key, $value);
      return;
    }

    // Update existing node.
    $node->value = $value;
  }

  /**
   * Get a value at the specified path in the tree.
   *
   * @param array<string> $path
   *   The path to the value.
   *
   * @return mixed
   *   The value at the path.
   *
   * @throws AstException
   *   If the path is not found.
   */
  public function getValue(array $path): mixed {
    $node = $this->findNodeByPath($path);
    if (!$node instanceof Node) {
      throw new AstException('Path not found: ' . implode('.', $path));
    }

    return $node->value;
  }

  /**
   * Check if a path exists in the tree.
   *
   * @param array<string> $path
   *   The path to check.
   *
   * @return bool
   *   TRUE if the path exists, FALSE otherwise.
   */
  public function has(array $path): bool {
    return $this->findNodeByPath($path) instanceof Node;
  }

  /**
   * Add a new key to the tree.
   *
   * @param array<string> $parent_path
   *   The path to the parent.
   * @param string $key
   *   The key to add.
   * @param mixed $value
   *   The value to set.
   * @param string|null $comment
   *   Optional comment for the key.
   */
  public function addKey(array $parent_path, string $key, mixed $value, ?string $comment = NULL): void {
    $indent = count($parent_path) * 2;

    // Handle complex values inline.
    if (is_array($value)) {
      $child_nodes = $this->buildNodesFromArray([$key => $value], $indent);
      // Returns array with single node for our key.
      $new_node = $child_nodes[0];
    }
    else {
      // Simple scalar value.
      $new_node = new Node(NodeType::KeyValue, [
        'key' => $key,
        'value' => $value,
        'indent' => $indent,
      ]);
    }

    if ($comment !== NULL) {
      $new_node->attachedComment = $comment;
    }

    if (empty($parent_path)) {
      // Add to root level - append at the end.
      $this->nodes[] = $new_node;
    }
    else {
      // Find the parent container and add the new node after its last child.
      $parent_node = $this->findNodeByPath($parent_path);
      if (!$parent_node instanceof Node) {
        throw new AstException('Parent path not found: ' . implode('.', $parent_path));
      }

      // Find the position where the parent's children end.
      $target_indent_level = $parent_node->indent;
      $insert_position = count($this->nodes);
      // Default to end
      // Look for the position after the parent's last child.
      // Default to end.
      $counter = count($this->nodes);

      // Look for the position after the parent's last child.
      for ($i = 0; $i < $counter; $i++) {
        if ($this->nodes[$i] === $parent_node) {
          // Found parent, now find where its children end.
          for ($j = $i + 1; $j < count($this->nodes); $j++) {
            if (isset($this->nodes[$j]->key) && $this->nodes[$j]->indent <= $target_indent_level) {
              // Found a node at same or shallower level - insert before it.
              $insert_position = $j;
              break;
            }
          }
          break;
        }
      }

      // Insert the new node at the calculated position.
      array_splice($this->nodes, $insert_position, 0, [$new_node]);
    }
  }

  /**
   * Delete a key from the tree.
   *
   * @param array<string> $path
   *   The path to the key to delete.
   *
   * @throws AstException
   *   If the path is not found.
   */
  public function deleteKey(array $path): void {
    if (empty($path)) {
      throw new AstException('Path cannot be empty');
    }

    $node = $this->findNodeByPath($path);
    if (!$node instanceof Node) {
      throw new AstException('Path not found: ' . implode('.', $path));
    }
    // Find the node and determine what to remove.
    $counter = count($this->nodes);

    // Find the node and determine what to remove.
    for ($i = 0; $i < $counter; $i++) {
      if ($this->nodes[$i] === $node) {
        // If this is a MAPPING_START or complex node, we need to remove all
        // its children too.
        // At minimum, remove this node.
        $nodes_to_remove = 1;

        if ($node->type === NodeType::MappingStart) {
          // Find all child nodes by looking for nodes with deeper indentation.
          $node_indent_level = $node->indent;
          for ($j = $i + 1; $j < count($this->nodes); $j++) {
            $child_node = $this->nodes[$j];

            // If we encounter a node at the same or shallower level, we've
            // reached the end of children.
            if ($child_node->key !== NULL && $child_node->indent <= $node_indent_level) {
              break;
            }

            // This is a child node, include it in removal.
            $nodes_to_remove++;
          }
        }

        // Remove the node and all its children.
        array_splice($this->nodes, $i, $nodes_to_remove);
        return;
      }
    }
  }

  /**
   * Get a comment for a node at the specified path.
   *
   * @param array<string> $path
   *   The path to the node.
   *
   * @return string|null
   *   The comment, or NULL if no comment exists.
   *
   * @throws AstException
   *   If the path is not found.
   */
  public function getComment(array $path): ?string {
    $node = $this->findNodeByPath($path);
    if (!$node instanceof Node) {
      throw new AstException('Path not found: ' . implode('.', $path));
    }

    return $node->attachedComment;
  }

  /**
   * Set a comment for a node at the specified path.
   *
   * @param array<string> $path
   *   The path to the node.
   * @param string $comment
   *   The comment to set.
   *
   * @throws AstException
   *   If the path is not found.
   */
  public function setComment(array $path, string $comment): void {
    $node = $this->findNodeByPath($path);
    if (!$node instanceof Node) {
      throw new AstException('Path not found: ' . implode('.', $path));
    }

    $node->attachedComment = $comment;
  }

  /**
   * Build AST nodes from array data (similar to Editor logic).
   *
   * @param array<mixed> $parsed
   *   The parsed data array.
   * @param int $indent
   *   Current indentation level.
   *
   * @return array<Node>
   *   Array of AST nodes.
   */
  protected function buildNodesFromArray(array $parsed, int $indent = 0): array {
    $nodes = [];

    foreach ($parsed as $key => $value) {
      if (is_array($value)) {
        // Detect list vs mapping.
        $is_list = array_keys($value) === range(0, count($value) - 1);
        if ($is_list) {
          $children = array_map(fn($item): Node => new Node(NodeType::SequenceItem, [
            'value' => $item,
            'indent' => $indent + 2,
          ]),
            $value
          );

          $nodes[] = new Node(NodeType::MappingStart, [
            'key' => (string) $key,
            'children' => $children,
            'indent' => $indent,
          ]);
        }
        else {
          $children = $this->buildNodesFromArray($value, $indent + 2);
          $nodes[] = new Node(NodeType::MappingStart, [
            'key' => (string) $key,
            'children' => $children,
            'indent' => $indent,
          ]);
        }
      }
      else {
        $nodes[] = new Node(NodeType::KeyValue, [
          'key' => (string) $key,
          'value' => $value,
          'indent' => $indent,
        ]);
      }
    }

    return $nodes;
  }

  /**
   * Visit all nodes in the tree recursively with a callback.
   *
   * The visitor callback receives each node and an array of parent path
   * elements.
   * If the visitor returns null, the node will be removed from the tree.
   *
   * @param callable $visitor
   *   A callback that receives (Node $node, array $parent_path).
   *   If it returns null, the node is removed.
   */
  public function visit(callable $visitor): void {
    $nodes_to_remove = [];
    // Stack to track parent path based on indentation.
    $parent_stack = [];

    foreach ($this->nodes as $index => $node) {
      // Update parent stack based on current node's indentation
      // Remove parents that are at same or deeper indentation.
      while (!empty($parent_stack) && end($parent_stack)['indent'] >= $node->indent) {
        array_pop($parent_stack);
      }

      // Build parent path from stack.
      $parent_path = array_column($parent_stack, 'key');

      // Apply visitor to current node.
      $result = $visitor($node, $parent_path);

      // If visitor returns null, mark node for removal.
      if ($result === NULL) {
        $nodes_to_remove[] = $index;
      }
      elseif ($node->key !== NULL) {
        // Add current node to parent stack if it has a key (could be a parent)
        $parent_stack[] = [
          'key' => $node->key,
          'indent' => $node->indent,
        ];
      }
    }

    // Remove nodes marked for removal (in reverse order to maintain indices)
    foreach (array_reverse($nodes_to_remove) as $index) {
      array_splice($this->nodes, $index, 1);
    }
  }

}
