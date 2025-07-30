<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Parser;

use AlexSkrypnyk\Yaml\Ast\AstTree;
use AlexSkrypnyk\Yaml\Ast\Node;
use AlexSkrypnyk\Yaml\Ast\NodeType;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Parser class handles YAML content parsing and AST tree building.
 */
class Parser {

  /**
   * Parse YAML content and build AST tree.
   *
   * @param string $content
   *   The YAML content to parse.
   *
   * @return \AlexSkrypnyk\Yaml\Ast\AstTree
   *   The parsed AST tree.
   */
  public function parse(string $content): AstTree {
    // Tokenize the content.
    $lexer = new Lexer();
    $tokens = $lexer->tokenize($content);

    // Map comments to tokens.
    $mapped_tokens = $this->mapComments($tokens);

    // Parse the YAML into PHP data.
    $data = SymfonyYaml::parse($content);

    // Build AST from parsed data.
    $enhanced_tokens = [];
    if (is_array($data)) {
      $ast = $this->dataToAst($data);

      // Align tokens with AST.
      $matcher = new LineMatcher();
      $enhanced_tokens = $matcher->align($mapped_tokens, $ast);
    }
    else {
      $enhanced_tokens = $mapped_tokens;
    }

    // Initialize and return AST tree with enhanced tokens.
    return new AstTree($enhanced_tokens);
  }

  /**
   * Maps block comments to structural nodes.
   *
   * Maps comments to KeyValue, MappingStart, and SequenceItem nodes.
   * Floating comments remain as standalone Comment nodes.
   *
   * @param \AlexSkrypnyk\Yaml\Ast\Node[] $tokens
   *   Array of tokens to process.
   *
   * @return \AlexSkrypnyk\Yaml\Ast\Node[]
   *   Array of tokens with comments mapped.
   */
  protected function mapComments(array $tokens): array {
    $result = [];
    $pending_comments = [];
    $has_blank_line_since_last_comment = FALSE;

    foreach ($tokens as $node) {
      if ($node->type === NodeType::Comment) {
        $pending_comments[] = $node->rawLine;
        $has_blank_line_since_last_comment = FALSE;
        continue;
      }

      if ($node->type === NodeType::BlankLine) {
        // If we have pending comments and encounter a blank line,
        // flush them as floating comments.
        if (!empty($pending_comments)) {
          foreach ($pending_comments as $comment_line) {
            $result[] = new Node(NodeType::Comment, [
              'rawLine' => $comment_line,
            ]);
          }
          $pending_comments = [];
        }

        $result[] = $node;
        $has_blank_line_since_last_comment = TRUE;
        continue;
      }

      if (in_array($node->type, [
        NodeType::KeyValue,
        NodeType::MappingStart,
        NodeType::SequenceItem,
        NodeType::LiteralBlock,
        NodeType::FoldedBlock,
      ])) {
        // Only attach comments if there's no blank line separation.
        if (!empty($pending_comments) && !$has_blank_line_since_last_comment) {
          $node->attachedComment = implode(PHP_EOL, $pending_comments);
          $pending_comments = [];
        }
        // If there was a blank line, treat pending comments as floating.
        elseif (!empty($pending_comments) && $has_blank_line_since_last_comment) {
          foreach ($pending_comments as $comment_line) {
            $result[] = new Node(NodeType::Comment, [
              'rawLine' => $comment_line,
            ]);
          }
          $pending_comments = [];
        }

        $result[] = $node;
        $has_blank_line_since_last_comment = FALSE;
      }
      else {
        // For safety, preserve unknowns.
        $result[] = $node;
        $has_blank_line_since_last_comment = FALSE;
      }
    }

    // If any pending comments remain and weren't attached, treat them as
    // floating Comment nodes.
    foreach ($pending_comments as $comment_line) {
      $result[] = new Node(NodeType::Comment, [
        'rawLine' => $comment_line,
      ]);
    }

    return $result;
  }

  /**
   * Build AST nodes from Symfony's parsed data structure.
   *
   * @param array<mixed> $parsed
   *   The parsed data from Symfony YAML.
   * @param int $indent
   *   Current indentation level.
   *
   * @return array<Node>
   *   Array of AST nodes.
   */
  protected function dataToAst(array $parsed, int $indent = 0): array {
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
          $children = $this->dataToAst($value, $indent + 2);
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

}
