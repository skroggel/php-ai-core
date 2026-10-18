# Configuration Exports

`PipelineMarkdownExporter` creates an AI-readable Markdown document from an
`AssistantConfigurationInterface`.

The export contains global prompts, every pipeline step, processor types and
stages, include settings, step prompts, runtime settings, retrieval identifiers,
filters, selector contracts and effective UI component definitions.

Provider credentials, endpoints, API keys and other secrets are never included.
