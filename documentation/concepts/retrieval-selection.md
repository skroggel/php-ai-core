# Retrieval Selection

The optional `retrieval_selector` pipeline step lets the model choose which
configured retrieval targets should run. Without a selector result, the default
`RetrievalPlan` is `all`, so existing pipelines execute every retriever.

Every retriever needs a stable `retrievalIdentifier`. The selector receives the
available targets and the original/current query as prompt context.

The fixed output contract is:

```json
{
  "mode": "selected",
  "targets": ["products"],
  "metadata": {
    "primary_intent": "product_information"
  }
}
```

Supported modes are `all`, `selected`, `all_except` and `none`. `targets` may
only contain configured retrieval identifiers. `metadata` is an optional object
for configured values such as intents or free-text search context.

The selector writes a `RetrievalPlan` to the shared context. Retrievers check
that plan before execution. Later context optimizers, answer generators and
quality gates receive the plan and metadata as a dedicated prompt section.
