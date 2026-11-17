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
component rule. It opens a grouped content section and may be emitted again to
start the next section. The frontend closes the previous section before the
next one and closes the final open section at the end of the response. The
model must not emit a separate closing component.

## Definitions

A `Definition` contains:

- a stable `identifier`;
- a human-readable title and description;
- a frontend template;
- a data schema and allowed placeholders;
- configured actions and prompt templates;
- an optional wrapper CSS class.

Definitions can supply an outer tag through `tagName`.
With `renderMode: block` the element is rendered as a block; with
`renderMode: inline` it remains inside the current Markdown fragment. The
configured template becomes the element's light-DOM children. The `container`
render mode creates the closing wrapper tag automatically. Existing `block` and
`inline` definitions are unchanged.

Example definition:

```text
renderMode: block
tagName: custom-card-slider
template: <div data-label="{{label}}">{{text}}</div>
```

The host frontend must register `custom-card-slider` before or while the response
is inserted. A normal Vue component is not compiled when it is inserted via
`v-html`; use a Vue custom element adapter when the component needs Vue
lifecycle or interactivity.

## Generic JSON custom element

A component can omit its HTML template and let a registered custom element
render the JSON data directly. For example:

```text
identifier: inline-link
template: ""
renderMode: inline
tagName: ui-inline-link
dataSchema: {"label":{"type":"string"},"url":{"type":"string"}}
placeholders: label, url
```

The assistant emits only the component marker and data:

```text
(@@UI_COMPONENT {"type":"inline-link","identifier":"inline-link","data":{"label":"Source","url":"/example"}})
```

The registered custom element receives the serialized JSON through the
component metadata attribute and owns the final HTML rendering. A generic
custom element can read the payload like this:

```js
const readComponentData = (element) => {
    try {
        const data = JSON.parse(element.getAttribute('data-ai-component-data') || '{}');
        return data && typeof data === 'object' ? data : {};
    } catch (error) {
        return {};
    }
};

const data = readComponentData(this);
const label = data.label || '';
const url = data.url || '';
```

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
