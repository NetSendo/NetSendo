<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    rule: Object, triggerEvents: Object, actionTypes: Object, conditionTypes: Object,
    lists: Array, tags: Array, messages: Array, funnels: Array, forms: Array, customFields: Array,
});

const form = useForm({
    name: props.rule?.name || '', description: props.rule?.description || '',
    trigger_event: props.rule?.trigger_event || 'subscriber_signup',
    trigger_config: props.rule?.trigger_config || {}, conditions: props.rule?.conditions || [],
    condition_logic: props.rule?.condition_logic || 'all', actions: props.rule?.actions || [],
    is_active: props.rule?.is_active ?? true, limit_per_subscriber: props.rule?.limit_per_subscriber || false,
    limit_count: props.rule?.limit_count || 1, limit_period: props.rule?.limit_period || 'ever',
});

const isEditing = computed(() => !!props.rule?.id);
const icons = { 
    subscriber_signup: '📝', email_opened: '👁️', email_clicked: '🖱️', 
    form_submitted: '📋', tag_added: '🏷️', tag_removed: '🏷️',
    page_visited: '🌐', specific_link_clicked: '🔗', date_reached: '📅',
    read_time_threshold: '⏱️', subscriber_birthday: '🎂', subscription_anniversary: '🎉'
};

const addAction = () => form.actions.push({ type: 'add_tag', config: {} });
const removeAction = (i) => form.actions.splice(i, 1);
const addCondition = () => form.conditions.push({ type: 'tag_exists', value: '' });
const removeCondition = (i) => form.conditions.splice(i, 1);
const submit = () => isEditing.value ? form.put(route('automations.update', props.rule.id)) : form.post(route('automations.store'));
</script>

