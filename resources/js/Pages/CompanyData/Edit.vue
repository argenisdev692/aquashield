<script setup lang="ts">
/**
 * Company profile — the one screen for the CompanyData singleton.
 *
 * There is no list, no create and no delete: the module provisions exactly one
 * row and replaces it in place. So the set is `Edit` alone, the read arrives as
 * an Inertia prop (no second cache owner, no skeleton flash) and the write goes
 * through the form kit, which is the repo's single validation path.
 *
 * The record is ~30 columns, so the page is grouped into sections that match
 * how the data is consumed: identity (public site), legal & billing (invoices),
 * address (maps), online presence (socials) and brand assets.
 *
 * Assets are a second write path on purpose: a file cannot ride the JSON form
 * PUT without turning the whole profile into a multipart request, and an upload
 * that fails should not discard the text edits the user already made. They save
 * immediately and answer with the refreshed URLs.
 */
import { computed, ref, useTemplateRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import {
    AppButton,
    FormErrorSummary,
    ImageDropzone,
    PhoneField,
    SignatureField,
    TextField,
    TextareaField,
} from '@/Components/Forms';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useZodForm } from '@/lib/forms';
import {
    CompanyAssetRequestError,
    useCompanyAssets,
    type CompanyAssetKind,
} from '@/Modules/CompanyData/composables/useCompanyAssets';
import {
    companyProfileSchema,
    toCompanyProfileForm,
} from '@/Modules/CompanyData/schemas/companyProfile';
import SecondaryButton from '@/volt/SecondaryButton.vue';

defineOptions({
    layout: [AppLayout, { title: 'Company profile' }],
});

const props = defineProps<{
    company: Modules.CompanyData.Application.Dtos.CompanyDataDto;
    /** Resolved asset URLs (public for logos, signed for the signature). */
    assets: Modules.CompanyData.Application.Dtos.CompanyAssetsDto;
    /** VIEW is enough to open the screen; UPDATE is required to submit. */
    canUpdate: boolean;
}>();

const toast = useToast();
const confirm = useConfirm();
const formEl = useTemplateRef<HTMLFormElement>('companyForm');

const { form, errors, submit, touch, clearError } = useZodForm({
    initial: toCompanyProfileForm(props.company),
    schema: companyProfileSchema,
    formEl,
});

const onSubmit = (): void => {
    submit(() =>
        form.put('/company-data', {
            // The server redirects back here with fresh props and, on a 422, the
            // error bag that `useZodForm` merges into `errors` (server wins).
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Company profile saved',
                    detail: 'The public site, emails and invoices now use these details.',
                    life: 4000,
                });
            },
        }),
    );
};

/* ------------------------------------------------------------------ assets */

type LogoKind = Exclude<CompanyAssetKind, 'signature'>;

interface LogoSlot {
    kind: LogoKind;
    label: string;
    url: string | null;
}

const assets = ref<Modules.CompanyData.Application.Dtos.CompanyAssetsDto>(props.assets);
const logoFiles = ref<Record<LogoKind, File | null>>({ logo: null, logo_white: null, mark: null });
const signatureFile = ref<File | null>(null);

const { pending, upload, remove } = useCompanyAssets();

const logoSlots = computed<LogoSlot[]>(() => [
    { kind: 'logo', label: 'Primary logo', url: assets.value.logo_url },
    { kind: 'logo_white', label: 'White logo', url: assets.value.logo_white_url },
    { kind: 'mark', label: 'Mark', url: assets.value.mark_url },
]);

const failed = (caught: unknown): void => {
    toast.add({
        severity: 'error',
        summary: 'Asset not saved',
        detail:
            caught instanceof CompanyAssetRequestError
                ? caught.message
                : 'The upload failed. Try again.',
        life: 5000,
    });
};

const onLogoSelected = async (kind: LogoKind, file: File | null): Promise<void> => {
    logoFiles.value[kind] = file;

    if (file === null) {
        return;
    }

    try {
        assets.value = await upload(kind, file);
        logoFiles.value[kind] = null;
        toast.add({ severity: 'success', summary: 'Logo updated', life: 3000 });
    } catch (caught: unknown) {
        failed(caught);
    }
};

