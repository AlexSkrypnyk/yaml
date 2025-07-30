<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="logo.png" alt="Yaml logo"></a>
</p>

<h1 align="center">Drop-in replacement for Symfony's Yaml component with comments support.</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/alexskrypnyk/yaml.svg)](https://github.com/alexskrypnyk/yaml/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/alexskrypnyk/yaml.svg)](https://github.com/alexskrypnyk/yaml/pulls)
[![Test PHP](https://github.com/alexskrypnyk/yaml/actions/workflows/test-php.yml/badge.svg)](https://github.com/alexskrypnyk/yaml/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/alexskrypnyk/yaml/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/alexskrypnyk/yaml)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/alexskrypnyk/yaml)
![LICENSE](https://img.shields.io/github/license/alexskrypnyk/yaml)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

## Why

Symfony's Yaml component is a powerful tool for parsing and dumping YAML files,
but it does not preserve comments when modifying YAML content. This can be
problematic when you want to maintain the original context and documentation
within your YAML files.

[`consolidation/comments`](https://github.com/consolidation/comments) helps
to preserve comments when parsing and dumping YAML files, but it does not
provide a drop-in replacement for Symfony's Yaml component. It also has some
issues with duplicated comments.

This package makes a best-effort attempt to address these issues, though it may
not fully resolve all cases. We use [before/after fixtures](tests/fixtures) in
tests to address specific scenarios.

## Features

- **Drop-in replacement**: Compatible with Symfony's Yaml component API
- **Comment preservation**: Maintains YAML comments when parsing and dumping
- **Post-processing**: Automatically unquotes unnecessary strings and removes duplicate lines

## Installation

    composer require alexskrypnyk/yaml

## Usage

```php
use AlexSkrypnyk\Yaml\Yaml;

// Parse YAML string.
$data = Yaml::parse($yamlString);

// Parse and modify.
$data = Yaml::parseFile('config.yml');

$data['config']['database']['host'] = 'newhost';

// Dump PHP array to YAML with preserved comments.
$output = Yaml::dump($data, 4, 2);
```

## Maintenance

    composer install
    composer lint
    composer test

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
