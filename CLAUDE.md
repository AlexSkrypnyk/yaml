# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP library that provides a drop-in replacement for Symfony's Yaml component with comment preservation functionality. The library wraps Symfony's YAML parser and adds the ability to maintain comments when parsing and dumping YAML files.

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

- **`src/Yaml.php`**: Main class that wraps Symfony's Yaml component
  - Provides static methods: `parse()`, `parseFile()`, and `dump()`
  - Maintains original YAML content in `$content` property for comment preservation
  - Uses `Consolidation\Comments` library to extract and inject comments
  - Includes post-processing methods: `unquoteStrings()` and `deduplicateLines()`

### Key Dependencies
- **Symfony YAML**: Core YAML parsing/dumping functionality
- **Consolidation Comments**: Comment extraction and injection
- **PHP 8.2+**: Required minimum PHP version

### Testing Structure
- **`tests/YamlTest.php`**: Main test class with data provider for fixture-based testing
- **`tests/fixtures/`**: Contains before/after YAML fixtures for testing comment preservation
  - `idempotence/`: Tests that parsing and dumping doesn't change content
  - `update/`: Tests that comments are preserved when modifying YAML data

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