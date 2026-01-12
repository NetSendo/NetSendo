<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm, Link, router } from "@inertiajs/vue3";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import AdvancedEditor from "@/Components/AdvancedEditor.vue";
import TemplateSelectModal from "@/Components/TemplateSelectModal.vue";
import MessageAiAssistant from "@/Components/MessageAiAssistant.vue";
import SubjectAiAssistant from "@/Components/SubjectAiAssistant.vue";
import InsertPickerModal from "@/Components/InsertPickerModal.vue";
import ResponsiveTabs from "@/Components/ResponsiveTabs.vue";
import TrackedLinksSection from "@/Components/TrackedLinksSection.vue";
import { computed, ref, watch, nextTick } from "vue";
import { useI18n } from "vue-i18n";
import mjml2html from "mjml-browser";

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
    inserts: {
        type: Array,
        default: () => [],
    },
    signatures: {
        type: Array,
        default: () => [],
    },
    systemVariables: {
        type: Array,
        default: () => [],
    },
    webinars: {
        type: Array,
        default: () => [],
    },
    preselectedListId: {
        type: Number,
        default: null,
    },
});

const isEditing = computed(() => !!props.message);

// Active tab
const activeTab = ref("content"); // content, settings, triggers, ab_testing

// Tab configuration for ResponsiveTabs component
const messageTabs = computed(() => [
    {
        id: "content",
        label: t("messages.tabs.content"),
        emoji: "✏️",
    },
    {
        id: "settings",
        label: t("messages.tabs.settings"),
        emoji: "⚙️",
    },
    {
        id: "triggers",
        label: t("messages.tabs.triggers"),
        emoji: "⚡",
        indicator: !!form.trigger_type,
    },
    {
        id: "ab_testing",
        label: t("messages.tabs.ab_testing"),
        emoji: "📊",
        badge: t("common.soon"),
    },
]);

// Template modal
const showTemplateModal = ref(false);
const showAiPanel = ref(false);
const showInsertPickerModal = ref(false);

// Reference to advanced editor for mode control
const advancedEditorRef = ref(null);

// Subject emoji picker
const showSubjectEmojiPicker = ref(false);
const subjectInputRef = ref(null);
const subjectCursorPos = ref(0);

// Subject variable picker
const showSubjectVariablePicker = ref(false);

// Preheader cursor tracking and variable picker
const preheaderInputRef = ref(null);
const preheaderCursorPos = ref(0);
const showPreheaderVariablePicker = ref(false);

// Available quick variables for subject/preheader
const quickVariables = [
    { code: "[[fname]]", label: "messages.fields.insert_fname" },
    { code: "[[!fname]]", label: "messages.fields.insert_fname_vocative" },
    { code: "{{męska|żeńska}}", label: "messages.fields.insert_gender_form" },
];

// Common emojis for subject
const subjectEmojis = [
    "😀",
    "😊",
    "🎉",
    "🔥",
    "💡",
    "✨",
    "🚀",
    "💪",
    "👍",
    "❤️",
    "📧",
    "📬",
    "💌",
    "🎯",
    "💰",
    "🏆",
    "⭐",
    "🌟",
    "💎",
    "🎁",
    "📢",
    "🔔",
    "⚡",
    "🌈",
    "☀️",
    "🌙",
    "🎊",
    "🎈",
    "🎀",
    "💝",
];

