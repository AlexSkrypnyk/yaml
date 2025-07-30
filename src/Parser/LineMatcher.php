<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Parser;

use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use Symfony\Component\Yaml\Yaml;

/**
 * Reverse Line Aligner that uses tokenized data as primary source.
 *
 * Instead of starting with AST tree and aligning with tokens, this starts
 * with tokens and enhances them with parsed values from Symfony.
 */
class LineMatcher {

  /**
   * Array of line tokens.
   *
   * @var array<Node>
   */
  protected array $lineTokens = [];

  /**
   * Aligns tokenized nodes with parsed data from Symfony.
   *
   * @param array<Node> $line_tokens
   *   Line tokens from tokenizer (and mapped by comment mapper)
   * @param array<Node> $ast_tree
   *   Tree generated from Symfony/Editor.
   *
   * @return array<Node>
   *   Enhanced tokens with parsed values
   */
  public function align(array $line_tokens, array $ast_tree): array {
    // Store lineTokens for use in filtering.
    $this->lineTokens = $line_tokens;

    // Pre-process AST to remove children that have corresponding
    // LITERAL_BLOCK/FOLDED_BLOCK tokens.
    $this->removeConflictingAstChildren($ast_tree);

    // Create a flat list of all AST nodes for lookup.
    $ast_lookup = [];
    $this->flattenAstTree($ast_tree, $ast_lookup);

    // Track which tokens are children of other tokens to avoid duplication.
    $child_token_keys = [];
    $this->identifyChildTokens($line_tokens, $child_token_keys);

    // Process each token and enhance with AST data where applicable.
    $result = [];
    foreach ($line_tokens as $token) {
      // Skip tokens that are children of mapping/sequence nodes to avoid
      // duplication
      // BUT preserve LITERAL_BLOCK and FOLDED_BLOCK nodes to maintain their
      // formatting
      // AND preserve KEY_VALUE nodes that don't have AST children (they were
      // filtered out)
      $token_key = $this->getTokenKey($token);
      $is_child = $token_key && isset($child_token_keys[$token_key]);

      // PRESERVE ALL TOKENS - no skipping.
      $enhanced_token = clone $token;

      // For structural nodes, try to find matching AST node and enhance with
      // parsed value
      // BUT preserve original token values for KEY_VALUE nodes to maintain
      // original quoting.
      if (in_array($token->type, [
        NodeType::MappingStart,
        NodeType::SequenceItem,
        NodeType::LiteralBlock,
        NodeType::FoldedBlock,
      ])) {
        $matching_ast_node = $this->findMatchingAstNode($token, $ast_lookup);
        if ($matching_ast_node instanceof Node) {
          // Enhance token with parsed value from AST.
          $enhanced_token->value = $matching_ast_node->value;

          // For mapping nodes, only include AST children if we're not
          // preserving
          // individual child tokens.
          if ($token->type === NodeType::MappingStart && isset($matching_ast_node->children)) {
            // Don't include AST children since we're preserving individual
            // tokens
            // $enhanced_token->children = $matching_ast_node->children;.
          }
        }
      }

      // For KEY_VALUE nodes, prefer original token values for
      // idempotence
      // but allow AST values when they're different (indicating an
      // update)
      if ($token->type === NodeType::KeyValue) {
        $matching_ast_node = $this->findMatchingAstNode($token, $ast_lookup);
        if ($matching_ast_node instanceof Node) {
          // Parse both values to compare semantic content.
          try {
            $value_str = is_scalar($token->value) ? (string) $token->value : '';
            $parsed_result = Yaml::parse($token->key . ': ' . $value_str);
            $token_value_parsed = is_array($parsed_result) ? $parsed_result[$token->key] : $token->value;
          }
          catch (\Exception $e) {
            // If parsing fails, fall back to simple trimming.
            $token_value_parsed = is_string($token->value) ? trim($token->value, '\'"') : $token->value;
          }
          $ast_value_parsed = $matching_ast_node->value;

          // If values are semantically different, use AST value (indicates
          // update). If values are the same, preserve original formatting
          // but use parsed value.
          if ($token_value_parsed !== $ast_value_parsed) {
            $enhanced_token->value = $matching_ast_node->value;
          }
          else {
            // Use the parsed value for semantic comparison, but rawLine
            // preserves original formatting.
            $enhanced_token->value = $token_value_parsed;
          }
        }
      }

      $result[] = $enhanced_token;
    }

    return $result;
  }

  /**
   * Flatten AST tree into a lookup array.
   *
   * @param array<Node> $astTree
   *   The AST tree to flatten.
   * @param array<string, Node> $lookup
   *   The lookup array to populate.
   * @param array<string> $parentPath
   *   The parent path for context.
   */
  protected function flattenAstTree(array $astTree, array &$lookup, array $parentPath = []): void {
    foreach ($astTree as $node) {
      $key = $this->getNodeKey($node, $parentPath);
      if ($key) {
        $lookup[$key] = $node;
      }

      if (isset($node->children) && is_array($node->children)) {
        // Add current node to parent path.
        $newParentPath = $parentPath;
        if ($node->key) {
          $newParentPath[] = $node->key;
        }
        $this->flattenAstTree($node->children, $lookup, $newParentPath);
      }
    }
  }

