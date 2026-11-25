# Form kit

One engine, one field contract, **React 19.3** on shadcn/ui + Base UI. The kit
owns presentation, validation wiring and error focus; the page owns the endpoint,
the Zod schema and the submit call.

> There is exactly **one** form-state owner: the `form` returned by
> `useZodForm`. Do not call Inertia's `useForm` alongside it. Two owners make
> `submit()` validate the initial values while the inputs edit a different
> object.

## The engine

```ts
import { useZodForm } from '@/lib/forms';
import * as z from 'zod';

const schema = z.object({
  name: z.string().min(2, 'Enter your full name'),
  email: z.string().email('Enter a valid email address'),
});

const { form, errors, submit, touch } = useZodForm({
  initial: { name: '', email: '' },
  schema,
});

const onSubmit = (event: React.FormEvent) => {
  event.preventDefault();
  submit(() => form.put('/settings/profile', { preserveScroll: true }));
};
```

```tsx
<form onSubmit={onSubmit} className="space-y-4">
  <FormErrorSummary errors={errors} onFieldFocus={() => {}} />

  <TextField
    label="Full name"
    name="name"
    value={form.data.name}
    onChange={(value) => form.setData('name', value)}
    onBlur={() => touch('name')}
    error={errors.name}
    required
  />

  <FormActions submitLabel="Save changes" loading={form.processing} />
</form>
```

Contract:

- `errors` merges client errors and the server 422 bag; the server wins.
- `touch(name)` validates that field on blur.
- `clearError(name)` clears that field's client and server error on input.
- `submit(action)` validates everything, focuses the first
  `[aria-invalid="true"]` control, then runs the Inertia call.
- `form` is Inertia's form object: `form.data`, `form.setData`,
  `form.processing`, `form.post/put/patch/delete`, `form.errors`.
- `FormErrorSummary` accepts `string | undefined` values, so pass `errors`
  directly. Do not wrap it in `Object.fromEntries(...)`.

## Fields

Every field is controlled with `value` + `onChange`; none uses Vue `v-model`.

```tsx
<SelectField
  label="Status"
  name="status"
  value={form.data.status}
  onChange={(value) => form.setData('status', value)}
  onBlur={() => touch('status')}
  error={errors.status}
  options={[
    { label: 'Active', value: 'active' },
    { label: 'Invited', value: 'invited' },
  ]}
/>
```

Available fields: `TextField`, `PasswordField`, `TextareaField`, `SelectField`,
`FilterSelectField`, `MultiSelectField`, `TagsField`, `OtpField`, `DateField`,
`DatePickerField`, `NumberField`, `CurrencyField`, `PhoneField`,
`CheckboxField`, `RadioGroupField`, `SwitchField`, `AddressAutocompleteField`,
`SignatureField`, `AvatarCropperField`, `FileDropzone`, `ImageDropzone`,
`ImagesDropzone`. Layout: `FormSection`. Ordered collections:
`SortableList`.

### Google address autocomplete

`AddressAutocompleteField` is a real Google Places API (New) field, not a
hardcoded suggestion list. It needs the browser key the server exposes as
`googleMapsApiKey` (`GOOGLE_MAPS_API_KEY`, restricted by HTTP referrer) and the
Places API (New) enabled in the same Google Cloud project as the Maps
JavaScript API.

```tsx
<AddressAutocompleteField
  label="Street address"
  name="address"
  value={form.data.address}
  onChange={(value) => form.setData('address', value)}
  onAddressSelect={(selection) => {
    // selection.address is the street line only.
    // address_2 is deliberately absent: keep the apartment/suite manual.
    // Inertia v3 REPLACES the payload for an object `setData`; the functional
    // updater merges. useZodForm exposes `setFields` for the same write.
    form.setData((previous) => ({
      ...previous,
      zip_code: selection.zip_code,
      city: selection.city,
      state: selection.state,
      country: selection.country,
      latitude: selection.latitude,
      longitude: selection.longitude,
    }));
  }}
  apiKey={googleMapsApiKey}
  error={errors.address}
/>
```

