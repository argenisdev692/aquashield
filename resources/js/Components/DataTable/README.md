# DataTable kit

A small, server-first table kit for AquaShield list screens. It composes a
debounced search, advanced filters in a popover, a contextual bulk bar, typed
row actions, server-side pagination and CSV/XLSX/PDF export over one query
contract.

The kit is deliberately **not** a data source. It never calls an endpoint, never
sorts or filters in memory, and never assumes a module, model or route. A page
owns the query (through its data composable) and passes `rows`, `total` and
`loading` down.

- Vue 3.5, TypeScript strict, no `any`
- PrimeVue 5 unstyled through `@/volt/*`; `Column` is the one direct import
  (`primevue/column`), because it is a child primitive rather than a component
  with its own chrome
- Tailwind v4 utilities and the AquaShield tokens only — no raw palette colours
- WCAG 2.2 AA: real labels, visible focus, `aria-live` result counts, no
  colour-only meaning, ConfirmDialog for every destructive action and for
  restore

## Import

```ts
import {
  CrudDialog,
  DataTableKit,
  DataTableFilters,
  DataTableFilterField,
  DataTableDateRange,
  DataTableBulkActions,
  DataTableRowActions,
  DataTablePagination,
  DataTableExport,
  DataTableEmpty,
  TableButton,
  type CrudDialogSize,
  type ExportFormat,
  type RowAction,
} from '@/Components/DataTable';
```

`DataTableKit` is the composed screen. The parts are exported for the one page
that needs a different layout; they share the same props and events.

## Full example

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Column from 'primevue/column';
import {
  DataTableDateRange,
  DataTableFilterField,
  DataTableKit,
  DataTableRowActions,
  type ExportFormat,
  type RowAction,
} from '@/Components/DataTable';
import Select from '@/volt/Select.vue';
import { useTableState } from '@/lib/table';

interface Member {
  id: number;
  name: string;
  email: string;
  status: 'active' | 'invited';
  deleted_at: string | null;
}

type MemberFilters = {
  status?: string;
  from?: string;
  to?: string;
};

defineProps<{
  rows: Member[];
  total: number;
  loading: boolean;
  showDeleted?: boolean;
}>();

/**
 * Destructure: these are refs, and template ref unwrapping only applies to
 * top-level bindings. `params` is the exact query string the page's data
 * composable should send.
 */
const { page, perPage, search, sort, filters, params, activeFilterCount, resetFilters, exportUrl } =
  useTableState<MemberFilters>({
    filters: { status: '', from: '', to: '' },
  });

const selection = ref<Member[]>([]);

/**
 * Filters are slot state, so the kit cannot watch them. Changing one starts a
 * new result set: go back to page 1 and drop page-scoped selection.
 */
watch(filters, () => {
  page.value = 1;
  selection.value = [];
});

const statuses = [
  { label: 'Active', value: 'active' },
  { label: 'Invited', value: 'invited' },
];

const rowActions = computed<RowAction<Member>[]>(() => [
  {
    key: 'edit',
    label: 'Edit member',
    icon: 'pi pi-pencil',
    hidden: (row) => row.deleted_at !== null,
    action: (row) => router.visit(`/members/${row.id}/edit`),
  },
  {
    key: 'restore',
    label: 'Restore member',
    icon: 'pi pi-replay',
    tone: 'accent',
    hidden: (row) => row.deleted_at === null,
    confirm: {
      header: 'Restore member',
      message: (row) => `Restore ${row.name}? They will become active again.`,
      acceptLabel: 'Restore',
    },
    action: (row) => router.post(`/members/${row.id}/restore`),
  },
  {
    key: 'delete',
    label: 'Delete member',
    icon: 'pi pi-trash',
    tone: 'danger',
    hidden: (row) => row.deleted_at !== null,
    confirm: {
      header: 'Delete member',
      message: (row) => `Delete ${row.name}? You can restore them from the deleted view.`,
      acceptLabel: 'Delete',
    },
    action: (row) => router.delete(`/members/${row.id}`),
  },
]);

const exportHref = (format: ExportFormat): string => exportUrl(`/members/export/${format}`);

const bulkDelete = (): void => {
  router.delete('/members', {
    data: { ids: selection.value.map((row) => row.id) },
    preserveScroll: true,
    onSuccess: () => {
      selection.value = [];
    },
  });
};

