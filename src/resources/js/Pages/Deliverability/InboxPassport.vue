<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm, Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    domains: { type: Array, default: () => [] },
});

const form = useForm({
    domain_id: props.domains[0]?.id || null,
    subject: "",
    content: "",
});

const isAnalyzing = ref(false);
const previewHtml = ref(false);

// Selected domain
const selectedDomain = computed(() => {
    return props.domains.find((d) => d.id === form.domain_id);
});

// Character count
const contentLength = computed(() => form.content.length);

// Submit simulation
const runSimulation = () => {
    if (!form.domain_id || !form.subject || !form.content) return;

    isAnalyzing.value = true;
    form.post(route("deliverability.simulate"), {
        onFinish: () => {
            isAnalyzing.value = false;
        },
    });
};
</script>

<template>
    <Head :title="$t('deliverability.inbox_passport.title')" />

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
                        InboxPassport AI
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("deliverability.inbox_passport.subtitle") }}
                    </p>
                </div>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Input Form -->
            <div class="space-y-6">
                <!-- Domain Selection -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                    >
                        {{ $t("deliverability.select_domain") }}
                    </label>

                    <div
                        v-if="domains.length === 0"
                        class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center dark:border-slate-600"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("deliverability.no_verified_domains") }}
                        </p>
                        <Link
                            :href="route('deliverability.domains.create')"
                            class="mt-3 inline-flex items-center text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                        >
                            {{ $t("deliverability.add_domain") }} →
                        </Link>
                    </div>

                    <div v-else class="grid gap-2">
                        <button
                            v-for="domain in domains"
                            :key="domain.id"
                            @click="form.domain_id = domain.id"
                            class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors"
                            :class="
                                form.domain_id === domain.id
                                    ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-900/20'
                                    : 'border-gray-200 hover:border-gray-300 dark:border-slate-600 dark:hover:border-slate-500'
                            "
                        >
                            <span
                                class="h-3 w-3 rounded-full"
                                :class="{
                                    'bg-emerald-500':
                                        domain.overall_status === 'excellent',
                                    'bg-blue-500':
                                        domain.overall_status === 'good',
                                    'bg-amber-500':
                                        domain.overall_status === 'warning',
                                    'bg-rose-500':
                                        domain.overall_status === 'critical',
                                    'bg-gray-400': !domain.overall_status,
                                }"
                            ></span>
                            <span
                                class="font-medium text-gray-900 dark:text-white"
                                >{{ domain.domain }}</span
                            >
                        </button>
                    </div>
                </div>

                <!-- Subject -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                    >
                        {{ $t("deliverability.email_subject") }}
                    </label>
                    <input
                        v-model="form.subject"
                        type="text"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white sm:text-sm"
                        :placeholder="$t('deliverability.subject_placeholder')"
                    />
                    <p
                        v-if="form.errors.subject"
                        class="mt-2 text-sm text-rose-600 dark:text-rose-400"
                    >
                        {{ form.errors.subject }}
                    </p>
                </div>

                <!-- Content -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-center justify-between mb-2">
                        <label
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{ $t("deliverability.email_content") }}
                        </label>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ contentLength }} {{ $t("common.characters") }}
                        </span>
                    </div>
                    <textarea
                        v-model="form.content"
                        rows="12"
                        class="block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        :placeholder="$t('deliverability.content_placeholder')"
                    ></textarea>
                    <p
                        v-if="form.errors.content"
                        class="mt-2 text-sm text-rose-600 dark:text-rose-400"
                    >
                        {{ form.errors.content }}
                    </p>
                </div>

                <!-- Submit Button -->
                <button
                    @click="runSimulation"
                    :disabled="
                        isAnalyzing ||
                        !form.domain_id ||
                        !form.subject ||
                        !form.content
                    "
                    class="w-full inline-flex items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 text-lg font-semibold text-white shadow-lg transition-all hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg
                        v-if="isAnalyzing"
                        class="h-6 w-6 animate-spin"
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
                    <svg
                        v-else
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                        />
                    </svg>
                    {{
                        isAnalyzing
                            ? $t("deliverability.analyzing")
                            : $t("deliverability.run_simulation")
                    }}
                </button>
            </div>

            <!-- Info Panel -->
            <div class="space-y-6">
                <!-- How it works -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <h3
                        class="font-semibold text-gray-900 dark:text-white mb-4"
                    >
                        {{ $t("deliverability.inbox_passport.how_it_works") }}
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                            >
                                1
                            </div>
                            <div>
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        $t(
                                            "deliverability.inbox_passport.step1_title",
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            "deliverability.inbox_passport.step1_desc",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                            >
                                2
                            </div>
                            <div>
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        $t(
                                            "deliverability.inbox_passport.step2_title",
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            "deliverability.inbox_passport.step2_desc",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                            >
                                3
                            </div>
                            <div>
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        $t(
                                            "deliverability.inbox_passport.step3_title",
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            "deliverability.inbox_passport.step3_desc",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- What we analyze -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <h3
                        class="font-semibold text-gray-900 dark:text-white mb-4"
                    >
                        {{ $t("deliverability.inbox_passport.what_we_check") }}
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            SPF Record
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            DKIM Signature
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            DMARC Policy
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t("deliverability.spam_words") }}
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t("deliverability.subject_analysis") }}
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t("deliverability.link_check") }}
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t("deliverability.html_structure") }}
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t("deliverability.formatting_title") }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