  /**
   * Generate a lookup key for a node.
   *
   * @param \AlexSkrypnyk\Yaml\Ast\Node $node
   *   The node to generate a key for.
   * @param array<string> $parentPath
   *   The parent path for context.
   */
  protected function getNodeKey(Node $node, array $parentPath = []): ?string {
    if ($node->type === NodeType::KeyValue ||
        $node->type === NodeType::MappingStart ||
        $node->type === NodeType::LiteralBlock ||
        $node->type === NodeType::FoldedBlock) {
      $pathStr = empty($parentPath) ? '' : implode('.', $parentPath) . '.';
      return sprintf('%s:%s%s:%d', $node->type->name, $pathStr, $node->key, $node->indent);
    }

    if ($node->type === NodeType::SequenceItem) {
      // Use value and indent for sequence items since they don't have keys.
      $pathStr = empty($parentPath) ? '' : implode('.', $parentPath) . '.';
      $value_str = is_scalar($node->value) ? (string) $node->value : '';
      return sprintf('%s:%s%s:%d', $node->type->name, $pathStr, $value_str, $node->indent);
    }

    return NULL;
  }

  /**
   * Find matching AST node for a token.
   *
   * @param \AlexSkrypnyk\Yaml\Ast\Node $token
   *   The token to find a match for.
   * @param array<string, Node> $astLookup
   *   The AST lookup array.
   */
  protected function findMatchingAstNode(Node $token, array $astLookup): ?Node {
    $parentPath = $this->getTokenParentPath($token);
    $key = $this->getNodeKey($token, $parentPath);
    return $key ? ($astLookup[$key] ?? NULL) : NULL;
  }

  /**
   * Identify child tokens that belong to parent mapping/sequence nodes.
   *
   * @param array<Node> $lineTokens
   *   The line tokens to analyze.
   * @param array<string, bool> $childTokenKeys
   *   The array to store child token keys.
   */
  protected function identifyChildTokens(array $lineTokens, array &$childTokenKeys): void {
    $indentStack = [];

    foreach ($lineTokens as $token) {
      // Clean up stack - remove entries with higher or equal indent.
      while (!empty($indentStack) && end($indentStack)['indent'] >= $token->indent) {
        array_pop($indentStack);
      }

      // If this token is indented and we have a parent, mark it as a child.
      if (!empty($indentStack) && $token->indent > 0) {
        $tokenKey = $this->getTokenKey($token);
        if ($tokenKey) {
          $childTokenKeys[$tokenKey] = TRUE;
        }
      }

      // Add this token to stack if it's a container type.
      if ($token->type == NodeType::MappingStart) {
        $indentStack[] = ['indent' => $token->indent, 'token' => $token];
      }
    }
  }

  /**
   * Generate a lookup key for a token.
   *
   * Similar to getNodeKey but for any token.
   */
  protected function getTokenKey(Node $token): ?string {
    $parentPath = $this->getTokenParentPath($token);
    return $this->getNodeKey($token, $parentPath);
  }

  /**
   * Get parent path for a token by analyzing token positions and indentation.
   *
   * @param \AlexSkrypnyk\Yaml\Ast\Node $token
   *   The token to get parent path for.
   *
   * @return array<string>
   *   The parent path array.
   */
  protected function getTokenParentPath(Node $token): array {
    $parentPath = [];
    $currentIndent = $token->indent;

    // Find the position of the current token.
    $tokenIndex = array_search($token, $this->lineTokens, TRUE);
    if ($tokenIndex === FALSE) {
      return $parentPath;
    }

    // Look backwards from the token's position for less indented MAPPING_START
    // nodes.
    for ($i = (int) $tokenIndex - 1; $i >= 0; $i--) {
      $prevToken = $this->lineTokens[$i];

      if ($prevToken->type === NodeType::MappingStart &&
          $prevToken->indent < $currentIndent &&
          $prevToken->key !== NULL) {
        array_unshift($parentPath, $prevToken->key);
        $currentIndent = $prevToken->indent;

        // Stop when we reach root level.
        if ($currentIndent === 0) {
          break;
        }
      }
    }

    return $parentPath;
  }

  /**
   * Pre-process AST tree to remove conflicting children.
   *
   * Removes children that have corresponding LITERAL_BLOCK/FOLDED_BLOCK tokens.
   * Only removes AST children that would conflict with preserved tokens.
   *
   * @param array<Node> $astTree
   *   The AST tree to process.
   */
  protected function removeConflictingAstChildren(array &$astTree): void {
    foreach ($astTree as $node) {
      if (isset($node->children) && is_array($node->children)) {
        // Filter out ONLY KEY_VALUE children that have corresponding
        // LITERAL_BLOCK/FOLDED_BLOCK tokens.
        $filteredChildren = [];
        foreach ($node->children as $child) {
          $shouldRemove = FALSE;

          // Only consider removing KEY_VALUE children (from Symfony parsing)
          if ($child->type === NodeType::KeyValue) {
            foreach ($this->lineTokens as $lineToken) {
              if (in_array($lineToken->type, [NodeType::LiteralBlock, NodeType::FoldedBlock]) &&
                  $lineToken->key === $child->key &&
                  $lineToken->indent === $child->indent) {
                $shouldRemove = TRUE;
                break;
              }
            }
          }

          if (!$shouldRemove) {
            $filteredChildren[] = $child;
          }
        }

        $node->children = $filteredChildren;

        // Recursively process children.
        $this->removeConflictingAstChildren($node->children);
      }
    }
  }

}
