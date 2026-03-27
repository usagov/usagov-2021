# Cloud.gov Instruction Files

This directory contains task-specific instruction files for AI coding agents working with cloud.gov deployments.

## Available Instructions

| File | Purpose | Applies To |
|------|---------|------------|
| `deployment.instructions.md` | CF CLI deployment workflows | `manifest.yml`, deployment scripts |
| `services.instructions.md` | Service binding and configuration | Service integration code |
| `manifest.instructions.md` | Manifest file structure | `manifest*.yml` files |
| `cicd.instructions.md` | CI/CD pipeline setup | `.github/workflows/*.yml` |
| `security.instructions.md` | Security and compliance | Security-related files |
| `logging.instructions.md` | Logging and monitoring | Application logging code |

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
