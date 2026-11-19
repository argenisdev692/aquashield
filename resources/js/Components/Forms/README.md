# Form kit

One engine, one field contract, and PrimeVue 5 primitives underneath. The kit
is presentation + validation wiring; the page owns the endpoint, the schema and
the submit call.

| Piece                      | What it owns                                                         |
| -------------------------- | -------------------------------------------------------------------- |
| `useZodForm`               | Inertia form state, Zod validation, server-error merge, error focus  |
| `FormErrorSummary`         | Submit-time summary, `role="alert"`, links that focus each control   |
| `FieldShell`               | Label / hint / error ids and `aria-describedby`                      |
| `TextField`                | Single-line text, email, search, URL, tel                            |
| `AddressAutocompleteField` | Google Places address search; emits the canonical address fields     |
| `PasswordField`            | `InputPassword` (v5) with a real show/hide button                    |
| `OtpField`                 | Segmented one-time code; paste, arrows and autofill across the group |
| `TextareaField`            | Multi-line text                                                      |
| `RadioGroupField`          | 2–5 exclusive options as radios or cards                             |
| `SelectField`              | Single select, optional filter and clear                             |
| `MultiSelectField`         | Multiple values from a known list; the model is always an array      |
| `LookupField`              | Async record picker; the model is the foreign key                    |
| `DateField`                | Date picker; the model is ISO `YYYY-MM-DD`                           |
| `DateRangeField`           | From/to ISO dates, optional relative presets                         |
| `DateTimeField`            | Local date + time; the model is `YYYY-MM-DDTHH:mm`                   |
| `TimeField`                | Time of day; the model is `HH:mm`                                    |
| `NumberField`              | Numeric / currency input                                             |
| `SliderField`              | Bounded number with the current value shown                          |
| `PhoneField`               | libphonenumber-js formatting; the model is E.164                     |
| `CheckboxField`            | Binary checkbox with an inline label                                 |
| `SwitchField`              | Boolean setting with the label beside the switch                     |
| `RepeaterField`            | Repeatable rows with add / remove / keyboard reorder                 |
| `FormSection`              | Titled grid that groups fields in a long record form                 |
| `SignatureField`           | Canvas signature + upload fallback; the model is a PNG `File`        |
| `FileDropzone`             | One file + name / size / type preview                                |
| `ImageDropzone`            | One image + object-URL preview; PNG/JPEG/WebP by default             |
| `AvatarCropField`          | Profile photo: dropzone + square/circle cropper; cropped `File`      |
| `ImagesDropzone`           | Image array, previews, drag reorder, keyboard move buttons           |
| `AppButton`                | Hover sheen + 1px lift, reduced-motion safe                          |
| `FormActions`              | Submit / cancel row                                                  |

Import from `@/Components/Forms`, never from a deep path.

---

## The engine

```ts
const schema = z.object({
  name: z.string().min(2, 'Enter your full name'),
  email: z.email('Enter a valid email address'),
  phone: phoneSchema('US'),
  starts_on: z.string().min(1, 'Choose a start date'),
});

const formRef = useTemplateRef<HTMLFormElement>('form');

const { form, formEl, errors, submit, touch, clearError, focusFirstError } = useZodForm({
  initial: { name: '', email: '', phone: '', starts_on: '' },
  schema,
  formEl: formRef,
});

const onSubmit = (): void => {
  submit(() => form.post('/members'));
};
```

```vue
<form ref="form" novalidate @submit.prevent="onSubmit">
    <FormErrorSummary :errors="errors" :form-el="formEl" />

    <TextField
        v-model="form.name"
        name="name"
        label="Name"
        required
        :error="errors.name"
        @update:model-value="clearError('name')"
        @blur="touch('name')"
    />

    <PhoneField
        v-model="form.phone"
        name="phone"
        label="Phone"
        required
        :error="errors.phone"
        @update:model-value="clearError('phone')"
        @blur="touch('phone')"
    />

    <AppButton type="submit" :loading="form.processing">Create member</AppButton>
</form>
```

Contract:

- `errors` merges client and server errors; the server wins.
- `touch(name)` validates on blur; `clearError(name)` clears on input.
- `submit(action)` validates everything, focuses the first
  `[aria-invalid="true"]` control, and only then runs the Inertia call.
- `formEl` is the page's `useTemplateRef` — the 3.5 template-ref rule, not a
  matching-name string ref.

`useZodForm` deliberately uses Inertia's `useForm` rather than a second form
state library: the Laravel 422 bag is the source of server errors, and two state
owners is how the old pages drifted. The PrimeVue Forms resolver is therefore
not registered in `app.ts`.

---

## Fields

