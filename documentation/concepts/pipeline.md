# Pipeline Runtime

A pipeline is an ordered list of `PipelineStepConfigurationInterface`
instances. The step defines **what role** should run, **which
implementation** should execute it and **how** that execution should be
configured.

## Step selection

Two values determine processor resolution:

``` php
$step->getProcessorIdentifier();
$step->getType();
```

`ProcessorRegistry::get()` first finds the processor with the requested
identifier and then verifies that `supports($type)` returns `true`.

This separation allows multiple implementations for the same semantic
processor type.

### Intent selection contract

`IntentSelectionProcessor` accepts independently configured output areas:

```json
{
  "retrieval": {"mode": "all", "targets": []},
  "presentationRules": {"mode": "all", "rules": []},
  "retrieverPayload": {"values": []},
  "metadata": {}
}
```

The `retrieval` and `presentationRules` areas support `all`, `selected`,
`all_except` and `none`. `retrieverPayload` contains only `values`; its field
path, target retrievers and `and`/`or` conjunction are supplied by pipeline
configuration. The model supplies values, not filter logic.

Missing or invalid areas retain the configured default. In an empty legacy
configuration, retrieval and presentation-rule selection are enabled and
payload filtering is disabled. The intent context lists the available target
and rule identifiers and, when payload filtering is enabled, the configured
payload field path.

## Processor types

`AssistantPipelineProcessorType` currently defines:

| Type                | Typical responsibility                           |
|---------------------|--------------------------------------------------|
| `query_optimizer`   | Rewrite/normalize the working query              |
| `retriever`         | Retrieve source documents                        |
| `intent_selector`   | Select retrieval targets and presentation rules  |
| `context_optimizer` | Condense retrieval results for answer generation |
| `answer_generator`  | Create the answer candidate                      |
| `quality_gate`      | Review/finalize an answer candidate              |
| `memory`            | Explicitly read/write pipeline state from memory |

`AssistantPipelineStage` adds semantic placement (`pre_retrieval`,
`retrieval`, `post_retrieval`, `pre_answer`, `post_answer`). Execution
order, however, is the order of the provided step iterable; stage is
metadata/semantics rather than an internal scheduler.

## Step execution algorithm

```
flowchart TD
    S[Next configured step] --> R[Resolve processor]
    R --> C{canProcess?}
    C -- no --> Skip[log step.skipped]
    C -- yes --> Exec[process/processStream]
    Exec --> Ok{success?}
    Ok -- yes --> S2[Next step]
    Ok -- no --> F{failure strategy = stop?}
    F -- yes --> Throw[throw exception]
    F -- no --> S2
```

If `canProcess()` returns false, no exception is raised; the step is
skipped and optionally logged.

## Failure strategies

`AssistantPipelineFailureStrategy` contains:

- `Stop` - rethrow step failures and abort the pipeline;
- `Continue` - log the failure and continue;
- `Fallback` - deprecated legacy alias; no separate fallback action is
  executed by `Pipeline`.

### Streaming exception rule

There is one important override: when the currently streamed visible
answer step has already emitted data and then fails, the exception is
rethrown regardless of `Continue`. This prevents the runtime from
silently continuing after a partial response has already reached the
client.

## Streaming semantics

Streaming is deliberately limited to the **last configured step whose
type is either**:

- `AnswerGenerator`, or
- `QualityGate`.

Only when that processor implements `ProcessorStreamingInterface` does
the pipeline call `processStream()`.

All earlier processors run synchronously. If no chunk was emitted, the
final built response is sent through the callback once after execution.

This allows a pipeline such as:

```
QueryOptimizer -> Retriever -> AnswerGenerator -> QualityGate
```

to stream the `QualityGate` result, while `AnswerGenerator` runs
synchronously as the candidate producer.

Without a quality gate, the answer generator becomes the visible
streaming step.

## Source metadata

Whenever a step has a non-empty `getPromptMetadataFieldList()`, the
pipeline remembers that list. The last non-empty list is used when
converting retrieved documents into frontend `sources` in
`AssistantResponse`.

Sources are deduplicated primarily by `source_identifier`, then `url`,
then a hash of the source payload.

## Response construction

The final response contains:

```
answer
context.currentQuery
context.answerContext
context.retrievalCount
context.retrievals[]
context.sources[]
```

The pipeline response intentionally represents a presentation-friendly
summary; the full mutable `Context` remains internal to the pipeline
execution.