const bulkRestore = (): void => {
  router.post('/members/restore', {
    data: { ids: selection.value.map((row) => row.id) },
    preserveScroll: true,
    onSuccess: () => {
      selection.value = [];
    },
  });
};
</script>

<template>
  <DataTableKit
    v-model:page="page"
    v-model:per-page="perPage"
    v-model:search="search"
    v-model:sort="sort"
    v-model:selection="selection"
    :rows="rows"
    :total="total"
    :loading="loading"
    :filter-count="activeFilterCount"
    :export-href="exportHref"
    :can-restore="showDeleted"
    title="Members"
    description="People with access to this workspace."
    @bulk-delete="bulkDelete"
    @bulk-restore="bulkRestore"
    @reset-filters="resetFilters"
  >
    <template #filters>
      <DataTableFilterField v-slot="{ id }" label="Status">
        <Select
          :input-id="id"
          v-model="filters.status"
          :options="statuses"
          option-label="label"
          option-value="value"
          placeholder="Any status"
          show-clear
        />
      </DataTableFilterField>

      <DataTableDateRange v-model:from="filters.from" v-model:to="filters.to" label="Created" />
    </template>

    <template #actions>
      <Link
        href="/members/create"
        class="inline-flex h-9 items-center rounded-md border border-primary bg-primary px-3 text-sm font-medium text-primary-contrast transition-colors duration-150 hover:border-primary-emphasis hover:bg-primary-emphasis focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
      >
        New member
      </Link>
    </template>

    <Column selection-mode="multiple" header-style="width: 3rem" />
    <Column field="name" header="Name" sortable />
    <Column field="email" header="Email" sortable />
    <Column field="status" header="Status" sortable />
    <Column field="created_at" header="Created" sortable />

    <Column header="" style="width: 1%">
      <template #body="{ data }">
        <DataTableRowActions :row="data" :row-label="data.name" :actions="rowActions" :disabled="loading" />
      </template>
    </Column>
  </DataTableKit>
