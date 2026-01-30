<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    rule: Object,
    triggerEvents: Object,
    actionTypes: Object,
    conditionTypes: Object,
    lists: Array,
    tags: Array,
    messages: Array,
    funnels: Array,
    forms: Array,
    customFields: Array,
    pipelines: Array,
    stages: Array,
    users: Array,
});

// Normalize actions to ensure each has a config object
const normalizedActions = (props.rule?.actions || []).map((a) => ({
    ...a,
    config: a.config || {},
}));

const form = useForm({
    name: props.rule?.name || "",
    description: props.rule?.description || "",
    trigger_event: props.rule?.trigger_event || "subscriber_signup",
    trigger_config: props.rule?.trigger_config || {},
    conditions: props.rule?.conditions || [],
    condition_logic: props.rule?.condition_logic || "all",
    actions: normalizedActions,
    is_active: props.rule?.is_active ?? true,
    limit_per_subscriber: props.rule?.limit_per_subscriber || false,
    limit_count: props.rule?.limit_count || 1,
    limit_period: props.rule?.limit_period || "ever",
});

const isEditing = computed(() => !!props.rule?.id);
const icons = {
    subscriber_signup: "📝",
    email_opened: "👁️",
    email_clicked: "🖱️",
    form_submitted: "📋",
    tag_added: "🏷️",
    tag_removed: "🏷️",
    page_visited: "🌐",
    specific_link_clicked: "🔗",
    date_reached: "📅",
    read_time_threshold: "⏱️",
    subscriber_birthday: "🎂",
    subscription_anniversary: "🎉",
    list_join: "📥",
    purchase: "🛒",
    // CRM triggers
    crm_deal_stage_changed: "💼",
    crm_deal_won: "🏆",
    crm_deal_lost: "❌",
    crm_deal_created: "➕",
    crm_deal_idle: "💤",
    crm_task_completed: "✅",
    crm_task_overdue: "⚠️",
    crm_contact_created: "👤",
    crm_contact_status_changed: "🔄",
    crm_score_threshold: "📊",
    crm_activity_logged: "📝",
};

const addAction = () => form.actions.push({ type: "add_tag", config: {} });
const removeAction = (i) => form.actions.splice(i, 1);
const addCondition = () =>
    form.conditions.push({ type: "tag_exists", value: "" });
const removeCondition = (i) => form.conditions.splice(i, 1);
const submit = () =>
    isEditing.value
        ? form.put(route("automations.update", props.rule.id))
        : form.post(route("automations.store"));
</script>

