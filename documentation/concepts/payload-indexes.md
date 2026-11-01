# Payload Indexes

Qdrant payload indexes are created automatically when the Qdrant connector
ensures a collection. Scalar fields exported by `DocumentMetadata` are used as
the source of truth. The standard payload paths are below `meta`, for example:

```text
meta.source_type
meta.source_identifier
meta.source_id
meta.source_group_identifier
meta.title
meta.url
meta.language
meta.language_id
meta.page_id
meta.path
meta.filename
meta.changed_at
```

The `additional` metadata container is deliberately not indexed as one field.
Additional metadata indexes are configured on the vector-store connection in
the JSON `additional_options` field:

```json
{
  "payload_indexes": {
    "asset_id": "integer",
    "document_type": "keyword",
    "modified_at": "datetime",
    "teaser": "text",
    "regions": "keyword"
  }
}
```

Unqualified names are stored below `meta.additional`, so the example creates:

```text
meta.additional.asset_id
meta.additional.document_type
meta.additional.modified_at
meta.additional.teaser
meta.additional.regions
```

The full `meta.additional.` path may also be used. Qdrant schema options can be
provided as an object when needed:

```json
{
  "payload_indexes": {
    "asset_id": {
      "type": "integer",
      "lookup": true,
      "range": true
    }
  }
}
```

Existing collections are checked on subsequent initialization. Missing indexes
are added automatically; incompatible existing index types cause an explicit
error and require collection recreation.

Payload indexes accelerate payload filtering. They do not change embeddings or
the semantic vector similarity calculation.
