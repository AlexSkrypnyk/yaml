# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP library that provides a drop-in replacement for Symfony's Yaml component with comment preservation functionality. The library uses an AST (Abstract Syntax Tree) approach to maintain comments and formatting when parsing and dumping YAML files.

## Development Commands

### Testing
- **Run tests**: `composer test` (PHPUnit without coverage)
- **Run tests with coverage**: `composer test-coverage` (PHPUnit with coverage reporting)

### Code Quality
- **Lint code**: `composer lint` (runs phpcs, phpstan, and rector dry-run)
- **Fix code issues**: `composer lint-fix` (runs rector and phpcbf)
- **Reset dependencies**: `composer reset` (removes vendor, vendor-bin, and composer.lock)

### Individual Tools
- **PHP CodeSniffer**: `phpcs` (check coding standards)
- **PHP CodeSniffer fix**: `phpcbf` (auto-fix coding standards)
- **PHPStan**: `phpstan` (static analysis at level 9)
- **Rector**: `rector --clear-cache --dry-run` (check for code improvements)
- **Rector fix**: `rector --clear-cache` (apply code improvements)

## Architecture

### Core Components

- **`src/Yaml.php`**: Main class that implements YamlInterface for YAML manipulation
  - Provides both static methods (Symfony compatibility) and instance-based API
  - Uses AST tree for all YAML manipulation operations
  - Maintains original YAML content for unescaping during dump operations
  - Stores only essential properties: `$content` (original) and `$tree` (AST)

- **`src/Parser/Parser.php`**: Handles YAML content parsing and AST tree building
  - Encapsulates all parsing complexity (tokenization, comment mapping, AST alignment)
  - Returns a single AstTree instance as the source of truth
  - Uses Lexer, CommentMapper, Editor, and ReverseLineAligner internally

- **`src/Ast/AstTree.php`**: AST tree class that handles all tree manipulation operations
  - Provides methods: `getValue()`, `setValue()`, `addKey()`, `deleteKey()`, `has()`
  - Manages comment operations: `setComment()`, `getComment()`
  - Serves as the single source of truth for all YAML data and structure

### AST-Based Processing Pipeline

1. **`src/Parser/Lexer.php`**: Tokenizes YAML content line-by-line into Node objects
2. **`src/Parser/CommentMapper.php`**: Maps block comments to structural nodes, preserves floating comments
3. **`src/Parser/Editor.php`**: Builds AST from Symfony's parsed data structure via `dataToAst()`
4. **`src/Parser/ReverseLineAligner.php`**: Aligns AST nodes with tokenized lines to preserve formatting/comments
5. **`src/Dumper/Dumper.php`**: Outputs final YAML from AST with preserved comments and formatting

### AST Node System

- **`src/Ast/Node.php`**: Core AST node with properties:
  - `type`: Node type (enum value)
  - `key`: YAML key name
  - `value`: Node value
  - `indent`: Indentation level (renamed from indentLevel)
  - `children`: Array of child nodes
  - `attachedComment`: Comments attached to this node
  - `rawLine`: Original line content
  - `modified`, `deleted`, `inserted`: Modification tracking flags

- **`src/Ast/NodeType.php`**: Enum defining node types (PascalCase):
  - `KeyValue`: Simple key-value pairs
  - `MappingStart`: Start of a mapping structure
  - `SequenceItem`: Items in YAML sequences
  - `Comment`: Comment lines
  - `BlankLine`: Empty lines
  - `LiteralBlock`: YAML literal blocks (|)
  - `FoldedBlock`: YAML folded blocks (>)

### Key Dependencies
- **Symfony YAML**: Core YAML parsing/dumping functionality
- **PHP 8.2+**: Required minimum PHP version

### Testing Structure
- **`tests/YamlTest.php`**: Main test class with data provider for fixture-based testing
- **`tests/fixtures/`**: Contains before/after YAML fixtures for testing comment preservation
  - `idempotence/`: Tests that parsing and dumping doesn't change content
  - `update/`: Tests that comments are preserved when modifying YAML data
  - `comments-*`: Various comment preservation scenarios
  - `collapse-empty-lines-*`: Empty line handling tests
- **`tests/Parser/`**: Unit tests for individual Parser components (Lexer, AstTree, etc.)
- **`tests/Ast/`**: Unit tests for AST components

## Coding Standards

### Variable Naming Conventions
- **Local variables and function arguments**: Use `snake_case` (e.g., `$yaml_content`, `$node_type`)
- **Class properties and methods**: Use `camelCase` (e.g., `$this->tree`, `$this->getValue()`)

## Configuration Files

- **`phpunit.xml`**: PHPUnit configuration with coverage reporting to `.logs/` directory
- **`phpcs.xml`**: Uses Drupal coding standards with strict types requirement
- **`phpstan.neon`**: Static analysis at level 9, excludes vendor and node_modules
- **`rector.php`**: Modern PHP refactoring rules for PHP 8.2/8.3 with PHPUnit 10.0 support

## Test Fixtures

The test suite uses a fixture-based approach where YAML files in `tests/fixtures/` contain:
- `before.yml`: Original YAML content with comments
- `after.yml`: Expected output after parsing and dumping

Test data providers in `YamlTest::dataProviderUpdate()` can include optional callable manipulators to modify the parsed data before dumping.

## Key Features

- **Comment Preservation**: Comments are tokenized, mapped to structural elements, and reattached during dumping
- **Ungreedy Quoting**: Custom value formatting that reduces unnecessary quotes (controllable via `DUMP_STRICT_SINGLE_QUOTING`)
- **Modification Tracking**: Nodes have flags for tracking changes during processing
- **Format Preservation**: Original indentation and structure maintained through `rawLine` storage

## Test Coverage Strategy

The project aims for comprehensive test coverage through systematic testing practices:

### Coverage Process
- **Coverage Analysis**: Use `composer test-coverage` to generate coverage reports in `.logs/` directory
- **Coverage Reports**: Review Cobertura XML and HTML reports to identify uncovered code paths
- **Iterative Improvement**: Add tests for uncovered lines, focusing on edge cases and error conditions

### Coverage Best Practices
- **Exception Testing**: Test all exception paths, including file operations and invalid input scenarios
- **Edge Case Coverage**: Test boundary conditions like different line endings, empty inputs, and null handling
- **Error Suppression**: Use `@` operator in tests when intentionally triggering PHP warnings for exception testing
- **Defensive Code**: Mark unreachable error handling code with `@codeCoverageIgnore` annotations
- **Data Providers**: Use comprehensive data providers to test multiple scenarios efficiently

### Coverage Strategies
- **Method Isolation**: Test each public method independently and in combination
- **State Testing**: Test methods with different internal states (e.g., with/without prior setup)
- **Input Variation**: Test with various input types, formats, and edge cases
- **Error Conditions**: Ensure all error paths and defensive code are exercised
