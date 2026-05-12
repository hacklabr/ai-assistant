# Architecture Overview

## HackLab AI Assistant

An embeddable AI assistant framework built on top of Neuron AI, providing advanced context management, sub-agent orchestration, skill configuration, auto-learning, and MCP integration.

## Package Information

- **Package**: `hacklab/ai-assistant`
- **Namespace**: `HackLab\AIAssistant`
- **PHP**: `^8.2`
- **Base Framework**: [Neuron AI](https://neuron-ai.dev/) (sole dependency)

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    Consumer Application                      │
│              (Laravel, Symfony, custom PHP)                  │
├─────────────────────────────────────────────────────────────┤
│  HackLab\AIAssistant                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │  Assistant   │  │  Context     │  │   Sub-Agent      │
│  │  (extends    │  │  Condenser   │  │   Orchestrator   │
│  │   Neuron)    │  │              │  │                  │
│  └──────────────┘  └──────────────┘  └──────────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │   Skills     │  │   Auto-      │  │   MCP Bridge     │
│  │   (.md)      │  │   Learning   │  │   (wrapper)      │
│  └──────────────┘  └──────────────┘  └──────────────────┘  │
├─────────────────────────────────────────────────────────────┤
│              Neuron AI Framework                             │
│     Agent, Workflow, Tools, RAG, ChatHistory, MCP           │
├─────────────────────────────────────────────────────────────┤
│              PHP 8.2+                                        │
└─────────────────────────────────────────────────────────────┘
```

## Core Design Principles

1. **Zero Additional Dependencies**: Beyond Neuron AI, no external packages. Pure PHP implementations for all features. (Exception: `smalot/pdfparser` and `phpoffice/phpword` for document reading — both pure PHP with no server-side requirements.)

2. **Neuron Native Integration**: All MCP transports (stdio, SSE, HTTP), middleware, tools, and workflows are Neuron-native. We only add abstraction layers for easier configuration.

3. **Context-Aware Delegation**: When delegating to sub-agents, the framework condenses context intelligently based on the target agent's specialization.

4. **Declarative Configuration**: Skills, sub-agents, and MCPs are configured via arrays, JSON, or Markdown files with YAML frontmatter.

5. **Embeddable by Design**: The library is designed to be embedded into existing PHP applications without requiring framework-specific code.

## What Neuron Provides (We Reuse)

| Component | Neuron Class | Usage |
|-----------|--------------|-------|
| Agent Base | `NeuronAI\Agent\Agent` | Extended by `Assistant` |
| Workflow | `NeuronAI\Workflow\Workflow` | Used for orchestration |
| Tools | `ToolInterface`, `AbstractToolkit` | Used directly |
| MCP stdio | `StdioTransport` | Configured via wrapper |
| MCP SSE | `SseHttpTransport` | Configured via wrapper |
| MCP HTTP | `StreamableHttpTransport` | Configured via wrapper |
| Chat History | `AbstractChatHistory` | Extended for hierarchical |
| Middleware | `WorkflowMiddleware` | Extended for condensation |
| Providers | `AIProviderInterface` | Used via configuration |
| Persistence | `PersistenceInterface` | Used for workflow state |
| Observability | `InspectorObserver`, `EventBus` | Integrated |

## What We Build

| Component | Purpose |
|-----------|---------|
| `Assistant` | Main facade extending Neuron Agent |
| `ContextCondenser` | 4 strategies for context reduction |
| `SubAgentRegistry` | Registry of sub-agent configurations |
| `SubAgentDispatcher` | Delegation with context condensation |
| `SkillRegistry` | Manages skill definitions from .md files |
| `McpConfigBridge` | Simplifies MCP configuration in sub-agents |
| `AutoLearningEngine` | Tool learning + bug collection |
| `HierarchicalChatHistory` | Multi-level memory (summary + recent + facts) |
| `FileStorage` | Default persistence in .md + .json |
| `FileReaderTool` | Document reading (PDF, DOCX, TXT, CSV, etc.) |
