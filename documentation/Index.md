# AI Core - Developer Documentation

`madj2k/ai-core` provides the reusable runtime building blocks for
configurable AI assistants and vector-based retrieval/indexing
workflows.

The library is deliberately split into small contracts: assistant
orchestration, pipeline processors, prompt context builders, AI
connectors, vector-store connectors, memory and indexing components can
be replaced or extended independently.

## Where to start

If you are new to the library, read the documentation in this order:

1.  [Architecture](concepts/architecture.md) - components and
    responsibilities.
2.  [Assistant request lifecycle](workflows/assistant-request.md) -
    what happens during one chat turn.
3.  [Pipeline runtime](concepts/pipeline.md) - step resolution,
    skipping, failures and streaming.
4.  [Context and state](concepts/context.md) - how processors exchange
    data.
5.  [Prompt construction](concepts/prompting.md) - system prompt,
    history and dynamic context.
6.  [Retrieval](concepts/retrieval.md) - embeddings, vector search and
     retrieval groups.
7.  [UI components](concepts/ui-components.md) - declarative streamed answer
     components and configured interactions.
8.  [Vector filters](concepts/vector-filters.md) - generic payload filters and
     connector mapping.
9.  [Retrieval selection](concepts/retrieval-selection.md) - model-controlled
     retrieval routing and metadata.
10. [Configuration exports](concepts/exports.md) - AI-readable pipeline
     configuration documents.
11. [Indexing](workflows/indexing.md) - chunking, embeddings,
     generations and vector writes.
12. [Extension points](extending/overview.md) - where custom
    implementations plug in.

## Architectural principles

The codebase follows a few important principles:

- **Configuration defines behavior; services execute it.** Assistant and
  pipeline configuration are represented through interfaces instead of
  being hard-coded into the runtime.
- **Processors communicate through \`\`Context\`\`.** A processor reads
  input state and writes its result back to the shared runtime context.
- **Semantic step type and implementation identifier are separate.** A
  pipeline step has an `AssistantPipelineProcessorType`, but the
  concrete implementation is selected through
  `getProcessorIdentifier()`.
- **Provider code is behind connector contracts.** Chat/embedding
  providers implement `AiConnectorInterface`; vector databases implement
  `VectorStoreConnectorInterface`.
- **Retrieval memory is explicit pipeline behavior.** The orchestrator
  does not automatically persist retrieval results.
- **Index replacement is generation based.** New vectors are written
  before obsolete generations are deleted.

## Main packages

| Package | Responsibility |
|----|----|
| `Assistant/Application` | Assistant entry points and orchestration |
| `Assistant/Context` | Runtime state shared between steps |
| `Assistant/Pipeline` | Step execution, validation and processor lookup |
| `Assistant/Prompt` | Prompt assembly and extensible prompt context |
| `Assistant/Memory` | Conversation and retrieval-state persistence |
| `Connection/Ai` | LLM and embedding provider abstraction |
| `Connection/VectorStore` | Vector database abstraction |
| `Connection/Resilience` | Retry and provider error handling infrastructure |
| `Indexing` | Source normalization, chunking, embeddings and vector writes |

## Documentation conventions

The developer guide focuses on **runtime contracts and control flow**,
not just class signatures. The [API reference](reference/api.md)
complements this with a compact class/interface inventory.

<div class="toctree" hidden="">

concepts/architecture concepts/context concepts/pipeline
concepts/prompting concepts/retrieval workflows/assistant-request
workflows/indexing extending/overview operations/observability
cookbook/standard-rag-pipeline reference/api Architecture Pipeline
Connections ToolCalling ExtensionPoints

</div>