The component restricts predictions with `includedRegionCodes: ['us']` and
re-checks the selected place's country before it returns an `AddressSelection`.
It uses an `AutocompleteSessionToken` per search, discards it after selection
(so Google bills one session, not one request per keystroke), and exposes the
combobox/listbox keyboard contract. `AddressSelection` is declared in
`@/lib/google-maps` and re-exported from `Components/Forms/types.ts`; do not
redeclare it in a page.

- `FilterSelectField` is the searchable single/multi picker. With `options`
  it filters locally; with `fetcher(query, signal)` the server owns the
  search, keystrokes are debounced, the superseded request is aborted and
  selected labels are cached so an edit form never falls back to a raw id.
- `MultiSelectField` is the `string[]` preset of `FilterSelectField` — one
  implementation, two names. It now accepts the same remote `fetcher`.
- `TagsField` edits a free-form `string[]`: Enter or comma commits, Backspace
  on an empty draft removes the last tag, pasted comma/newline lists are split
  and duplicates are case-insensitive. Use it for segments, industries and
  labels; use `MultiSelectField` when the choices are a closed list.
- `CurrencyField` stores integer minor units by default (`1999` = `$19.99`).
  The focused draft is editable; the blur state is the formatted currency.
  `min`/`max` are in the model unit, not the display unit. `NumberField` takes
  `prefix`/`suffix` for the non-money units (`suffix="%"` for probability,
  `suffix="months"` for term).
- `DatePickerField` is the calendar-popover date. It keeps ISO `YYYY-MM-DD`
  on the wire, accepts `min`/`max` ISO bounds and `yearRange` for a date of
  birth. `DateField` remains the native `<input type="date">` fallback.
- `RadioGroupField` is a real `<fieldset>`/`<legend>` group with per-option
  descriptions; `SwitchField` is the boolean toggle. Both wire `aria-invalid`,
  `aria-describedby` and `role="alert"` the same way as the text fields.
- `FormSection` groups a long form into a named, responsive grid. Fields that
  need the full width opt in with `className="sm:col-span-2"`.
- `SortableList` reorders a **client-owned draft** (line items, pipeline stages,
  saved views): native drag, ArrowUp/ArrowDown/Home/End on the grip, and
  explicit up/down buttons for touch. Never use it on a paginated server list.
- `OtpField` is the segmented six-digit input used by the email-code and
  authenticator sign-in flows. Paste, Backspace and arrow keys are handled.
- `AvatarCropperField` validates type/size, crops to a square JPEG at
  `outputSize` (512 px default), and only then calls `onChange(file)`. It is
  built on `react-easy-crop` (MIT, current v6) and wired into
  `Pages/Settings/Profile.tsx`. `react-advanced-cropper` is more configurable
  but GPL-3.0, so it is not the default for this commercial app.

A CRM record usually needs the whole set together:

```tsx
<FormSection title="Deal" description="Commercial terms and qualification.">
  <CurrencyField
    label="Amount"
    name="amount"
    value={form.data.amount}
    onChange={(value) => form.setData('amount', value)}
    currency="USD"
    min={0}
    error={errors.amount}
  />

  <DatePickerField
    label="Close date"
    name="close_date"
    value={form.data.close_date}
    onChange={(value) => form.setData('close_date', value)}
    min="2026-01-01"
    error={errors.close_date}
  />

  <FilterSelectField
    label="Account"
    name="account_id"
    value={form.data.account_id}
    onChange={(value) => form.setData('account_id', value)}
    // Imported from Modules/Deals/hooks/useAccounts.ts — network I/O never
    // lives in a page or a component (architecture invariant 1).
    fetcher={searchAccounts}
    // options={[selectedAccount]} // label seed on an edit form
    minQueryLength={2}
    error={errors.account_id}
  />

  <TagsField
    label="Segments"
    name="segments"
    value={form.data.segments}
    onChange={(value) => form.setData('segments', value)}
    error={errors.segments}
  />

  <RadioGroupField
    label="Stage"
    name="stage"
    value={form.data.stage}
    onChange={(value) => form.setData('stage', value)}
    options={[
      { label: 'Qualified', value: 'qualified' },
      { label: 'Proposal', value: 'proposal' },
      { label: 'Won', value: 'won' },
    ]}
    error={errors.stage}
  />

  <SwitchField
    label="Renewal reminder"
    name="renewal_reminder"
    checked={form.data.renewal_reminder}
    onChange={(value) => form.setData('renewal_reminder', value)}
    description="Send the owner a task 30 days before the close date."
  />
</FormSection>
```

