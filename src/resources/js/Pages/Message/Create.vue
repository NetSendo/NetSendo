<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AdvancedEditor from '@/Components/AdvancedEditor.vue';
import TemplateSelectModal from '@/Components/TemplateSelectModal.vue';
import MessageAiAssistant from '@/Components/MessageAiAssistant.vue';
import SubjectAiAssistant from '@/Components/SubjectAiAssistant.vue';
import { computed, ref, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import mjml2html from 'mjml-browser';

const { t } = useI18n();

const props = defineProps({
    message: {
        type: Object,
        default: null,
    },
    lists: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
    tags: {
        type: Array,
        default: () => [],
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    mailboxes: {
        type: Array,
        default: () => [],
    },
    templates: {
        type: Array,
        default: () => [],
    },
    defaultMailbox: {
        type: Object,
        default: null,
    },
});

const isEditing = computed(() => !!props.message);

// Active tab
const activeTab = ref('content'); // content, settings, triggers, ab_testing

// Template modal
const showTemplateModal = ref(false);
const showAiPanel = ref(false);

// Reference to advanced editor for mode control
const advancedEditorRef = ref(null);

// Subject emoji picker
const showSubjectEmojiPicker = ref(false);
const subjectInputRef = ref(null);
const subjectCursorPos = ref(0);

// Common emojis for subject
const subjectEmojis = [
    '😀', '😊', '🎉', '🔥', '💡', '✨', '🚀', '💪', '👍', '❤️',
    '📧', '📬', '💌', '🎯', '💰', '🏆', '⭐', '🌟', '💎', '🎁',
    '📢', '🔔', '⚡', '🌈', '☀️', '🌙', '🎊', '🎈', '🎀', '💝'
];

// Insert emoji into subject field
const insertSubjectEmoji = (emoji) => {
    const input = subjectInputRef.value?.$el || subjectInputRef.value;
    if (input) {
        const start = subjectCursorPos.value;
        const text = form.subject || '';
        form.subject = text.substring(0, start) + emoji + text.substring(start);
        showSubjectEmojiPicker.value = false;
        nextTick(() => {
            input.focus();
            const newPos = start + emoji.length;
            input.setSelectionRange(newPos, newPos);
        });
    }
};

// Track cursor position in subject
const updateSubjectCursor = (event) => {
    subjectCursorPos.value = event.target.selectionStart;
};

const form = useForm({
    subject: props.message?.subject || '',
    preheader: props.message?.preheader || '',
    type: props.message?.type || 'broadcast',
    day: props.message?.day || 0,
    contact_list_ids: props.message?.contact_list_ids || [],
    excluded_list_ids: props.message?.excluded_list_ids || [],
    status: props.message?.status || 'draft',
    content: props.message?.content || '',
    send_at: props.message?.send_at || null,
    time_of_day: props.message?.time_of_day || null,
    timezone: props.message?.timezone || null,
    // New fields
    template_id: props.message?.template_id || null,
    mailbox_id: props.message?.mailbox_id || null,
    ab_enabled: props.message?.ab_enabled || false,
    ab_variant_subject: props.message?.ab_variant_subject || '',
    ab_variant_content: props.message?.ab_variant_content || '',
    ab_split_percentage: props.message?.ab_split_percentage || 50,
    trigger_type: props.message?.trigger_type || null,
    trigger_config: props.message?.trigger_config || {},
});

// Selected template for display
const selectedTemplate = computed(() => {
    if (!form.template_id) return null;
    return props.templates.find(t => t.id === form.template_id);
});

// Determine effective mailbox source and info
const effectiveMailboxInfo = computed(() => {
    // Explicit selection
    if (form.mailbox_id) {
        const mailbox = props.mailboxes.find(m => m.id === form.mailbox_id);
        return {
            mailbox,
            source: 'message',
            label: t('messages.mailbox.selected_for_message'),
        };
    }

    // From contact list
    if (form.contact_list_ids.length > 0) {
        const firstListId = form.contact_list_ids[0];
        const list = props.lists.find(l => l.id === firstListId);
        if (list?.default_mailbox) {
            return {
                mailbox: list.default_mailbox,
                source: 'list',
                label: t('messages.mailbox.inherited_from_list', { name: list.name }),
            };
        }
    }

    // Global default
    if (props.defaultMailbox) {
        return {
            mailbox: props.defaultMailbox,
            source: 'global',
            label: t('messages.mailbox.inherited_global'),
        };
    }

    return {
        mailbox: null,
        source: null,
        label: t('messages.mailbox.none_configured'),
    };
});

// UI State for Broadcast Scheduling
const scheduleMode = ref(form.status === 'scheduled' ? 'later' : 'now');

watch(scheduleMode, (val) => {
    if (val === 'now') {
        form.send_at = null;
        if (form.status === 'scheduled') form.status = 'draft';
    }
});

// UI State for Autoresponder Time
const autoresponderTimeMode = ref(form.time_of_day ? 'custom' : 'signup');

watch(autoresponderTimeMode, (val) => {
    if (val === 'signup') {
        form.time_of_day = null;
    }
});

// Handle template selection - loads full content into editor
const handleTemplateSelect = async (template) => {
    form.template_id = template.id;
    
    let htmlContent = '';
    let templatePreheader = template.preheader || '';
    
    // If template has already compiled content, use it
    if (template.content) {
        htmlContent = template.content;
    } 
    // For MJML or builder templates, fetch from API and compile with mjml-browser
    else if (template.mjml_content || template.json_structure?.blocks) {
        try {
            const response = await axios.get(route('templates.compiled', template.id));
            
            // If API returned already compiled HTML
            if (response.data.html) {
                htmlContent = response.data.html;
            }
            // If API returned MJML (for frontend compilation)
            else if (response.data.mjml) {
                try {
                    const result = mjml2html(response.data.mjml, { validationLevel: 'soft' });
                    htmlContent = result.html;
                } catch (mjmlError) {
                    console.warn('MJML compilation failed:', mjmlError);
                    // Fallback: show raw MJML info
                    htmlContent = `<p>${t('messages.template.compilation_error')}</p>`;
                }
            }
            
            if (response.data.preheader) {
                templatePreheader = response.data.preheader;
            }
        } catch (error) {
            console.warn('Could not fetch template:', error);
        }
    }
    
    // Set the content - AdvancedEditor watcher will auto-switch to preview for full HTML
    form.content = htmlContent;
    
    // Set preheader from template if available
    if (templatePreheader) {
        form.preheader = templatePreheader;
    }
    
    showTemplateModal.value = false;
};

// Clear template selection
const clearTemplate = () => {
    form.template_id = null;
};

// Handle AI generated content - inserts/replaces content in editor
const handleAiInsertContent = (content) => {
    // Append AI content to existing content
    form.content = form.content + content;
    showAiPanel.value = false;
};

// Handle AI replace content
const handleAiReplaceContent = (content) => {
    // Replace all content with AI generated content
    form.content = content;
    showAiPanel.value = false;
};

// Handle AI subject selection
const handleSubjectSelect = (subject) => {
    form.subject = subject;
};

const submit = (targetStatus = null) => {
    if (targetStatus) {
        form.status = targetStatus;
    }

    // For broadcast, if sending now, clear send_at.
    if (form.type === 'broadcast') {
        if (targetStatus === 'sent' || targetStatus === 'draft') {
            form.send_at = null;
        }
    }

    // Wrap content with layout container if using WYSIWYG editor (no template)
    if (!selectedTemplate.value && advancedEditorRef.value?.getWrappedContent) {
        form.content = advancedEditorRef.value.getWrappedContent();
    }

    if (isEditing.value) {
        form.put(route('messages.update', props.message.id));
    } else {
        form.post(route('messages.store'));
    }
};

const autoresponderListId = computed({
    get: () => form.contact_list_ids[0],
    set: (val) => {
        form.contact_list_ids = val ? [val] : [];
    }
});

const areAllListsSelected = computed(() => {
    return props.lists.length > 0 && form.contact_list_ids.length === props.lists.length;
});

const toggleSelectAll = () => {
    if (areAllListsSelected.value) {
        form.contact_list_ids = [];
    } else {
        form.contact_list_ids = props.lists.map(list => list.id);
    }
};

// Selected filter mode and tag
const selectedFilterMode = ref('all'); // 'all', 'groups', 'tags'
const selectedTag = ref(null);

// Group lists by their actual CRM groups (from contact_list_group_id)
const groupedLists = computed(() => {
    const result = [];
    
    // Create a map of group_id -> group
    const groupMap = {};
    for (const group of props.groups) {
        groupMap[group.id] = { ...group, lists: [] };
    }
    
    // "Bez grupy" for lists without a group
    const ungrouped = { id: null, name: t('messages.fields.no_group'), lists: [] };
    
    // Assign lists to their groups
    for (const list of props.lists) {
        if (list.contact_list_group_id && groupMap[list.contact_list_group_id]) {
            groupMap[list.contact_list_group_id].lists.push(list);
        } else {
            ungrouped.lists.push(list);
        }
    }
    
    // Add groups that have lists
    for (const group of props.groups) {
        if (groupMap[group.id].lists.length > 0) {
            result.push(groupMap[group.id]);
        }
    }
    
    // Add ungrouped at the end if any
    if (ungrouped.lists.length > 0) {
        result.push(ungrouped);
    }
    
    return result;
});

// Lists filtered by selected tag
const listsFilteredByTag = computed(() => {
    if (!selectedTag.value) return props.lists;
    return props.lists.filter(list => 
        list.tags && list.tags.some(tag => tag.id === selectedTag.value)
    );
});

// Check if all lists in a group are selected
const isGroupSelected = (group) => {
    return group.lists.every(list => form.contact_list_ids.includes(list.id));
};

// Check if some (but not all) lists in a group are selected
const isGroupPartiallySelected = (group) => {
    const selectedCount = group.lists.filter(list => form.contact_list_ids.includes(list.id)).length;
    return selectedCount > 0 && selectedCount < group.lists.length;
};

// Toggle all lists in a group
const toggleGroup = (group) => {
    const groupIds = group.lists.map(list => list.id);
    const allSelected = isGroupSelected(group);
    
    if (allSelected) {
        form.contact_list_ids = form.contact_list_ids.filter(id => !groupIds.includes(id));
    } else {
        const newIds = [...new Set([...form.contact_list_ids, ...groupIds])];
        form.contact_list_ids = newIds;
    }
};

// Toggle all lists with a specific tag
const toggleByTag = (tagId) => {
    const listsWithTag = props.lists.filter(list => 
        list.tags && list.tags.some(tag => tag.id === tagId)
    );
    const tagListIds = listsWithTag.map(list => list.id);
    const allSelected = tagListIds.every(id => form.contact_list_ids.includes(id));
    
    if (allSelected) {
        form.contact_list_ids = form.contact_list_ids.filter(id => !tagListIds.includes(id));
    } else {
        const newIds = [...new Set([...form.contact_list_ids, ...tagListIds])];
        form.contact_list_ids = newIds;
    }
};

// Check if all lists with a tag are selected
const isTagSelected = (tagId) => {
    const listsWithTag = props.lists.filter(list => 
        list.tags && list.tags.some(tag => tag.id === tagId)
    );
    return listsWithTag.length > 0 && listsWithTag.every(list => form.contact_list_ids.includes(list.id));
};

// Count lists with a specific tag
const getTagListCount = (tagId) => {
    return props.lists.filter(list => 
        list.tags && list.tags.some(tag => tag.id === tagId)
    ).length;
};

// Test send modal
const showTestModal = ref(false);
const showScheduleModal = ref(false);
const testEmail = ref('');
const sendingTest = ref(false);

// Send test email
const sendTestEmail = async () => {
    if (!testEmail.value) return;
    
    sendingTest.value = true;
    try {
        // Use wrapped content for proper width/alignment
        const contentToSend = (!selectedTemplate.value && advancedEditorRef.value?.getWrappedContent)
            ? advancedEditorRef.value.getWrappedContent()
            : form.content;
            
        await axios.post(route('messages.test'), {
            email: testEmail.value,
            subject: form.subject,
            content: contentToSend,
            mailbox_id: form.mailbox_id || effectiveMailboxInfo.value.mailbox?.id,
        });
        showTestModal.value = false;
        testEmail.value = '';
    } catch (error) {
        console.error('Test send failed:', error);
    } finally {
        sendingTest.value = false;
    }
};

// Provider icons
const providerIcons = {
    smtp: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M2 6l10 7 10-7"/></svg>`,
    sendgrid: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M1.344 0v8.009h8.009V0zm6.912 6.912H2.441V1.097h5.815z"/></svg>`,
    gmail: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/></svg>`,
};

const providerColors = {
    smtp: '#6366F1',
    sendgrid: '#1A82E2',
    gmail: '#EA4335',
};

// Trigger types
const triggerTypes = [
    { value: 'signup', label: t('messages.triggers.types.signup'), icon: '📝' },
    { value: 'anniversary', label: t('messages.triggers.types.anniversary'), icon: '🎂' },
    { value: 'inactivity', label: t('messages.triggers.types.inactivity'), icon: '😴' },
    { value: 'custom', label: t('messages.triggers.types.custom'), icon: '⚙️' },
];
</script>

<template>
    <Head :title="isEditing ? $t('messages.edit_title') : $t('messages.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ isEditing ? $t('messages.edit_title') : $t('messages.create_title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ isEditing ? $t('messages.edit_subtitle') : $t('messages.create_subtitle') }}
                    </p>
                </div>
                <Link
                    :href="route('messages.index')"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                >
                    {{ $t('messages.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="rounded-2xl bg-white shadow-sm dark:bg-slate-900">
            <!-- Tabs -->
            <div class="border-b border-slate-200 dark:border-slate-700">
                <nav class="flex gap-1 px-4">
                    <button
                        @click="activeTab = 'content'"
                        :class="activeTab === 'content' 
                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ $t('messages.tabs.content') }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'settings'"
                        :class="activeTab === 'settings' 
                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ $t('messages.tabs.settings') }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'triggers'"
                        :class="activeTab === 'triggers' 
                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ $t('messages.tabs.triggers') }}
                            <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                {{ $t('common.soon') }}
                            </span>
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'ab_testing'"
                        :class="activeTab === 'ab_testing' 
                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            {{ $t('messages.tabs.ab_testing') }}
                            <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                {{ $t('common.soon') }}
                            </span>
                        </span>
                    </button>
                </nav>
            </div>

            <form @submit.prevent="submit()" class="p-6">
                <!-- TAB: Content -->
                <div v-show="activeTab === 'content'" class="space-y-6">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <!-- Left column: Main content -->
                        <div class="space-y-6 lg:col-span-2">
                            <!-- Type Selection -->
                            <div>
                                <InputLabel :value="$t('messages.fields.type')" class="mb-2" />
                                <div class="flex gap-4">
                                    <label class="flex flex-1 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 transition-all hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                                        :class="form.type === 'broadcast' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-900/20' : ''">
                                        <input type="radio" v-model="form.type" value="broadcast" class="text-indigo-600 focus:ring-indigo-500" />
                                        <div>
                                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('messages.fields.type_broadcast') }}</span>
                                            <span class="block text-xs text-slate-500">{{ $t('messages.fields.type_broadcast_desc') }}</span>
                                        </div>
                                    </label>
                                    <label class="flex flex-1 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 transition-all hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                                        :class="form.type === 'autoresponder' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-900/20' : ''">
                                        <input type="radio" v-model="form.type" value="autoresponder" class="text-indigo-600 focus:ring-indigo-500" />
                                        <div>
                                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('messages.fields.type_autoresponder') }}</span>
                                            <span class="block text-xs text-slate-500">{{ $t('messages.fields.type_autoresponder_desc') }}</span>
                                        </div>
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.type" />
                            </div>

                            <!-- Subject with Emoji Picker and AI -->
                            <div>
                                <InputLabel for="subject" :value="$t('messages.fields.subject')" />
                                <div class="relative mt-1">
                                    <TextInput
                                        ref="subjectInputRef"
                                        id="subject"
                                        type="text"
                                        class="block w-full pr-20"
                                        v-model="form.subject"
                                        :placeholder="$t('messages.fields.subject_placeholder')"
                                        @click="updateSubjectCursor"
                                        @keyup="updateSubjectCursor"
                                    />
                                    <!-- AI Button for Subject -->
                                    <div class="absolute right-8 top-1/2 -translate-y-1/2">
                                        <SubjectAiAssistant
                                            :current-content="form.content"
                                            @select-subject="handleSubjectSelect"
                                        />
                                    </div>
                                    <!-- Emoji Button for Subject -->
                                    <button
                                        type="button"
                                        @click="showSubjectEmojiPicker = !showSubjectEmojiPicker"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                                        title="Emoji"
                                    >
                                        <span class="text-lg">😊</span>
                                    </button>
                                    <!-- Emoji Dropdown for Subject -->
                                    <div 
                                        v-if="showSubjectEmojiPicker" 
                                        class="absolute right-0 top-full mt-1 p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 w-[280px]"
                                    >
                                        <div class="grid grid-cols-10 gap-1">
                                            <button
                                                v-for="emoji in subjectEmojis"
                                                :key="emoji"
                                                type="button"
                                                @click="insertSubjectEmoji(emoji)"
                                                class="p-1 text-lg hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                                            >
                                                {{ emoji }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.subject" />
                            </div>

                            <!-- Preheader (optional) -->
                            <div>
                                <InputLabel for="preheader">
                                    {{ $t('messages.fields.preheader') }}
                                    <span class="text-slate-400 font-normal ml-1">({{ $t('common.optional') }})</span>
                                </InputLabel>
                                <TextInput
                                    id="preheader"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.preheader"
                                    :placeholder="$t('messages.fields.preheader_placeholder')"
                                />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $t('messages.fields.preheader_help') }}
                                </p>
                                <InputError class="mt-2" :message="form.errors.preheader" />
                            </div>

                            <!-- Content Editor -->
                            <div>
                                <InputLabel for="content" :value="$t('messages.fields.content')" class="mb-2" />
                                <AdvancedEditor ref="advancedEditorRef" v-model="form.content" min-height="400px" />
                                <InputError class="mt-2" :message="form.errors.content" />
                            </div>
                        </div>

                        <!-- Right column: Tools -->
                        <div class="space-y-4">
                            <!-- Template Selection -->
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                                    <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                    </svg>
                                    {{ $t('messages.template.title') }}
                                </h3>

                                <div v-if="selectedTemplate" class="mb-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-800 dark:bg-indigo-900/20">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ selectedTemplate.name }}</span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="clearTemplate"
                                            class="text-indigo-500 hover:text-indigo-700"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    @click="showTemplateModal = true"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 py-3 text-sm font-medium text-slate-600 transition-colors hover:border-indigo-500 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-indigo-500 dark:hover:text-indigo-400"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                    </svg>
                                    {{ selectedTemplate ? $t('messages.template.change') : $t('messages.template.select') }}
                                </button>
                            </div>

                            <!-- AI Assistant -->
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                                    <svg class="h-4 w-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    {{ $t('messages.ai_assistant.title') }}
                                </h3>
                                <button
                                    type="button"
                                    @click="showAiPanel = true"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-all hover:from-indigo-600 hover:to-purple-700"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    {{ $t('messages.ai_assistant.generate') }}
                                </button>
                            </div>

                            <!-- Mailbox Preview -->
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                                    <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    {{ $t('messages.mailbox.label') }}
                                </h3>

                                <div v-if="effectiveMailboxInfo.mailbox" class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
                                    <div class="flex items-center gap-3">
                                        <div 
                                            class="flex h-10 w-10 items-center justify-center rounded-lg"
                                            :style="{ backgroundColor: providerColors[effectiveMailboxInfo.mailbox.provider] + '20' }"
                                        >
                                            <div 
                                                class="h-5 w-5"
                                                :style="{ color: providerColors[effectiveMailboxInfo.mailbox.provider] }"
                                                v-html="providerIcons[effectiveMailboxInfo.mailbox.provider]"
                                            ></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                                {{ effectiveMailboxInfo.mailbox.name }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                                {{ effectiveMailboxInfo.mailbox.from_email }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        {{ effectiveMailboxInfo.label }}
                                    </p>
                                </div>
                                <div v-else class="rounded-lg border-2 border-dashed border-slate-300 p-3 text-center dark:border-slate-600">
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ $t('messages.mailbox.none_configured') }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="activeTab = 'settings'"
                                    class="mt-3 text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    {{ $t('messages.mailbox.change_in_settings') }} →
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Settings -->
                <div v-show="activeTab === 'settings'" class="space-y-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Audience -->
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <InputLabel :value="$t('messages.fields.audience')" class="mb-3" />

                            <!-- Autoresponder: Single Select -->
                            <div v-if="form.type === 'autoresponder'">
                                <select
                                    v-model="autoresponderListId"
                                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    <option :value="undefined">{{ $t('messages.fields.select_list') }}</option>
                                    <option v-for="list in lists" :key="list.id" :value="list.id">
                                        {{ list.name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">{{ $t('messages.fields.time_help') }}</p>
                            </div>

                            <!-- Broadcast: Multi Select with Groups and Tags -->
                            <div v-else>
                                <!-- Selection controls -->
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                                        <input 
                                            type="checkbox" 
                                            :checked="areAllListsSelected"
                                            @change="toggleSelectAll"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                        >
                                        {{ $t('messages.fields.select_all') }}
                                    </label>
                                    <span class="text-xs text-slate-400">({{ form.contact_list_ids.length }}/{{ lists.length }})</span>
                                </div>

                                <!-- Tags quick select -->
                                <div v-if="tags.length > 0" class="mb-3">
                                    <div class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                                        {{ $t('messages.fields.select_by_tag') }}
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button 
                                            v-for="tag in tags" 
                                            :key="tag.id"
                                            @click="toggleByTag(tag.id)"
                                            :class="isTagSelected(tag.id) 
                                                ? 'bg-indigo-100 text-indigo-700 border-indigo-300 dark:bg-indigo-900/50 dark:text-indigo-300 dark:border-indigo-700' 
                                                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700 dark:hover:bg-slate-700'"
                                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                            {{ tag.name }}
                                            <span class="text-slate-400">({{ getTagListCount(tag.id) }})</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Lists grouped by CRM groups -->
                                <div class="max-h-64 overflow-y-auto rounded-md border border-slate-300 dark:border-slate-700 dark:bg-slate-900">
                                    <div v-for="group in groupedLists" :key="group.id ?? group.name" class="border-b border-slate-200 last:border-b-0 dark:border-slate-700">
                                        <!-- Group Header -->
                                        <div 
                                            class="flex items-center gap-2 bg-slate-50 px-3 py-2 dark:bg-slate-800"
                                            :class="{ 'cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700': group.lists.length > 1 }"
                                            @click="group.lists.length > 1 && toggleGroup(group)"
                                        >
                                            <input 
                                                v-if="group.lists.length > 1"
                                                type="checkbox" 
                                                :checked="isGroupSelected(group)"
                                                :indeterminate="isGroupPartiallySelected(group)"
                                                @click.stop
                                                @change="toggleGroup(group)"
                                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                            >
                                            <svg v-if="group.id" class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                            </svg>
                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                                {{ group.name }}
                                            </span>
                                            <span class="ml-auto rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-600 dark:text-slate-300">
                                                {{ group.lists.length }}
                                            </span>
                                        </div>
                                        <!-- Group Lists -->
                                        <div class="bg-white dark:bg-slate-900">
                                            <label 
                                                v-for="list in group.lists" 
                                                :key="list.id" 
                                                class="flex cursor-pointer items-center gap-2 py-1.5 pl-6 pr-3 hover:bg-slate-50 dark:hover:bg-slate-800"
                                            >
                                                <input 
                                                    type="checkbox" 
                                                    :value="list.id" 
                                                    v-model="form.contact_list_ids"
                                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                                >
                                                <span class="flex-1 text-sm text-slate-700 dark:text-slate-300">
                                                    {{ list.name }}
                                                </span>
                                                <!-- Show list tags -->
                                                <span v-if="list.tags && list.tags.length > 0" class="flex gap-1">
                                                    <span 
                                                        v-for="tag in list.tags.slice(0, 2)" 
                                                        :key="tag.id" 
                                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                                                    >
                                                        {{ tag.name }}
                                                    </span>
                                                    <span v-if="list.tags.length > 2" class="text-[10px] text-slate-400">
                                                        +{{ list.tags.length - 2 }}
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <div v-if="lists.length === 0" class="p-3 text-sm text-slate-400 italic">
                                        {{ $t('messages.fields.no_lists') }}
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $t('messages.fields.broadcast_help') }}</p>
                            </div>
                            <InputError class="mt-2" :message="form.errors.contact_list_ids" />
                        </div>

                        <!-- Excluded Lists (for broadcast only) -->
                        <div v-if="form.type === 'broadcast'" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="mb-3 flex items-center gap-2">
                                <svg class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                <InputLabel :value="$t('messages.fields.excluded_lists')" />
                            </div>
                            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ $t('messages.fields.excluded_lists_help') }}
                            </p>

                            <!-- Exclusion list checkboxes -->
                            <div class="max-h-48 overflow-y-auto rounded-md border border-slate-300 dark:border-slate-700 dark:bg-slate-900">
                                <div v-for="group in groupedLists" :key="group.id ?? group.name" class="border-b border-slate-200 last:border-b-0 dark:border-slate-700">
                                    <!-- Group Header -->
                                    <div class="flex items-center gap-2 bg-slate-50 px-3 py-2 dark:bg-slate-800">
                                        <svg v-if="group.id" class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                            {{ group.name }}
                                        </span>
                                    </div>
                                    <!-- Group Lists -->
                                    <div class="bg-white dark:bg-slate-900">
                                        <label 
                                            v-for="list in group.lists" 
                                            :key="'excl-' + list.id" 
                                            class="flex cursor-pointer items-center gap-2 py-1.5 pl-6 pr-3 hover:bg-red-50 dark:hover:bg-red-900/20"
                                        >
                                            <input 
                                                type="checkbox" 
                                                :value="list.id" 
                                                v-model="form.excluded_list_ids"
                                                class="rounded border-slate-300 text-red-600 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-800"
                                            >
                                            <span class="flex-1 text-sm text-slate-700 dark:text-slate-300">
                                                {{ list.name }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="lists.length === 0" class="p-3 text-sm text-slate-400 italic">
                                    {{ $t('messages.fields.no_lists') }}
                                </div>
                            </div>
                            
                            <!-- Count of excluded lists -->
                            <p v-if="form.excluded_list_ids.length > 0" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                {{ $t('messages.fields.excluded_count', { count: form.excluded_list_ids.length }) }}
                            </p>
                            <InputError class="mt-2" :message="form.errors.excluded_list_ids" />
                        </div>

                        <!-- Mailbox Selection -->
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <InputLabel :value="$t('messages.mailbox.label')" class="mb-3" />

                            <div v-if="effectiveMailboxInfo.mailbox && !form.mailbox_id" class="mb-3 rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20">
                                <div class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ effectiveMailboxInfo.label }}
                                </div>
                            </div>

                            <select
                                v-model="form.mailbox_id"
                                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            >
                                <option :value="null">{{ $t('messages.mailbox.use_default') }}</option>
                                <option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id">
                                    {{ mailbox.name }} ({{ mailbox.from_email }})
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $t('messages.mailbox.help') }}
                            </p>
                            <InputError class="mt-2" :message="form.errors.mailbox_id" />
                        </div>

                        <!-- Autoresponder Settings -->
                        <template v-if="form.type === 'autoresponder'">
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <InputLabel for="day" :value="$t('messages.fields.day')" class="mb-3" />
                                <TextInput
                                    id="day"
                                    type="number"
                                    min="0"
                                    class="block w-full"
                                    v-model="form.day"
                                    :placeholder="$t('messages.fields.day_placeholder')"
                                />
                                <p class="mt-1 text-xs text-slate-500">{{ $t('messages.fields.day_help') }}</p>
                                <InputError class="mt-2" :message="form.errors.day" />
                            </div>

                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <InputLabel :value="$t('messages.fields.time')" class="mb-3" />
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" v-model="autoresponderTimeMode" value="signup" class="text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $t('messages.fields.time_signup') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" v-model="autoresponderTimeMode" value="custom" class="text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $t('messages.fields.time_custom') }}</span>
                                    </label>
                                </div>

                                <div v-if="autoresponderTimeMode === 'custom'" class="mt-3">
                                    <input 
                                        type="time" 
                                        v-model="form.time_of_day" 
                                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    />
                                    <InputError class="mt-2" :message="form.errors.time_of_day" />
                                </div>
                            </div>
                        </template>

                        <!-- Broadcast Settings -->
                        <template v-if="form.type === 'broadcast'">
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700 lg:col-span-2">
                                <InputLabel :value="$t('messages.fields.scheduling')" class="mb-3" />
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="radio" v-model="scheduleMode" value="now" class="text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('messages.fields.schedule_now') }}</span>
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="radio" v-model="scheduleMode" value="later" class="text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('messages.fields.schedule_later') }}</span>
                                    </label>
                                </div>

                                <div v-if="scheduleMode === 'later'" class="mt-4">
                                    <InputLabel for="send_at" :value="$t('messages.fields.datetime')" />
                                    <input 
                                        type="datetime-local" 
                                        id="send_at" 
                                        v-model="form.send_at"
                                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    />
                                    <InputError class="mt-2" :message="form.errors.send_at" />
                                </div>
                            </div>
                        </template>

                        <!-- Timezone -->
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" :class="form.type === 'autoresponder' ? '' : 'lg:col-span-2'">
                            <InputLabel for="timezone" :value="$t('messages.fields.timezone')" class="mb-3" />
                            <select
                                id="timezone"
                                v-model="form.timezone"
                                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            >
                                <option :value="null">{{ $t('messages.fields.timezone_default') }}</option>
                                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ $t('messages.fields.timezone_help') }}</p>
                            <InputError class="mt-2" :message="form.errors.timezone" />
                        </div>
                    </div>
                </div>

                <!-- TAB: Triggers -->
                <div v-show="activeTab === 'triggers'" class="space-y-6">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center dark:border-amber-800 dark:bg-amber-900/20">
                        <svg class="mx-auto h-12 w-12 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-semibold text-amber-800 dark:text-amber-200">
                            {{ $t('messages.triggers.title') }}
                        </h3>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            {{ $t('messages.triggers.coming_soon') }}
                        </p>
                    </div>

                    <!-- Trigger Type Preview (disabled) -->
                    <div class="opacity-50 pointer-events-none">
                        <InputLabel :value="$t('messages.triggers.select_type')" class="mb-3" />
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div 
                                v-for="trigger in triggerTypes" 
                                :key="trigger.value"
                                class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <span class="text-2xl">{{ trigger.icon }}</span>
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ trigger.label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: A/B Testing -->
                <div v-show="activeTab === 'ab_testing'" class="space-y-6">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center dark:border-amber-800 dark:bg-amber-900/20">
                        <svg class="mx-auto h-12 w-12 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-semibold text-amber-800 dark:text-amber-200">
                            {{ $t('messages.ab_testing.title') }}
                        </h3>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            {{ $t('messages.ab_testing.coming_soon') }}
                        </p>
                    </div>

                    <!-- A/B Testing Preview (disabled) -->
                    <div class="opacity-50 pointer-events-none space-y-4">
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <input type="checkbox" disabled class="rounded border-slate-300 text-indigo-600" />
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $t('messages.ab_testing.enable') }}</span>
                        </label>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                <h4 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $t('messages.ab_testing.variant_a') }}</h4>
                                <p class="text-sm text-slate-500">{{ $t('messages.ab_testing.variant_a_desc') }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                <h4 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $t('messages.ab_testing.variant_b') }}</h4>
                                <TextInput type="text" class="w-full" :placeholder="$t('messages.ab_testing.variant_b_placeholder')" disabled />
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ $t('messages.ab_testing.split') }}: 50%
                            </label>
                            <input type="range" min="10" max="90" value="50" disabled class="w-full" />
                        </div>
                    </div>
                </div>

                <!-- Actions Footer -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                    <Link
                        :href="route('messages.index')"
                        class="text-sm text-slate-600 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                    >
                        {{ $t('common.cancel') }}
                    </Link>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Test Button -->
                        <button
                            type="button"
                            @click="showTestModal = true"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{ $t('messages.actions.test') }}
                        </button>

                        <template v-if="form.type === 'broadcast'">
                            <!-- Save Draft -->
                            <SecondaryButton @click="submit('draft')" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                {{ $t('messages.actions.save_draft') }}
                            </SecondaryButton>
                            
                            <!-- Schedule Button -->
                            <button
                                type="button"
                                @click="activeTab = 'settings'"
                                class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ $t('messages.actions.schedule') }}
                            </button>
                            
                            <!-- Send Now / Submit Scheduled -->
                            <PrimaryButton 
                                v-if="scheduleMode === 'now'"
                                @click="submit('scheduled')" 
                                :class="{ 'opacity-25': form.processing }" 
                                :disabled="form.processing"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                {{ $t('messages.actions.send_now') }}
                            </PrimaryButton>

                            <PrimaryButton 
                                v-if="scheduleMode === 'later' && form.send_at"
                                @click="submit('scheduled')" 
                                :class="{ 'opacity-25': form.processing }" 
                                :disabled="form.processing"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ $t('messages.actions.schedule_send') }}
                            </PrimaryButton>
                        </template>

                        <template v-else>
                            <SecondaryButton @click="submit('draft')" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                {{ $t('messages.actions.save_draft') }}
                            </SecondaryButton>
                            <PrimaryButton @click="submit('sent')" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                {{ $t('messages.actions.activate') }}
                            </PrimaryButton>
                        </template>
                    </div>
                </div>
            </form>
        </div>

        <!-- Template Selection Modal -->
        <TemplateSelectModal
            v-if="showTemplateModal"
            :templates="templates"
            :selected-template-id="form.template_id"
            @close="showTemplateModal = false"
            @select="handleTemplateSelect"
        />

        <!-- AI Assistant Panel -->
        <MessageAiAssistant
            v-if="showAiPanel"
            :currentContent="form.content"
            @close="showAiPanel = false"
            @insert-content="handleAiInsertContent"
            @replace-content="handleAiReplaceContent"
        />

        <!-- Test Send Modal -->
        <div v-if="showTestModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 backdrop-blur-sm">
            <div class="relative mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-800">
                <button
                    @click="showTestModal = false"
                    class="absolute right-4 top-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ $t('messages.test.title') }}
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $t('messages.test.subtitle') }}
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="test_email" :value="$t('messages.test.email_label')" />
                        <TextInput
                            id="test_email"
                            type="email"
                            v-model="testEmail"
                            class="mt-1 w-full"
                            :placeholder="$t('messages.test.email_placeholder')"
                        />
                    </div>

                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50">
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            <span class="font-medium">{{ $t('messages.test.preview_subject') }}:</span> {{ form.subject || $t('messages.test.no_subject_preview') }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('messages.test.preview_mailbox') }}: {{ effectiveMailboxInfo.mailbox?.name || $t('messages.test.no_mailbox_preview') }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showTestModal = false">
                        {{ $t('common.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton 
                        @click="sendTestEmail" 
                        :disabled="!testEmail || sendingTest"
                        :class="{ 'opacity-50': !testEmail || sendingTest }"
                    >
                        <svg v-if="sendingTest" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        {{ sendingTest ? $t('messages.test.sending') : $t('messages.test.send') }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
