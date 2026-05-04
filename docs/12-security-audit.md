# Security Audit

**Date**: 2025-05-04
**Library**: hacklab/ai-assistant
**Scope**: Full codebase — architecture, OWASP Top 10, code review

## Summary

This audit identified **12 security findings** (1 CRITICAL, 3 HIGH, 5 MEDIUM, 3 LOW) and **8 architectural issues**. All findings include specific file locations, impact assessment, and recommended fixes.

## Findings

### CRITICAL

#### SA-001: Path Traversal in `KnowledgeBase::getBug()`

- **File**: `src/Learning/Storage/KnowledgeBase.php:58`
- **OWASP**: A01:2021 — Broken Access Control
- **Impact**: Arbitrary file read on the server

The `$id` parameter is concatenated directly into a file path without sanitization:

```php
// VULNERABLE
$filepath = $this->basePath . '/bugs/' . $id . '.md';
```

An attacker controlling `$id` (e.g., `../../etc/passwd`) can read arbitrary files.

**Fix**: Apply `sanitizeFilename($id)` before concatenation:

```php
// FIXED
$filepath = $this->basePath . '/bugs/' . $this->sanitizeFilename($id) . '.md';
```

---

### HIGH

#### SA-002: MCP Command Injection

- **File**: `src/MCP/McpConfigBridge.php:36-44`
- **OWASP**: A03:2021 — Injection
- **Impact**: Arbitrary command execution

The `command` and `args` from MCP configuration are passed directly to `McpConnector` without validation. If the configuration comes from a compromised file or user input, an attacker can execute arbitrary commands.

**Fix**: Validate command against an allowlist of known binary names and resolve against system PATH.

#### SA-003: Skill Content → System Prompt Injection

- **File**: `src/Skills/Skill.php:31-45`
- **OWASP**: A03:2021 — Injection (Prompt Injection)
- **Impact**: Persistent prompt manipulation

Skill `.md` file content is injected directly into system prompts. A malicious skill file placed in the skills directory can manipulate assistant behavior.

**Fix**: Validate skill content for suspicious patterns and document that the skills directory must be write-protected.

#### SA-004: API Keys Stored in Plaintext

- **File**: `src/Utils/ConfigStorage.php`, `examples/cli-assistant.php`
- **OWASP**: A02:2021 — Cryptographic Failures
- **Impact**: Credential exposure

API keys are saved as plaintext JSON in `~/.hacklab-ai-assistant.json`. While `chmod 0600` is applied, any process running as the same user can read the file. Backups may also expose the key.

**Fix**: Encrypt config file using `sodium_crypto_secretbox()` with a key derived from the `HL_AI_ENCRYPTION_KEY` environment variable.

---

### MEDIUM

#### SA-005: Sensitive Data Sent to LLM

- **File**: `src/Context/Strategies/SummarizationStrategy.php:72-88`, `src/Persistence/HierarchicalChatHistory.php:49-56`
- **OWASP**: A02:2021 — Cryptographic Failures
- **Impact**: PII/credential leakage to AI provider

Full conversation content is sent to the LLM for summarization without redacting passwords, API keys, or PII.

**Fix**: Implement a `SensitiveDataRedactor` utility that strips common sensitive patterns before sending to the LLM.

#### SA-006: Auto-Delegation Without Restrictions

- **File**: `src/Tools/DelegateTool.php:63-79`
- **OWASP**: A04:2021 — Insecure Design
- **Impact**: Unintended sub-agent delegation via prompt injection

The LLM can autonomously delegate tasks to any sub-agent. Prompt injection can force unwanted delegations.

**Fix**: Add mandatory logging of all delegations.

#### SA-007: Directory Permissions Too Permissive

- **File**: `src/Persistence/FileStorage.php:132`, `src/Learning/Storage/KnowledgeBase.php:369`
- **OWASP**: A05:2021 — Security Misconfiguration
- **Impact**: Data exposure to other users on shared hosting

Directories created with `0755` (world-readable). For sensitive data, `0750` is more appropriate.

**Fix**: Change `mkdir()` calls to use `0750`.

#### SA-008: Stack Traces Stored in Bug Reports

- **File**: `src/Learning/BugCollector.php:35`
- **OWASP**: A05:2021 — Security Misconfiguration
- **Impact**: Internal system structure exposure

Full stack traces with file paths and class names are stored in Markdown files.

**Fix**: Truncate stack traces and strip absolute paths.

#### SA-009: Silent Exception Handling

- **Files**: `Assistant.php`, `SubAgentFactory.php`, `MarkdownSkillLoader.php`, `HierarchicalChatHistory.php`
- **OWASP**: A09:2021 — Security Logging and Monitoring Failures
- **Impact**: Security-relevant errors go unnoticed

