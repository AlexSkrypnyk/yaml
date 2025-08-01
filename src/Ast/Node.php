<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Ast;

/**
 * AST Node representing a YAML structure element.
 */
class Node implements \JsonSerializable {

  /**
   * The type of this AST node.
   */
  public NodeType $type;

  /**
   * The YAML key for this node (if applicable).
   */
  public ?string $key = NULL;

  /**
   * The value associated with this node.
   */
  public mixed $value = NULL;

  /**
   * The indentation level of this node in the source.
   */
  public int $indent = 0;

  /**
   * Array of child nodes.
   *
   * This is used for complex structures like mappings and sequences.
   *
   * @var Node[]
   */
  public array $children = [];

  /**
   * Comment attached to this node.
   */
  public ?string $attachedComment = NULL;

  /**
   * The original raw line from the source.
   */
  public ?string $rawLine = NULL;

  /**
   * Construct a new Node.
   *
   * @param NodeType $type
   *   The node type.
   * @param array<string, mixed> $data
   *   Initial data for the node.
   */
  public function __construct(NodeType $type, array $data = []) {
    $this->type = $type;

    // Handle each property with proper type checking.
    if (isset($data['key'])) {
      if (is_string($data['key'])) {
        $this->key = $data['key'];
      }
      elseif (is_numeric($data['key'])) {
        $this->key = (string) $data['key'];
      }
    }
    if (isset($data['value'])) {
      $this->value = $data['value'];
    }
    if (isset($data['indent'])) {
      if (is_int($data['indent'])) {
        $this->indent = $data['indent'];
      }
      elseif (is_numeric($data['indent'])) {
        $this->indent = (int) $data['indent'];
      }
    }
    if (isset($data['children']) && is_array($data['children'])) {
      // Validate that all children are Node instances.
      $valid_children = [];
      foreach ($data['children'] as $child) {
        if ($child instanceof Node) {
          $valid_children[] = $child;
        }
      }
      $this->children = $valid_children;
    }
    if (isset($data['attachedComment']) && is_string($data['attachedComment'])) {
      $this->attachedComment = $data['attachedComment'];
    }
    if (isset($data['rawLine']) && is_string($data['rawLine'])) {
      $this->rawLine = $data['rawLine'];
    }
  }

  /**
   * Magic method for JSON serialization.
   *
   * @return array<string, mixed>
   *   The serialized array representation.
   */
  public function jsonSerialize(): array {
    return [
      'type' => $this->type->name,
      'key' => $this->key,
      'value' => $this->value,
      'indent' => $this->indent,
      'children' => $this->children,
      'attachedComment' => $this->attachedComment,
      'rawLine' => $this->rawLine,
    ];
  }

  /**
   * Magic method for serialization.
   *
   * @return array<string, mixed>
   *   The serialized data.
   */
  public function __serialize(): array {
    return [
      'type' => $this->type,
      'key' => $this->key,
      'value' => $this->value,
      'indent' => $this->indent,
      'children' => $this->children,
      'attachedComment' => $this->attachedComment,
      'rawLine' => $this->rawLine,
    ];
  }

  /**
   * Magic method for unserialization.
   *
   * @param array<string, mixed> $data
   *   The data to unserialize from.
   */
  public function __unserialize(array $data): void {
    if (isset($data['type']) && $data['type'] instanceof NodeType) {
      $this->type = $data['type'];
    }
    if (isset($data['key']) && is_string($data['key'])) {
      $this->key = $data['key'];
    }
    if (isset($data['value'])) {
      $this->value = $data['value'];
    }
    if (isset($data['indent']) && is_int($data['indent'])) {
      $this->indent = $data['indent'];
    }
    if (isset($data['children']) && is_array($data['children'])) {
      $valid_children = [];
      foreach ($data['children'] as $child) {
        if ($child instanceof Node) {
          $valid_children[] = $child;
        }
      }
      $this->children = $valid_children;
    }
    if (isset($data['attachedComment']) && is_string($data['attachedComment'])) {
      $this->attachedComment = $data['attachedComment'];
    }
    if (isset($data['rawLine']) && is_string($data['rawLine'])) {
      $this->rawLine = $data['rawLine'];
    }
  }

  /**
   * Magic method for string representation.
   */
  public function __toString(): string {
    $parts = [];
    $parts[] = 'Node(' . $this->type->name;

    if ($this->key !== NULL) {
      $parts[] = sprintf("key: '%s'", $this->key);
    }

    if ($this->value !== NULL) {
      $value_str = is_string($this->value) ? sprintf("'%s'", $this->value) : var_export($this->value, TRUE);
      $parts[] = 'value: ' . $value_str;
    }

    if ($this->indent > 0) {
      $parts[] = 'indent: ' . $this->indent;
    }

    if (!empty($this->children)) {
      $parts[] = "children: " . count($this->children);
    }

    if ($this->attachedComment !== NULL) {
      $parts[] = sprintf("comment: '%s'", $this->attachedComment);
    }

    $parts[] = ")";

    return implode(", ", array_slice($parts, 0, -1)) . $parts[count($parts) - 1];
  }

  /**
   * Magic method for debugging output.
   *
   * @return array<string, mixed>
   *   The debug information array.
   */
  public function __debugInfo(): array {
    return [
      'type' => $this->type->name,
      'key' => $this->key,
      'value' => $this->value,
      'indent' => $this->indent,
      'children' => count($this->children) . ' children',
      'attachedComment' => $this->attachedComment,
      'rawLine' => $this->rawLine,
    ];
  }

}
