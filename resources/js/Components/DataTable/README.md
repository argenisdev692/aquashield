# DataTable kit

A server-first table kit for AquaShield list screens, written in **React 19.3** on
shadcn/ui + Base UI. It is deliberately **not** a data source: it never calls an
endpoint and never sorts, filters or paginates rows in memory.

The kit is built on **TanStack Table v9** (`@tanstack/react-table`). The table instance lives in
`resources/js/lib/data-table.ts`, so pages and components never import the table framework directly
(architecture invariant 2). Features are registered explicitly: pagination, sorting, row selection
and column visibility. No client row models are registered — the server already processed the page.

## Import

```ts
import {
  CrudDialog,
  CrudFilters,
  DataTableKit,
  TableButton,
  type CrudFilterDef,
  type ExportFormat,
  type SimpleColumn,
} from '@/components/DataTable';
```

## A page is columns + filter definitions

`useCrudTable` (in `lib/crud-table.ts`) owns the URL. Spread `kitProps`; pass the rows the server
returned and the filter definitions. There is no URL wiring per page.

```tsx
const table = useCrudTable({ endpoint: '/users' });
const bulk = useBulkActions({
  deleteUrl: canBulkDelete ? '/users/bulk-delete' : undefined,
  restoreUrl: canBulkRestore ? '/users/bulk-restore' : undefined,
});

const filters: CrudFilterDef[] = [
  {
    key: 'status',
    label: 'Status',
    type: 'select',
    placeholder: 'Active',
    options: [{ value: 'trashed', label: 'Trashed' }],
  },
  { key: 'created', label: 'Created', type: 'date-range' },
];

<DataTableKit
  {...table.kitProps}
  columns={userColumns}
  data={rows}
  total={total}
  filters={<CrudFilters defs={filters} state={table.state} onChange={table.setFilters} />}
  views={<SavedViewsMenu resource="users" state={table.state} onApply={table.apply} />}
  filterCount={table.activeFilterCount}
  hasActiveFilters={table.hasActiveFilters}
  onResetFilters={table.reset}
  exportFormats={canExport ? ['csv', 'xlsx', 'pdf'] : []}
  exportHref={canExport ? table.exportUrl : undefined}
  onBulkDelete={canBulkDelete ? bulk.deleteRows : undefined}
  onBulkRestore={canBulkRestore ? bulk.restoreRows : undefined}
  actionError={bulk.error}
/>;
```

Rules:

- **The URL is the state.** `useCrudTable` parses `usePage().url` on every render, so reload,
  back/forward and a saved view resolve to the same table. No effect copies server props into
  local state.
- **Every setter preserves the rest of the state.** Page, search, sort, filters and page size are
  one object written through `buildTableUrl`; changing one can never erase another.
- **Columns are module-level.** TanStack Table v9 treats them as a model input; a fresh array on
  every render rebuilds the column model. Use `sortKey` when the accessor is not a DB column
  (`name` → `first_name`), `sortable: false` for computed cells, `align: 'right'` for numbers,
  `width` for compact columns and `className` for truncation/`tabular-nums`.
- **Selection is page-scoped.** Bulk handlers receive the selected loaded rows. Omit a handler and
  its control (and, when no handler at all exists, the whole checkbox column) disappears.

## Visual system

- **48px header, 48px rows at comfortable density** (compact trims the row padding), 16px gutters
  (32px between columns). Header labels are 12px
  semibold in sentence case with a subtle sort hint (`ChevronsUpDown` at 30% opacity); the sorted
  column brightens its label and accents the icon.
- **One header contract.** The type lives on the `<th>`; the sort control is a full-width flex row
  because Chrome's UA stylesheet resets `text-transform` and `text-align` on `<button>` — relying on
  inheritance makes sortable headers disagree with static ones. Never split header typography
  between the cell and the button.
- **Alignment follows the data:** text left, numbers and dates right (`align: 'right'` +
  `tabular-nums`), timestamps `whitespace-nowrap`. Headers share the column alignment through
  `justify-*`, not just `text-align`.
- **Dates arrive as ISO strings and are formatted at the edge** (`formatDateTime` from
  `@/lib/date`) — a raw `2026-10-28T15:29:48+00:00` eats a column and is unreadable.
- **Soft-deleted rows** pass `rowState={() => 'deleted'}`: a red tint, a left accent and a muted
  hover. The page must also render a status tag (`Deleted` / `Quarantined`) — colour is never the
  only signal (WCAG 1.4.1).
- **Selection** uses a 20px checkbox in a 48px leading column, centred; the header is select-all
  for the current page. The bulk bar states how many rows are selected.
- **Toolbar controls are 36px** (`h-9`): search, filters, export, page actions. Mixing 28/32/38px
  controls in one row is what makes a table toolbar read as unpolished.

## URL contract

| Key             | Example                             |
| --------------- | ----------------------------------- |
| `page`          | `2`                                 |
| `per_page`      | `25`                                |
| `search`        | `ada`                               |
| `sort`          | `-created_at` (`-` = descending)    |
| `filter[key]`   | `filter[status]=active`             |
| `filter[key][]` | `filter[team][]=1&filter[team][]=2` |