// Insert emoji into subject field
const insertSubjectEmoji = (emoji) => {
    const input = subjectInputRef.value?.$el || subjectInputRef.value;
    if (input) {
        const start = subjectCursorPos.value;
        const text = form.subject || "";
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

// Track cursor position in preheader
const updatePreheaderCursor = (event) => {
    preheaderCursorPos.value = event.target.selectionStart;
};

// Insert variable into subject field
const insertSubjectVariable = (code) => {
    const input = subjectInputRef.value?.$el || subjectInputRef.value;
    if (input) {
        const start = subjectCursorPos.value;
        const text = form.subject || "";
        form.subject = text.substring(0, start) + code + text.substring(start);
        showSubjectVariablePicker.value = false;
        nextTick(() => {
            input.focus();
            const newPos = start + code.length;
            input.setSelectionRange(newPos, newPos);
            subjectCursorPos.value = newPos;
        });
    }
};

// Insert variable into preheader field
const insertPreheaderVariable = (code) => {
    const input = preheaderInputRef.value?.$el || preheaderInputRef.value;
    if (input) {
        const start = preheaderCursorPos.value;
        const text = form.preheader || "";
        form.preheader =
            text.substring(0, start) + code + text.substring(start);
        showPreheaderVariablePicker.value = false;
        nextTick(() => {
            input.focus();
            const newPos = start + code.length;
            input.setSelectionRange(newPos, newPos);
            preheaderCursorPos.value = newPos;
        });
    }
};

const form = useForm({
    subject: props.message?.subject || "",
    preheader: props.message?.preheader || "",
    type: props.message?.type || "broadcast",
    day: props.message?.day || 0,
    contact_list_ids: props.message?.contact_list_ids || (props.preselectedListId ? [props.preselectedListId] : []),
    excluded_list_ids: props.message?.excluded_list_ids || [],
    status: props.message?.status || "draft",
    content: props.message?.content || "",
    send_at: props.message?.send_at || null,
    time_of_day: props.message?.time_of_day || null,
    timezone: props.message?.timezone || null,
    // New fields
    template_id: props.message?.template_id || null,
    mailbox_id: props.message?.mailbox_id || null,
    ab_enabled: props.message?.ab_enabled || false,
    ab_variant_subject: props.message?.ab_variant_subject || "",
    ab_variant_content: props.message?.ab_variant_content || "",
    ab_split_percentage: props.message?.ab_split_percentage || 50,
    trigger_type: props.message?.trigger_type || null,
    trigger_config: props.message?.trigger_config || {},
    // PDF Attachments
    attachments: [],
    remove_attachment_ids: [],
    // Webinar Integration
    webinar_id: props.message?.webinar_id || null,
    webinar_auto_register: props.message?.webinar_auto_register ?? true,
    // Tracked Links
    tracked_links: props.message?.tracked_links || [],
});

// Existing attachments from database (for editing)
const existingAttachments = ref(props.message?.attachments || []);

// Pending files to upload (File objects)
const pendingFiles = ref([]);

// Selected template for display
const selectedTemplate = computed(() => {
    if (!form.template_id) return null;
    return props.templates.find((t) => t.id === form.template_id);
});

// Determine effective mailbox source and info
const effectiveMailboxInfo = computed(() => {
    // Explicit selection
    if (form.mailbox_id) {
        const mailbox = props.mailboxes.find((m) => m.id === form.mailbox_id);
        return {
            mailbox,
            source: "message",
            label: t("messages.mailbox.selected_for_message"),
        };
    }

    // From contact list
    if (form.contact_list_ids.length > 0) {
        const firstListId = form.contact_list_ids[0];
        const list = props.lists.find((l) => l.id === firstListId);
        if (list?.default_mailbox) {
            return {
                mailbox: list.default_mailbox,
                source: "list",
                label: t("messages.mailbox.inherited_from_list", {
                    name: list.name,
                }),
            };
        }
    }

    // Global default
    if (props.defaultMailbox) {
        return {
            mailbox: props.defaultMailbox,
            source: "global",
            label: t("messages.mailbox.inherited_global"),
        };
    }

    return {
        mailbox: null,
        source: null,
        label: t("messages.mailbox.none_configured"),
    };
});

// Get list's default mailbox (if different from global default)
const listDefaultMailbox = computed(() => {
    if (form.contact_list_ids.length > 0) {
        const firstListId = form.contact_list_ids[0];
        const list = props.lists.find((l) => l.id === firstListId);
        if (
            list?.default_mailbox &&
            list.default_mailbox.id !== props.defaultMailbox?.id
        ) {
            return {
                mailbox: list.default_mailbox,
                listName: list.name,
            };
        }
    }
    return null;
});

// Get other mailboxes (excluding global default and list default)
const otherMailboxes = computed(() => {
    const excludeIds = [];
    if (props.defaultMailbox) {
        excludeIds.push(props.defaultMailbox.id);
    }
    if (listDefaultMailbox.value) {
        excludeIds.push(listDefaultMailbox.value.mailbox.id);
    }
    return props.mailboxes.filter((m) => !excludeIds.includes(m.id));
});

// UI State for Broadcast Scheduling
const scheduleMode = ref(form.status === "scheduled" ? "later" : "now");

watch(scheduleMode, (val) => {
    if (val === "now") {
        form.send_at = null;
        if (form.status === "scheduled") form.status = "draft";
    }
});

// UI State for Autoresponder Time
const autoresponderTimeMode = ref(form.time_of_day ? "custom" : "signup");

watch(autoresponderTimeMode, (val) => {
    if (val === "signup") {
        form.time_of_day = null;
    }
});

// Handle template selection - loads full content into editor
const handleTemplateSelect = async (template) => {
    form.template_id = template.id;

    let htmlContent = "";
    let templatePreheader = template.preheader || "";

    // If template has already compiled content, use it
    if (template.content) {
        htmlContent = template.content;
    }
    // For MJML or builder templates, fetch from API and compile with mjml-browser
    else if (template.mjml_content || template.json_structure?.blocks) {
        try {
            const response = await axios.get(
                route("templates.compiled", template.id)
            );

            // If API returned already compiled HTML
            if (response.data.html) {
                htmlContent = response.data.html;
            }
            // If API returned MJML (for frontend compilation)
            else if (response.data.mjml) {
                try {
                    const result = mjml2html(response.data.mjml, {
                        validationLevel: "soft",
                    });
                    htmlContent = result.html;
                } catch (mjmlError) {
                    console.warn("MJML compilation failed:", mjmlError);
                    // Fallback: show raw MJML info
                    htmlContent = `<p>${t(
                        "messages.template.compilation_error"
                    )}</p>`;
                }
            }

            if (response.data.preheader) {
                templatePreheader = response.data.preheader;
            }
        } catch (error) {
            console.warn("Could not fetch template:", error);
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

// Handle AI subject selection (supports both old string format and new object format)
const handleSubjectSelect = (data) => {
    if (typeof data === "object" && data.subject) {
        form.subject = data.subject;
        // Auto-fill preheader if provided and preheader field is empty
        if (data.preheader && !form.preheader) {
            form.preheader = data.preheader;
        }
    } else {
        // Backward compatibility for string format
        form.subject = data;
    }
};

// Merge HTML content intelligently - for signatures, insert before </body> or at end
const mergeHtmlContent = (template, signatureHtml) => {
    const lowerTemplate = template.trim().toLowerCase();

    // If template contains </body>, insert signature before it
    if (lowerTemplate.includes("</body>")) {
        return template.replace(
            /<\/body>/i,
            `\n<!-- Signature Start -->\n${signatureHtml}\n<!-- Signature End -->\n</body>`
        );
    }

    // If template contains tables (typical email template), find last content cell
    if (lowerTemplate.includes("</table>")) {
        // Find the last </td> before the last </table>
        const lastTableCloseIndex = template.lastIndexOf("</table>");
        const lastTdCloseBeforeTable = template.lastIndexOf(
            "</td>",
            lastTableCloseIndex
        );

        if (lastTdCloseBeforeTable > -1) {
            return (
                template.slice(0, lastTdCloseBeforeTable) +
                `\n<!-- Signature -->\n${signatureHtml}\n` +
                template.slice(lastTdCloseBeforeTable)
            );
        }
    }

    // For simple HTML - just append at the end
    return template + `\n${signatureHtml}`;
};

// Handle insert content at cursor position or smart merge for signatures
const handleInsert = (data) => {
    // Handle new format { content, type } or legacy string format
    const content = typeof data === "object" ? data.content : data;
    const type = typeof data === "object" ? data.type : "variable";

    if (type === "signature") {
        // For signatures - use smart HTML merging
        const currentContent =
            advancedEditorRef.value?.getSourceCode() || form.content;
        const mergedContent = mergeHtmlContent(currentContent, content);
        form.content = mergedContent;

        // Update editor's source code
        if (advancedEditorRef.value?.setSourceCode) {
            advancedEditorRef.value.setSourceCode(mergedContent);
        }
    } else {
        // For variables and inserts - insert at cursor position
        if (advancedEditorRef.value?.insertAtCursor) {
            advancedEditorRef.value.insertAtCursor(content);
        }
    }
    showInsertPickerModal.value = false;
};

// ===== PDF Attachments Logic =====
const fileInputRef = ref(null);
const isDragging = ref(false);
const maxFiles = 5;
const maxFileSize = 10 * 1024 * 1024; // 10MB

// Total attachments count (existing + pending)
const totalAttachments = computed(() => {
    return existingAttachments.value.length + pendingFiles.value.length;
});

// Handle file input change
const handleFileSelect = (event) => {
    const files = Array.from(event.target.files || []);
    addFiles(files);
    // Reset input so same file can be selected again
    if (fileInputRef.value) fileInputRef.value.value = "";
};

// Handle drag and drop
const handleDrop = (event) => {
    event.preventDefault();
    isDragging.value = false;
    const files = Array.from(event.dataTransfer?.files || []);
    addFiles(files);
};

// Add files to pending list
const addFiles = (files) => {
    for (const file of files) {
        // Validate file count
        if (totalAttachments.value >= maxFiles) {
            alert(t("messages.attachments.max_files", { count: maxFiles }));
            break;
        }

        // Validate file type
        if (file.type !== "application/pdf") {
            alert(t("messages.attachments.only_pdf"));
            continue;
        }

        // Validate file size
        if (file.size > maxFileSize) {
            alert(t("messages.attachments.max_size", { size: "10MB" }));
            continue;
        }

        // Add to pending files and form
        pendingFiles.value.push({
            id: Date.now() + Math.random(), // temp ID for UI
            file: file,
            name: file.name,
            size: file.size,
        });
    }

    // Update form attachments array
    form.attachments = pendingFiles.value.map((p) => p.file);
};

// Remove existing attachment (mark for deletion)
const removeExistingAttachment = (attachmentId) => {
    form.remove_attachment_ids.push(attachmentId);
    existingAttachments.value = existingAttachments.value.filter(
        (a) => a.id !== attachmentId
    );
};

// Remove pending file
const removePendingFile = (tempId) => {
    pendingFiles.value = pendingFiles.value.filter((p) => p.id !== tempId);
    form.attachments = pendingFiles.value.map((p) => p.file);
};

// Format file size
const formatFileSize = (bytes) => {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + " MB";
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + " KB";
    return bytes + " B";
};
// ===== End PDF Attachments Logic =====

const submit = (targetStatus = null) => {
    if (targetStatus) {
        form.status = targetStatus;
    }

    // For broadcast, if sending now, clear send_at.
    if (form.type === "broadcast") {
        if (targetStatus === "sent" || targetStatus === "draft") {
            form.send_at = null;
        }
    }

    // Wrap content with layout container if using WYSIWYG editor (no template)
    if (!selectedTemplate.value && advancedEditorRef.value?.getWrappedContent) {
        form.content = advancedEditorRef.value.getWrappedContent();
    }

    if (isEditing.value) {
        form.put(route("messages.update", props.message.id));
    } else {
        form.post(route("messages.store"));
    }
};

const autoresponderListId = computed({
    get: () => form.contact_list_ids[0],
    set: (val) => {
        form.contact_list_ids = val ? [val] : [];
    },
});

const areAllListsSelected = computed(() => {
    return (
        props.lists.length > 0 &&
        form.contact_list_ids.length === props.lists.length
    );
});

const toggleSelectAll = () => {
    if (areAllListsSelected.value) {
        form.contact_list_ids = [];
    } else {
        form.contact_list_ids = props.lists.map((list) => list.id);
    }
};

// Selected filter mode and tag
const selectedFilterMode = ref("all"); // 'all', 'groups', 'tags'
const selectedTag = ref(null);

// List type filter and search
const listTypeFilter = ref("");
const listSearch = ref("");

// Filter lists by type and search
const listsFilteredByTypeAndSearch = computed(() => {
    let result = props.lists;

    // Filter by type
    if (listTypeFilter.value) {
        result = result.filter((list) => list.type === listTypeFilter.value);
    }

    // Filter by search
    if (listSearch.value) {
        const search = listSearch.value.toLowerCase();
        result = result.filter((list) =>
            list.name.toLowerCase().includes(search)
        );
    }

    return result;
});

// Group lists by their actual CRM groups (from contact_list_group_id)
const groupedLists = computed(() => {
    const result = [];

    // Use filtered lists instead of all props.lists
    const filteredLists = listsFilteredByTypeAndSearch.value;

    // Create a map of group_id -> group
    const groupMap = {};
    for (const group of props.groups) {
        groupMap[group.id] = { ...group, lists: [] };
    }

    // "Bez grupy" for lists without a group
    const ungrouped = {
        id: null,
        name: t("messages.fields.no_group"),
        lists: [],
    };

    // Assign lists to their groups
    for (const list of filteredLists) {
        if (
            list.contact_list_group_id &&
            groupMap[list.contact_list_group_id]
        ) {
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
    return props.lists.filter(
        (list) =>
            list.tags && list.tags.some((tag) => tag.id === selectedTag.value)
    );
});

// Check if all lists in a group are selected
const isGroupSelected = (group) => {
    return group.lists.every((list) => form.contact_list_ids.includes(list.id));
};

// Check if some (but not all) lists in a group are selected
const isGroupPartiallySelected = (group) => {
    const selectedCount = group.lists.filter((list) =>
        form.contact_list_ids.includes(list.id)
    ).length;
    return selectedCount > 0 && selectedCount < group.lists.length;
};

// Toggle all lists in a group
const toggleGroup = (group) => {
    const groupIds = group.lists.map((list) => list.id);
    const allSelected = isGroupSelected(group);

    if (allSelected) {
        form.contact_list_ids = form.contact_list_ids.filter(
            (id) => !groupIds.includes(id)
        );
    } else {
        const newIds = [...new Set([...form.contact_list_ids, ...groupIds])];
        form.contact_list_ids = newIds;
    }
};

// Toggle all lists with a specific tag
const toggleByTag = (tagId) => {
    const listsWithTag = props.lists.filter(
        (list) => list.tags && list.tags.some((tag) => tag.id === tagId)
    );
    const tagListIds = listsWithTag.map((list) => list.id);
    const allSelected = tagListIds.every((id) =>
        form.contact_list_ids.includes(id)
    );

    if (allSelected) {
        form.contact_list_ids = form.contact_list_ids.filter(
            (id) => !tagListIds.includes(id)
        );
    } else {
        const newIds = [...new Set([...form.contact_list_ids, ...tagListIds])];
        form.contact_list_ids = newIds;
    }
};

// Check if all lists with a tag are selected
const isTagSelected = (tagId) => {
    const listsWithTag = props.lists.filter(
        (list) => list.tags && list.tags.some((tag) => tag.id === tagId)
    );
    return (
        listsWithTag.length > 0 &&
        listsWithTag.every((list) => form.contact_list_ids.includes(list.id))
    );
};

// Count lists with a specific tag
const getTagListCount = (tagId) => {
    return props.lists.filter(
        (list) => list.tags && list.tags.some((tag) => tag.id === tagId)
    ).length;
};

// Test send modal
const showTestModal = ref(false);
const showScheduleModal = ref(false);
const testEmail = ref("");
const sendingTest = ref(false);

// Send test email
const sendTestEmail = async () => {
    if (!testEmail.value) return;

    sendingTest.value = true;
    try {
        // Use wrapped content for proper width/alignment
        const contentToSend =
            !selectedTemplate.value &&
            advancedEditorRef.value?.getWrappedContent
                ? advancedEditorRef.value.getWrappedContent()
                : form.content;

        await axios.post(route("messages.test"), {
            email: testEmail.value,
            subject: form.subject,
            content: contentToSend,
            preheader: form.preheader,
            mailbox_id:
                form.mailbox_id || effectiveMailboxInfo.value.mailbox?.id,
            contact_list_ids: form.contact_list_ids,
        });
        showTestModal.value = false;
        testEmail.value = "";
    } catch (error) {
        console.error("Test send failed:", error);
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
    smtp: "#6366F1",
    sendgrid: "#1A82E2",
    gmail: "#EA4335",
};

// Trigger types
const triggerTypes = [
    {
        value: "signup",
        label: t("messages.triggers.types.signup"),
        icon: "📝",
        description: t("messages.triggers.desc.signup"),
    },
    {
        value: "anniversary",
        label: t("messages.triggers.types.anniversary"),
        icon: "🎉",
        description: t("messages.triggers.desc.anniversary"),
    },
    {
        value: "birthday",
        label: t("messages.triggers.types.birthday"),
        icon: "🎂",
        description: t("messages.triggers.desc.birthday"),
    },
    {
        value: "inactivity",
        label: t("messages.triggers.types.inactivity"),
        icon: "😴",
        description: t("messages.triggers.desc.inactivity"),
    },
    {
        value: "page_visit",
        label: t("messages.triggers.types.page_visit"),
        icon: "🌐",
        description: t("messages.triggers.desc.page_visit"),
    },
    {
        value: "custom",
        label: t("messages.triggers.types.custom"),
        icon: "⚙️",
        description: t("messages.triggers.desc.custom"),
    },
];

// Preview Logic
const previewSubscriber = ref(null);
const previewSubscribers = ref([]);
const previewSearch = ref("");
const isPreviewLoading = ref(false);
const previewHtml = ref(null);

// Fetch random subscribers for preview
const fetchPreviewSubscribers = async () => {
    try {
        const response = await axios.post(
            route("messages.preview-subscribers"),
            {
                contact_list_ids: form.contact_list_ids,
                search: previewSearch.value,
            }
        );
        previewSubscribers.value = response.data.subscribers;

        // If we have subscribers but none selected, select first one?
        // No, let user choose, default is raw placeholders.
    } catch (e) {
        console.error(e);
    }
};

// Fetch preview content
const fetchPreviewContent = async () => {
    if (!previewSubscriber.value) {
        previewHtml.value = null;
        return;
    }

    // Only fetch if we have content
    const content = advancedEditorRef.value?.getSourceCode() || form.content;
    if (!content) return;

    isPreviewLoading.value = true;
    try {
        const response = await axios.post(route("messages.preview"), {
            subject: form.subject,
            content: content,
            preheader: form.preheader,
            subscriber_id: previewSubscriber.value.id,
        });
        previewHtml.value = response.data.content;
    } catch (e) {
        console.error(e);
        previewHtml.value = null;
    } finally {
        isPreviewLoading.value = false;
    }
};

// Watchers
watch(previewSubscriber, () => {
    fetchPreviewContent();
});

// Watch contact lists to refresh subscribers list
watch(
    () => form.contact_list_ids,
    () => {
        if (form.contact_list_ids.length > 0) {
            fetchPreviewSubscribers();
        }
    },
    { deep: true }
);

// Initial fetch if lists pre-selected
if (form.contact_list_ids.length > 0) {
    fetchPreviewSubscribers();
}
</script>

<template>
    <Head
        :title="
            isEditing ? $t('messages.edit_title') : $t('messages.create_title')
        "
    />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            isEditing
                                ? $t("messages.edit_title")
                                : $t("messages.create_title")
                        }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{
                            isEditing
                                ? $t("messages.edit_subtitle")
                                : $t("messages.create_subtitle")
                        }}
                    </p>
                </div>
                <Link
                    :href="route('messages.index')"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                >
                    {{ $t("messages.back_to_list") }}
                </Link>
            </div>
        </template>

        <div class="rounded-2xl bg-white shadow-sm dark:bg-slate-900">
            <!-- Responsive Tabs -->
            <div class="px-4 pt-4">
                <ResponsiveTabs v-model="activeTab" :tabs="messageTabs" />
            </div>

            <form @submit.prevent="submit()" class="p-6">
                <!-- TAB: Content -->
                <div v-show="activeTab === 'content'" class="space-y-6">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <!-- Left column: Main content -->
                        <div class="space-y-6 lg:col-span-2">
                            <!-- Type Selection -->
                            <div>
                                <InputLabel
                                    :value="$t('messages.fields.type')"
                                    class="mb-2"
                                />
                                <div class="flex gap-4">
                                    <label
                                        class="flex flex-1 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 transition-all hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                                        :class="
                                            form.type === 'broadcast'
                                                ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-900/20'
                                                : ''
                                        "
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.type"
                                            value="broadcast"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <div>
                                            <span
                                                class="block text-sm font-medium text-slate-900 dark:text-white"
                                                >{{
                                                    $t(
                                                        "messages.fields.type_broadcast"
                                                    )
                                                }}</span
                                            >
                                            <span
                                                class="block text-xs text-slate-500"
                                                >{{
                                                    $t(
                                                        "messages.fields.type_broadcast_desc"
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </label>
                                    <label
                                        class="flex flex-1 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 transition-all hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                                        :class="
                                            form.type === 'autoresponder'
                                                ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-900/20'
                                                : ''
                                        "
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.type"
                                            value="autoresponder"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <div>
                                            <span
                                                class="block text-sm font-medium text-slate-900 dark:text-white"
                                                >{{
                                                    $t(
                                                        "messages.fields.type_autoresponder"
                                                    )
                                                }}</span
                                            >
                                            <span
                                                class="block text-xs text-slate-500"
                                                >{{
                                                    $t(
                                                        "messages.fields.type_autoresponder_desc"
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </label>
                                </div>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.type"
                                />
                            </div>

                            <!-- Subject with Emoji Picker and AI -->
                            <div>
                                <InputLabel
                                    for="subject"
                                    :value="$t('messages.fields.subject')"
                                />
                                <div class="relative mt-1 z-40">
                                    <TextInput
                                        ref="subjectInputRef"
                                        id="subject"
                                        type="text"
                                        class="block w-full pr-20"
                                        v-model="form.subject"
                                        :placeholder="
                                            $t(
                                                'messages.fields.subject_placeholder'
                                            )
                                        "
                                        @click="updateSubjectCursor"
                                        @keyup="updateSubjectCursor"
                                    />
                                    <!-- Variable Button for Subject -->
                                    <div
                                        class="absolute right-16 top-1/2 -translate-y-1/2"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                showSubjectVariablePicker =
                                                    !showSubjectVariablePicker
                                            "
                                            class="p-1 text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors"
                                            :title="
                                                $t(
                                                    'messages.fields.insert_variable'
                                                )
                                            "
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                />
                                            </svg>
                                        </button>
                                        <!-- Variable Dropdown for Subject -->
                                        <div
                                            v-if="showSubjectVariablePicker"
                                            class="absolute right-0 top-full mt-1 p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-[100] min-w-[180px]"
                                        >
                                            <button
                                                v-for="variable in quickVariables"
                                                :key="variable.code"
                                                type="button"
                                                @click="
                                                    insertSubjectVariable(
                                                        variable.code
                                                    )
                                                "
                                                class="w-full text-left px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-2"
                                            >
                                                <code
                                                    class="text-xs text-indigo-600 dark:text-indigo-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded"
                                                    v-text="variable.code"
                                                ></code>
                                                <span
                                                    class="text-slate-700 dark:text-slate-300"
                                                    >{{
                                                        $t(variable.label)
                                                    }}</span
                                                >
                                            </button>
                                        </div>
                                    </div>
                                    <!-- AI Button for Subject -->
                                    <div
                                        class="absolute right-8 top-1/2 -translate-y-1/2"
                                    >
                                        <SubjectAiAssistant
                                            :current-content="form.content"
                                            @select="handleSubjectSelect"
                                        />
                                    </div>
                                    <!-- Emoji Button for Subject -->
                                    <button
                                        type="button"
                                        @click="
                                            showSubjectEmojiPicker =
                                                !showSubjectEmojiPicker
                                        "
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
                                                @click="
                                                    insertSubjectEmoji(emoji)
                                                "
                                                class="p-1 text-lg hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                                            >
                                                {{ emoji }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.subject"
                                />
                            </div>

                            <!-- Preheader (optional) -->
                            <div>
                                <InputLabel for="preheader">
                                    {{ $t("messages.fields.preheader") }}
                                    <span
                                        class="text-slate-400 font-normal ml-1"
                                        >({{ $t("common.optional") }})</span
                                    >
                                </InputLabel>
                                <div class="relative mt-1 z-30">
                                    <TextInput
                                        ref="preheaderInputRef"
                                        id="preheader"
                                        type="text"
                                        class="block w-full pr-10"
                                        v-model="form.preheader"
                                        :placeholder="
                                            $t(
                                                'messages.fields.preheader_placeholder'
                                            )
                                        "
                                        @click="updatePreheaderCursor"
                                        @keyup="updatePreheaderCursor"
                                    />
                                    <!-- Variable Button for Preheader -->
                                    <div
                                        class="absolute right-2 top-1/2 -translate-y-1/2"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                showPreheaderVariablePicker =
                                                    !showPreheaderVariablePicker
                                            "
                                            class="p-1 text-slate-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors"
                                            :title="
                                                $t(
                                                    'messages.fields.insert_variable'
                                                )
                                            "
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                />
                                            </svg>
                                        </button>
                                        <!-- Variable Dropdown for Preheader -->
                                        <div
                                            v-if="showPreheaderVariablePicker"
                                            class="absolute right-0 top-full mt-1 p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-[100] min-w-[180px]"
                                        >
                                            <button
                                                v-for="variable in quickVariables"
                                                :key="variable.code"
                                                type="button"
                                                @click="
                                                    insertPreheaderVariable(
                                                        variable.code
                                                    )
                                                "
                                                class="w-full text-left px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-2"
                                            >
                                                <code
                                                    class="text-xs text-indigo-600 dark:text-indigo-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded"
                                                    v-text="variable.code"
                                                ></code>
                                                <span
                                                    class="text-slate-700 dark:text-slate-300"
                                                    >{{
                                                        $t(variable.label)
                                                    }}</span
                                                >
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ $t("messages.fields.preheader_help") }}
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.preheader"
                                />
                            </div>

                            <!-- Content Editor -->
                            <div>
                                <InputLabel
                                    for="content"
                                    :value="$t('messages.fields.content')"
                                    class="mb-2"
                                />
                                <AdvancedEditor
                                    ref="advancedEditorRef"
                                    v-model="form.content"
                                    min-height="400px"
                                    :external-preview-content="previewHtml"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.content"
                                />
                            </div>

                            <!-- PDF Attachments Section -->
                            <div class="mt-6">
                                <InputLabel class="mb-2">
                                    📎 {{ $t("messages.attachments.title") }}
                                    <span
                                        class="text-slate-400 font-normal ml-1"
                                        >({{ $t("common.optional") }})</span
                                    >
                                </InputLabel>

                                <!-- Dropzone -->
                                <div
                                    @dragenter.prevent="isDragging = true"
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop="handleDrop"
                                    :class="[
                                        'relative rounded-xl border-2 border-dashed p-6 text-center transition-all cursor-pointer',
                                        isDragging
                                            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                            : 'border-slate-300 dark:border-slate-600 hover:border-indigo-400 hover:bg-slate-50 dark:hover:bg-slate-800/50',
                                    ]"
                                    @click="fileInputRef?.click()"
                                >
                                    <input
                                        ref="fileInputRef"
                                        type="file"
                                        accept=".pdf,application/pdf"
                                        multiple
                                        class="hidden"
                                        @change="handleFileSelect"
                                    />

                                    <div
                                        class="flex flex-col items-center gap-2"
                                    >
                                        <svg
                                            class="h-10 w-10 text-slate-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.5"
                                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                            />
                                        </svg>
                                        <p
                                            class="text-sm text-slate-600 dark:text-slate-400"
                                        >
                                            {{
                                                $t(
                                                    "messages.attachments.dropzone"
                                                )
                                            }}
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            {{
                                                $t(
                                                    "messages.attachments.info",
                                                    { max: 5, size: "10MB" }
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Existing Attachments -->
                                <div
                                    v-if="existingAttachments.length > 0"
                                    class="mt-4"
                                >
                                    <p
                                        class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2"
                                    >
                                        {{
                                            $t("messages.attachments.existing")
                                        }}
                                    </p>
                                    <div class="space-y-2">
                                        <div
                                            v-for="attachment in existingAttachments"
                                            :key="attachment.id"
                                            class="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <svg
                                                    class="h-8 w-8 text-red-500"
                                                    fill="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M10.92,12.31C10.68,11.54 10.15,9.08 11.55,9.04C12.95,9 12.03,12.16 12.03,12.16C12.42,13.65 14.05,14.72 14.05,14.72C14.55,14.57 17.4,14.24 17,15.72C16.57,17.2 13.5,15.81 13.5,15.81C11.55,15.95 10.09,16.47 10.09,16.47C8.96,18.58 7.64,19.5 7.1,18.61C6.43,17.5 9.23,16.07 9.23,16.07C10.68,13.72 10.9,12.35 10.92,12.31Z"
                                                    />
                                                </svg>
                                                <div>
                                                    <p
                                                        class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate max-w-[200px]"
                                                    >
                                                        {{ attachment.name }}
                                                    </p>
                                                    <p
                                                        class="text-xs text-slate-400"
                                                    >
                                                        {{
                                                            attachment.formatted_size
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                @click="
                                                    removeExistingAttachment(
                                                        attachment.id
                                                    )
                                                "
                                                class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Files (new uploads) -->
                                <div
                                    v-if="pendingFiles.length > 0"
                                    class="mt-4"
                                >
                                    <p
                                        class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2"
                                    >
                                        {{ $t("messages.attachments.new") }}
                                    </p>
                                    <div class="space-y-2">
                                        <div
                                            v-for="file in pendingFiles"
                                            :key="file.id"
                                            class="flex items-center justify-between p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <svg
                                                    class="h-8 w-8 text-red-500"
                                                    fill="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M10.92,12.31C10.68,11.54 10.15,9.08 11.55,9.04C12.95,9 12.03,12.16 12.03,12.16C12.42,13.65 14.05,14.72 14.05,14.72C14.55,14.57 17.4,14.24 17,15.72C16.57,17.2 13.5,15.81 13.5,15.81C11.55,15.95 10.09,16.47 10.09,16.47C8.96,18.58 7.64,19.5 7.1,18.61C6.43,17.5 9.23,16.07 9.23,16.07C10.68,13.72 10.9,12.35 10.92,12.31Z"
                                                    />
                                                </svg>
                                                <div>
                                                    <p
                                                        class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate max-w-[200px]"
                                                    >
                                                        {{ file.name }}
                                                    </p>
                                                    <p
                                                        class="text-xs text-emerald-600 dark:text-emerald-400"
                                                    >
                                                        {{
                                                            formatFileSize(
                                                                file.size
                                                            )
                                                        }}
                                                        •
                                                        {{
                                                            $t(
                                                                "messages.attachments.ready"
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                @click="
                                                    removePendingFile(file.id)
                                                "
                                                class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attachment Errors -->
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.attachments"
                                />
                                <InputError
                                    class="mt-1"
                                    :message="form.errors['attachments.0']"
                                />
                            </div>
                        </div>

                        <!-- Right column: Tools -->
                        <div class="space-y-4">
                            <!-- Template Selection -->
                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h3
                                    class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    <svg
                                        class="h-4 w-4 text-indigo-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
                                        />
                                    </svg>
                                    {{ $t("messages.template.title") }}
                                </h3>

                                <div
                                    v-if="selectedTemplate"
                                    class="mb-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-800 dark:bg-indigo-900/20"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-2">
                                            <svg
                                                class="h-5 w-5 text-indigo-500"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                            <span
                                                class="text-sm font-medium text-indigo-700 dark:text-indigo-300"
                                                >{{
                                                    selectedTemplate.name
                                                }}</span
                                            >
                                        </div>
                                        <button
                                            type="button"
                                            @click="clearTemplate"
                                            class="text-indigo-500 hover:text-indigo-700"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    @click="showTemplateModal = true"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 py-3 text-sm font-medium text-slate-600 transition-colors hover:border-indigo-500 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-indigo-500 dark:hover:text-indigo-400"
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
                                            d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
                                        />
                                    </svg>
                                    {{
                                        selectedTemplate
                                            ? $t("messages.template.change")
                                            : $t("messages.template.select")
                                    }}
                                </button>
                            </div>

                            <!-- Webinar Integration -->
                            <div
                                v-if="webinars.length > 0"
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h3
                                    class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    <svg
                                        class="h-4 w-4 text-emerald-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                                        />
                                    </svg>
                                    {{ $t("messages.webinar.title") }}
                                </h3>

                                <select
                                    v-model="form.webinar_id"
                                    class="w-full rounded-lg border-slate-300 text-sm transition-colors focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                >
                                    <option :value="null">
                                        {{ $t("messages.webinar.none") }}
                                    </option>
                                    <option
                                        v-for="webinar in webinars"
                                        :key="webinar.id"
                                        :value="webinar.id"
                                    >
                                        {{ webinar.name }}
                                    </option>
                                </select>

                                <div
                                    v-if="form.webinar_id"
                                    class="mt-3 space-y-2"
                                >
                                    <label
                                        class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="form.webinar_auto_register"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        {{
                                            $t("messages.webinar.auto_register")
                                        }}
                                    </label>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            $t(
                                                "messages.webinar.placeholders_info"
                                            )
                                        }}
                                    </p>
                                    <div
                                        class="mt-2 rounded-lg bg-slate-100 p-2 dark:bg-slate-700"
                                    >
                                        <p
                                            class="text-xs font-mono text-slate-600 dark:text-slate-300"
                                        >
                                            [[webinar_register_link]]<br />
                                            [[webinar_watch_link]]
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- AI Assistant -->
                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h3
                                    class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    <svg
                                        class="h-4 w-4 text-purple-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z"
                                        />
                                    </svg>
                                    {{ $t("messages.ai_assistant.title") }}
                                </h3>
                                <button
                                    type="button"
                                    @click="showAiPanel = true"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-all hover:from-indigo-600 hover:to-purple-700"
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
                                            d="M13 10V3L4 14h7v7l9-11h-7z"
                                        />
                                    </svg>
                                    {{ $t("messages.ai_assistant.generate") }}
                                </button>
                            </div>

                            <!-- Insert Snippets & Variables -->
                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h3
                                    class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    <svg
                                        class="h-4 w-4 text-emerald-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
                                        />
                                    </svg>
                                    {{ $t("inserts.button_title") }}
                                </h3>
                                <button
                                    type="button"
                                    @click="showInsertPickerModal = true"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-all hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                            d="M12 4v16m8-8H4"
                                        />
                                    </svg>
                                    {{ $t("inserts.insert_variable") }}
                                </button>
                            </div>

                            <!-- Preview with Data -->
                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h3
                                    class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    <svg
                                        class="h-4 w-4 text-slate-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                        />
                                    </svg>
                                    {{
                                        $t("messages.preview.title") ||
                                        "Podgląd z danymi"
                                    }}
                                </h3>

                                <div class="space-y-3">
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                                        >
                                            {{
                                                $t(
                                                    "messages.preview.select_subscriber"
                                                ) ||
                                                "Wybierz odbiorcę do podglądu"
                                            }}
                                        </label>

                                        <!-- Search input -->
                                        <div class="mb-2 flex gap-2">
                                            <input
                                                v-model="previewSearch"
                                                type="text"
                                                :placeholder="
                                                    $t('common.search') ||
                                                    'Szukaj...'
                                                "
                                                class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                                @keyup.enter="
                                                    fetchPreviewSubscribers
                                                "
                                            />
                                            <button
                                                type="button"
                                                @click="fetchPreviewSubscribers"
                                                class="rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                                    />
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Select dropdown -->
                                        <select
                                            v-model="previewSubscriber"
                                            class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                        >
                                            <option :value="null">
                                                {{
                                                    $t(
                                                        "messages.preview.no_subscriber"
                                                    ) ||
                                                    "Brak (surowe placeholdery)"
                                                }}
                                            </option>
                                            <option
                                                v-for="sub in previewSubscribers"
                                                :key="sub.id"
                                                :value="sub"
                                            >
                                                {{ sub.email }} ({{
                                                    sub.first_name
                                                }}
                                                {{ sub.last_name }})
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                previewSubscribers.length ===
                                                    0 &&
                                                form.contact_list_ids.length > 0
                                            "
                                            class="mt-1 text-xs text-slate-400"
                                        >
                                            {{
                                                $t(
                                                    "messages.preview.no_results"
                                                ) ||
                                                "Brak wyników. Spróbuj wyszukać."
                                            }}
                                        </p>
                                    </div>

                                    <p
                                        v-if="previewSubscriber"
                                        class="text-xs text-slate-500"
                                    >
                                        {{
                                            $t("messages.preview.info") ||
                                            "Podgląd uwzględnia dane wybranego odbiorcy."
                                        }}
                                    </p>

                                    <button
                                        v-if="previewSubscriber"
                                        type="button"
                                        @click="fetchPreviewContent"
                                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                                    >
                                        <svg
                                            v-if="isPreviewLoading"
                                            class="h-3 w-3 animate-spin"
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
                                            class="h-3 w-3"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            ></path>
                                        </svg>
                                        {{
                                            $t("messages.preview.refresh") ||
                                            "Odśwież podgląd"
                                        }}
                                    </button>
                                </div>
                            </div>

                            <!-- Mailbox Preview -->
                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h3
                                    class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    <svg
                                        class="h-4 w-4 text-emerald-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                        />
                                    </svg>
                                    {{ $t("messages.mailbox.label") }}
                                </h3>

                                <div
                                    v-if="effectiveMailboxInfo.mailbox"
                                    class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-lg"
                                            :style="{
                                                backgroundColor:
                                                    providerColors[
                                                        effectiveMailboxInfo
                                                            .mailbox.provider
                                                    ] + '20',
                                            }"
                                        >
                                            <div
                                                class="h-5 w-5"
                                                :style="{
                                                    color: providerColors[
                                                        effectiveMailboxInfo
                                                            .mailbox.provider
                                                    ],
                                                }"
                                                v-html="
                                                    providerIcons[
                                                        effectiveMailboxInfo
                                                            .mailbox.provider
                                                    ]
                                                "
                                            ></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="truncate text-sm font-medium text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    effectiveMailboxInfo.mailbox
                                                        .name
                                                }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    effectiveMailboxInfo.mailbox
                                                        .from_email
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <p
                                        class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ effectiveMailboxInfo.label }}
                                    </p>
                                </div>
                                <div
                                    v-else
                                    class="rounded-lg border-2 border-dashed border-slate-300 p-3 text-center dark:border-slate-600"
                                >
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            $t(
                                                "messages.mailbox.none_configured"
                                            )
                                        }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="activeTab = 'settings'"
                                    class="mt-3 text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    {{
                                        $t(
                                            "messages.mailbox.change_in_settings"
                                        )
                                    }}
                                    →
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tracked Links Section -->
                    <TrackedLinksSection
                        v-model="form.tracked_links"
                        :content="form.content"
                        :lists="lists"
                    />
                </div>

                <!-- TAB: Settings -->
                <div v-show="activeTab === 'settings'" class="space-y-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Audience -->
                        <div
                            class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <InputLabel
                                :value="$t('messages.fields.audience')"
                                class="mb-3"
                            />

                            <!-- Autoresponder: Single Select -->
                            <div v-if="form.type === 'autoresponder'">
                                <!-- Filter Controls -->
                                <div class="mb-2 grid grid-cols-2 gap-2">
                                    <!-- List Type Filter -->
                                    <select
                                        v-model="listTypeFilter"
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="">
                                            {{
                                                $t("subscribers.all_list_types")
                                            }}
                                        </option>
                                        <option value="email">
                                            {{
                                                $t(
                                                    "subscribers.list_type_email"
                                                )
                                            }}
                                        </option>
                                        <option value="sms">
                                            {{
                                                $t("subscribers.list_type_sms")
                                            }}
                                        </option>
                                    </select>
                                    <!-- Search -->
                                    <input
                                        v-model="listSearch"
                                        type="text"
                                        :placeholder="
                                            $t('common.search') + '...'
                                        "
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <select
                                    v-model="autoresponderListId"
                                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    size="6"
                                >
                                    <option :value="undefined" disabled>
                                        {{ $t("messages.fields.select_list") }}
                                    </option>
                                    <option
                                        v-for="list in listsFilteredByTypeAndSearch"
                                        :key="list.id"
                                        :value="list.id"
                                    >
                                        {{ list.name }} ({{
                                            list.type === "sms"
                                                ? "SMS"
                                                : "Email"
                                        }})
                                    </option>
                                </select>
                                <p
                                    v-if="
                                        listsFilteredByTypeAndSearch.length ===
                                        0
                                    "
                                    class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                                >
                                    {{ $t("common.no_results") }}
                                </p>
                                <p v-else class="mt-1 text-xs text-slate-500">
                                    {{ listsFilteredByTypeAndSearch.length }}
                                    {{ $t("common.of") }} {{ lists.length }}
                                    {{ $t("common.lists_lowercase") }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $t("messages.fields.time_help") }}
                                </p>
                            </div>

                            <!-- Broadcast: Multi Select with Groups and Tags -->
                            <div v-else>
                                <!-- Filter Controls -->
                                <div class="mb-3 grid grid-cols-2 gap-2">
                                    <!-- List Type Filter -->
                                    <select
                                        v-model="listTypeFilter"
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="">
                                            {{
                                                $t("subscribers.all_list_types")
                                            }}
                                        </option>
                                        <option value="email">
                                            {{
                                                $t(
                                                    "subscribers.list_type_email"
                                                )
                                            }}
                                        </option>
                                        <option value="sms">
                                            {{
                                                $t("subscribers.list_type_sms")
                                            }}
                                        </option>
                                    </select>
                                    <!-- Search -->
                                    <input
                                        v-model="listSearch"
                                        type="text"
                                        :placeholder="
                                            $t('common.search') + '...'
                                        "
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <!-- Selection controls -->
                                <div
                                    class="mb-3 flex flex-wrap items-center gap-2"
                                >
                                    <label
                                        class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="areAllListsSelected"
                                            @change="toggleSelectAll"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                        />
                                        {{ $t("messages.fields.select_all") }}
                                    </label>
                                    <span class="text-xs text-slate-400">
                                        ({{ form.contact_list_ids.length }}/{{
                                            lists.length
                                        }})
                                        <span
                                            v-if="
                                                listsFilteredByTypeAndSearch.length <
                                                lists.length
                                            "
                                            class="text-amber-500"
                                        >
                                            — {{ $t("common.showing") }}
                                            {{
                                                listsFilteredByTypeAndSearch.length
                                            }}
                                        </span>
                                    </span>
                                </div>

                                <!-- Tags quick select -->
                                <div v-if="tags.length > 0" class="mb-3">
                                    <div
                                        class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            $t("messages.fields.select_by_tag")
                                        }}
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="tag in tags"
                                            :key="tag.id"
                                            @click="toggleByTag(tag.id)"
                                            :class="
                                                isTagSelected(tag.id)
                                                    ? 'bg-indigo-100 text-indigo-700 border-indigo-300 dark:bg-indigo-900/50 dark:text-indigo-300 dark:border-indigo-700'
                                                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700 dark:hover:bg-slate-700'
                                            "
                                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                                        >
                                            <svg
                                                class="h-3 w-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                                />
                                            </svg>
                                            {{ tag.name }}
                                            <span class="text-slate-400"
                                                >({{
                                                    getTagListCount(tag.id)
                                                }})</span
                                            >
                                        </button>
                                    </div>
                                </div>

                                <!-- Lists grouped by CRM groups -->
                                <div
                                    class="max-h-64 overflow-y-auto rounded-md border border-slate-300 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <div
                                        v-for="group in groupedLists"
                                        :key="group.id ?? group.name"
                                        class="border-b border-slate-200 last:border-b-0 dark:border-slate-700"
                                    >
                                        <!-- Group Header -->
                                        <div
                                            class="flex items-center gap-2 bg-slate-50 px-3 py-2 dark:bg-slate-800"
                                            :class="{
                                                'cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700':
                                                    group.lists.length > 1,
                                            }"
                                            @click="
                                                group.lists.length > 1 &&
                                                    toggleGroup(group)
                                            "
                                        >
                                            <input
                                                v-if="group.lists.length > 1"
                                                type="checkbox"
                                                :checked="
                                                    isGroupSelected(group)
                                                "
                                                :indeterminate="
                                                    isGroupPartiallySelected(
                                                        group
                                                    )
                                                "
                                                @click.stop
                                                @change="toggleGroup(group)"
                                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                            />
                                            <svg
                                                v-if="group.id"
                                                class="h-4 w-4 text-slate-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                                />
                                            </svg>
                                            <span
                                                class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                            >
                                                {{ group.name }}
                                            </span>
                                            <span
                                                class="ml-auto rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-600 dark:text-slate-300"
                                            >
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
                                                    v-model="
                                                        form.contact_list_ids
                                                    "
                                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                                />
                                                <span
                                                    class="flex-1 text-sm text-slate-700 dark:text-slate-300"
                                                >
                                                    {{ list.name }}
                                                    <span
                                                        class="ml-1 text-xs text-slate-400 dark:text-slate-500"
                                                    >
                                                        ({{
                                                            list.subscribers_count ??
                                                            0
                                                        }})
                                                    </span>
                                                </span>
                                                <!-- Show list tags -->
                                                <span
                                                    v-if="
                                                        list.tags &&
                                                        list.tags.length > 0
                                                    "
                                                    class="flex gap-1"
                                                >
                                                    <span
                                                        v-for="tag in list.tags.slice(
                                                            0,
                                                            2
                                                        )"
                                                        :key="tag.id"
                                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                                                    >
                                                        {{ tag.name }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            list.tags.length > 2
                                                        "
                                                        class="text-[10px] text-slate-400"
                                                    >
                                                        +{{
                                                            list.tags.length - 2
                                                        }}
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <div
                                        v-if="lists.length === 0"
                                        class="p-3 text-sm text-slate-400 italic"
                                    >
                                        {{ $t("messages.fields.no_lists") }}
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $t("messages.fields.broadcast_help") }}
                                </p>
                            </div>
                            <InputError
                                class="mt-2"
                                :message="form.errors.contact_list_ids"
                            />
                        </div>

                        <!-- Excluded Lists (for broadcast only) -->
                        <div
                            v-if="form.type === 'broadcast'"
                            class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <div class="mb-3 flex items-center gap-2">
                                <svg
                                    class="h-4 w-4 text-red-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                                    />
                                </svg>
                                <InputLabel
                                    :value="
                                        $t('messages.fields.excluded_lists')
                                    "
                                />
                            </div>
                            <p
                                class="mb-3 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("messages.fields.excluded_lists_help") }}
                            </p>

                            <!-- Exclusion list checkboxes -->
                            <div
                                class="max-h-48 overflow-y-auto rounded-md border border-slate-300 dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div
                                    v-for="group in groupedLists"
                                    :key="group.id ?? group.name"
                                    class="border-b border-slate-200 last:border-b-0 dark:border-slate-700"
                                >
                                    <!-- Group Header -->
                                    <div
                                        class="flex items-center gap-2 bg-slate-50 px-3 py-2 dark:bg-slate-800"
                                    >
                                        <svg
                                            v-if="group.id"
                                            class="h-4 w-4 text-slate-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                            />
                                        </svg>
                                        <span
                                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                        >
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
                                            />
                                            <span
                                                class="flex-1 text-sm text-slate-700 dark:text-slate-300"
                                            >
                                                {{ list.name }}
                                                <span
                                                    class="ml-1 text-xs text-slate-400 dark:text-slate-500"
                                                >
                                                    ({{
                                                        list.subscribers_count ??
                                                        0
                                                    }})
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div
                                    v-if="lists.length === 0"
                                    class="p-3 text-sm text-slate-400 italic"
                                >
                                    {{ $t("messages.fields.no_lists") }}
                                </div>
                            </div>

                            <!-- Count of excluded lists -->
                            <p
                                v-if="form.excluded_list_ids.length > 0"
                                class="mt-2 text-xs text-red-600 dark:text-red-400"
                            >
                                {{
                                    $t("messages.fields.excluded_count", {
                                        count: form.excluded_list_ids.length,
                                    })
                                }}
                            </p>
                            <InputError
                                class="mt-2"
                                :message="form.errors.excluded_list_ids"
                            />
                        </div>

                        <!-- Mailbox Selection -->
                        <div
                            class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <InputLabel
                                :value="$t('messages.mailbox.label')"
                                class="mb-3"
                            />

                            <div
                                v-if="
                                    effectiveMailboxInfo.mailbox &&
                                    !form.mailbox_id
                                "
                                class="mb-3 rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-300"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    {{ effectiveMailboxInfo.label }}
                                </div>
                            </div>

                            <select
                                v-model="form.mailbox_id"
                                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            >
                                <!-- Use Default (null = auto-resolve) -->
                                <optgroup
                                    :label="
                                        $t('messages.mailbox.group_default')
                                    "
                                >
                                    <option :value="null">
                                        {{ $t("messages.mailbox.use_auto") }}
                                        <template v-if="defaultMailbox">
                                            ({{ defaultMailbox.name }})
                                        </template>
                                    </option>
                                </optgroup>

                                <!-- List's Default Mailbox (if different from global) -->
                                <optgroup
                                    v-if="listDefaultMailbox"
                                    :label="
                                        $t('messages.mailbox.group_list', {
                                            name: listDefaultMailbox.listName,
                                        })
                                    "
                                >
                                    <option
                                        :value="listDefaultMailbox.mailbox.id"
                                    >
                                        {{ listDefaultMailbox.mailbox.name }}
                                        ({{
                                            listDefaultMailbox.mailbox
                                                .from_email
                                        }})
                                    </option>
                                </optgroup>

                                <!-- Other Mailboxes -->
                                <optgroup
                                    v-if="otherMailboxes.length > 0"
                                    :label="$t('messages.mailbox.group_other')"
                                >
                                    <option
                                        v-for="mailbox in otherMailboxes"
                                        :key="mailbox.id"
                                        :value="mailbox.id"
                                    >
                                        {{ mailbox.name }} ({{
                                            mailbox.from_email
                                        }})
                                    </option>
                                </optgroup>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $t("messages.mailbox.help") }}
                            </p>
                            <InputError
                                class="mt-2"
                                :message="form.errors.mailbox_id"
                            />
                        </div>

                        <!-- Autoresponder Settings -->
                        <template v-if="form.type === 'autoresponder'">
                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <InputLabel
                                    for="day"
                                    :value="$t('messages.fields.day')"
                                    class="mb-3"
                                />
                                <TextInput
                                    id="day"
                                    type="number"
                                    min="0"
                                    class="block w-full"
                                    v-model="form.day"
                                    :placeholder="
                                        $t('messages.fields.day_placeholder')
                                    "
                                />
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $t("messages.fields.day_help") }}
                                </p>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.day"
                                />
                            </div>

                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <InputLabel
                                    :value="$t('messages.fields.time')"
                                    class="mb-3"
                                />
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            v-model="autoresponderTimeMode"
                                            value="signup"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span
                                            class="text-sm text-slate-700 dark:text-slate-300"
                                            >{{
                                                $t(
                                                    "messages.fields.time_signup"
                                                )
                                            }}</span
                                        >
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            v-model="autoresponderTimeMode"
                                            value="custom"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span
                                            class="text-sm text-slate-700 dark:text-slate-300"
                                            >{{
                                                $t(
                                                    "messages.fields.time_custom"
                                                )
                                            }}</span
                                        >
                                    </label>
                                </div>

                                <div
                                    v-if="autoresponderTimeMode === 'custom'"
                                    class="mt-3"
                                >
                                    <input
                                        type="time"
                                        v-model="form.time_of_day"
                                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.time_of_day"
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- Broadcast Settings -->
                        <template v-if="form.type === 'broadcast'">
                            <div
                                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <InputLabel
                                    :value="$t('messages.fields.scheduling')"
                                    class="mb-3"
                                />
                                <div class="flex flex-wrap gap-4">
                                    <label
                                        class="flex cursor-pointer items-center gap-2"
                                    >
                                        <input
                                            type="radio"
                                            v-model="scheduleMode"
                                            value="now"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span
                                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                            >{{
                                                $t(
                                                    "messages.fields.schedule_now"
                                                )
                                            }}</span
                                        >
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-center gap-2"
                                    >
                                        <input
                                            type="radio"
                                            v-model="scheduleMode"
                                            value="later"
                                            class="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span
                                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                            >{{
                                                $t(
                                                    "messages.fields.schedule_later"
                                                )
                                            }}</span
                                        >
                                    </label>
                                </div>

                                <div
                                    v-if="scheduleMode === 'later'"
                                    class="mt-4"
                                >
                                    <InputLabel
                                        for="send_at"
                                        :value="$t('messages.fields.datetime')"
                                    />
                                    <input
                                        type="datetime-local"
                                        id="send_at"
                                        v-model="form.send_at"
                                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:[color-scheme:dark]"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.send_at"
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- Timezone -->
                        <div
                            class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <InputLabel
                                for="timezone"
                                :value="$t('messages.fields.timezone')"
                                class="mb-3"
                            />
                            <select
                                id="timezone"
                                v-model="form.timezone"
                                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            >
                                <option :value="null">
                                    {{ $t("messages.fields.timezone_default") }}
                                </option>
                                <option
                                    v-for="tz in timezones"
                                    :key="tz"
                                    :value="tz"
                                >
                                    {{ tz }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $t("messages.fields.timezone_help") }}
                            </p>
                            <InputError
                                class="mt-2"
                                :message="form.errors.timezone"
                            />
                        </div>
                    </div>
                </div>

                <!-- TAB: Triggers -->
                <div v-show="activeTab === 'triggers'" class="space-y-6">
                    <!-- Info Banner -->
                    <div
                        class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20"
                    >
                        <div class="flex gap-3">
                            <svg
                                class="h-5 w-5 flex-shrink-0 text-indigo-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <div>
                                <p
                                    class="text-sm text-indigo-800 dark:text-indigo-200"
                                >
                                    {{ $t("messages.triggers.info") }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Trigger Type Selection -->
                    <div>
                        <InputLabel
                            :value="$t('messages.triggers.select_type')"
                            class="mb-3"
                        />
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <!-- No trigger option -->
                            <div
                                @click="
                                    form.trigger_type = null;
                                    form.trigger_config = {};
                                "
                                :class="
                                    !form.trigger_type
                                        ? 'border-slate-400 bg-slate-50 ring-1 ring-slate-400 dark:border-slate-500 dark:bg-slate-800'
                                        : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'
                                "
                                class="cursor-pointer rounded-lg border p-4 transition-all"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">🚫</span>
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-900 dark:text-white"
                                        >
                                            {{
                                                $t(
                                                    "messages.triggers.types.none"
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                $t(
                                                    "messages.triggers.desc.none"
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Trigger options -->
                            <div
                                v-for="trigger in triggerTypes"
                                :key="trigger.value"
                                @click="form.trigger_type = trigger.value"
                                :class="
                                    form.trigger_type === trigger.value
                                        ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:border-indigo-500 dark:bg-indigo-900/20'
                                        : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'
                                "
                                class="cursor-pointer rounded-lg border p-4 transition-all"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">{{
                                        trigger.icon
                                    }}</span>
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-900 dark:text-white"
                                        >
                                            {{ trigger.label }}
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ trigger.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trigger Configuration (shown when trigger is selected) -->
                    <div
                        v-if="form.trigger_type"
                        class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <h4
                            class="mb-4 text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            ⚙️ {{ $t("messages.triggers.config_title") }}
                        </h4>

                        <!-- Inactivity config -->
                        <div
                            v-if="form.trigger_type === 'inactivity'"
                            class="space-y-3"
                        >
                            <div>
                                <InputLabel
                                    :value="
                                        $t(
                                            'messages.triggers.config.inactive_days'
                                        )
                                    "
                                />
                                <select
                                    v-model="form.trigger_config.inactive_days"
                                    class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    <option :value="7">
                                        7 {{ $t("common.days") }}
                                    </option>
                                    <option :value="14">
                                        14 {{ $t("common.days") }}
                                    </option>
                                    <option :value="30">
                                        30 {{ $t("common.days") }}
                                    </option>
                                    <option :value="60">
                                        60 {{ $t("common.days") }}
                                    </option>
                                    <option :value="90">
                                        90 {{ $t("common.days") }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Page visit config -->
                        <div
                            v-if="form.trigger_type === 'page_visit'"
                            class="space-y-3"
                        >
                            <div>
                                <InputLabel
                                    :value="
                                        $t(
                                            'messages.triggers.config.url_pattern'
                                        )
                                    "
                                />
                                <TextInput
                                    v-model="form.trigger_config.url_pattern"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="https://example.com/pricing/*"
                                />
                                <p class="mt-1 text-xs text-slate-500">
                                    {{
                                        $t(
                                            "messages.triggers.config.url_pattern_help"
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Custom/tag config -->
                        <div
                            v-if="form.trigger_type === 'custom'"
                            class="space-y-3"
                        >
                            <div>
                                <InputLabel
                                    :value="$t('messages.triggers.config.tag')"
                                />
                                <select
                                    v-model="form.trigger_config.tag_id"
                                    class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    <option :value="null">
                                        {{
                                            $t(
                                                "messages.triggers.config.select_tag"
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="tag in tags"
                                        :key="tag.id"
                                        :value="tag.id"
                                    >
                                        {{ tag.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Anniversary/Birthday - no additional config needed -->
                        <div
                            v-if="
                                ['anniversary', 'birthday', 'signup'].includes(
                                    form.trigger_type
                                )
                            "
                            class="text-sm text-slate-500 dark:text-slate-400"
                        >
                            <p>
                                ✅
                                {{
                                    $t(
                                        "messages.triggers.config.no_config_needed"
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Link to Automations -->
                    <div
                        v-if="form.trigger_type"
                        class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{ $t("messages.triggers.advanced_link") }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        $t(
                                            "messages.triggers.advanced_link_desc"
                                        )
                                    }}
                                </p>
                            </div>
                            <Link
                                :href="route('automations.index')"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                            >
                                {{ $t("messages.triggers.go_to_automations") }}
                                →
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- TAB: A/B Testing -->
                <div v-show="activeTab === 'ab_testing'" class="space-y-6">
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center dark:border-amber-800 dark:bg-amber-900/20"
                    >
                        <svg
                            class="mx-auto h-12 w-12 text-amber-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                        <h3
                            class="mt-4 text-lg font-semibold text-amber-800 dark:text-amber-200"
                        >
                            {{ $t("messages.ab_testing.title") }}
                        </h3>
                        <p
                            class="mt-2 text-sm text-amber-700 dark:text-amber-300"
                        >
                            {{ $t("messages.ab_testing.coming_soon") }}
                        </p>
                    </div>

                    <!-- A/B Testing Preview (disabled) -->
                    <div class="opacity-50 pointer-events-none space-y-4">
                        <label
                            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <input
                                type="checkbox"
                                disabled
                                class="rounded border-slate-300 text-indigo-600"
                            />
                            <span
                                class="text-sm font-medium text-slate-900 dark:text-white"
                                >{{ $t("messages.ab_testing.enable") }}</span
                            >
                        </label>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div
                                class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h4
                                    class="mb-3 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ $t("messages.ab_testing.variant_a") }}
                                </h4>
                                <p class="text-sm text-slate-500">
                                    {{
                                        $t("messages.ab_testing.variant_a_desc")
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <h4
                                    class="mb-3 text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ $t("messages.ab_testing.variant_b") }}
                                </h4>
                                <TextInput
                                    type="text"
                                    class="w-full"
                                    :placeholder="
                                        $t(
                                            'messages.ab_testing.variant_b_placeholder'
                                        )
                                    "
                                    disabled
                                />
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <label
                                class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ $t("messages.ab_testing.split") }}: 50%
                            </label>
                            <input
                                type="range"
                                min="10"
                                max="90"
                                value="50"
                                disabled
                                class="w-full"
                            />
                        </div>
                    </div>
                </div>

                <!-- Actions Footer -->
                <div
                    class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-6 dark:border-slate-800"
                >
                    <Link
                        :href="route('messages.index')"
                        class="text-sm text-slate-600 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                    >
                        {{ $t("common.cancel") }}
                    </Link>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Test Button -->
                        <button
                            type="button"
                            @click="showTestModal = true"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>
                            {{ $t("messages.actions.test") }}
                        </button>

                        <template v-if="form.type === 'broadcast'">
                            <!-- Save Draft -->
                            <SecondaryButton
                                @click="submit('draft')"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ $t("messages.actions.save_draft") }}
                            </SecondaryButton>

                            <!-- Schedule Button -->
                            <button
                                type="button"
                                @click="activeTab = 'settings'"
                                class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                {{ $t("messages.actions.schedule") }}
                            </button>

                            <!-- Send Now / Submit Scheduled -->
                            <PrimaryButton
                                v-if="scheduleMode === 'now'"
                                @click="submit('scheduled')"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                <svg
                                    class="mr-2 h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                    />
                                </svg>
                                {{ $t("messages.actions.send_now") }}
                            </PrimaryButton>

                            <PrimaryButton
                                v-if="scheduleMode === 'later' && form.send_at"
                                @click="submit('scheduled')"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                <svg
                                    class="mr-2 h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                {{ $t("messages.actions.schedule_send") }}
                            </PrimaryButton>
                        </template>

                        <template v-else>
                            <SecondaryButton
                                @click="submit('draft')"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ $t("messages.actions.save_draft") }}
                            </SecondaryButton>
                            <PrimaryButton
                                @click="submit('scheduled')"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ $t("messages.actions.activate") }}
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
        <div
            v-if="showTestModal"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 backdrop-blur-sm"
        >
            <div
                class="relative mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-800"
            >
                <button
                    @click="showTestModal = false"
                    class="absolute right-4 top-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
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
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>

                <div class="flex items-center gap-3 mb-4">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                    >
                        <svg
                            class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            {{ $t("messages.test.title") }}
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $t("messages.test.subtitle") }}
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel
                            for="test_email"
                            :value="$t('messages.test.email_label')"
                        />
                        <TextInput
                            id="test_email"
                            type="email"
                            v-model="testEmail"
                            class="mt-1 w-full"
                            :placeholder="$t('messages.test.email_placeholder')"
                        />
                    </div>

                    <div
                        class="rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50"
                    >
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            <span class="font-medium"
                                >{{
                                    $t("messages.test.preview_subject")
                                }}:</span
                            >
                            {{
                                form.subject ||
                                $t("messages.test.no_subject_preview")
                            }}
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("messages.test.preview_mailbox") }}:
                            {{
                                effectiveMailboxInfo.mailbox?.name ||
                                $t("messages.test.no_mailbox_preview")
                            }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showTestModal = false">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                    <PrimaryButton
                        @click="sendTestEmail"
                        :disabled="!testEmail || sendingTest"
                        :class="{ 'opacity-50': !testEmail || sendingTest }"
                    >
                        <svg
                            v-if="sendingTest"
                            class="mr-2 h-4 w-4 animate-spin"
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
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{
                            sendingTest
                                ? $t("messages.test.sending")
                                : $t("messages.test.send")
                        }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Insert Picker Modal -->
    <InsertPickerModal
        :show="showInsertPickerModal"
        :system-variables="systemVariables"
        :inserts="inserts"
        :signatures="signatures"
        @close="showInsertPickerModal = false"
        @insert="handleInsert"
    />
</template>
