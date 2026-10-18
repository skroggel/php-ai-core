# Prompt Construction

LLM-based processors use `PromptBuilder` rather than creating prompts
directly.

## Message shape

Each LLM step receives two messages:

```
system
  assistant-level rules
  step-level rules
  runtime response requirements

user
  optional chat history
  dynamic prompt context sections
```

`PromptBuilder::buildMessages()` returns plain arrays, which
`AbstractLlmProcessor` converts into `AiMessage` DTOs before invoking an
AI connector.

## System prompt composition

The system prompt may include assistant-level sections depending on step
configuration:

- `===== BEGIN Assistant Identity =====` / matching `END`
- `===== BEGIN Assistant Behavior Rules =====` / matching `END`
- `===== BEGIN Assistant Retrieval Rules =====` / matching `END`
- `===== BEGIN Assistant Output Rules =====` / matching `END`

It can also include step-specific sections:

- `===== BEGIN Step Identity =====` / matching `END`
- `===== BEGIN Step Behavior Rules =====` / matching `END`
- `===== BEGIN Step Retrieval Rules =====` / matching `END`
- `===== BEGIN Step Output Rules =====` / matching `END`

For `AnswerGenerator` and `QualityGate`, runtime chat options can add
explicit response-language and plain-language instructions.

A final precedence instruction states that runtime
response/accessibility requirements win over assistant/step output
preferences; assistant rules otherwise take precedence over step rules.

## History

History is controlled per pipeline step through `HistoryMode`.

`None` adds no history. `LastN` uses `History::last(getHistoryLimit())`
and serializes messages as:

```
===== BEGIN Chat History =====
user: ...
assistant: ...
===== END Chat History =====
```

## Dynamic context builders

The user-side context is extensible through `ContextBuilderInterface`.

```
flowchart LR
    Context --> Registry[ContextBuilderRegistry]
    Step --> Registry
    Registry --> Builders[matching ContextBuilderInterface instances]
    Builders --> Sections[PromptSection[]]
    Sections --> Sort[sort by priority]
    Sort --> Formatter
    Formatter --> UserPrompt[formatted context]
```

Built-in builders include:

- `QueryContextBuilder` - original/current query;
- `RetrievalContextBuilder` - retrieved/answer context;
- `AnswerCandidateContextBuilder` - answer candidate for quality-gate
  steps.

A custom builder can add new context without modifying `PromptBuilder`.

`Formatter` emits every `PromptSection` with plain-text boundaries. Nested
entries use five-dash boundaries, for example:

```
----- BEGIN PRESENTATION RULE: answer_topic -----
Instructions...
----- END PRESENTATION RULE: answer_topic -----
```

The same format is used for UI component definitions and retrieval groups.
No HTML or XML marker syntax is used for prompt boundaries.

## Retrieval attribution

When `ContextOptimizerProcessor` runs with retrieval groups, it adds a
system instruction requiring retrieval identifiers and attribution
boundaries to be preserved.

`RetrievalContextBuilder` constructs grouped retrieval sections and
applies per-group/global context limits. When multiple retrieval groups
exist, its global character limiter distributes space across groups
instead of simply truncating everything after the first groups.

## Design guidance

Use assistant-level rules for stable behavior shared across many steps.
Use step-level rules for the narrow responsibility of one processor. Use
context builders for structured runtime data; avoid embedding dynamic
application state directly into static assistant prompts.
