# Panda action buttons

The Panda family uses one semantic action contract in every list, editor and module. Mark controls with `data-contactpanda-action="…"`; ContactPanda Basic supplies the icon, accessible label treatment, state colour and disabled presentation.

## Canonical actions

| Action key | Meaning | Visual |
|---|---|---|
| `view`, `edit`, `duplicate`, `delete` | Standard record actions | Eye, pencil, copy, red trash |
| `confirm`, `cancel` | Confirm or cancel | Green check, red cross |
| `publish`, `draft`, `save`, `schedule` | Publishing workflow | Megaphone, document with pen, save, calendar |
| `template`, `templateCreate`, `templateGallery` | Template workflow | Layout, new template, grid |
| `import`, `export`, `download` | Data transfer | File in, file out, download |
| `deactivate`, `remove`, `refresh` | State and assignment | Power, minus, refresh |
| `levelUp`, `back` | Navigation | Left-then-up, straight left |
| `desktop`, `tablet`, `mobile` | Device previews | Desktop, tablet, mobile |
| `report`, `email`, `external`, `website` | Common secondary actions | Chart, envelope, external link, globe |
| `add`, `drag`, `moveUp`, `moveDown`, `undo`, `redo`, `image` | Editor actions | Plus, grip/move, arrows, history, image |

`remove` is reserved for removing an assignment. `deactivate` always uses the power symbol. `publish` always uses the megaphone and must never reuse import, export or download symbols.

Icon-only controls require an English source `aria-label` or `title`; translations remain in the owning plugin. Destructive actions stay visible when unavailable, but use `disabled` or `aria-disabled="true"` so the standard crossed-out disabled state is shown.

Use `data-contactpanda-action-mode="labelled"` for primary create, save or publish buttons whose text must remain visible. List actions remain icon-only by default.

## Large labelled actions

Large actions above or below lists and in settings use the same neutral-grey family style everywhere. Secondary actions have a white background and grey border; the primary action is charcoal. Product blue is not used for these controls. On narrow screens an action group wraps and may use the full available width.

| Label | Action key | Icon |
|---|---|---|
| Save / Save changes | `save` | Save/check |
| Save draft | `draft` | Document with pen |
| Apply | `apply` | Check |
| Accept / Use | `accept` | Arrow into line |
| Update | `refresh` | Circular arrows |
| Activate / Deactivate | `activate` / `deactivate` | Power |
| Publish | `publish` | Megaphone |
| Schedule | `schedule` | Calendar |
| Reset | `reset` | Undo |
| Test | `test` | Tools |

Use the exact source label `Save draft`; never use `Save as draft`. Keep one main action group in the page toolbar and one form action group at the end of long forms. Do not use the large-button treatment for row actions in tables.

Standalone products must ship the same local JavaScript and CSS fallback. When ContactPanda Basic is active, its central contract remains authoritative.
