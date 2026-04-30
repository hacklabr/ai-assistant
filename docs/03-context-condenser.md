# Context Condenser

The Context Condenser is responsible for reducing conversation history before delegation to sub-agents, ensuring only relevant information is passed while respecting token limits.

## Interface

```php
namespace HackLab\AIAssistant\Context;

interface ContextCondenserInterface
{
    /**
     * Condense messages for a specific task/target.
     *
     * @param Message[] $messages Full conversation history
     * @param string $taskDescription Description of the task being delegated
     * @param int $maxTokens Maximum tokens allowed for the sub-agent
     * @param string|null $contextStrategy Strategy name (e.g., 'code-focused')
     * @return CondensedContext Condensed context with metadata
     */
    public function condense(
        array $messages,
        string $taskDescription,
        int $maxTokens,
        ?string $contextStrategy = null
    ): CondensedContext;
}
```

## Strategies

### 1. TruncationStrategy

Simple token-based cutting. Removes oldest messages until under token limit.

```php
$condenser = new TruncationStrategy(
    tokenEstimator: new TokenEstimator()
);
```

- **Fastest**: No LLM calls
- **Dumb**: No semantic understanding
- **Use case**: Emergency fallback when token limit is exceeded

### 2. SummarizationStrategy

Uses an LLM (via Neuron) to summarize old messages when token limit is approached.

```php
$condenser = new SummarizationStrategy(
    provider: $summarizationProvider, // Can be a smaller/cheaper model
    maxTokens: 10000,
    messagesToKeep: 5, // Keep last N messages verbatim
    systemPrompt: 'Summarize the key information...'
);
```

- **Smart**: LLM understands what's important
- **Slow**: Requires extra LLM call
- **Use case**: Long conversations where older context matters

### 3. RelevanceStrategy (MVP)

Extracts messages containing keywords/patterns relevant to the task. Pure PHP, zero dependencies.

```php
$condenser = new RelevanceStrategy(
    scorer: new RelevanceScorer([
        'code-focused' => [
            'keywords' => ['function', 'class', 'method', 'bug', 'error', 'fix', 'refactor'],
            'patterns' => ['/```[a-z]*/', '/namespace\s+/'],
        ],
        'security-focused' => [
            'keywords' => ['sql injection', 'xss', 'csrf', 'vulnerability', 'exploit'],
            'patterns' => ['/password\s*=/', '/secret\s*=/'],
        ],
    ])
);
```

- **Fast**: Pure PHP regex/keyword matching
- **Targeted**: Keeps only task-relevant messages
- **Use case**: Delegation to specialized sub-agents

### 4. HierarchicalStrategy (Default)

Maintains three levels of context:

1. **Summary**: Condensed summary of older conversation (via SummarizationStrategy)
2. **Recent**: Last N messages kept verbatim
3. **Key Facts**: Extracted important facts (decisions, user preferences, etc.)

```php
$condenser = new HierarchicalStrategy(
    summarizer: new SummarizationStrategy($provider, 8000, 3),
    recentMessages: 5,
    factExtractor: new FactExtractor($provider),
    maxTokens: 200000
);
```

- **Comprehensive**: Best of both worlds
- **Default**: Recommended for most use cases
- **Use case**: Complex multi-turn conversations

## Context Strategies

Strategies can be configured per sub-agent:

```php
'subAgents' => [
    'code-reviewer' => [
        'contextStrategy' => 'code-focused',
    ],
    'architect' => [
        'contextStrategy' => 'architecture-focused',
    ],
]
```

The `RelevanceScorer` uses these strategy names to apply appropriate keyword/pattern matching.

## Integration with Neuron Middleware

The condenser is integrated as Neuron middleware on `ChatNode`:

```php
class ContextCondensationMiddleware implements WorkflowMiddleware
{
    public function before(NodeInterface $node, Event $event, WorkflowState $state): void
    {
        if (!$event instanceof AIInferenceEvent) {
            return;
        }
        
        // Condensation happens here before LLM call
        // Only triggered when token count exceeds threshold
    }
    
    public function after(NodeInterface $node, Event $result, WorkflowState $state): void
    {
        // Post-processing if needed
    }
}
```

## CondensedContext Object

```php
class CondensedContext
{
    public function __construct(
        public readonly array $messages,     // Condensed message array
        public readonly ?string $summary,     // Optional summary text
        public readonly array $keyFacts,      // Extracted key facts
        public readonly int $originalTokens,  // Token count before condensation
        public readonly int $condensedTokens, // Token count after condensation
        public readonly string $strategy,     // Strategy used
    ) {}
    
    /** Convert back to Neuron Messages */
    public function toMessages(): array;
}
```