</template>
```

`params` is a `ComputedRef<URLSearchParams>`; a data composable should use it
verbatim rather than rebuilding the query. `scroll`/partial-reload behaviour is
the page composable's decision, not the kit's.

## Server contract

The kit serialises one shape, in `resources/js/lib/table.ts`:

| Query key       | Example                             | Notes                                           |
| --------------- | ----------------------------------- | ----------------------------------------------- |
| `page`          | `2`                                 | Omitted on page 1.                              |
| `per_page`      | `25`                                | Omitted at the default 15.                      |
| `search`        | `ada`                               | Trimmed; blank is omitted.                      |
| `sort`          | `-created_at`                       | `-` prefix = descending; no prefix = ascending. |
| `filter[key]`   | `filter[status]=active`             | Scalar filter.                                  |
| `filter[key][]` | `filter[team][]=1&filter[team][]=2` | Multi-value filter.                             |

`false`, `null`, `undefined`, `''` and empty arrays are treated as inactive, so
a cleared control disappears from the query. When the domain genuinely needs a
`false` filter value, send `'0'` (a string) instead.

Recommended server behaviour:

- Validate `page`, `per_page`, `sort` and every `filter.*` key against an
  allow-list. Never pass a client `sort` field straight to `orderBy`.
- Return `rows` (the current page) and `total` (the count after filters).
  `total` drives the footer, so it must be a real count, not the page length.
- Clamp `per_page` (for example 10–100) even though the UI only offers four
  values.

## Export

`DataTableExport` and `DataTableKit` use the same state as the table, minus
pagination: an export covers **every matching row**, not the current page.

```ts
const exportHref = (format: ExportFormat): string => exportUrl(`/members/export/${format}`);
// -> /members/export/csv?search=ada&sort=-created_at&filter[status]=active
```

The URL can carry the format as a path segment or as `?format=csv`. The server
should:

1. Re-authorise the request and re-apply the same filters.
2. Stream the response with `Content-Disposition: attachment`.
3. Cap the export (row count or time) and report the cap, rather than letting a
   filtered export exhaust memory.
4. Neutralise spreadsheet formulas: a cell whose value starts with `=`, `+`,
   `-` or `@` is executable in Excel/Sheets, and names and notes are user
   input. Prefix those cells (a leading apostrophe is the usual convention).

`href` is optional. Omit it to own the transport yourself:

```vue
<DataTableKit ... @export="onExport" />
```

`onExport` can request a signed URL or POST a job. `export-formats` restricts
the menu (`['csv', 'xlsx']`); an empty list disables the standalone button (the
kit hides it).

## Soft delete and restore

The kit has no notion of a model, so both states are page decisions:

- Pass `can-restore` when the current view contains deleted rows. The bulk bar
  then offers **Restore** next to **Delete**.
- Compute `RowAction<T>[]` from `row.deleted_at` (or your state enum).
  `hidden` and `disabled` are functions, so one array can cover active and
  deleted rows.
- A deleted row is a first-class state: keep it visible and marked (a status
  column or `rowClass`) rather than hiding it, so restore stays reachable.

### Delete and restore confirmation

Every delete and every restore — single row and bulk — goes through PrimeVue
`ConfirmDialog` (`useConfirm`). There is no `window.confirm()` anywhere in the
kit, and no silent mutation: both operations change server state, and a misclick
in a dense action column or a selection bar should be recoverable.

The contract is **one** `<ConfirmDialog />` per layout, mounted once in
`AppLayout`. The kit only calls `confirm.require(...)`; it never owns a dialog.

| Surface | Trigger                          | Message                                                                            |
| ------- | -------------------------------- | ---------------------------------------------------------------------------------- |
| Row     | `RowAction.confirm` block        | `header`, `message` (string or `(row) => string`), `acceptLabel`, `rejectLabel`    |
| Bulk    | `@bulk-delete` / `@bulk-restore` | count-aware copy; delete says “you can restore them” only when `can-restore` is on |

Rules:

- The modal fires **before** the action. Accepting runs the page-supplied
  handler (`router.delete` / `router.post`); cancelling changes nothing.
- A row action without a `confirm` block runs immediately. Reserve that for
  reversible, non-destructive navigation such as an edit link.
- `tone: 'danger'` is the visual half; the modal is the interactive half. Both
  are required for a destructive row action.
- The bulk bar lists **Restore** before **Delete**, so the safer action sits
  where a misclick lands first.
- Restore is reversible, but it is still a state change — confirm it like delete
  rather than firing it straight through.

## Parts and props

### `DataTableKit`

Required models: `v-model:page` and `v-model:per-page`. Optional models:
`v-model:search`, `v-model:sort`, `v-model:selection`.

| Prop                | Default                | Purpose                                          |
| ------------------- | ---------------------- | ------------------------------------------------ |
| `rows`              | —                      | Current page only.                               |
| `total`             | —                      | Filtered total, not page length.                 |
| `loading`           | `false`                | Disables controls and shows the table mask.      |
| `data-key`          | `'id'`                 | Stable row identity for selection.               |
| `title`             | —                      | Rendered as the section heading.                 |
| `description`       | —                      | Supporting line under the title.                 |
| `label`             | `'Data table'`         | Accessible name when no title is given.          |
| `search-min-length` | `2`                    | Below this, the search clears.                   |
| `filter-count`      | `0`                    | Active count shown on the filter trigger.        |
| `per-page-options`  | `[10, 15, 25, 50]`     | Current value is always included.                |
| `export-formats`    | `['csv','xlsx','pdf']` | Menu entries.                                    |
| `export-href`       | —                      | `(format) => string \| undefined`.               |
| `can-restore`       | `false`                | Shows Restore in the bulk bar.                   |
| `empty-*`           | auto copy              | Title, description and icon for the empty state. |

Events: `export(format)`, `bulk-delete`, `bulk-restore`, `reset-filters`.

Slots: `default` (columns), `toolbar`, `filters`, `actions`, `empty`,
`empty-action`, `bulk-actions`. `toolbar` replaces the whole default toolbar;
`actions` is the additive slot for a page-level button.

The kit resets to page 1 and clears selection when the search, page size or sort
changes — selection is page-scoped on purpose. Carrying row references across a
server page is how bulk actions delete the wrong records.

### Filters

`DataTableFilters` is a popover with an active-count badge and a Reset action.
Filters apply live; **Done** only closes the panel, so there is no second
apply/commit model. The count is a count of non-empty filter values, so a date
range with both ends set reports `2`; override `filter-count` on the kit if a
screen wants to count groups instead. Pass the count (the example uses
`activeFilterCount`) or the badge stays at zero and Reset stays disabled.

`DataTableFilterField` supplies the label, hint and id wiring:

```vue
<DataTableFilterField v-slot="{ id, describedBy }" label="Status" hint="Only active members can sign in.">
    <Select :input-id="id" :aria-describedby="describedBy" ... />
