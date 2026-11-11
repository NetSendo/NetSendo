<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import debounce from "lodash/debounce";
import pickBy from "lodash/pickBy";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import TemplatePreviewCard from "@/Components/TemplatePreviewCard.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SearchField from "@/Components/Ui/SearchField.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    templates: Object,
    starterTemplates: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: Object,
});

const { t, te } = useI18n();
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const filters = Array.isArray(props.filters) ? {} : props.filters || {};

const form = reactive({
    search: filters.search || "",
    category: filters.category ? Number(filters.category) : "",
    per_page: filters.per_page || "12",
});

watch(
    form,
    debounce(() => {
        router.get(route("templates.index"), pickBy(form), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300),
);

const hasFilters = computed(() => Boolean(form.search || form.category));

const resetFilters = () => {
    form.search = "";
    form.category = "";
};

// Ready-made templates carry a category slug; their names are translated here
const starterCategoryLabel = (slug) =>
    slug && te(`templates.starter.categories.${slug}`) ? t(`templates.starter.categories.${slug}`) : "";

// The chosen category narrows the ready-made templates too, matched by slug
const visibleStarters = computed(() => {
    if (!form.category) return props.starterTemplates;
    const chosen = props.categories.find((category) => category.id === form.category);
    if (!chosen) return props.starterTemplates;
    return props.starterTemplates.filter((template) => template.category === chosen.slug);
});

// Whether the ready-made section is open is the viewer's own choice, kept in this browser
const STARTERS_KEY = "netsendo.templates.starters";
const readStartersOpen = () => {
    try {
        return localStorage.getItem(STARTERS_KEY) !== "closed";
    } catch {
        return true;
    }
};
const startersOpen = ref(readStartersOpen());

const toggleStarters = () => {
    startersOpen.value = !startersOpen.value;
    try {
        localStorage.setItem(STARTERS_KEY, startersOpen.value ? "open" : "closed");
    } catch {
        // Private mode: the choice lasts until the page is left
    }
};

// A copy opens in the editor straight away, for one's own template and a ready-made one alike
const duplicating = ref(null);

const duplicateTemplate = (template) => {
    if (duplicating.value) return;
    duplicating.value = template.id;
    router.post(
        route("templates.duplicate", template.id),
        {},
        { onFinish: () => (duplicating.value = null) },
    );
};

const templateToDelete = ref(null);
const deleting = ref(false);

const deleteTemplate = () => {
    if (!templateToDelete.value) return;
    deleting.value = true;
    router.delete(route("templates.destroy", templateToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => showToast(t("templates.deleted")),
        onFinish: () => {
            deleting.value = false;
            templateToDelete.value = null;
        },
    });
};

const chipClass = (selected) =>
    selected
        ? "border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900"
        : "border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:text-slate-900 dark:border-slate-700/60 dark:bg-slate-800/50 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white";
</script>

<template>
    <Head :title="$t('templates.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('templates.title')" :subtitle="$t('templates.subtitle')">
                <CreateLink :href="route('templates.create')">
                    {{ $t("templates.new_template") }}
                </CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-6">
            <!-- Search and categories -->
            <div class="flex flex-col gap-3">
                <div class="flex">
                    <SearchField
                        v-model="form.search"
                        :placeholder="$t('templates.search_placeholder')"
                        class="sm:max-w-md"
                    />
                </div>

                <div
                    v-if="categories.length"
                    role="group"
                    :aria-label="$t('templates.categories_label')"
                    class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1"
                >
                    <button
                        type="button"
                        :aria-pressed="!form.category"
                        class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        :class="chipClass(!form.category)"
                        @click="form.category = ''"
                    >
                        {{ $t("common.all") }}
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        :aria-pressed="form.category === category.id"
                        class="inline-flex min-h-9 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        :class="chipClass(form.category === category.id)"
                        @click="form.category = category.id"
                    >
                        <span
                            aria-hidden="true"
                            class="h-2 w-2 rounded-full"
                            :style="{ backgroundColor: category.color }"
                        ></span>
                        {{ category.name }}
                    </button>
                </div>
            </div>

            <!-- The account's own templates -->
            <section class="flex flex-col gap-3" :aria-label="$t('templates.your_templates')">
                <h2 class="flex items-baseline gap-2 text-base font-semibold text-slate-900 dark:text-white">
                    {{ $t("templates.your_templates") }}
                    <span class="text-sm font-normal tabular-nums text-slate-600 dark:text-slate-400">
                        {{ templates.total }}
                    </span>
                </h2>

                <Panel v-if="templates.data.length === 0" flush>
                    <EmptyState
                        v-if="hasFilters"
                        class="!border-t-0"
                        :title="$t('templates.empty_filtered')"
                    >
                        <button
                            type="button"
                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="resetFilters"
                        >
                            {{ $t("messages.filters.clear") }}
                        </button>
                    </EmptyState>
                    <EmptyState
                        v-else
                        class="!border-t-0"
                        :title="$t('templates.empty_title')"
                        :description="$t('templates.empty_description')"
                    >
                        <CreateLink :href="route('templates.create')">
                            {{ $t("templates.start") }}
                        </CreateLink>
                    </EmptyState>
                </Panel>

                <template v-else>
                    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        <li
                            v-for="template in templates.data"
                            :key="template.id"
                            class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition-colors hover:border-slate-300 dark:border-slate-700/60 dark:bg-slate-800/50 dark:hover:border-slate-600"
                        >
                            <Link
                                :href="route('templates.edit', template.id)"
                                class="relative block h-52 overflow-hidden border-b border-slate-200 dark:border-slate-700/60"
                                tabindex="-1"
                                aria-hidden="true"
                            >
                                <TemplatePreviewCard
                                    :blocks="template.json_structure?.blocks || []"
                                    :settings="template.settings"
                                    :thumbnail="template.thumbnail"
                                />
                            </Link>

                            <div class="flex items-start gap-2 p-4">
                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="route('templates.edit', template.id)"
                                        class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                        :title="template.name"
                                    >
                                        {{ template.name }}
                                    </Link>
                                    <p
                                        class="mt-0.5 flex items-center gap-x-2 text-xs text-slate-600 dark:text-slate-400"
                                    >
                                        <span
                                            v-if="template.category_data"
                                            class="inline-flex min-w-0 items-center gap-1.5"
                                        >
                                            <span
                                                aria-hidden="true"
                                                class="h-2 w-2 shrink-0 rounded-full"
                                                :style="{ backgroundColor: template.category_data.color }"
                                            ></span>
                                            <span class="truncate">{{ template.category_data.name }}</span>
                                        </span>
                                        <span v-if="template.category_data" aria-hidden="true">·</span>
                                        <span class="shrink-0 whitespace-nowrap">
                                            {{ $t("templates.updated_at") }} {{ template.updated_at }}
                                        </span>
                                    </p>
                                </div>

                                <ActionMenu :label="$t('templates.actions_for', { name: template.name })">
                                    <ActionMenuItem :href="route('templates.edit', template.id)">
                                        {{ $t("common.edit") }}
                                    </ActionMenuItem>
                                    <ActionMenuItem
                                        :disabled="duplicating === template.id"
                                        @click="duplicateTemplate(template)"
                                    >
                                        {{ $t("common.duplicate") }}
                                    </ActionMenuItem>
                                    <ActionMenuItem danger @click="templateToDelete = template">
                                        {{ $t("common.delete") }}
                                    </ActionMenuItem>
                                </ActionMenu>
                            </div>
                        </li>
                    </ul>

                    <div
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700/60 dark:bg-slate-800/50 [&>div]:border-t-0"
                    >
                        <Pagination
                            v-model:per-page="form.per_page"
                            :paginator="templates"
                            :per-page-options="[12, 24, 48]"
                        />
                    </div>
                </template>
            </section>

            <!-- Ready-made templates: one click makes an editable copy -->
            <Panel v-if="visibleStarters.length > 0">
                <template #title>
                    {{ $t("templates.starter.title") }}
                    <span class="text-sm font-normal tabular-nums text-slate-600 dark:text-slate-400">
                        {{ visibleStarters.length }}
                    </span>
                </template>
                <template #actions>
                    <button
                        type="button"
                        :aria-expanded="startersOpen"
                        class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="toggleStarters"
                    >
                        {{ startersOpen ? $t("templates.starter.hide") : $t("templates.starter.show") }}
                    </button>
                </template>

                <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("templates.starter.subtitle_plain") }}
                </p>

                <ul
                    v-if="startersOpen"
                    class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6"
                >
                    <li
                        v-for="template in visibleStarters"
                        :key="template.id"
                        class="flex min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700/60"
                    >
                        <div class="relative h-40 overflow-hidden border-b border-slate-200 dark:border-slate-700/60">
                            <TemplatePreviewCard
                                :blocks="template.json_structure?.blocks || []"
                                :settings="template.settings"
                                :thumbnail="template.thumbnail"
                            />
                        </div>
                        <div class="flex flex-1 flex-col gap-3 p-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-medium text-slate-900 dark:text-white"
                                    :title="template.name"
                                >
                                    {{ template.name }}
                                </p>
                                <p
                                    v-if="starterCategoryLabel(template.category)"
                                    class="truncate text-xs text-slate-600 dark:text-slate-400"
                                >
                                    {{ starterCategoryLabel(template.category) }}
                                </p>
                            </div>
                            <button
                                type="button"
                                :disabled="duplicating === template.id"
                                class="mt-auto inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-transparent dark:text-slate-200 dark:hover:bg-slate-700/60"
                                @click="duplicateTemplate(template)"
                            >
                                {{ $t("templates.starter.use_template") }}
                            </button>
                        </div>
                    </li>
                </ul>
            </Panel>
        </div>

        <ConfirmModal
            :show="!!templateToDelete"
            type="danger"
            :title="$t('templates.delete_title')"
            :message="$t('templates.delete_description')"
            :confirm-text="$t('common.delete')"
            :processing="deleting"
            @close="templateToDelete = null"
            @confirm="deleteTemplate"
        >
            {{ templateToDelete?.name }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
