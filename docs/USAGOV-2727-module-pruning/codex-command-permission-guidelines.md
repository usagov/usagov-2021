# Codex Command Permission Guidelines

Date: 2026-05-04
Scope: `/srv/usagov-2021`

## Session instruction to provide once
Use this instruction at the start of a session:

`Run commands normally first. Only request escalation if a command actually fails due to sandbox, network, or permission restrictions.`

## Behavioral constraint for the agent
Add this explicit rule:

`Do not use require_escalated by default; only use it after a real failure that blocks required work.`

## Pre-approve common command prefixes
To reduce repeated prompts, approve reusable prefixes for common project operations:

- `["/usr/bin/zsh", "-lc", "find"]`
- `["/usr/bin/zsh", "-lc", "rg"]`
- `["/usr/bin/zsh", "-lc", "sed"]`
- `["/usr/bin/zsh", "-lc", "awk"]`
- `["/usr/bin/zsh", "-lc", "jq"]`
- `["/usr/bin/zsh", "-lc", "comm"]`
- `["/srv/usagov-2021/bin/drush"]`

## Recommended workflow
1. Try normal command execution first.
2. If a command fails because of sandbox/network/permission restrictions, rerun with escalation.
3. Keep approved prefixes focused and narrow to project-safe operations.

## Notes
- This guidance is intended to reduce unnecessary approval prompts while still keeping escalation available when truly needed.
- Drush checks should run via the CMS container wrapper script: `/srv/usagov-2021/bin/drush`.