```vue
<SelectField
  v-model="form.status"
  name="status"
  label="Status"
  :options="[
    { label: 'Active', value: 'active' },
    { label: 'Invited', value: 'invited' },
  ]"
  filter
  show-clear
  :error="errors.status"
  @update:model-value="clearError('status')"
  @blur="touch('status')"
/>

<DateField
  v-model="form.starts_on"
  name="starts_on"
  label="Starts on"
  :min-date="new Date()"
  :error="errors.starts_on"
  @update:model-value="clearError('starts_on')"
  @blur="touch('starts_on')"
/>

<NumberField v-model="form.seats" name="seats" label="Seats" :min="1" :max="500" :error="errors.seats" />
```

`DateField` converts the picker's `Date` to `YYYY-MM-DD` and back, so the server
never receives a timezone-shifted timestamp. `PhoneField` stores E.164 once the
number is valid and keeps the draft while it is incomplete.

```vue
<RadioGroupField
  v-model="form.priority"
  name="priority"
  label="Priority"
  variant="cards"
  :options="[
    { label: 'Low', value: 'low', description: 'Follow up this quarter' },
    { label: 'Normal', value: 'normal', description: 'Respond within a week' },
    { label: 'High', value: 'high', description: 'Respond within 24 hours' },
  ]"
  :error="errors.priority"
  @update:model-value="clearError('priority')"
  @blur="touch('priority')"
/>

<MultiSelectField
  v-model="form.labels"
  name="labels"
  label="Labels"
  filter
  :options="labelOptions"
  :error="errors.labels"
  @update:model-value="clearError('labels')"
  @blur="touch('labels')"
/>

<SwitchField
  v-model="form.do_not_call"
  name="do_not_call"
  label="Do not call"
  hint="Suppresses outbound calls and SMS for this contact."
/>

<SliderField
  v-model="form.probability"
  name="probability"
  label="Probability"
  :min="0"
  :max="100"
  :step="5"
  :format-value="(value) => `${value}%`"
/>
```

`MultiSelectField` is generic over the value type and always normalises the
model to `T[]`, so a `string[]` field stays `string[]` — a page cannot
accidentally post `null` or a comma-joined string. `RadioGroupField` is the
right control up to about five options; beyond that, use `SelectField`.

### Record lookups

`LookupField` is the async picker for a foreign key — a contact, company, owner
or deal. The page supplies the search function from a composable; the component
never performs network I/O itself.

```ts
// src/Modules/Contacts/composables/useContactSearch.ts
export function useContactSearch() {
  return async (query: string, signal: AbortSignal): Promise<LookupOption[]> => {
    const response = await fetch(`/api/contacts?search=${encodeURIComponent(query)}`, {
      signal,
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error('Contact search failed');
    }

    return (await response.json()) as LookupOption[];
  };
}
```

```vue
<LookupField
  v-model="form.contact_id"
  name="contact_id"
  label="Primary contact"
  :search="searchContacts"
  :selected-label="contact?.name"
  :error="errors.contact_id"
  @update:model-value="clearError('contact_id')"
  @select="onContactSelected"
  @blur="touch('contact_id')"
/>
```

- `search` receives an `AbortSignal`; a new keystroke aborts the previous
  request, so the last query always wins.
- `LookupOption.group` renders a section heading, so one search box can return
  accounts, contacts and deals in labelled blocks without a second control.
- The model is only the id. `selectedLabel` paints the current value on an edit
  form without a second request; `@select` gives the full option when the page
  wants to copy more fields.
- Debounce is 250 ms and the minimum query length is 2 characters; both are
  props. Do not put the fetch in the page — the composable rule is enforced by
  `FrontendBoundariesTest`.

### Ranges and time

```vue
<DateRangeField
  v-model:from="form.starts_on"
  v-model:to="form.ends_on"
  name="period"
  label="Contract period"
  :error="errors.starts_on"
/>

<DateRangeField
  v-model:from="filters.date_from"
  v-model:to="filters.date_to"
  name="created_period"
  label="Created"
  presets
/>

<DateTimeField v-model="form.starts_at" name="starts_at" label="Starts at" />
<TimeField v-model="form.preferred_time" name="preferred_time" label="Best time to call" />
```

`DateRangeField` keeps both ends as ISO dates; `date_from > date_to` is rejected
by the picker, and the server still validates. `presets` adds Last 7/30/90 days
and This quarter. `DateTimeField` stores local wall-clock time with no zone
suffix — a 09:00 meeting stays 09:00 for every user.

### Repeatable rows

```ts
interface QuoteItem {
  key: string; // local row id, stripped before the request
  product_id: string | number | null;
  quantity: number;
  unit_price: number;
}

const newItem = (): QuoteItem => ({
  key: crypto.randomUUID(),
  product_id: null,
  quantity: 1,
  unit_price: 0,
});
```