const saveSignature = async (): Promise<void> => {
    const file = signatureFile.value;

    if (file === null) {
        return;
    }

    try {
        assets.value = await upload('signature', file);
        signatureFile.value = null;
        toast.add({ severity: 'success', summary: 'Signature saved', life: 3000 });
    } catch (caught: unknown) {
        failed(caught);
    }
};

const deleteSignature = async (): Promise<void> => {
    try {
        assets.value = await remove('signature');
        signatureFile.value = null;
        toast.add({ severity: 'success', summary: 'Signature removed', life: 3000 });
    } catch (caught: unknown) {
        failed(caught);
    }
};

const confirmSignatureRemoval = (): void => {
    confirm.require({
        header: 'Remove signature',
        message: 'The stored signature will be deleted. You can upload a new one at any time.',
        acceptLabel: 'Remove',
        rejectLabel: 'Keep',
        accept: () => {
            void deleteSignature();
        },
    });
};
</script>

<template>
    <Head title="Company profile" />

    <p class="max-w-3xl text-sm text-surface-500 dark:text-surface-400">
        These details feed the public site, outgoing email and generated invoices. Blank fields are
        omitted rather than rendered empty.
    </p>

    <form ref="companyForm" class="mt-6 flex flex-col gap-5" novalidate @submit.prevent="onSubmit">
        <FormErrorSummary :errors="errors" :form-el="formEl" />

        <div
            v-if="!props.canUpdate"
            class="rounded-lg border border-aq-shell-line bg-surface-100 px-4 py-3 text-sm text-surface-700 dark:bg-surface-800 dark:text-surface-200"
        >
            <p class="font-medium">Read-only</p>
            <p class="mt-1">
                Your role can view the company profile but not change it. Ask an administrator for
                edit access.
            </p>
        </div>

        <fieldset :disabled="!props.canUpdate" class="flex min-w-0 flex-col gap-5">
            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 dark:bg-surface-900"
            >
                <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                    Identity
                </h2>
                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    How the company introduces itself on the site and in signatures.
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <TextField
                        v-model="form.company_name"
                        name="company_name"
                        label="Company name"
                        autocomplete="organization"
                        :maxlength="255"
                        required
                        :error="errors.company_name"
                        @update:model-value="clearError('company_name')"
                        @blur="touch('company_name')"
                    />

                    <TextField
                        v-model="form.name"
                        name="name"
                        label="Contact name"
                        autocomplete="name"
                        :maxlength="255"
                        required
                        :error="errors.name"
                        @update:model-value="clearError('name')"
                        @blur="touch('name')"
                    />

                    <TextField
                        v-model="form.email"
                        name="email"
                        label="Email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        :maxlength="255"
                        required
                        :error="errors.email"
                        @update:model-value="clearError('email')"
                        @blur="touch('email')"
                    />

                    <PhoneField
                        v-model="form.phone"
                        name="phone"
                        label="Phone"
                        :error="errors.phone"
                        @update:model-value="clearError('phone')"
                        @blur="touch('phone')"
                    />

                    <TextareaField
                        v-model="form.description"
                        name="description"
                        label="Description"
                        hint="A short summary shown on the public site."
                        :rows="3"
                        class="sm:col-span-2"
                        :error="errors.description"
                        @update:model-value="clearError('description')"
                        @blur="touch('description')"
                    />
                </div>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 dark:bg-surface-900"
            >
                <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                    Legal &amp; billing
                </h2>
                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    Tax identifiers and bank details printed on invoices.
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <TextField
                        v-model="form.nif_nipc"
                        name="nif_nipc"
                        label="NIF / NIPC"
                        :maxlength="20"
                        :error="errors.nif_nipc"
                        @update:model-value="clearError('nif_nipc')"
                        @blur="touch('nif_nipc')"
                    />

                    <TextField
                        v-model="form.nie"
                        name="nie"
                        label="NIE"
                        :maxlength="20"
                        :error="errors.nie"
                        @update:model-value="clearError('nie')"
                        @blur="touch('nie')"
                    />

                    <TextField
                        v-model="form.bank_beneficiary"
                        name="bank_beneficiary"
                        label="Account holder"
                        autocomplete="name"
                        :maxlength="255"
                        :error="errors.bank_beneficiary"
                        @update:model-value="clearError('bank_beneficiary')"
                        @blur="touch('bank_beneficiary')"
                    />

                    <TextField
                        v-model="form.bank_name"
                        name="bank_name"
                        label="Bank"
                        :maxlength="255"
                        :error="errors.bank_name"
                        @update:model-value="clearError('bank_name')"
                        @blur="touch('bank_name')"
                    />

                    <TextField
                        v-model="form.bank_iban"
                        name="bank_iban"
                        label="IBAN"
                        hint="Spaces are allowed."
                        :maxlength="34"
                        :error="errors.bank_iban"
                        @update:model-value="clearError('bank_iban')"
                        @blur="touch('bank_iban')"
                    />

                    <TextField
                        v-model="form.bank_bic"
                        name="bank_bic"
                        label="BIC / SWIFT"
                        :maxlength="11"
                        :error="errors.bank_bic"
                        @update:model-value="clearError('bank_bic')"
                        @blur="touch('bank_bic')"
                    />

                    <TextareaField
                        v-model="form.invoice_notes"
                        name="invoice_notes"
                        label="Invoice notes"
                        hint="Payment terms or a thank-you line, added to every invoice."
                        :rows="3"
                        class="sm:col-span-2"
                        :error="errors.invoice_notes"
                        @update:model-value="clearError('invoice_notes')"
                        @blur="touch('invoice_notes')"
                    />
                </div>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 dark:bg-surface-900"
            >
                <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                    Address
                </h2>
                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    Postal address and the exact coordinates used for maps and routing.
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <TextField
                        v-model="form.address"
                        name="address"
                        label="Street"
                        autocomplete="address-line1"
                        class="sm:col-span-2"
                        :error="errors.address"
                        @update:model-value="clearError('address')"
                        @blur="touch('address')"
                    />

                    <TextField
                        v-model="form.address_2"
                        name="address_2"
                        label="Street, line 2"
                        autocomplete="address-line2"
                        class="sm:col-span-2"
                        :error="errors.address_2"
                        @update:model-value="clearError('address_2')"
                        @blur="touch('address_2')"
                    />

                    <TextField
                        v-model="form.zip_code"
                        name="zip_code"
                        label="ZIP / postal code"
                        autocomplete="postal-code"
                        :maxlength="10"
                        :error="errors.zip_code"
                        @update:model-value="clearError('zip_code')"
                        @blur="touch('zip_code')"
                    />

                    <TextField
                        v-model="form.city"
                        name="city"
                        label="City"
                        autocomplete="address-level2"
                        :maxlength="255"
                        :error="errors.city"
                        @update:model-value="clearError('city')"
                        @blur="touch('city')"
                    />

                    <TextField
                        v-model="form.state"
                        name="state"
                        label="State / region"
                        autocomplete="address-level1"
                        :maxlength="255"
                        :error="errors.state"
                        @update:model-value="clearError('state')"
                        @blur="touch('state')"
                    />

                    <TextField
                        v-model="form.country"
                        name="country"
                        label="Country"
                        autocomplete="country-name"
                        :maxlength="255"
                        :error="errors.country"
                        @update:model-value="clearError('country')"
                        @blur="touch('country')"
                    />

                    <TextField
                        v-model="form.country_code"
                        name="country_code"
                        label="Country code"
                        hint="Two-letter ISO code, e.g. US."
                        autocomplete="country"
                        :maxlength="2"
                        :error="errors.country_code"
                        @update:model-value="clearError('country_code')"
                        @blur="touch('country_code')"
                    />

                    <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                        <TextField
                            v-model="form.latitude"
                            name="latitude"
                            label="Latitude"
                            hint="Decimal degrees, optional."
                            inputmode="decimal"
                            autocomplete="off"
                            :error="errors.latitude"
                            @update:model-value="clearError('latitude')"
                            @blur="touch('latitude')"
                        />

                        <TextField
                            v-model="form.longitude"
                            name="longitude"
                            label="Longitude"
                            hint="Decimal degrees, optional."
                            inputmode="decimal"
                            autocomplete="off"
                            :error="errors.longitude"
                            @update:model-value="clearError('longitude')"
                            @blur="touch('longitude')"
                        />
                    </div>
                </div>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 dark:bg-surface-900"
            >
                <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                    Online presence
                </h2>
                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    Full URLs, including <span class="font-mono">https://</span>. Empty links are
                    not rendered.
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <TextField
                        v-model="form.website"
                        name="website"
                        label="Website"
                        type="url"
                        inputmode="url"
                        autocomplete="url"
                        :maxlength="255"
                        class="sm:col-span-2"
                        :error="errors.website"
                        @update:model-value="clearError('website')"
                        @blur="touch('website')"
                    />

                    <TextField
                        v-model="form.linkedin_link"
                        name="linkedin_link"
                        label="LinkedIn"
                        type="url"
                        inputmode="url"
                        :maxlength="255"
                        :error="errors.linkedin_link"
                        @update:model-value="clearError('linkedin_link')"
                        @blur="touch('linkedin_link')"
                    />

                    <TextField
                        v-model="form.facebook_link"
                        name="facebook_link"
                        label="Facebook"
                        type="url"
                        inputmode="url"
                        :maxlength="255"
                        :error="errors.facebook_link"
                        @update:model-value="clearError('facebook_link')"
                        @blur="touch('facebook_link')"
                    />

                    <TextField
                        v-model="form.instagram_link"
                        name="instagram_link"
                        label="Instagram"
                        type="url"
                        inputmode="url"
                        :maxlength="255"
                        :error="errors.instagram_link"
                        @update:model-value="clearError('instagram_link')"
                        @blur="touch('instagram_link')"
                    />

                    <TextField
                        v-model="form.twitter_link"
                        name="twitter_link"
                        label="X / Twitter"
                        type="url"
                        inputmode="url"
                        :maxlength="255"
                        :error="errors.twitter_link"
                        @update:model-value="clearError('twitter_link')"
                        @blur="touch('twitter_link')"
                    />

                    <TextField
                        v-model="form.tiktok_link"
                        name="tiktok_link"
                        label="TikTok"
                        type="url"
                        inputmode="url"
                        :maxlength="255"
                        :error="errors.tiktok_link"
                        @update:model-value="clearError('tiktok_link')"
                        @blur="touch('tiktok_link')"
                    />
                </div>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 dark:bg-surface-900"
            >
                <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                    Brand assets
                </h2>
                <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
                    PNG, JPG or WebP up to 2 MB. Every upload is re-encoded, replaces the previous
                    file and goes live immediately — saving the form is not required.
                </p>

                <div class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="slot in logoSlots" :key="slot.kind" class="flex flex-col gap-3">
                        <div
                            class="flex h-16 items-center justify-center rounded-lg border border-aq-shell-line bg-surface-50 p-2 dark:bg-surface-800"
                        >
                            <img
                                v-if="slot.url !== null"
                                :src="slot.url"
                                alt=""
                                class="max-h-12 w-auto object-contain"
                            />
                        </div>

                        <ImageDropzone
                            :model-value="logoFiles[slot.kind]"
                            :label="slot.label"
                            :max-size-mb="2"
                            :disabled="pending !== null"
                            @update:model-value="onLogoSelected(slot.kind, $event)"
                        />
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <div class="flex flex-col gap-3">
                        <SignatureField
                            v-model="signatureFile"
                            label="Authorised signature"
                            :current-url="assets.signature_url"
                            :disabled="!props.canUpdate"
                        />

                        <div class="flex flex-wrap items-center gap-2">
                            <AppButton
                                type="button"
                                :disabled="signatureFile === null || pending !== null"
                                :loading="pending === 'signature'"
                                @click="saveSignature"
                            >
                                Save signature
                            </AppButton>

                            <SecondaryButton
                                v-if="assets.signature_url !== null"
                                type="button"
                                :disabled="pending !== null"
                                @click="confirmSignatureRemoval"
                            >
                                Remove signature
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </section>
        </fieldset>

        <div
            class="sticky bottom-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-aq-shell-line bg-surface-0/95 p-4 shadow-sm backdrop-blur dark:bg-surface-900/95"
        >
            <p class="text-sm text-surface-500 dark:text-surface-400">
                Text fields go live when you save; logos and signature update immediately.
            </p>

            <AppButton v-if="props.canUpdate" type="submit" :loading="form.processing">
                Save profile
            </AppButton>
        </div>
    </form>
</template>
