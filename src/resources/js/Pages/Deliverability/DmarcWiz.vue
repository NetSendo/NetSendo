<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm, Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    existingDomains: { type: Array, default: () => [] },
    verifyTarget: { type: String, required: true },
    isLocalhost: { type: Boolean, default: false },
});

const form = useForm({
    domain: "",
    mailbox_id: null,
});

const step = ref(1);
const domainValid = ref(false);
const copiedField = ref(null);

// Computed CNAME values
const cnameHost = computed(() => `_netsendo.${form.domain}`);
const cnameTarget = computed(() => props.verifyTarget);

// Validate domain format
const validateDomain = () => {
    const pattern = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
    domainValid.value = pattern.test(form.domain.trim());
};

// Copy to clipboard
const copyToClipboard = async (text, fieldName) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = fieldName;
        setTimeout(() => {
            copiedField.value = null;
        }, 2000);
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};

// Submit domain
const submitDomain = () => {
    if (!domainValid.value) return;

    form.domain = form.domain.toLowerCase().trim();
    form.post(route("deliverability.domains.store"), {
        preserveScroll: true,
    });
};

// Go to next step
const nextStep = () => {
    if (step.value === 1 && domainValid.value) {
        // Submit directly - step 2 will be shown on DomainStatus page with correct CNAME values
        submitDomain();
    }
};
</script>

