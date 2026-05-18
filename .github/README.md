# Cloud.gov Instruction Files

This directory contains task-specific instruction files for AI coding agents working with cloud.gov deployments.

## Available Instructions

| File | Purpose | Applies To |
|------|---------|------------|
| `deployment.instructions.md` | CF CLI deployment workflows | `manifest*.yml`, `.cfignore`, `.profile`, `Procfile`, and deploy/bootstrap `*.sh` |
| `services.instructions.md` | Service binding and configuration | Drupal/PHP files, JS/TS files, Cloud.gov shell scripts, and `manifest*.yml` |
| `manifest.instructions.md` | Manifest file structure | `manifest*.yml` and `vars*.yml` files |
| `cicd.instructions.md` | CI/CD pipeline setup | `.github/workflows/*.yml` and `.github/workflows/*.yaml` |
| `security.instructions.md` | Security and compliance | Drupal/PHP files, JS/TS files, Cloud.gov shell scripts, manifests, and workflow YAML |
| `logging.instructions.md` | Logging and monitoring | Drupal/PHP files, JS/TS files, Cloud.gov shell scripts, and `manifest*.yml` |

## File Format

Each instruction file follows this structure:

```markdown
---
applyTo: [glob pattern for relevant files]
---

# [Topic] Instructions

## Overview
[Brief description]

## Prerequisites
[Required setup]

## Steps
[Detailed guidance]

## Examples
[Code samples]

## Troubleshooting
[Common issues]

## References
[Documentation links]
```

## Usage

These files are automatically used by GitHub Copilot when working with matching file patterns. The `applyTo` frontmatter determines which files trigger each instruction set.