`buildTableParams` / `buildTableUrl` serialise it, `parseTableParams` reads it back, and
`exportUrl(format)` reuses the same state with `paginate: false`. `per_page` is clamped to 100,
matching every filter DTO's `max:100`.

Column visibility and density are **not** URL state: they are presentation preferences and live in
`localStorage` (see “Columns and density”). A pasted link reproduces the filtered rows, not the
recipient's column layout.

## Filters

`CrudFilters` renders a panel from a `CrudFilterDef[]`:

| Type         | Behaviour                                                                                                                                |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `text`       | Ignored below 2 characters, blank removes the key.                                                                                       |
| `select`     | Single value from a bounded list; "All" clears the key. Above 40 options (or `searchable: true`) it becomes a searchable inline list.    |
| `date-range` | Two ISO inputs plus presets (7/30 days, month, year, all time). An inverted range clears the opposite bound instead of submitting a 422. |

The panel is a **draft**: opening seeds it from the URL, fields edit locally, and `Apply` commits one
`setFilters` call. This avoids two bugs of binding selects straight to `state.filters`: the control
would snap back for the whole Inertia round-trip, and three changed fields would fire three visits.
`Reset` clears the committed filters immediately; `Cancel` discards the draft.

Applied state never hides behind the panel: the trigger carries a count badge and every active filter
renders as a removable chip with a `Clear all` control. `Apply` is disabled when the draft equals the
committed state.

Unknown keys never reach the URL: the page declares the definitions and the server's filter DTO
rejects anything outside its allowlist.

## Columns and density

The `Columns` popover is presentation only — it never changes the request or the export. It lists
every leaf column, locks `hidable: false` columns (the identity column) and refuses to hide the last
visible column. `Compact rows` switches the cell padding; `Reset to defaults` shows every column and
restores comfortable density.

Both preferences persist per resource in `localStorage` (`aq:table:<resource>`), keyed by
`kitProps.preferencesKey` (the endpoint, supplied by `useCrudTable`). They are not saved views:
filters and search stay in the URL because they define _which_ records are on screen; visibility and
density define _how_ the same records are displayed. `lib/table-preferences.ts` validates the stored
blob, drops unknown/locked ids and never restores a state with every column hidden.

## Export

`exportHref` receives a format and returns the download URL. Without it (and without `onExport`) the
menu is hidden — there is no inert control. The endpoint must re-apply the same filters, omit
pagination, stream the response with `Content-Disposition: attachment`, cap the row count, and
neutralise spreadsheet formulas (`=`, `+`, `-`, `@`) in user-controlled cells. The kit downloads
through a real anchor so the SPA stays mounted.

## Destructive actions

Bulk delete opens a Base UI `Dialog` inside the kit before calling `onBulkDelete`; restore is not
destructive and runs directly. `window.confirm()` and `window.alert()` are forbidden. A failed bulk
action is surfaced through `actionError` above the table, never as a replaced table body.

## Saved views

`SavedViewsMenu` stores named views **on the server**, per user (`saved_views` table), never in
`localStorage`: a view can contain a search term (often a name or email), must follow the account
across devices, and must not be readable by any script on the origin. Ownership is enforced by
scoping every query to `auth()->id()`; a forged UUID is a 404. The server validates the resource and
every filter key against `App\Support\SavedViewCatalog`, caps 20 views per screen and bounds the name
to 60 characters. The collection is a client-owned panel, so it is read with TanStack Query
(`Modules/SavedViews/hooks/useSavedViews.ts`); the list itself still comes from Inertia.

## Accessibility notes

- Sortable headers use a real `<button>` and expose `aria-sort`.
- Search and filter controls have labels; errors use `aria-invalid` and `role="alert"`.
- The filter panel is a Base UI popover with a title; Escape closes it and focus returns to the
  trigger. The count badge reads as “N active filters”, not just a number.
- Active filter chips have a labelled remove button; `Clear all` is a real button.
- Column checkboxes are labelled, locked columns say why, and the last visible column cannot be
  hidden.
- Selection uses a real checkbox column (header select-all plus one per row), each with an
  `aria-label`; it is absent when the screen has no bulk capability.
- The pagination summary is `aria-live="polite"` and reads `first–last of total · page N of M`.
- Every icon-only control has an `aria-label`; decorative icons are hidden.

## Known gaps

- Column pinning, grouping, ordering and resizing are not registered. Add the matching feature and
  row-model slot only when a screen needs it.
- Selection is page-scoped. Cross-page “select all filtered” needs a controlled `selectedIds`
  contract and a server endpoint; do not fake it with loaded rows.
- Saved views are private. Sharing needs an explicit read policy and a visibility column — do not
  add it until a product decision exists.
- No component test runner covers sorting, pagination, selection or keyboard flows yet. The
  architecture suite checks structure, not behaviour; the saved-view API has feature coverage.
- Server rows must use the stable `uuid`/`id` field. Pass `getRowId` when a row uses another key.
