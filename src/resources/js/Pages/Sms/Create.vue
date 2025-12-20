<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ActionMessage from '@/Components/ActionMessage.vue';

const props = defineProps({
    sms: Object,
    lists: Array,
});

const isEditing = computed(() => !!props.sms);

const form = useForm({
    subject: props.sms?.subject || '',
    content: props.sms?.content || '',
    list_id: props.sms?.list_id || '',
    type: props.sms?.type || 'broadcast',
    day: props.sms?.day || 0,
    time: props.sms?.time || '09:00',
    schedule_date: props.sms?.scheduled_at ? props.sms.scheduled_at.substring(0, 16) : '',
    status: props.sms?.status || 'draft',
});

const activeTab = ref('content');
const segments = computed(() => {
    // Basic calculation: 160 chars per SMS if pure GSM, 70 if Unicode. 
    // For MVP/simplicity, using 160 assumption or just pure length / 160.
    // Ideally we'd detect non-GSM chars.
    const isUnicode = /[^\x00-\x7F]/.test(form.content);
    const limit = isUnicode ? 70 : 160;
    return Math.ceil(form.content.length / limit) || 1;
});

const isUnicode = computed(() => /[^\x00-\x7F]/.test(form.content));

const submit = (targetStatus = null) => {
    if (targetStatus) {
        form.status = targetStatus;
    }

    if (isEditing.value) {
        form.put(route('sms.update', props.sms.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('sms.store'), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="isEditing ? $t('sms.edit_title') : $t('sms.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                     <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ isEditing ? $t('sms.edit_title') : $t('sms.create_title') }}
                    </h1>
                     <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ isEditing ? $t('sms.edit_subtitle') : $t('sms.create_subtitle') }}
                    </p>
                </div>
                 <Link
                    :href="route('sms.index')"
                    class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                    &larr; {{ $t('sms.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
             <!-- Left Column: Form -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Main Settings Card -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900">
                    <form @submit.prevent="submit('draft')" class="space-y-6">
                        
                        <!-- Internal Name -->
                        <div>
                             <InputLabel for="subject" :value="$t('sms.fields.subject')" />
                             <TextInput
                                id="subject"
                                v-model="form.subject"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="$t('sms.fields.subject_placeholder')"
                             />
                             <InputError :message="form.errors.subject" class="mt-2" />
                        </div>

                         <!-- Tabs -->
                        <div class="border-b border-slate-200 dark:border-slate-700">
                            <nav class="-mb-px flex space-x-8">
                                <button
                                    type="button"
                                    @click="activeTab = 'content'"
                                    :class="[
                                        activeTab === 'content'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                                        'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium'
                                    ]"
                                >
                                    {{ $t('sms.tabs.content') }}
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'settings'"
                                    :class="[
                                        activeTab === 'settings'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                                        'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium'
                                    ]"
                                >
                                    {{ $t('sms.tabs.settings') }}
                                </button>
                            </nav>
                        </div>

                        <!-- Content Tab -->
                        <div v-show="activeTab === 'content'" class="space-y-6 pt-4">
                             <div>
                                <div class="flex justify-between">
                                    <InputLabel for="content" :value="$t('sms.fields.content')" />
                                    <span class="text-xs" :class="isUnicode ? 'text-orange-500' : 'text-slate-500'">
                                          {{ form.content.length }} chars / {{ segments }} SMS <span v-if="isUnicode">(Unicode)</span>
                                    </span>
                                </div>
                                <textarea
                                    id="content"
                                    v-model="form.content"
                                    rows="5"
                                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white sm:text-sm"
                                    :placeholder="$t('sms.fields.content_placeholder')"
                                ></textarea>
                                <InputError :message="form.errors.content" class="mt-2" />
                            </div>
                        </div>

                        <!-- Settings Tab -->
                         <div v-show="activeTab === 'settings'" class="space-y-6 pt-4">
                            <!-- Type Selection -->
                            <div>
                                <InputLabel :value="$t('sms.fields.type')" />
                                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <!-- Broadcast -->
                                    <div 
                                        class="cursor-pointer rounded-lg border p-4 hover:border-indigo-500"
                                        :class="form.type === 'broadcast' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-slate-200 dark:border-slate-700'"
                                        @click="form.type = 'broadcast'"
                                    >
                                        <div class="flex items-center">
                                            <input type="radio" v-model="form.type" value="broadcast" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500" />
                                            <span class="ml-2 font-medium text-slate-900 dark:text-white">{{ $t('sms.type_broadcast') }}</span>
                                        </div>
                                         <p class="mt-1 ml-6 text-xs text-slate-500">{{ $t('sms.fields.type_broadcast_desc') }}</p>
                                    </div>
                                    <!-- Autoresponder -->
                                    <div 
                                        class="cursor-pointer rounded-lg border p-4 hover:border-indigo-500"
                                        :class="form.type === 'autoresponder' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-slate-200 dark:border-slate-700'"
                                        @click="form.type = 'autoresponder'"
                                    >
                                        <div class="flex items-center">
                                            <input type="radio" v-model="form.type" value="autoresponder" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500" />
                                            <span class="ml-2 font-medium text-slate-900 dark:text-white">{{ $t('sms.type_autoresponder') }}</span>
                                        </div>
                                        <p class="mt-1 ml-6 text-xs text-slate-500">{{ $t('sms.fields.type_autoresponder_desc') }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- List Selection -->
                            <div>
                                <InputLabel for="list_id" :value="$t('sms.fields.audience')" />
                                <select
                                    id="list_id"
                                    v-model="form.list_id"
                                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                >
                                    <option value="">{{ $t('sms.fields.select_list') }}</option>
                                    <option v-for="list in lists" :key="list.id" :value="list.id">
                                        {{ list.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.list_id" class="mt-2" />
                            </div>

                            <!-- Autoresponder Settings -->
                            <div v-if="form.type === 'autoresponder'" class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="day" :value="$t('sms.fields.day')" />
                                    <TextInput id="day" v-model="form.day" type="number" min="0" class="mt-1 block w-full" />
                                    <p class="mt-1 text-xs text-slate-500">{{ $t('sms.fields.day_help') }}</p>
                                </div>
                                <div>
                                    <InputLabel for="time" :value="$t('sms.fields.time')" />
                                    <TextInput id="time" v-model="form.time" type="time" class="mt-1 block w-full" />
                                </div>
                            </div>

                            <!-- Sending / Scheduling -->
                            <div v-if="form.type === 'broadcast'">
                                <InputLabel :value="$t('sms.fields.scheduling')" />
                                 <div class="mt-2 space-y-4">
                                     <div class="flex items-center">
                                         <input 
                                            type="radio" 
                                            :value="'send_now'" 
                                            :checked="!form.schedule_date" 
                                            @change="form.schedule_date = ''"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500" 
                                            id="schedule_now"
                                         />
                                         <label for="schedule_now" class="ml-2 text-sm text-slate-700 dark:text-slate-300">
                                             {{ $t('sms.fields.schedule_now') }}
                                         </label>
                                     </div>
                                     <div class="flex items-center">
                                         <input 
                                            type="radio" 
                                            :value="'schedule'" 
                                            :checked="!!form.schedule_date" 
                                            @change="!form.schedule_date ? form.schedule_date = new Date().toISOString().slice(0, 16) : null"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500" 
                                            id="schedule_later"
                                         />
                                         <label for="schedule_later" class="ml-2 text-sm text-slate-700 dark:text-slate-300">
                                              {{ $t('sms.fields.schedule_later') }}
                                         </label>
                                     </div>
                                     <div v-if="form.schedule_date" class="ml-6">
                                         <InputLabel for="schedule_date" :value="$t('sms.fields.datetime')" />
                                         <TextInput id="schedule_date" v-model="form.schedule_date" type="datetime-local" class="mt-1 block w-full" />
                                     </div>
                                 </div>
                            </div>
                         </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Preview & Actions -->
            <div class="space-y-6">
                 <!-- Actions -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900">
                     <h3 class="font-medium text-slate-900 dark:text-white">{{ $t('sms.table.actions') }}</h3>
                     <div class="mt-4 space-y-3">
                         <PrimaryButton @click="submit('sent')" class="w-full justify-center" :disabled="form.processing">
                            {{ form.type === 'broadcast' && form.schedule_date ? $t('sms.actions.schedule') : $t('sms.actions.send_now') }}
                         </PrimaryButton>
                         <SecondaryButton @click="submit('draft')" class="w-full justify-center" :disabled="form.processing">
                             {{ $t('sms.actions.save_draft') }}
                         </SecondaryButton>
                     </div>
                     <ActionMessage :on="form.recentlySuccessful" class="mt-3">
                        {{ $t('common.saved') }}
                    </ActionMessage>
                </div>

                <!-- Phone Preview -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900">
                     <h3 class="font-medium text-slate-900 dark:text-white mb-4">Preview</h3>
                     <div class="mx-auto w-[280px] rounded-[3rem] bg-slate-800 p-4 shadow-2xl ring-8 ring-slate-900">
                         <div class="h-[500px] w-full bg-slate-100 dark:bg-slate-800 rounded-[2rem] overflow-hidden flex flex-col relative">
                             <!-- Notch -->
                             <div class="absolute top-0 left-1/2 -translate-x-1/2 h-6 w-32 bg-slate-900 rounded-b-xl z-10"></div>
                             
                             <!-- Header -->
                             <div class="bg-white dark:bg-slate-900 p-4 pt-10 border-b dark:border-slate-700 flex items-center gap-3">
                                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center text-white text-xs">NS</div>
                                <div>
                                    <div class="text-xs font-bold dark:text-white">NetSendo</div>
                                </div>
                             </div>

                             <!-- Content -->
                             <div class="flex-1 p-4 flex flex-col gap-4 overflow-y-auto bg-slate-50 dark:bg-slate-800">
                                 <div class="self-start max-w-[85%] rounded-2xl rounded-tl-none bg-slate-200 dark:bg-slate-700 p-3 text-sm text-slate-800 dark:text-slate-200 shadow-sm">
                                     {{ form.content || '...' }}
                                 </div>
                             </div>
                         </div>
                     </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
