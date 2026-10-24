# Retrieval Selection

The `intent_selector` pipeline step can control three independent output areas:

* `retriever` selects which configured retrieval targets run;
* `presentation_rules` selects the presentation rules for the answer;
* `retriever_payload` creates a dynamic payload filter for retrieval.

The areas are configured independently. An area that is not enabled keeps the
existing runtime default. Empty or missing legacy output configuration enables
`retriever` and `presentation_rules`, while `retriever_payload` remains disabled.

Every retriever needs a stable `retrievalIdentifier`. The selector receives the
available targets and the original/current query as prompt context.

The output contract contains only the enabled areas and the optional metadata
object. The complete contract is:

```json
{
  "retrieval": {
    "mode": "selected",
    "targets": ["products"]
  },
  "presentationRules": {
    "mode": "selected",
    "rules": ["compact_answer"]
  },
  "retrieverPayload": {
    "values": [12, 15]
  },
  "metadata": {
    "primary_intent": "product_information"
  }
}
```

The retrieval and presentation areas support `all`, `selected`, `all_except`
and `none`. Their lists may only contain configured identifiers. The
`retrieverPayload` area always uses the `values` key. A single value is also
represented as a one-element array.

The payload field path and value conjunction are configuration, not model
output. The integration configures a path such as
`meta.additional.category_ids`; the model supplies only the values. The vector
filter combines multiple values with the configured `and` or `or` conjunction.
The model does not choose that conjunction.

Payload paths use dot notation and can address any indexed payload field,
including additional metadata such as `meta.additional.categories`,
`meta.additional.category_ids` or a project-specific field.

The selector writes a `RetrievalPlan` to the shared context. Retrievers check
that plan before execution. A dynamic payload filter is applied to every
retriever by default. The integration can limit it to selected retriever
identifiers; an empty target selection means all retrievers allowed by the
retrieval plan. A retriever that is not selected for the payload filter runs
without that dynamic filter.

Static filters configured on a retriever remain active. When both static and
dynamic filters exist, their conditions are combined with `AND`.

Missing, empty or invalid payload values produce no dynamic filter. This keeps
retrieval unfiltered when the model does not provide a usable payload value.

Later context optimizers, answer generators and quality gates receive the plan
and metadata as dedicated prompt sections. Retrieval and presentation areas
that are disabled in the selector configuration are not overwritten by the
selector and retain their existing defaults.