</DataTableFilterField>
```

`DataTableDateRange` emits ISO `YYYY-MM-DD` strings through `v-model:from` and
`v-model:to`. It never puts a `Date` on the wire, and it shows an inline error
when `from > to`; the server still validates the range.

### Search

`DataTableSearch` owns a local draft and commits only after the debounce window,
so a query key never changes on every keystroke. `onWatcherCleanup` cancels the
pending timer on the next keystroke or on unmount. It is composed by the kit and
not part of the public import surface; import it only to build a custom toolbar.

| Prop          | Default     | Purpose                                        |
| ------------- | ----------- | ---------------------------------------------- |
| `placeholder` | `'Search…'` | Input placeholder.                             |
| `label`       | `'Search'`  | Screen-reader label (visually hidden).         |
| `minLength`   | `2`         | Below this the search clears.                  |
| `debounce`    | `300`       | Milliseconds before the draft commits.         |
| `loading`     | `false`     | Shows the spinner instead of the clear button. |

The min-length rule is announced through `aria-describedby`. Enter commits
immediately; the clear button never waits for the debounce.

### Row actions

```ts
interface RowAction<T> {
  key: string;
  label: string;
  icon: string;
  tone?: 'default' | 'accent' | 'danger';
  hidden?: (row: T) => boolean;
  disabled?: (row: T) => boolean;
  confirm?: {
    header: string;
    message: string | ((row: T) => string);
    acceptLabel?: string;
    rejectLabel?: string;
  };
  action: (row: T) => void | Promise<void>;
}
```

Every icon button gets `aria-label` and a tooltip, so the icon is never the only
label. Destructive actions should use `tone: 'danger'` and a `confirm` block;
the component routes a confirmed action through ConfirmDialog.

`DataTableRowActions` props:

| Prop       | Default | Purpose                                                   |
| ---------- | ------- | --------------------------------------------------------- |
| `row`      | —       | The current row; passed to `hidden`/`disabled`.           |
| `actions`  | —       | `RowAction<T>[]`, computed by the page.                   |
| `rowLabel` | —       | Names the group (`aria-label="Actions for X"`).           |
| `disabled` | `false` | Disables every action, e.g. while a request is in flight. |

The group renders `null` when every action is hidden, so a row with no
available actions leaves the column empty instead of a stray group wrapper.

### Bulk actions

`DataTableBulkActions` is the contextual selection bar. It renders only while
rows are selected and sits above the table rather than floating over the last
row. Delete and restore both confirm through `ConfirmDialog` before emitting.

| Prop         | Default | Purpose                                       |
| ------------ | ------- | --------------------------------------------- |
| `count`      | —       | Selected rows; drives the copy and the count. |
| `canRestore` | `false` | Shows the Restore button.                     |
| `busy`       | `false` | Disables every control.                       |

Events: `delete`, `restore`, `clear`. Slot: `default` (extra controls, placed
between the actions and the clear button).

### Pagination and empty state

`DataTablePagination` is a custom server-side footer (first, current ±1, last)
with a page-size select. `DataTableEmpty` distinguishes “nothing yet” from
“nothing matched”; `DataTableKit` switches the copy automatically when a search
or filter is active.

| `DataTablePagination` | Default         | Purpose                               |
| --------------------- | --------------- | ------------------------------------- |
| `page`                | —               | Current page (1-based).               |
| `perPage`             | —               | Rows per page.                        |
| `total`               | —               | Filtered total, not page length.      |
| `perPageOptions`      | `[10,15,25,50]` | The current value is always included. |
| `disabled`            | `false`         | Disables the whole footer.            |

Events: `update:page`, `update:perPage`. The window is intentionally short
(first, current ±1, last); below seven pages every page is shown. On narrow
screens the numbered window collapses to “Page N of M”.

| `DataTableEmpty` | Default            | Purpose          |
| ---------------- | ------------------ | ---------------- |
| `title`          | `'No records yet'` | Heading.         |
| `description`    | auto copy          | Supporting line. |
| `icon`           | `'pi pi-inbox'`    | Leading glyph.   |

Slot: `default` (the recovery action, e.g. a “Clear filters” button).

### Export control

`DataTableExport` is the standalone dropdown. The kit renders it only when
`export-formats` is non-empty; `href` is optional so a page can own a signed URL
or a POST flow.

| Prop       | Default                | Purpose                                               |
| ---------- | ---------------------- | ----------------------------------------------------- |
| `formats`  | `['csv','xlsx','pdf']` | Menu entries; empty disables the button.              |
| `label`    | `'Export'`             | Trigger text.                                         |
| `disabled` | `false`                | Disables the trigger.                                 |
| `href`     | —                      | `(format) => string \| undefined`; omit to emit only. |

Event: `export(format)`. With `href` the component also triggers a download;
without it, the page handles transport itself.

### Buttons

`TableButton` is the kit's one neutral control — a 36px button with a 3:1
border used by the search, filters, export, bulk bar and pagination. It is not a
general-purpose button: branded calls to action should use Volt's `Button`.

| Prop       | Default     | Purpose                                               |
| ---------- | ----------- | ----------------------------------------------------- |
| `variant`  | `'neutral'` | `neutral`, `primary`, `danger`, `ghost`.              |
| `size`     | `'md'`      | `sm` (32px) or `md` (36px).                           |
| `iconOnly` | `false`     | Square hit area; the caller must supply `aria-label`. |
| `type`     | `'button'`  | `button`, `submit` or `reset`.                        |
| `disabled` | `false`     | Standard disabled state.                              |

## Create and edit dialog

`CrudDialog` is the create/edit shell for a list screen. It owns the dialog
chrome, the native form and the submit/cancel wiring; the page owns the fields,
the schema and the endpoint. Delete and restore are **not** form operations —
use the row-action `confirm` block (see “Soft delete and restore”) instead.

```vue
<CrudDialog
  v-model="open"
  title="New member"
  description="People with access to this workspace."
  submit-label="Create member"
  :busy="isSaving"
  size="md"
  @submit="onSubmit"
  @cancel="resetForm"