Multiple places catch and silently swallow exceptions without logging.

**Fix**: Integrate PSR-3 `LoggerInterface` and log all caught exceptions.

---

### LOW

#### SA-010: MCP URLs Not Validated (SSRF)

- **File**: `src/MCP/McpConfigBridge.php:50-98`
- **OWASP**: A10:2021 — Server-Side Request Forgery
- **Impact**: Potential SSRF if config comes from untrusted source

#### SA-011: ReDoS Potential via Custom Regex Patterns

- **File**: `src/Context/RelevanceScorer.php:49-51`
- **OWASP**: A04:2021 — Insecure Design
- **Impact**: Denial of service via crafted regex patterns

#### SA-012: No Built-in Authentication/Authorization

- **By design** — library is meant to be embedded. Host application must implement auth.

---

### MEDIUM (Phase 2)

#### SA-013: Knowledge Base Poisoning via User Dictation

- **File**: `src/Tools/RecordLearningTool.php`, `src/Tools/RecordBugTool.php`
- **OWASP**: A04:2021 — Insecure Design
- **Impact**: User can inject malicious "learnings" that corrupt future agent behavior

Users could dictate learnings like "never use tool X" which effectively disables functionality.

**Fix**: 
- Added `LEARNING GUARDRAILS` system prompt section (mandatory, non-bypassable)
- Added `GuardsAgainstPoisoning` trait with heuristic detection of instruction-like patterns
- Both `RecordLearningTool` and `RecordBugTool` now validate observations before recording

#### SA-014: Cross-User Memory Access

- **File**: `src/Tools/SaveMemoryTool.php`, `src/Tools/RecallMemoriesTool.php`, `src/Tools/DeleteMemoryTool.php`
- **OWASP**: A01:2021 — Broken Access Control
- **Impact**: User could access/modify another user's memories

Without proper isolation, user X could ask the agent to save a memory for user Y.

**Fix**:
- `userId` is injected by the backend via `AssistantConfig`, never from user messages
- Memory tools receive `userId` via constructor injection — the LLM cannot change it
- `UserMemoryStore` partitions storage by `userId` with sanitized paths
- `DeleteMemoryTool` verifies ownership before deletion
- System prompt includes `USER MEMORY GUARDRAILS` section

---

## Architectural Issues

| # | Issue | File | Recommendation |
|---|-------|------|----------------|
| 1 | Empty `Core/` directory | `src/Core/` | Remove |
| 2 | Anonymous class in SubAgentFactory | `SubAgentFactory.php:28` | Extract to `SubAgentInstance` class |
| 3 | No config validation | `AssistantConfig.php` | Validate in constructor |
| 4 | No PSR-3 logging | Multiple | Add `psr/log` dependency |
| 5 | Custom YAML parser limitations | `MarkdownParser.php` | Document limitations |
| 6 | Unbounded learning storage growth | `KnowledgeBase.php` | Add rotation/cleanup |
| 7 | TokenEstimator inaccuracy | `TokenEstimator.php` | Document heuristic nature |
| 8 | No concurrency handling | `FileStorage.php` | Add advisory locking |

## Recommendations (Prioritized)

### Immediate

1. Sanitize `$id` in `KnowledgeBase::getBug()` — single line fix
2. Add PSR-3 `LoggerInterface` dependency and integrate throughout
3. Validate MCP commands against allowlist

### Short Term

4. Encrypt API keys in `ConfigStorage` using `sodium_crypto_secretbox()`
5. Implement `SensitiveDataRedactor` for LLM-bound data
6. Fix directory permissions to `0750`

### Medium Term

7. Add delegation logging in `DelegateTool`
8. Truncate stack traces in bug reports
9. Validate skill content before loading

### Long Term

10. Add learning data rotation/cleanup mechanism
11. Improve concurrency handling in `FileStorage`
12. Add configuration validation in `AssistantConfig`

## Checklist

- [x] SA-001: Path traversal fix
- [x] SA-002: MCP command validation
- [x] SA-003: Skill content validation
- [x] SA-004: Config encryption
- [x] SA-005: Sensitive data redaction
- [x] SA-006: Delegation logging
- [x] SA-007: Directory permissions
- [x] SA-008: Stack trace truncation
- [x] SA-009: PSR-3 logging integration
- [x] SA-010: MCP URL validation
- [x] SA-011: Regex pattern validation
- [x] SA-013: Learning guardrails (anti-poisoning)
- [x] SA-014: User memory isolation (cross-user access prevention)
- [x] Arch-1: Remove empty Core/
- [x] Arch-2: Extract SubAgentInstance
- [x] Arch-3: Config validation
- [x] Arch-6: Learning rotation