<template>
    <Head :title="$t('deliverability.dmarc_wiz.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('deliverability.index')"
                    class="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </Link>
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ $t("deliverability.dmarc_wiz.title") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("deliverability.dmarc_wiz.subtitle") }}
                    </p>
                </div>
            </div>

            <!-- Localhost Warning -->
            <div
                v-if="isLocalhost"
                class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20"
            >
                <div class="flex gap-3">
                    <svg
                        class="h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <div>
                        <h4
                            class="font-medium text-amber-800 dark:text-amber-200"
                        >
                            {{ $t("deliverability.localhost_warning.title") }}
                        </h4>
                        <p
                            class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                        >
                            {{
                                $t(
                                    "deliverability.localhost_warning.description",
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <!-- Step Indicator -->
            <div class="mb-8 flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium"
                        :class="
                            step >= 1
                                ? 'bg-indigo-600 text-white'
                                : 'bg-gray-200 text-gray-600 dark:bg-slate-700 dark:text-gray-400'
                        "
                        >1</span
                    >
                    <span
                        class="text-sm font-medium"
                        :class="
                            step >= 1
                                ? 'text-gray-900 dark:text-white'
                                : 'text-gray-400'
                        "
                    >
                        {{ $t("deliverability.dmarc_wiz.step_domain") }}
                    </span>
                </div>
                <div class="h-px w-12 bg-gray-300 dark:bg-slate-600"></div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium"
                        :class="
                            step >= 2
                                ? 'bg-indigo-600 text-white'
                                : 'bg-gray-200 text-gray-600 dark:bg-slate-700 dark:text-gray-400'
                        "
                        >2</span
                    >
                    <span
                        class="text-sm font-medium"
                        :class="
                            step >= 2
                                ? 'text-gray-900 dark:text-white'
                                : 'text-gray-400'
                        "
                    >
                        {{ $t("deliverability.dmarc_wiz.step_verify") }}
                    </span>
                </div>
            </div>

            <!-- Step 1: Enter Domain -->
            <div
                v-if="step === 1"
                class="rounded-xl border bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="text-center mb-8">
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                    >
                        <svg
                            class="h-8 w-8 text-indigo-600 dark:text-indigo-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
                            />
                        </svg>
                    </div>
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $t("deliverability.dmarc_wiz.enter_domain_title") }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{
                            $t(
                                "deliverability.dmarc_wiz.enter_domain_description",
                            )
                        }}
                    </p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label
                            for="domain"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{ $t("deliverability.domain_name") }}
                        </label>
                        <div class="mt-1 relative">
                            <input
                                id="domain"
                                v-model="form.domain"
                                @input="validateDomain"
                                type="text"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white sm:text-sm"
                                :class="{
                                    'border-rose-500': form.errors.domain,
                                }"
                                placeholder="example.com"
                            />
                            <div
                                v-if="domainValid"
                                class="absolute inset-y-0 right-0 flex items-center pr-3"
                            >
                                <svg
                                    class="h-5 w-5 text-emerald-500"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                        </div>
                        <p
                            v-if="form.errors.domain"
                            class="mt-2 text-sm text-rose-600 dark:text-rose-400"
                        >
                            {{ form.errors.domain }}
                        </p>
                    </div>

                    <button
                        @click="nextStep"
                        :disabled="!domainValid || form.processing"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{ $t("deliverability.dmarc_wiz.add_domain_btn") }}
                    </button>
                </div>
            </div>

            <!-- Step 2: CNAME Instructions -->
            <div
                v-if="step === 2"
                class="rounded-xl border bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="text-center mb-8">
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
                    >
                        <svg
                            class="h-8 w-8 text-amber-600 dark:text-amber-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                            />
                        </svg>
                    </div>
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $t("deliverability.dmarc_wiz.add_record_title") }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{
                            $t(
                                "deliverability.dmarc_wiz.add_record_description",
                            )
                        }}
                    </p>
                </div>

                <!-- CNAME Record Box -->
                <div class="mb-6 rounded-lg bg-gray-50 p-4 dark:bg-slate-900">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p
                                class="font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("deliverability.record_type") }}
                            </p>
                            <p
                                class="mt-1 font-mono font-semibold text-gray-900 dark:text-white"
                            >
                                CNAME
                            </p>
                        </div>
                        <div>
                            <p
                                class="font-medium text-gray-500 dark:text-gray-400"
                            >
                                TTL
                            </p>
                            <p
                                class="mt-1 font-mono font-semibold text-gray-900 dark:text-white"
                            >
                                3600
                            </p>
                        </div>
                        <div class="col-span-2">
                            <div class="flex items-center justify-between">
                                <p
                                    class="font-medium text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("deliverability.host") }}
                                </p>
                                <button
                                    @click="copyToClipboard(cnameHost, 'host')"
                                    class="flex items-center gap-1 rounded px-2 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        copiedField === 'host'
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-400 dark:hover:bg-slate-600'
                                    "
                                >
                                    <svg
                                        v-if="copiedField === 'host'"
                                        class="h-3.5 w-3.5"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                        />
                                    </svg>
                                    {{
                                        copiedField === "host"
                                            ? $t("common.copied")
                                            : $t("common.copy")
                                    }}
                                </button>
                            </div>
                            <p
                                class="mt-1 font-mono font-semibold text-indigo-600 dark:text-indigo-400 break-all"
                            >
                                {{ cnameHost }}
                            </p>
                        </div>
                        <div class="col-span-2">
                            <div class="flex items-center justify-between">
                                <p
                                    class="font-medium text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("deliverability.target") }}
                                </p>
                                <button
                                    @click="
                                        copyToClipboard(cnameTarget, 'target')
                                    "
                                    class="flex items-center gap-1 rounded px-2 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        copiedField === 'target'
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-400 dark:hover:bg-slate-600'
                                    "
                                >
                                    <svg
                                        v-if="copiedField === 'target'"
                                        class="h-3.5 w-3.5"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                        />
                                    </svg>
                                    {{
                                        copiedField === "target"
                                            ? $t("common.copied")
                                            : $t("common.copy")
                                    }}
                                </button>
                            </div>
                            <p
                                class="mt-1 font-mono font-semibold text-gray-900 dark:text-white break-all"
                            >
                                {{ cnameTarget }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Info -->
                <div
                    class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
                >
                    <div class="flex gap-3">
                        <svg
                            class="h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            {{
                                $t(
                                    "deliverability.dmarc_wiz.dns_propagation_info",
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button
                        @click="step = 1"
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                    >
                        {{ $t("common.back") }}
                    </button>
                    <button
                        @click="submitDomain"
                        :disabled="form.processing"
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{ $t("deliverability.dmarc_wiz.add_and_verify") }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