```vue
<RepeaterField
  v-model="form.items"
  label="Line items"
  :create-row="newItem"
  :row-key="(row) => row.key"
  :min-rows="1"
  add-label="Add product"
  :error="errors.items"
>
  <template #default="{ row, index }">
    <div class="grid gap-3 sm:grid-cols-3">
      <LookupField
        v-model="row.product_id"
        :name="`items.${index}.product_id`"
        label="Product"
        :search="searchProducts"
      />
      <NumberField
        v-model="row.quantity"
        :name="`items.${index}.quantity`"
        label="Quantity"
        :min="1"
      />
      <NumberField
        v-model="row.unit_price"
        :name="`items.${index}.unit_price`"
        label="Unit price"
        mode="currency"
        currency="USD"
      />
    </div>
  </template>
</RepeaterField>
```

`RepeaterField` owns add, remove and the keyboard-accessible move buttons. It
does not clone the row object; `createRow` is the page's factory, so the shape
stays the page's type. `rowKey` must be stable — never call `randomUUID()` inside
the key function, or every render remounts the row and steals focus. Keep the
local key out of the payload the server receives.

### Sections

```vue
<FormSection title="Company" description="Legal entity and billing details">
  <TextField v-model="form.name" name="name" label="Name" required />
  <TextField v-model="form.tax_id" name="tax_id" label="Tax ID" />
  <TextField v-model="form.website" name="website" label="Website" type="url" />
</FormSection>

<FormSection title="Address" :columns="2" divided>
  <AddressAutocompleteField
    v-model="form.address"
    name="address"
    label="Address"
    :api-key="googleMapsApiKey"
  />
</FormSection>
```

`FormSection` is presentation only; a child that needs the full width uses
`class="sm:col-span-2"`.

### Google address autocomplete

```vue
<AddressAutocompleteField
  v-model="form.address"
  name="address"
  label="Address"
  :api-key="googleMapsApiKey"
  :error="errors.address"
  @update:model-value="clearError('address')"
  @select="onAddressSelected"
  @blur="touch('address')"
/>
```

```ts
const onAddressSelected = (selection: AddressSelection): void => {
  form.address = selection.address;
  form.zip_code = selection.zip_code;
  form.city = selection.city;
  form.state = selection.state;
  form.country = selection.country;
  form.latitude = selection.latitude;
  form.longitude = selection.longitude;
  // address_2 is intentionally not touched.
};
```

- Uses the Places API (New) `AutocompleteSuggestion` + `Place.fetchFields`; the
  legacy `google.maps.places.Autocomplete` is not available to new customers.
- The loader is lazy: the Maps JavaScript API is requested on first focus, not
  on page load.
- `apiKey` is a restricted **browser** key from `GOOGLE_MAPS_API_KEY`. Enable
  Maps JavaScript API and Places API (New) in the Google Cloud project.
- `includedRegionCodes` defaults to `['US']`, so the component is US-only
  without an extra prop. Pass another CLDR list only when a screen genuinely
  crosses a border.
- The listbox is a real combobox (`role`, `aria-expanded`,
  `aria-activedescendant`, arrow keys, Enter, Escape, outside click) and the
  status is announced through a polite live region.

---

## Dropzones

```vue
<FileDropzone
  v-model="form.contract"
  label="Contract"
  accept="application/pdf"
  :max-size-mb="10"
  :error="errors.contract"
/>

<ImageDropzone v-model="form.cover" label="Cover image" :max-size-mb="5" :error="errors.cover" />

<ImagesDropzone v-model="form.gallery" label="Gallery" :max-files="8" :max-size-mb="5" :error="errors.gallery" />
```

- All three use native `<input type="file">` inside a label, so the OS picker,
  keyboard activation and mobile sheet work without a custom click handler.
- `accept` and `maxSizeMb` are validated by `lib/files.ts`; the page should
  still validate server-side (MIME sniffing, size, count).
- `ImageDropzone` defaults to `image/png,image/jpeg,image/webp`, matching the
  Laravel `mimes` rules in this app. Pass `accept` only for a set the endpoint
  actually accepts.
- Object URLs are revoked on replace, remove and unmount.
- `ImagesDropzone` reorders with native HTML5 drag **and** left/right buttons.
  The buttons are the keyboard path (WCAG 2.2 SC 2.5.7); moves are announced
  through a polite live region. `vuedraggable@2` is Vue 2-only and is not used.

### Profile photo

```vue
<AvatarCropField
  v-model="photo"
  label="Profile photo"
  :current-url="user.profile_photo_url"
  :max-size-mb="2"
  :error="photoError ?? undefined"
/>
```

