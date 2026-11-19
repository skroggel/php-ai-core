# UI Components

`ai-core` supports declarative UI components in assistant answers. The model
does not emit HTML or JavaScript. It emits a component identifier and JSON data;
the frontend renders the component from a trusted definition.

## Response format

UI components use a dedicated, self-contained JSON marker syntax:

```text
@@UI_COMPONENT {"type":"select-list","data":{"id":"products","options":[{"label":"A","value":"a"}]}}
```

Normal Markdown and UI components can be mixed in one answer. The frontend
parses complete JSON markers and creates stable instance identifiers when no
`id` is given. Unknown or malformed markers must not create frontend
components.

The structural `container` component is an exception to the self-contained
component rule. Emit it before grouped content; a new container closes the
previous one and the final response closes the last open container. The model
must not emit a separate closing component.

## Definitions

A `Definition` contains:

- a stable `identifier`;
- a human-readable title and description;
- a frontend template;
- a data schema and allowed placeholders;
- configured actions and prompt templates;
- an optional wrapper CSS class.

Definitions can supply an outer tag through `tagName`. With `renderMode: block`
the element is rendered as a block; with `renderMode: inline` it remains inside
the current Markdown fragment. The configured template becomes the element's
light-DOM children. The `container` render mode creates the wrapper around the
rendered content automatically.

Example definition:

```text
renderMode: block
tagName: custom-slider
template: <div data-label="{{label}}">{{text}}</div>
```

The host frontend must register `custom-slider` before or while the response
is inserted. A normal Vue component is not compiled when it is inserted via
`v-html`; use a Vue custom element adapter when the component needs Vue
lifecycle or interactivity.

The built-in `headline` definition accepts `text` and an optional `type`. The
type must be one of `h1` through `h6`; missing or invalid values fall back to
`h2`.

Definitions are provided through `ProviderInterface`. The provider is the
configuration source, not a service registry. A CMS or another host can load
definitions from records, configuration files or code.

## Prompt instructions

`UIComponentsContextBuilder` contributes the available component definitions to
the existing central prompt-building process. It documents the component name,
purpose, schema, actions and block syntax for the current pipeline step.

Only the answer generator and quality gate should normally receive these
instructions. The quality gate may keep, remove or add valid UI blocks before
the final response is streamed.

## Actions

Interactive components submit normal user prompts through the existing chat
transport. Prompt templates are configured outside the model and may use only
allowed placeholders. The model can provide values, but cannot provide
arbitrary JavaScript, event handlers or prompt execution logic.

Navigation components use regular configured links and do not submit prompts.

## Built-in definitions

Built-in definitions live under:

```text
Assistant/Presentation/UIComponents/Defaults/
```

The catalog currently includes `container`, `select-list`, `link-list`, `link`,
`buttons`, `progress-indicator`, `decorative` and `headline`.