Form overlays are `FormDialog` / `FormSheet` from the same barrel. Both take the
`form` returned by `useZodForm`, an `errors` map, and `onSubmit`; they own the
native `<form>`, submit/cancel row and dirty-close guard. Use them instead of
hand-wiring a `Dialog` around a form.

```tsx
<FormDialog
  open={open}
  onOpenChange={setOpen}
  title="Edit member"
  form={form}
  errors={errors}
  onSubmit={() => submit(() => form.put(`/members/${member.uuid}`))}
  submitLabel="Save changes"
>
  <TextField
    label="Name"
    name="name"
    value={form.data.name}
    onChange={(value) => form.setData('name', value)}
    error={errors.name}
  />
</FormDialog>
```

- `DateField` and `DatePickerField` both keep ISO `YYYY-MM-DD` on the wire.
  Use `DatePickerField` when a calendar is expected; keep `DateField` when the
  native picker is better (compact filters, mobile-only screens).
- `PhoneField` stores E.164 once valid and keeps an **international draft**
  (`+3519123456789`) while incomplete — never a bare national string, so the
  country is never guessed. It carries the country picker (flag + calling code)
  and drops one more digit when a backspace lands on a separator, so a formatted
  value can always be edited back to empty. The field itself shows the
  **national** format (`912 345 678`) with the example of the selected country
  as its placeholder; the prefix lives in the picker next to the flag, so it is
  never printed twice. Typing a `+351`/`00351` prefix moves the picker to that
  country. Validation names the country and its example ("Too many digits for
  Portugal. Example: 912 345 678.") instead of a generic message.
- Dropzones create one object URL per file identity and revoke it on
  replace/remove/unmount (`useFilePreviews`). `ImagesDropzone` reorders with
  native drag **and** ArrowLeft/ArrowRight buttons for touch and keyboard.
- `AppButton` and `FormActions` are inside a native `<form>`; the submit button
  does not need an `onSubmit` prop.

## Server errors

The 422 bag is already in `errors`:

```ts
const onSubmit = (event: React.FormEvent) => {
  event.preventDefault();
  submit(() =>
    form.post('/users', {
      preserveScroll: true,
      onError: () => focusFirstError(),
    }),
  );
};
```

`FormErrorSummary` renders every key and focuses the matching `name`; inline
errors remain next to the field.

## Accessibility

- Every field gets a generated `useId` id, `<label for>`, `aria-describedby` and
  `aria-invalid`.
- Errors use `role="alert"`; the summary is announced once.
- Icon-only controls need `aria-label`.
- Focus is never removed; reduced-motion is respected by `AppButton`.

## Gap matrix vs the Vue reference kit (`resources/js-argenis-hub`)

The reference app is Vue 3.5 + TanStack Form; do not copy its SFCs. Port the
behaviour to React/Base UI only when a real screen needs it.

| Capability                                   | AquaShield today                                            | Reference kit                                         | Status                    |
| -------------------------------------------- | ----------------------------------------------------------- | ----------------------------------------------------- | ------------------------- |
| Form engine                                  | Inertia `useForm` + Zod (`useZodForm`)                      | TanStack Form + Standard Schema/Zod (`useAppForm`)    | Decision, not a component |
| Generic field wrapper bound to form meta     | `FieldShell` (render-prop) + controlled fields              | `AppField` + TanStack field API                       | Done (different engine)   |
| Field-level `isTouched` / `isValid`          | No (merged `errors`; `form.isDirty` exists on the form)     | Yes                                                   | P1, blocked on the engine |
| Searchable multi-select                      | **`FilterSelectField`** + `MultiSelectField` (local/remote) | `FilterSelect` with remote fetch, virtual list, multi | Done (no virtualiser yet) |
| Tags / free-form `string[]` input            | **`TagsField`**                                             | `TagsField`                                           | Done                      |
| Currency with minor units                    | **`CurrencyField`**                                         | `CurrencyInput`                                       | Done                      |
| Avatar cropper                               | **`AvatarCropperField`**                                    | `AvatarCropperInput`                                  | Done                      |
| Form dialog / sheet with dirty guard         | **`FormDialog` / `FormSheet`**                              | `FormDialog` / `FormSheet`                            | Done                      |
| Calendar date picker                         | **`DatePickerField`** (+ native `DateField`)                | `DatePickerInput`                                     | Done                      |
| OTP input                                    | **`OtpField` implemented and wired into Login**             | `vue-input-otp` (auth screens)                        | Done                      |
| Radio group / switch                         | **`RadioGroupField` / `SwitchField`**                       | shadcn-vue primitives                                 | Done                      |
| Sortable arbitrary list                      | **`SortableList`** (drag + keyboard + touch buttons)        | `SortableList`                                        | Done                      |
| Form section / long-form layout              | **`FormSection`**                                           | `FieldSet` / `FieldLegend`                            | Done                      |
| Rich text                                    | No                                                          | `RichTextInput` (Tiptap; deliberately lazy)           | P2, dependency decision   |
| Form wizard / stepper                        | No                                                          | `Stepper` UI primitive                                | P2, needs a real wizard   |
| Repeater / line items                        | `SortableList` is the primitive; no field-group wrapper     | No                                                    | P2, add when quotes land  |
| Address, phone, signature, file/image upload | Yes                                                         | Yes                                                   | —                         |

`useZodForm` returns Inertia's form, so `form.isDirty` and
`form.processing` are available and the overlays use them. Per-field
`isTouched` / `isValid` metadata is still missing; use the field-level
`touch()` / `errors` contract unless the engine moves to TanStack Form.

`DatePickerField` adds `react-day-picker` and `date-fns`; the `Calendar`
primitive is committed at `Components/ui/calendar.tsx`, so its class map and
token mapping are editable like every other shadcn primitive. The other new
fields add no runtime library: `FilterSelectField` composes the existing
Popover + Command, `TagsField` is a controlled input, `CurrencyField` uses
`Intl.NumberFormat`, and `SortableList` uses native drag plus buttons.

## UI primitives vs the reference

Only the form-relevant primitives are ported. `calendar`, `radio-group` and
`switch` are new in `Components/ui/`; the rest of the reference's `ui/` folder
is either already covered by a different primitive or is not form kit:

- Covered: `combobox` → `FilterSelectField` (Popover + Command), `field` →
  `FieldShell`, `input-otp` / `pin-input` → `OtpField`, `number-field` →
  `NumberField`, `tags-input` → `TagsField`, `pagination` →
  `Components/DataTable/Paginator`, `sidebar` → `AppSidebar`, `sonner` →
  deliberately not added (one notification system; inline errors + Dialog).
- Not form kit, still unported: `accordion`, `alert`, `alert-dialog`, `badge`,
  `breadcrumb`, `button-group`, `collapsible`, `drawer`, `empty`, `item`,
  `native-select`, `navigation-menu`, `progress`, `range-calendar`,
  `scroll-area`, `separator`, `slider`, `spinner`, `stepper`, `toggle`,
  `toggle-group`. Add them only when a screen needs them; `stepper` is the
  natural next one for a multi-step wizard.
