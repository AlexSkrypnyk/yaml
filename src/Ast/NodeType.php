<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Yaml\Ast;

/**
 * Enumeration of AST node types.
 */
enum NodeType {

  case KeyValue;
  case MappingStart;
  case SequenceItem;
  case Comment;
  case BlankLine;
  case LiteralBlock;
  case FoldedBlock;

}
