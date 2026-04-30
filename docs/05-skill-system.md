# Skill System

Skills are reusable instruction modules that can be assigned to sub-agents. They are stored as Markdown files with YAML frontmatter metadata.

## Skill File Format

```markdown
---
name: Security Auditor
description: OWASP security specialist
tools:
  - StaticAnalysisTool
  - DependencyCheckTool
context_strategy: security-focused
---

# Security Auditor Skill

When reviewing code for security:

1. **Injection Attacks**
   - Verify all SQL queries use prepared statements
   - Check for XSS vulnerabilities in output
   - Validate CSRF tokens on state-changing operations

2. **Authentication & Authorization**
   - Ensure passwords are hashed (never plaintext)
   - Verify JWT tokens are validated properly
   - Check role-based access controls

3. **Data Protection**
   - Never log secrets, API keys, or passwords
   - Use HTTPS for all external communications
   - Sanitize user input before processing

4. **Common Vulnerabilities**
   - Check for file upload restrictions
   - Verify command injection prevention
   - Look for insecure deserialization
```

## Frontmatter Schema

```yaml
---
name: string                    # Unique skill name
 description: string             # Short description
tools: string[]                 # Recommended tool names (optional)
 context_strategy: string        # Preferred context strategy (optional)
categories: string[]            # Categories for organization (optional)
version: string                 # Skill version (optional)
 author: string                 # Author name (optional)
---
```

## SkillRegistry

```php
class SkillRegistry
{
    public function __construct(
        protected string $skillsPath,
        protected MarkdownSkillLoader $loader,
    ) {}
    
    /** Load all skills from the skills directory */
    public function loadAll(): void;
    
    /** Register a skill programmatically */
    public function register(Skill $skill): void;
    
    /** Get a skill by name */
    public function get(string $name): ?Skill;
    
    /** Check if skill exists */
    public function has(string $name): bool;
    
    /** Get all registered skills */
    public function all(): array;
    
    /** Get skills by category */
    public function byCategory(string $category): array;
    
    /** Find skills matching a search query */
    public function search(string $query): array;
}
```

## MarkdownSkillLoader

```php
class MarkdownSkillLoader
{
    /** Parse a skill from a .md file */
    public function load(string $filePath): Skill;
    
    /** Load all skills from a directory */
    public function loadDirectory(string $directory): array;
    
    /** Parse YAML frontmatter from markdown content */
    protected function parseFrontmatter(string $content): array;
    
    /** Extract markdown body (without frontmatter) */
    protected function extractBody(string $content): string;
}
```

## Skill Entity

```php
class Skill
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $content,           // Markdown body
        public readonly array $tools = [],
        public readonly ?string $contextStrategy = null,
        public readonly array $categories = [],
        public readonly ?string $version = null,
        public readonly ?string $author = null,
        public readonly ?string $sourceFile = null,
    ) {}
    
    /** Generate system prompt addition from this skill */
    public function toSystemPrompt(): string;
}
```

## Integration with Sub-Agent

When a sub-agent is configured with skills:

```php
'skills' => ['security', 'psr12']
```

The `SubAgentConfig` loads these skills from the registry and injects their content into the system prompt:

```php
public function buildSystemPrompt(SkillRegistry $skills): string
{
    $prompt = $this->instructions;
    
    foreach ($this->skills as $skillName) {
        $skill = $skills->get($skillName);
        if ($skill) {
            $prompt .= "\n\n## Skill: {$skill->name}\n{$skill->toSystemPrompt()}";
        }
    }
    
    return $prompt;
}
```

## Directory Structure

```
skills/
├── security.md           # Security auditing skill
├── psr12.md              # PHP coding standards skill
├── c4-model.md           # C4 architecture modeling skill
├── adr.md                # Architecture Decision Records skill
├── testing.md            # Testing best practices skill
└── performance.md        # Performance optimization skill
```

## Built-in Skills

The framework may ship with common skills:

| Skill | Description |
|-------|-------------|
| `security` | OWASP security checklist |
| `psr12` | PHP PSR-12 coding standards |
| `c4-model` | C4 architecture diagrams |
| `adr` | Architecture Decision Records |
| `testing` | Unit/integration testing practices |
| `performance` | Performance optimization guidelines |

## Creating Custom Skills

1. Create a `.md` file in the skills directory
2. Add YAML frontmatter with metadata
3. Write instructions in Markdown
4. Reference the skill name in sub-agent configuration

## Skill Inheritance

Skills can reference other skills for composition:

```yaml
---
name: Full Stack Review
description: Complete code review skill
includes:
  - security
  - psr12
  - testing
---
```

The loader will inline included skills automatically.