<template>
    <Head
        :title="isEditing ? $t('automations.edit') : $t('automations.create')"
    />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('automations.index')"
                    class="text-gray-500 hover:text-gray-700"
                    >←</Link
                >
                <h2
                    class="text-xl font-semibold text-gray-800 dark:text-gray-200"
                >
                    ⚡ {{ isEditing ? $t("common.edit") : $t("common.create") }}
                    {{ $t("automations.title").toLowerCase() }}
                </h2>
            </div>
        </template>
        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic -->
                    <div
                        class="rounded-lg bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t("automations.builder.basic_info") }}
                        </h3>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            :placeholder="
                                $t('automations.builder.name_placeholder')
                            "
                            class="mb-3 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                        <textarea
                            v-model="form.description"
                            rows="2"
                            :placeholder="
                                $t(
                                    'automations.builder.description_placeholder',
                                )
                            "
                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                    </div>
                    <!-- Trigger -->
                    <div
                        class="rounded-lg bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            🎯 {{ $t("automations.builder.when") }}
                        </h3>
                        <select
                            v-model="form.trigger_event"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option
                                v-for="(label, key) in triggerEvents"
                                :key="key"
                                :value="key"
                            >
                                {{ icons[key] || "⚡" }} {{ label }}
                            </option>
                        </select>

                        <!-- List Filter (Common) -->
                        <div
                            v-if="
                                [
                                    'subscriber_signup',
                                    'subscriber_unsubscribed',
                                    'email_opened',
                                    'email_clicked',
                                ].includes(form.trigger_event)
                            "
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >{{
                                    $t("automations.builder.filter_list")
                                }}</label
                            >
                            <select
                                v-model="form.trigger_config.list_id"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            >
                                <option :value="null">
                                    {{ $t("automations.builder.all_lists") }}
                                </option>
                                <option
                                    v-for="l in lists"
                                    :key="l.id"
                                    :value="l.id"
                                >
                                    {{ l.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Page Visit Config -->
                        <div
                            v-if="form.trigger_event === 'page_visited'"
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Wzorzec URL (użyj * jako wieloznacznik)</label
                            >
                            <input
                                v-model="form.trigger_config.url_pattern"
                                type="text"
                                placeholder="https://twojastrona.pl/oferta/*"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <p class="text-xs text-gray-500 mt-1">
                                Przykład: <code>*/cennik</code> lub
                                <code>https://strona.pl/dziekujemy</code>
                            </p>
                        </div>

                        <!-- Specific Link Click Config -->
                        <div
                            v-if="
                                form.trigger_event === 'specific_link_clicked'
                            "
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Link URL</label
                            >
                            <input
                                v-model="form.trigger_config.link_url"
                                type="text"
                                placeholder="https://..."
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                        </div>

                        <!-- Read Time Threshold Config -->
                        <div
                            v-if="form.trigger_event === 'read_time_threshold'"
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Wymagany czas czytania (sekundy)</label
                            >
                            <input
                                v-model="
                                    form.trigger_config.read_time_threshold
                                "
                                type="number"
                                min="5"
                                placeholder="np. 30"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                        </div>

                        <!-- Date Reached Config -->
                        <div
                            v-if="form.trigger_event === 'date_reached'"
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Data uruchomienia</label
                            >
                            <input
                                v-model="form.trigger_config.date"
                                type="date"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                        </div>

                        <!-- CRM Trigger Configs -->
                        <div
                            v-if="form.trigger_event.startsWith('crm_deal')"
                            class="mt-3 space-y-3"
                        >
                            <div>
                                <label
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                    >📊 Pipeline</label
                                >
                                <select
                                    v-model="form.trigger_config.pipeline_id"
                                    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                >
                                    <option :value="null">
                                        Wszystkie pipeline
                                    </option>
                                    <option
                                        v-for="p in pipelines"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>
                            <div
                                v-if="
                                    form.trigger_event ===
                                    'crm_deal_stage_changed'
                                "
                            >
                                <label
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                    >Z etapu</label
                                >
                                <select
                                    v-model="form.trigger_config.from_stage_id"
                                    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                >
                                    <option :value="null">Dowolny etap</option>
                                    <option
                                        v-for="s in stages"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>
                            <div
                                v-if="
                                    form.trigger_event ===
                                    'crm_deal_stage_changed'
                                "
                            >
                                <label
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                    >Do etapu</label
                                >
                                <select
                                    v-model="form.trigger_config.to_stage_id"
                                    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                >
                                    <option :value="null">Dowolny etap</option>
                                    <option
                                        v-for="s in stages"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >Wartość min (PLN)</label
                                    >
                                    <input
                                        v-model="
                                            form.trigger_config.deal_value_min
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >Wartość max (PLN)</label
                                    >
                                    <input
                                        v-model="
                                            form.trigger_config.deal_value_max
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="bez limitu"
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    />
                                </div>
                            </div>
                            <div v-if="form.trigger_event === 'crm_deal_idle'">
                                <label
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                    >Dni bez aktywności</label
                                >
                                <input
                                    v-model="form.trigger_config.idle_days"
                                    type="number"
                                    min="1"
                                    placeholder="7"
                                    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                />
                            </div>
                        </div>

                        <!-- CRM Score Threshold Config -->
                        <div
                            v-if="form.trigger_event === 'crm_score_threshold'"
                            class="mt-3 space-y-3"
                        >
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >Próg score</label
                                    >
                                    <input
                                        v-model="
                                            form.trigger_config.score_threshold
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="50"
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >Kierunek</label
                                    >
                                    <select
                                        v-model="
                                            form.trigger_config.score_direction
                                        "
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    >
                                        <option value="above">
                                            Powyżej progu
                                        </option>
                                        <option value="below">
                                            Poniżej progu
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- CRM Task Trigger Config -->
                        <div
                            v-if="form.trigger_event.startsWith('crm_task')"
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Typ zadania</label
                            >
                            <select
                                v-model="form.trigger_config.task_type"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            >
                                <option :value="null">Wszystkie typy</option>
                                <option value="call">Telefon</option>
                                <option value="email">Email</option>
                                <option value="meeting">Spotkanie</option>
                                <option value="follow_up">Follow-up</option>
                            </select>
                        </div>

                        <!-- CRM Contact Status Config -->
                        <div
                            v-if="
                                form.trigger_event ===
                                'crm_contact_status_changed'
                            "
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Nowy status</label
                            >
                            <select
                                v-model="form.trigger_config.contact_status"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            >
                                <option :value="null">Dowolny status</option>
                                <option value="lead">Lead</option>
                                <option value="prospect">Prospect</option>
                                <option value="customer">Customer</option>
                                <option value="churned">Churned</option>
                            </select>
                        </div>

                        <!-- AutoTag Pro: Purchase Trigger Config -->
                        <div
                            v-if="form.trigger_event === 'purchase'"
                            class="mt-3 space-y-3"
                        >
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 p-2 rounded"
                            >
                                🛒 Trigger uruchamiany przez webhook z systemu
                                e-commerce
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >ID produktu</label
                                    >
                                    <input
                                        v-model="form.trigger_config.product_id"
                                        type="text"
                                        placeholder="np. course_premium"
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >Kategoria</label
                                    >
                                    <input
                                        v-model="
                                            form.trigger_config.product_category
                                        "
                                        type="text"
                                        placeholder="np. kursy, ebooki"
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >Min. wartość (PLN)</label
                                    >
                                    <input
                                        v-model="form.trigger_config.min_value"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >Max. wartość (PLN)</label
                                    >
                                    <input
                                        v-model="form.trigger_config.max_value"
                                        type="number"
                                        min="0"
                                        placeholder="bez limitu"
                                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- AutoTag Pro: Link Category Filter for email_clicked -->
                        <div
                            v-if="form.trigger_event === 'email_clicked'"
                            class="mt-3"
                        >
                            <label
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Kategoria linku (opcjonalnie)</label
                            >
                            <select
                                v-model="form.trigger_config.link_category"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            >
                                <option :value="null">Wszystkie linki</option>
                                <option value="ai">AI</option>
                                <option value="marketing">Marketing</option>
                                <option value="sales">Sprzedaż</option>
                                <option value="product">Produkt</option>
                                <option value="pricing">Cennik</option>
                                <option value="content">Treści</option>
                            </select>
                        </div>
                    </div>
                    <!-- Conditions -->
                    <div
                        class="rounded-lg bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <div class="mb-4 flex justify-between items-center">
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                🔍 {{ $t("automations.builder.if") }}
                                <span class="text-sm text-gray-500"
                                    >({{
                                        $t("automations.builder.optional")
                                    }})</span
                                >
                            </h3>
                            <button
                                type="button"
                                @click="addCondition"
                                class="text-indigo-600 text-sm"
                            >
                                + {{ $t("automations.builder.add_condition") }}
                            </button>
                        </div>
                        <div
                            v-if="form.conditions.length"
                            class="mb-3 flex gap-4 text-gray-900 dark:text-gray-200"
                        >
                            <label class="flex items-center gap-1"
                                ><input
                                    type="radio"
                                    v-model="form.condition_logic"
                                    value="all"
                                />
                                {{ $t("automations.builder.and") }}</label
                            >
                            <label class="flex items-center gap-1"
                                ><input
                                    type="radio"
                                    v-model="form.condition_logic"
                                    value="any"
                                />
                                {{ $t("automations.builder.or") }}</label
                            >
                        </div>
                        <div
                            v-for="(c, i) in form.conditions"
                            :key="i"
                            class="mb-2 flex gap-2 items-center bg-gray-50 dark:bg-gray-700 p-3 rounded"
                        >
                            <select
                                v-model="c.type"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                            >
                                <option
                                    v-for="(l, k) in conditionTypes"
                                    :key="k"
                                    :value="k"
                                >
                                    {{ l }}
                                </option>
                            </select>
                            <select
                                v-if="c.type.includes('tag')"
                                v-model="c.value"
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                            >
                                <option
                                    v-for="t in tags"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.name }}
                                </option>
                            </select>
                            <input
                                v-else
                                v-model="c.value"
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                            />
                            <button
                                type="button"
                                @click="removeCondition(i)"
                                class="text-red-500"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    <!-- Actions -->
                    <div
                        class="rounded-lg bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <div class="mb-4 flex justify-between items-center">
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                ⚡ {{ $t("automations.builder.then") }}
                            </h3>
                            <button
                                type="button"
                                @click="addAction"
                                class="text-indigo-600 text-sm"
                            >
                                + {{ $t("automations.builder.add_action") }}
                            </button>
                        </div>
                        <div
                            v-for="(a, i) in form.actions"
                            :key="i"
                            class="mb-3 bg-gray-50 dark:bg-gray-700 p-4 rounded"
                        >
                            <div class="flex gap-2 items-start">
                                <span
                                    class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-sm flex items-center justify-center"
                                    >{{ i + 1 }}</span
                                >
                                <div class="flex-1 space-y-2">
                                    <select
                                        v-model="a.type"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                    >
                                        <option
                                            v-for="(l, k) in actionTypes"
                                            :key="k"
                                            :value="k"
                                        >
                                            {{ l }}
                                        </option>
                                    </select>
                                    <select
                                        v-if="a.type.includes('tag')"
                                        v-model="a.config.tag_id"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                    >
                                        <option
                                            v-for="t in tags"
                                            :key="t.id"
                                            :value="t.id"
                                        >
                                            {{ t.name }}
                                        </option>
                                    </select>
                                    <select
                                        v-else-if="
                                            a.type.includes('list') ||
                                            a.type === 'unsubscribe'
                                        "
                                        v-model="a.config.list_id"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                    >
                                        <option
                                            v-for="l in lists"
                                            :key="l.id"
                                            :value="l.id"
                                        >
                                            {{ l.name }}
                                        </option>
                                    </select>
                                    <select
                                        v-else-if="a.type === 'send_email'"
                                        v-model="a.config.message_id"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                    >
                                        <option
                                            v-for="m in messages"
                                            :key="m.id"
                                            :value="m.id"
                                        >
                                            {{ m.subject }}
                                        </option>
                                    </select>
                                    <select
                                        v-else-if="
                                            a.type === 'start_funnel' ||
                                            a.type === 'start_sequence'
                                        "
                                        v-model="a.config.funnel_id"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                    >
                                        <option
                                            v-for="f in funnels"
                                            :key="f.id"
                                            :value="f.id"
                                        >
                                            {{ f.name }}
                                        </option>
                                    </select>
                                    <select
                                        v-else-if="
                                            a.type === 'stop_funnel' ||
                                            a.type === 'stop_sequence'
                                        "
                                        v-model="a.config.funnel_id"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                    >
                                        <option
                                            v-for="f in funnels"
                                            :key="f.id"
                                            :value="f.id"
                                        >
                                            {{ f.name }}
                                        </option>
                                    </select>
                                    <!-- AutoTag Pro: Add Score Action -->
                                    <div
                                        v-else-if="a.type === 'add_score'"
                                        class="w-full"
                                    >
                                        <input
                                            v-model="a.config.points"
                                            type="number"
                                            placeholder="Punkty do dodania (np. 10, -5)"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Użyj wartości ujemnej aby odjąć
                                            punkty
                                        </p>
                                    </div>
                                    <input
                                        v-else-if="a.type === 'call_webhook'"
                                        v-model="a.config.url"
                                        type="url"
                                        placeholder="https://..."
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                    />

                                    <!-- CRM Action Configs -->
                                    <div
                                        v-else-if="a.type === 'crm_create_task'"
                                        class="space-y-2"
                                    >
                                        <input
                                            v-model="a.config.title"
                                            type="text"
                                            placeholder="Tytuł zadania (użyj {{deal_name}}, {{first_name}})"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        />
                                        <div class="grid grid-cols-3 gap-2">
                                            <select
                                                v-model="a.config.task_type"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                            >
                                                <option value="follow_up">
                                                    Follow-up
                                                </option>
                                                <option value="call">
                                                    Telefon
                                                </option>
                                                <option value="email">
                                                    Email
                                                </option>
                                                <option value="meeting">
                                                    Spotkanie
                                                </option>
                                            </select>
                                            <select
                                                v-model="a.config.priority"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                            >
                                                <option value="high">
                                                    Wysoki
                                                </option>
                                                <option value="medium">
                                                    Normalny
                                                </option>
                                                <option value="low">
                                                    Niski
                                                </option>
                                            </select>
                                            <input
                                                v-model="a.config.due_days"
                                                type="number"
                                                min="0"
                                                placeholder="Za X dni"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                            />
                                        </div>
                                    </div>
                                    <div
                                        v-else-if="
                                            a.type === 'crm_update_score'
                                        "
                                        class="grid grid-cols-2 gap-2"
                                    >
                                        <input
                                            v-model="a.config.score_delta"
                                            type="number"
                                            placeholder="+/- punkty"
                                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        />
                                        <label
                                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            <input
                                                type="checkbox"
                                                v-model="a.config.set_absolute"
                                                class="rounded"
                                            />
                                            Ustaw wartość bezwzględną
                                        </label>
                                    </div>
                                    <div
                                        v-else-if="a.type === 'crm_move_deal'"
                                        class="w-full"
                                    >
                                        <select
                                            v-model="a.config.stage_id"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        >
                                            <option
                                                v-for="s in stages"
                                                :key="s.id"
                                                :value="s.id"
                                            >
                                                {{ s.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        v-else-if="
                                            a.type === 'crm_assign_owner'
                                        "
                                        class="w-full"
                                    >
                                        <select
                                            v-model="a.config.owner_id"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        >
                                            <option
                                                v-for="u in users"
                                                :key="u.id"
                                                :value="u.id"
                                            >
                                                {{ u.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        v-else-if="
                                            a.type ===
                                            'crm_update_contact_status'
                                        "
                                        class="w-full"
                                    >
                                        <select
                                            v-model="a.config.status"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        >
                                            <option value="lead">Lead</option>
                                            <option value="prospect">
                                                Prospect
                                            </option>
                                            <option value="customer">
                                                Customer
                                            </option>
                                            <option value="churned">
                                                Churned
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        v-else-if="a.type === 'crm_create_deal'"
                                        class="space-y-2"
                                    >
                                        <input
                                            v-model="a.config.name"
                                            type="text"
                                            placeholder="Nazwa deala (użyj {{first_name}}, {{last_name}})"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        />
                                        <div class="grid grid-cols-2 gap-2">
                                            <input
                                                v-model="a.config.value"
                                                type="number"
                                                min="0"
                                                placeholder="Wartość (PLN)"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                            />
                                            <select
                                                v-model="a.config.pipeline_id"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                            >
                                                <option
                                                    v-for="p in pipelines"
                                                    :key="p.id"
                                                    :value="p.id"
                                                >
                                                    {{ p.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div
                                        v-else-if="
                                            a.type === 'crm_log_activity'
                                        "
                                        class="space-y-2"
                                    >
                                        <select
                                            v-model="a.config.activity_type"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        >
                                            <option value="note">
                                                Notatka
                                            </option>
                                            <option value="call">
                                                Telefon
                                            </option>
                                            <option value="email">Email</option>
                                            <option value="meeting">
                                                Spotkanie
                                            </option>
                                        </select>
                                        <input
                                            v-model="a.config.content"
                                            type="text"
                                            placeholder="Treść aktywności"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200"
                                        />
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="removeAction(i)"
                                    class="text-red-500"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="!form.actions.length"
                            class="text-gray-500 text-center text-sm"
                        >
                            {{ $t("automations.builder.add_action_hint") }}
                        </p>
                    </div>
                    <!-- Submit -->
                    <div
                        class="flex justify-between items-center rounded-lg bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <label
                            class="flex items-center gap-2 text-gray-900 dark:text-gray-200"
                            ><input
                                type="checkbox"
                                v-model="form.is_active"
                                class="rounded"
                            />
                            {{
                                $t("automations.builder.activate_immediately")
                            }}</label
                        >
                        <div class="flex gap-3">
                            <Link
                                :href="route('automations.index')"
                                class="px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                                >{{ $t("common.cancel") }}</Link
                            >
                            <button
                                type="submit"
                                :disabled="
                                    form.processing || !form.actions.length
                                "
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg disabled:opacity-50"
                            >
                                {{
                                    isEditing
                                        ? $t("common.save")
                                        : $t("common.create")
                                }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
