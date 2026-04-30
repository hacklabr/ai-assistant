# Skills

This directory contains skill definitions for the AI Assistant.

Skills are Markdown files with YAML frontmatter that define reusable instruction modules for sub-agents.

## Format

```markdown
---
name: Skill Name
description: Brief description
tools:
  - ToolName
context_strategy: strategy-name
categories:
  - category-name
---

# Skill Instructions

Detailed instructions in Markdown format...
```

## Available Skills

- `security.md` - Security auditing skill (OWASP)
- `psr12.md` - PHP PSR-12 coding standards

## Creating New Skills

1. Create a `.md` file in this directory
2. Add YAML frontmatter with metadata
3. Write instructions in Markdown
4. Reference the skill name in sub-agent configuration