<template>
    <Head :title="isEditing ? $t('automations.edit') : $t('automations.create')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('automations.index')" class="text-gray-500 hover:text-gray-700">←</Link>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">⚡ {{ isEditing ? $t('common.edit') : $t('app.create') }} {{ $t('automations.title').toLowerCase() }}</h2>
            </div>
        </template>
        <div class="py-6"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic -->
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('automations.builder.basic_info') }}</h3>
                    <input v-model="form.name" type="text" required :placeholder="$t('automations.builder.name_placeholder')" class="mb-3 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"/>
                    <textarea v-model="form.description" rows="2" :placeholder="$t('automations.builder.description_placeholder')" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"/>
                </div>
                <!-- Trigger -->
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">🎯 {{ $t('automations.builder.when') }}</h3>
                    <select v-model="form.trigger_event" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option v-for="(label, key) in triggerEvents" :key="key" :value="key">{{ icons[key] || '⚡' }} {{ label }}</option>
                    </select>
                    
                    <!-- List Filter (Common) -->
                    <div v-if="['subscriber_signup','subscriber_unsubscribed','email_opened','email_clicked'].includes(form.trigger_event)" class="mt-3">
                        <label class="text-sm text-gray-600 dark:text-gray-400">{{ $t('automations.builder.filter_list') }}</label>
                        <select v-model="form.trigger_config.list_id" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            <option :value="null">{{ $t('automations.builder.all_lists') }}</option>
                            <option v-for="l in lists" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>

                    <!-- Page Visit Config -->
                    <div v-if="form.trigger_event === 'page_visited'" class="mt-3">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Wzorzec URL (użyj * jako wieloznacznik)</label>
                        <input v-model="form.trigger_config.url_pattern" type="text" placeholder="https://twojastrona.pl/oferta/*" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"/>
                        <p class="text-xs text-gray-500 mt-1">Przykład: <code>*/cennik</code> lub <code>https://strona.pl/dziekujemy</code></p>
                    </div>

                    <!-- Specific Link Click Config -->
                    <div v-if="form.trigger_event === 'specific_link_clicked'" class="mt-3">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Link URL</label>
                        <input v-model="form.trigger_config.link_url" type="text" placeholder="https://..." class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"/>
                    </div>

                    <!-- Read Time Threshold Config -->
                    <div v-if="form.trigger_event === 'read_time_threshold'" class="mt-3">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Wymagany czas czytania (sekundy)</label>
                        <input v-model="form.trigger_config.read_time_threshold" type="number" min="5" placeholder="np. 30" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"/>
                    </div>

                    <!-- Date Reached Config -->
                    <div v-if="form.trigger_event === 'date_reached'" class="mt-3">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Data uruchomienia</label>
                        <input v-model="form.trigger_config.date" type="date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"/>
                    </div>
                </div>
                <!-- Conditions -->
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">🔍 {{ $t('automations.builder.if') }} <span class="text-sm text-gray-500">({{ $t('automations.builder.optional') }})</span></h3>
                        <button type="button" @click="addCondition" class="text-indigo-600 text-sm">+ {{ $t('automations.builder.add_condition') }}</button>
                    </div>
                    <div v-if="form.conditions.length" class="mb-3 flex gap-4">
                        <label class="flex items-center gap-1"><input type="radio" v-model="form.condition_logic" value="all"/> {{ $t('automations.builder.and') }}</label>
                        <label class="flex items-center gap-1"><input type="radio" v-model="form.condition_logic" value="any"/> {{ $t('automations.builder.or') }}</label>
                    </div>
                    <div v-for="(c, i) in form.conditions" :key="i" class="mb-2 flex gap-2 items-center bg-gray-50 dark:bg-gray-700 p-3 rounded">
                        <select v-model="c.type" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                            <option v-for="(l, k) in conditionTypes" :key="k" :value="k">{{ l }}</option>
                        </select>
                        <select v-if="c.type.includes('tag')" v-model="c.value" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                            <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <input v-else v-model="c.value" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600"/>
                        <button type="button" @click="removeCondition(i)" class="text-red-500">✕</button>
                    </div>
                </div>
                <!-- Actions -->
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">⚡ {{ $t('automations.builder.then') }}</h3>
                        <button type="button" @click="addAction" class="text-indigo-600 text-sm">+ {{ $t('automations.builder.add_action') }}</button>
                    </div>
                    <div v-for="(a, i) in form.actions" :key="i" class="mb-3 bg-gray-50 dark:bg-gray-700 p-4 rounded">
                        <div class="flex gap-2 items-start">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-sm flex items-center justify-center">{{ i+1 }}</span>
                            <div class="flex-1 space-y-2">
                                <select v-model="a.type" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option v-for="(l, k) in actionTypes" :key="k" :value="k">{{ l }}</option>
                                </select>
                                <select v-if="a.type.includes('tag')" v-model="a.config.tag_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <select v-else-if="a.type.includes('list')" v-model="a.config.list_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option v-for="l in lists" :key="l.id" :value="l.id">{{ l.name }}</option>
                                </select>
                                <select v-else-if="a.type==='send_email'" v-model="a.config.message_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option v-for="m in messages" :key="m.id" :value="m.id">{{ m.subject }}</option>
                                </select>
                                <select v-else-if="a.type==='start_funnel'" v-model="a.config.funnel_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option v-for="f in funnels" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                                <input v-else-if="a.type==='call_webhook'" v-model="a.config.url" type="url" placeholder="https://..." class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600"/>
                            </div>
                            <button type="button" @click="removeAction(i)" class="text-red-500">✕</button>
                        </div>
                    </div>
                    <p v-if="!form.actions.length" class="text-gray-500 text-center text-sm">{{ $t('automations.builder.add_action_hint') }}</p>
                </div>
                <!-- Submit -->
                <div class="flex justify-between items-center rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.is_active" class="rounded"/> {{ $t('automations.builder.activate_immediately') }}</label>
                    <div class="flex gap-3">
                        <Link :href="route('automations.index')" class="px-4 py-2 border rounded-lg">{{ $t('common.cancel') }}</Link>
                        <button type="submit" :disabled="form.processing || !form.actions.length" class="px-4 py-2 bg-indigo-600 text-white rounded-lg disabled:opacity-50">{{ isEditing ? $t('common.save') : $t('app.create') }}</button>
                    </div>
                </div>
            </form>
        </div></div>
    </AuthenticatedLayout>
</template>
