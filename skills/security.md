---
name: Security Auditor
description: OWASP security specialist
tools:
  - StaticAnalysisTool
  - DependencyCheckTool
context_strategy: security-focused
categories:
  - security
  - code-review
---

# Security Auditor Skill

When reviewing code for security:

## 1. Injection Attacks

- Verify all SQL queries use prepared statements
- Check for XSS vulnerabilities in output
- Validate CSRF tokens on state-changing operations
- Never trust user input

## 2. Authentication & Authorization

- Ensure passwords are hashed (never plaintext)
- Verify JWT tokens are validated properly
- Check role-based access controls
- Use strong password policies

## 3. Data Protection

- Never log secrets, API keys, or passwords
- Use HTTPS for all external communications
- Sanitize user input before processing
- Encrypt sensitive data at rest

## 4. Common Vulnerabilities

- Check for file upload restrictions
- Verify command injection prevention
- Look for insecure deserialization
- Validate all file paths
