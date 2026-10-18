<script setup lang="ts">
import { computed, ref } from 'vue';
import { useHttp, usePage } from '@inertiajs/vue3';
import Button from '@/volt/Button.vue';

/**
 * Cookie-consent banner — the accept-only variant.
 *
 * The server owns the decision. `cookieConsent` is `null` unless this visitor
 * still owes an answer, so "is the banner showing" is a fact the backend
 * already sent rather than a guess made here from `document.cookie`. That is
 * also what lets the consent cookie be HttpOnly, which the package's own
 * Blade+JS banner cannot be.
 *
 * `useHttp()` rather than `router.post()`: this is not a page visit, and a
 * visit would re-render the whole page to hide a banner. It is also not the
 * raw network client that frontend/SKILL-ARCHITECTURE.md §4 invariant 1 bars
 * a page or a component from opening — `useHttp` is Inertia's own
 * non-navigating request client, so nothing here reaches around the app's data
 * layer the way `src/Modules/{ctx}/composables/` exists to prevent.
 */
const page = usePage();
const http = useHttp<Record<string, never>, { consented: boolean }>({});

const consent = computed(() => page.props.cookieConsent ?? null);

/** Set once the server has acknowledged the decision. */
const settled = ref(false);

/** True when the POST failed. The banner deliberately STAYS UP: consent that was never recorded is not consent. */
const failed = ref(false);

const visible = computed(() => consent.value !== null && !settled.value);

function accept(): void {
    const pending = consent.value;

    if (!pending || http.processing) {
        return;
    }

    failed.value = false;

    http.post(pending.endpoint, {
        onSuccess: () => {
            settled.value = true;
        },
        onHttpException: () => {
            failed.value = true;
        },
        onNetworkError: () => {
            failed.value = true;
        },
    }).catch(() => {
        // Both handlers above already put the failure in front of the user.
        // This catch exists only so the rejection does not surface as an
        // unhandled promise rejection — `useHttp` rethrows on every non-2xx.
    });
}
</script>

<template>
    <Transition
        enter-active-class="transition-[opacity,transform] duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition-[opacity,transform] duration-150 ease-in"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="visible"
            role="region"
            aria-label="Cookie consent"
            class="fixed inset-x-0 bottom-0 z-50 px-4 pb-4 sm:px-6 sm:pb-6"
        >
            <div
                class="mx-auto flex max-w-3xl flex-col gap-3 rounded-xl border border-surface-200 bg-surface-0/95 p-4 shadow-lg backdrop-blur-md sm:flex-row sm:items-center sm:justify-between dark:border-surface-700 dark:bg-surface-900/95"
            >
                <div>
                    <p class="text-sm text-surface-700 dark:text-surface-200">
                        {{ consent?.message }}
                    </p>
                    <p
                        v-if="failed"
                        role="status"
                        class="mt-1 text-xs text-surface-600 dark:text-surface-300"
                    >
                        We could not save your choice. Please try again.
                    </p>
                </div>

                <Button :disabled="http.processing" class="shrink-0" @click="accept">
                    {{ consent?.acceptLabel }}
                </Button>
            </div>
        </div>
    </Transition>
</template>