>
  <div class="flex flex-col gap-4">
    <label for="name" class="text-sm font-medium">Name</label>
    <InputText id="name" v-model="form.name" autocomplete="off" />
  </div>
</CrudDialog>
```

| Prop          | Default    | Purpose                                             |
| ------------- | ---------- | --------------------------------------------------- |
| `title`       | —          | Dialog header.                                      |
| `description` | —          | Supporting line, wired with `aria-describedby`.     |
| `submitLabel` | `'Save'`   | Footer submit button.                               |
| `cancelLabel` | `'Cancel'` | Footer cancel button.                               |
| `busy`        | `false`    | Disables every control and blocks dismissal.        |
| `size`        | `'md'`     | `sm` 24rem · `md` 32rem · `lg` 42rem.               |
| `dismissible` | `true`     | Close icon, Escape and mask. `busy` forces `false`. |

Events: `submit`, `cancel`.

Slots: `default({ formId, busy })`, `form({ formId, busy })`,
`footer({ formId, busy, cancel })`.

The default slot is wrapped in a native `<form>`, so Enter submits and browser
constraint validation is free. A page that needs its own form component (the
module's PrimeVue Forms + Zod setup) overrides the `form` slot and keeps the
same `formId`, so the footer submit button still targets it. The footer slot is
the escape hatch for a fully custom action row.

## Accessibility notes

- Search, filters and pagination all have real labels; the search hint and date
  errors are wired with `aria-describedby`.
- The selected-row count is a polite live region; the pagination summary is
  `aria-live="polite"`; the empty state is `role="status"`.
- Icon-only controls carry `aria-label`; tooltips are supplementary, never the
  only name.
- Pagination marks the current page with `aria-current="page"`.
- `ConfirmDialog` traps focus and returns it to the triggering control, so the
  delete/restore modals are keyboard-operable; `CrudDialog` is modal and
  blocks scroll while open.
- Targets are at least 24×24 CSS px (WCAG 2.2 SC 2.5.8); the default controls
  are 32–36 px.
- Every hover/focus state includes a transition and respects
  `prefers-reduced-motion`.

## Palette and boundaries

- Destructive **text** uses `text-aq-danger-strong`; the icon colour
  `text-aq-danger` is fine for glyphs but fails AA as button text. Both flip in
  dark mode through `globals.css`.
- Borders use `border-content-border` (3:1) and `border-aq-shell-line`, never a
  raw Tailwind palette class.
- No `:pt` at call sites. If a Volt primitive needs a different treatment, edit
  its own pass-through config in `src/volt/`.
- `Column` is imported from `primevue/column`; every other component comes
  through `@/volt/*`.