- `vue-advanced-cropper` (already in `package.json`, Vue 3 branch) is the
  maintained equivalent of `react-easy-crop`; there is no official
  `vue-easy-crop` package. The dialog uses `CircleStencil` with a 1:1 ratio,
  zoom/rotate/reset controls and the same keyboard-reachable file input.
- The model is the **cropped** `File`; the raw original never leaves the
  browser. A 512 px square is re-encoded as WebP (PNG fallback) and checked
  against `maxSizeMb`.
- `currentUrl` shows the stored photo until a new crop is applied.
- The dropzone accepts exactly `image/png,image/jpeg,image/webp`, matching the
  Laravel `mimes` rule. The old `image/*` picker also offered AVIF, which the
  server rejects — that was one way a selected photo appeared not to upload.

---

## Signature

```vue
<SignatureField
  v-model="signature"
  label="Authorised signature"
  :current-url="assets.signature_url"
  :disabled="!canUpdate"
/>
```

- The canvas is pointer/pen input; the always-visible **Upload image instead**
  control is the keyboard and screen-reader path (WCAG 2.2 has no "draw"
  primitive, so an image file is the equivalent action).
- The model is a PNG `File` from `canvas.toBlob`, or the image the user chose.
- Pen colour is read from the canvas's computed `color`, so it follows the
  `text-surface-*` token and `.dark` instead of freezing a literal; it is
  re-read when the theme changes.
- `redraw()` on resize replays the strokes, so rotating a phone does not wipe a
  half-drawn signature.

## Server errors

```ts
const onSubmit = (): void => {
  submit(() =>
    form.post('/members', {
      preserveScroll: true,
      onError: () => {
        // `errors` already contains the 422 bag; focus the first field.
        void focusFirstError();
      },
    }),
  );
};
```

`FormErrorSummary` renders every key in `errors`; each item focuses the control
with the matching `name`. Inline errors remain next to the field, so a
screen-reader user gets both the summary and the local message.

---

## Buttons

`AppButton` composes Volt's `Button` with a hover-only sheen and a 1px lift.
It does not use Button's deprecated `label` or `loading` props: the label is the
default slot and the spinner is rendered inside it while `loading` is true.
`prefers-reduced-motion: reduce` removes both the sweep and the lift.

---

## Choosing the control

| Question                                          | Control                                                        |
| ------------------------------------------------- | -------------------------------------------------------------- |
| More than about five known options?               | `SelectField`                                                  |
| Two to five known options and the choice matters? | `RadioGroupField`                                              |
| Several values from a known list?                 | `MultiSelectField`                                             |
| A record that lives on the server?                | `LookupField`                                                  |
| An on/off setting that applies immediately?       | `SwitchField`                                                  |
| Accepting terms, or a value in a list of checks?  | `CheckboxField`                                                |
| A position in a range (probability, score)?       | `SliderField`                                                  |
| An exact number, quantity or amount of money?     | `NumberField`                                                  |
| A day? A period? A meeting? A time of day?        | `DateField` / `DateRangeField` / `DateTimeField` / `TimeField` |
| Repeating rows owned by the record (line items)?  | `RepeaterField`                                                |

---

## Deliberately outside the kit

These are the gaps a complex CRM will eventually hit. They are not stubs: each
one needs a dependency or an architecture decision, and a half version now would
be worse than the gap.

| Need                               | Why it is not here                                                                                                   | What to do when it lands                                                          |
| ---------------------------------- | -------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| Rich text (notes, email templates) | `Editor` is PrimeUI PRO and removed in PrimeVue 6. A real editor means adding Tiptap plus a sanitiser.               | Add a Tiptap-based `RichTextField`; never `v-html` without sanitising.            |
| Masked input (EIN, VAT, IBAN)      | PrimeVue 5 deprecates the `InputMask` component, and this `5.0.1` package does not export the replacement directive. | Add the directive once the package exports it, or a small tested mask composable. |
| Free-form tags                     | Needs the `InputTags` primitive, which is not in `src/volt/` yet.                                                    | `npx volt-vue add InputTags`, then a `TagsField` wrapper.                         |
| Colour (pipeline stage, label)     | v5 replaces `ColorPicker` with `InputColor`; no Volt wrapper yet.                                                    | Add `InputColor`, then a `ColorField`.                                            |
| Multi-step wizard                  | Volt `Stepper` exists, but a wizard owns per-step validation, navigation and draft persistence — a page pattern.     | Build it in `Pages/`, not in `Components/Forms`.                                  |

`NumberField` already covers money (`mode="currency"` plus `currency` and
`locale`); a separate `CurrencyField` would be the same component under a second
name.
