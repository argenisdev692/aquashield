# Form kit

One engine, one field contract, and PrimeVue 5 primitives underneath. The kit
is presentation + validation wiring; the page owns the endpoint, the schema and
the submit call.

| Piece              | What it owns                                                        |
| ------------------ | ------------------------------------------------------------------- |
| `useZodForm`       | Inertia form state, Zod validation, server-error merge, error focus |
| `FormErrorSummary` | Submit-time summary, `role="alert"`, links that focus each control  |
| `FieldShell`       | Label / hint / error ids and `aria-describedby`                     |
| `TextField`        | Single-line text, email, search, URL, tel                           |
| `PasswordField`    | `InputPassword` (v5) with a real show/hide button                   |
| `TextareaField`    | Multi-line text                                                     |
| `SelectField`      | Single select, optional filter and clear                            |
| `DateField`        | Date picker; the model is ISO `YYYY-MM-DD`                          |
| `NumberField`      | Numeric / currency input                                            |
| `PhoneField`       | libphonenumber-js formatting; the model is E.164                    |
| `CheckboxField`    | Binary checkbox with an inline label                                |
| `SignatureField`   | Canvas signature + upload fallback; the model is a PNG `File`       |
| `FileDropzone`     | One file + name / size / type preview                               |
| `ImageDropzone`    | One image + object-URL preview                                      |
| `ImagesDropzone`   | Image array, previews, drag reorder, keyboard move buttons          |
| `AppButton`        | Hover sheen + 1px lift, reduced-motion safe                         |
| `FormActions`      | Submit / cancel row                                                 |

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
- Object URLs are revoked on replace, remove and unmount.
- `ImagesDropzone` reorders with native HTML5 drag **and** left/right buttons.
  The buttons are the keyboard path (WCAG 2.2 SC 2.5.7); moves are announced
  through a polite live region. `vuedraggable@2` is Vue 2-only and is not used.

---

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
