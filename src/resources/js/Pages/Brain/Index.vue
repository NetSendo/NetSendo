<script setup>
import { ref, computed, onMounted, nextTick, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import { marked } from "marked";

// Configure marked for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
});

const renderMarkdown = (content) => {
    if (!content) return "";
    return marked.parse(content);
};

const { t } = useI18n();

const props = defineProps({
    conversations: Array,
    settings: Object,
});

// --- State ---
const conversationList = ref(props.conversations || []);
const activeConversationId = ref(null);
const isNewConversation = ref(false);
const messages = ref([]);
const newMessage = ref("");
const isLoading = ref(false);
const isLoadingConversation = ref(false);
const chatContainer = ref(null);
const showMobileConversations = ref(false);

// --- Editable titles ---
const editingTitleId = ref(null);
const editingTitleValue = ref("");

const startEditTitle = (conv) => {
    editingTitleId.value = conv.id;
    editingTitleValue.value = conv.title || "";
    nextTick(() => {
        const input = document.querySelector(`#title-input-${conv.id}`);
        if (input) input.focus();
    });
};

const saveTitle = async (conv) => {
    const newTitle = editingTitleValue.value.trim();
    editingTitleId.value = null;
    if (!newTitle || newTitle === conv.title) return;
    conv.title = newTitle;
    try {
        await axios.put(`/brain/api/conversations/${conv.id}`, {
            title: newTitle,
        });
    } catch (error) {
        // Revert on error
        await refreshConversations();
    }
};

const cancelEditTitle = () => {
    editingTitleId.value = null;
};

// --- Computed ---
const activeConversation = computed(() =>
    conversationList.value.find((c) => c.id === activeConversationId.value),
);

// --- Auto-scroll ---
const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

watch(messages, scrollToBottom, { deep: true });

// --- API Methods ---
const sendMessage = async () => {
    const text = newMessage.value.trim();
    if (!text || isLoading.value) return;

    // Add user message to chat
    messages.value.push({
        id: Date.now(),
        role: "user",
        content: text,
        created_at: new Date().toISOString(),
    });

    newMessage.value = "";
    isLoading.value = true;

    // Create empty assistant message for streaming
    const assistantMsgId = Date.now() + 1;
    const assistantMsg = {
        id: assistantMsgId,
        role: "assistant",
        content: "",
        model_used: null,
        plan: null,
        created_at: new Date().toISOString(),
        isStreaming: true,
    };

    try {
        // Get CSRF token from meta tag or cookie
        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.content || "";

        const response = await fetch("/brain/api/chat/stream", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "text/event-stream",
                "X-CSRF-TOKEN": csrfToken,
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                message: text,
                conversation_id: activeConversationId.value,
                force_new: isNewConversation.value,
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        // Push the empty assistant message — typing dots disappear, streaming text appears
        messages.value.push(assistantMsg);
        isLoading.value = false; // Hide typing dots, streaming message is now visible

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = "";

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split("\n");
            // Keep the last (potentially incomplete) line in buffer
            buffer = lines.pop() || "";

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed.startsWith("data: ")) continue;

                const payload = trimmed.slice(6);
                try {
                    const json = JSON.parse(payload);

                    if (json.done) {
                        // Stream complete — update metadata
                        if (
                            json.conversation_id &&
                            !activeConversationId.value
                        ) {
                            activeConversationId.value = json.conversation_id;
                            isNewConversation.value = false;
                            await refreshConversations();
                        }

                        if (json.title) {
                            const conv = conversationList.value.find(
                                (c) =>
                                    c.id ===
                                    (json.conversation_id ||
                                        activeConversationId.value),
                            );
                            if (conv) conv.title = json.title;
                        }

                        // Update assistant message metadata
                        const msg = messages.value.find(
                            (m) => m.id === assistantMsgId,
                        );
                        if (msg) {
                            msg.model_used = json.model || null;
                            msg.plan = json.plan || null;
                            msg.isStreaming = false;
                        }
                    } else if (json.delta) {
                        // Append text chunk to streaming message
                        const msg = messages.value.find(
                            (m) => m.id === assistantMsgId,
                        );
                        if (msg) {
                            msg.content += json.delta;
                        }
                        scrollToBottom();
                    }
                } catch (e) {
                    // Skip malformed JSON lines
                }
            }
        }

        // Ensure streaming flag is off
        const finalMsg = messages.value.find((m) => m.id === assistantMsgId);
        if (finalMsg) finalMsg.isStreaming = false;
    } catch (error) {
        // Streaming failed — fall back to synchronous
        try {
            const fallbackResponse = await axios.post("/brain/api/chat", {
                message: text,
                conversation_id: activeConversationId.value,
                force_new: isNewConversation.value,
            });

            const data = fallbackResponse.data;

            if (data.conversation_id && !activeConversationId.value) {
                activeConversationId.value = data.conversation_id;
                isNewConversation.value = false;
                await refreshConversations();
            }

            if (data.title) {
                const conv = conversationList.value.find(
                    (c) =>
                        c.id ===
                        (data.conversation_id || activeConversationId.value),
                );
                if (conv) conv.title = data.title;
            }

            // If the streaming message was already pushed, update it
            const existingMsg = messages.value.find(
                (m) => m.id === assistantMsgId,
            );
            if (existingMsg) {
                existingMsg.content = data.response || data.message || "";
                existingMsg.model_used = data.model || null;
                existingMsg.plan = data.plan || null;
                existingMsg.isStreaming = false;
            } else {
                messages.value.push({
                    id: assistantMsgId,
                    role: "assistant",
                    content: data.response || data.message || "",
                    model_used: data.model || null,
                    plan: data.plan || null,
                    created_at: new Date().toISOString(),
                });
            }
        } catch (fallbackError) {
            messages.value.push({
                id: Date.now() + 2,
                role: "system",
                content:
                    fallbackError.response?.data?.error ||
                    t(
                        "brain.error_generic",
                        "Wystąpił błąd. Spróbuj ponownie.",
                    ),
                created_at: new Date().toISOString(),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const loadConversation = async (conversationId) => {
    if (conversationId === activeConversationId.value) return;

    activeConversationId.value = conversationId;
    isLoadingConversation.value = true;
    showMobileConversations.value = false;

    try {
        const response = await axios.get(
            `/brain/api/conversations/${conversationId}`,
        );
        messages.value = response.data.messages || [];
    } catch (error) {
        messages.value = [];
    } finally {
        isLoadingConversation.value = false;
    }
};

const startNewConversation = () => {
    activeConversationId.value = null;
    isNewConversation.value = true;
    messages.value = [];
    showMobileConversations.value = false;
};

const refreshConversations = async () => {
    try {
        const response = await axios.get("/brain/api/conversations");
        conversationList.value = response.data.data || response.data || [];
    } catch (error) {
        // Silent fail
    }
};

const handleKeyDown = (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};

// --- Plan Actions ---
const approvePlan = async (planId) => {
    try {
        await axios.post(`/brain/api/plans/${planId}/approve`, {
            approved: true,
        });
        // Refresh current conversation
        if (activeConversationId.value) {
            await loadConversation(activeConversationId.value);
        }
    } catch (error) {
        // Error handling
    }
};

const rejectPlan = async (planId) => {
    try {
        await axios.post(`/brain/api/plans/${planId}/approve`, {
            approved: false,
            reason: "User rejected from UI",
        });
        if (activeConversationId.value) {
            await loadConversation(activeConversationId.value);
        }
    } catch (error) {
        // Error handling
    }
};

// --- Format helpers ---
const formatTime = (dateStr) => {
    if (!dateStr) return "";
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
};

const formatDate = (dateStr) => {
    if (!dateStr) return "";
    const d = new Date(dateStr);
    const today = new Date();
    if (d.toDateString() === today.toDateString()) {
        return t("brain.today", "Dziś");
    }
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return t("brain.yesterday", "Wczoraj");
    }
    return d.toLocaleDateString();
};

const getLastMessage = (conversation) => {
    if (conversation.messages && conversation.messages.length > 0) {
        const msg = conversation.messages[0];
        return (
            msg.content?.substring(0, 60) +
                (msg.content?.length > 60 ? "..." : "") || ""
        );
    }
    return t("brain.no_messages", "Brak wiadomości");
};
</script>

<template>
    <Head :title="t('brain.title', 'NetSendo Brain')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 shadow-lg shadow-cyan-500/25"
                    >
                        <svg
                            class="h-5 w-5 text-white"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19 14.5M14.25 3.104c.251.023.501.05.75.082M19 14.5l-2.47 2.47a2.25 2.25 0 01-1.59.659H9.06a2.25 2.25 0 01-1.591-.659L5 14.5m14 0V17a2 2 0 01-2 2H7a2 2 0 01-2-2v-2.5"
                            />
                        </svg>
                    </div>
                    <div>
                        <h1
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ t("brain.title", "NetSendo Brain") }}
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{
                                t(
                                    "brain.subtitle",
                                    "Twój asystent AI do marketingu",
                                )
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-3 py-1 text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400':
                                settings?.work_mode === 'autonomous',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400':
                                settings?.work_mode === 'semi_auto',
                            'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300':
                                settings?.work_mode === 'manual',
                        }"
                    >
                        {{
                            settings?.work_mode === "autonomous"
                                ? t("brain.mode.autonomous", "Autonomiczny")
                                : settings?.work_mode === "semi_auto"
                                  ? t("brain.mode.semi_auto", "Półautomatyczny")
                                  : t("brain.mode.manual", "Doradczy")
                        }}
                    </span>
                    <!-- Mobile toggle -->
                    <button
                        class="rounded-lg bg-white/10 p-2 text-slate-400 hover:text-white lg:hidden"
                        @click="
                            showMobileConversations = !showMobileConversations
                        "
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
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <div class="flex h-[calc(100vh-180px)] gap-4">
            <!-- Conversation List (Left Panel) -->
            <div
                class="w-72 flex-shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800"
                :class="{
                    'fixed inset-0 z-50 w-full lg:relative lg:w-72':
                        showMobileConversations,
                    'hidden lg:block': !showMobileConversations,
                }"
            >
                <div class="flex h-full flex-col">
                    <!-- New Conversation Button -->
                    <div
                        class="border-b border-slate-200 p-3 dark:border-slate-700"
                    >
                        <button
                            @click="startNewConversation"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl hover:shadow-cyan-500/30"
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
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            {{
                                t("brain.new_conversation", "Nowa konwersacja")
                            }}
                        </button>
                    </div>

                    <!-- Conversations -->
                    <div class="flex-1 overflow-y-auto p-2">
                        <div
                            v-for="conv in conversationList"
                            :key="conv.id"
                            @click="
                                editingTitleId !== conv.id &&
                                loadConversation(conv.id)
                            "
                            class="group mb-1 cursor-pointer rounded-lg px-3 py-2.5 transition-all"
                            :class="{
                                'bg-gradient-to-r from-cyan-500/10 to-blue-500/10 text-slate-900 dark:text-white':
                                    conv.id === activeConversationId,
                                'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-700/50':
                                    conv.id !== activeConversationId,
                            }"
                        >
                            <div class="flex items-center justify-between">
                                <!-- Editable title -->
                                <input
                                    v-if="editingTitleId === conv.id"
                                    :id="`title-input-${conv.id}`"
                                    v-model="editingTitleValue"
                                    class="flex-1 truncate rounded border border-cyan-500 bg-transparent px-1 py-0 text-sm font-medium text-slate-900 outline-none dark:text-white"
                                    @blur="saveTitle(conv)"
                                    @keydown.enter.prevent="saveTitle(conv)"
                                    @keydown.escape.prevent="cancelEditTitle()"
                                    @click.stop
                                />
                                <p
                                    v-else
                                    class="truncate text-sm font-medium"
                                    :class="{
                                        'text-slate-900 dark:text-white':
                                            conv.id === activeConversationId,
                                        'text-slate-700 dark:text-slate-300':
                                            conv.id !== activeConversationId,
                                    }"
                                    @dblclick.stop="startEditTitle(conv)"
                                >
                                    {{
                                        conv.title ||
                                        t("brain.untitled", "Bez tytułu")
                                    }}
                                </p>
                                <div
                                    class="ml-2 flex flex-shrink-0 items-center gap-1"
                                >
                                    <!-- Edit button (visible on hover) -->
                                    <button
                                        v-if="editingTitleId !== conv.id"
                                        @click.stop="startEditTitle(conv)"
                                        class="hidden rounded p-0.5 text-slate-400 hover:text-cyan-500 group-hover:inline-block"
                                        :title="
                                            t(
                                                'brain.edit_title',
                                                'Edytuj tytuł',
                                            )
                                        "
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
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                            />
                                        </svg>
                                    </button>
                                    <span class="text-xs text-slate-400">
                                        {{
                                            formatDate(
                                                conv.last_activity_at ||
                                                    conv.created_at,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                            <p
                                class="mt-0.5 truncate text-xs text-slate-400 dark:text-slate-500"
                            >
                                {{ getLastMessage(conv) }}
                            </p>
                        </div>
                        <p
                            v-if="!conversationList.length"
                            class="px-3 py-8 text-center text-sm text-slate-400"
                        >
                            {{
                                t(
                                    "brain.no_conversations",
                                    "Brak konwersacji. Rozpocznij nową!",
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Chat Area (Center Panel) -->
            <div
                class="flex flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800"
            >
                <!-- Chat Messages -->
                <div
                    ref="chatContainer"
                    class="flex-1 overflow-y-auto p-4 space-y-4"
                >
                    <!-- Empty State -->
                    <div
                        v-if="!messages.length && !isLoadingConversation"
                        class="flex h-full flex-col items-center justify-center text-center"
                    >
                        <div
                            class="mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500/10 to-blue-600/10"
                        >
                            <svg
                                class="h-10 w-10 text-cyan-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714a2.25 2.25 0 00.659 1.591L19 14.5M14.25 3.104c.251.023.501.05.75.082M19 14.5l-2.47 2.47a2.25 2.25 0 01-1.59.659H9.06a2.25 2.25 0 01-1.591-.659L5 14.5m14 0V17a2 2 0 01-2 2H7a2 2 0 01-2-2v-2.5"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-2 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            {{
                                t(
                                    "brain.welcome_title",
                                    "Witaj w NetSendo Brain!",
                                )
                            }}
                        </h3>
                        <p
                            class="max-w-md text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{
                                t(
                                    "brain.welcome_text",
                                    "Zapytaj o kampanie, subskrybentów, statystyki lub wydaj polecenie — pomogę Ci z marketingiem.",
                                )
                            }}
                        </p>
                        <div class="mt-6 flex flex-wrap justify-center gap-2">
                            <button
                                v-for="suggestion in [
                                    t(
                                        'brain.suggestion_1',
                                        'Pokaż statystyki kampanii',
                                    ),
                                    t(
                                        'brain.suggestion_2',
                                        'Ile mam subskrybentów?',
                                    ),
                                    t(
                                        'brain.suggestion_3',
                                        'Utwórz nową kampanię',
                                    ),
                                ]"
                                :key="suggestion"
                                @click="
                                    newMessage = suggestion;
                                    sendMessage();
                                "
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 transition-all hover:border-cyan-500 hover:text-cyan-600 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:border-cyan-500 dark:hover:text-cyan-400"
                            >
                                {{ suggestion }}
                            </button>
                        </div>
                    </div>

                    <!-- Loading Conversation -->
                    <div
                        v-if="isLoadingConversation"
                        class="flex h-full items-center justify-center"
                    >
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-2 border-cyan-500 border-t-transparent"
                        ></div>
                    </div>

                    <!-- Messages -->
                    <template v-if="!isLoadingConversation">
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            class="flex"
                            :class="{
                                'justify-end': msg.role === 'user',
                                'justify-start': msg.role === 'assistant',
                                'justify-center': msg.role === 'system',
                            }"
                        >
                            <!-- User Message -->
                            <div
                                v-if="msg.role === 'user'"
                                class="max-w-[70%] rounded-2xl rounded-br-md bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3 text-sm text-white shadow-lg shadow-cyan-500/10"
                            >
                                <p class="whitespace-pre-wrap">
                                    {{ msg.content }}
                                </p>
                                <p
                                    class="mt-1 text-right text-[10px] text-cyan-100/70"
                                >
                                    {{ formatTime(msg.created_at) }}
                                </p>
                            </div>

                            <!-- Assistant Message -->
                            <div
                                v-else-if="msg.role === 'assistant'"
                                class="max-w-[70%]"
                            >
                                <div
                                    class="rounded-2xl rounded-bl-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-200"
                                >
                                    <div class="flex items-start gap-2">
                                        <div
                                            class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-blue-600"
                                        >
                                            <svg
                                                class="h-3 w-3 text-white"
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
                                        </div>
                                        <div class="flex-1">
                                            <div
                                                class="brain-markdown"
                                                :class="{
                                                    inline: msg.isStreaming,
                                                }"
                                                v-html="
                                                    renderMarkdown(msg.content)
                                                "
                                            ></div>
                                            <span
                                                v-if="msg.isStreaming"
                                                class="streaming-cursor"
                                                >▊</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="mt-1 flex items-center justify-between"
                                    >
                                        <span
                                            v-if="msg.model_used"
                                            class="rounded bg-slate-200/50 px-1.5 py-0.5 text-[9px] font-medium text-slate-400 dark:bg-slate-600/50"
                                        >
                                            {{ msg.model_used }}
                                        </span>
                                        <span v-else></span>
                                        <span
                                            class="text-[10px] text-slate-400"
                                        >
                                            {{ formatTime(msg.created_at) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Action Plan Preview -->
                                <div
                                    v-if="msg.plan"
                                    class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-700/50 dark:bg-amber-900/20"
                                >
                                    <h4
                                        class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-300"
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
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                            />
                                        </svg>
                                        {{
                                            t(
                                                "brain.plan.title",
                                                "Plan działania",
                                            )
                                        }}
                                    </h4>
                                    <div
                                        v-for="(step, idx) in msg.plan.steps"
                                        :key="idx"
                                        class="mb-1 flex items-center gap-2 text-xs text-amber-700 dark:text-amber-300/80"
                                    >
                                        <span
                                            class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-amber-300 text-[10px] font-bold dark:border-amber-600"
                                        >
                                            {{ idx + 1 }}
                                        </span>
                                        <span>{{
                                            step.description || step.action
                                        }}</span>
                                    </div>
                                    <div class="mt-3 flex gap-2">
                                        <button
                                            @click="approvePlan(msg.plan.id)"
                                            class="rounded-lg bg-green-500 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-green-600"
                                        >
                                            ✓
                                            {{
                                                t(
                                                    "brain.plan.approve",
                                                    "Zatwierdź",
                                                )
                                            }}
                                        </button>
                                        <button
                                            @click="rejectPlan(msg.plan.id)"
                                            class="rounded-lg bg-red-500 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-600"
                                        >
                                            ✕
                                            {{
                                                t("brain.plan.reject", "Odrzuć")
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- System Message -->
                            <div
                                v-else
                                class="max-w-[60%] rounded-xl bg-amber-50 px-4 py-2 text-center text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
                            >
                                {{ msg.content }}
                            </div>
                        </div>

                        <!-- Typing Indicator -->
                        <div v-if="isLoading" class="flex justify-start">
                            <div
                                class="rounded-2xl rounded-bl-md border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-600 dark:bg-slate-700/50"
                            >
                                <div class="flex items-center gap-1">
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-cyan-500"
                                        style="animation-delay: 0ms"
                                    ></div>
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-cyan-500"
                                        style="animation-delay: 150ms"
                                    ></div>
                                    <div
                                        class="h-2 w-2 animate-bounce rounded-full bg-cyan-500"
                                        style="animation-delay: 300ms"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Input Area -->
                <div
                    class="border-t border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-end gap-3">
                        <textarea
                            v-model="newMessage"
                            @keydown="handleKeyDown"
                            :placeholder="
                                t(
                                    'brain.placeholder',
                                    'Napisz wiadomość do Brain...',
                                )
                            "
                            rows="1"
                            class="flex-1 resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition-colors focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-500 dark:focus:border-cyan-500 dark:focus:bg-slate-600"
                            :disabled="isLoading"
                        ></textarea>
                        <button
                            @click="sendMessage"
                            :disabled="!newMessage.trim() || isLoading"
                            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl hover:shadow-cyan-500/30 disabled:opacity-50 disabled:shadow-none"
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
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-2 text-center text-[10px] text-slate-400">
                        {{
                            t(
                                "brain.input_hint",
                                "Enter aby wysłać, Shift+Enter nowa linia",
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Brain Markdown Styles */
:deep(.brain-markdown) {
    line-height: 1.6;
}
:deep(.brain-markdown p) {
    margin-bottom: 0.5em;
}
:deep(.brain-markdown p:last-child) {
    margin-bottom: 0;
}
:deep(.brain-markdown h1),
:deep(.brain-markdown h2),
:deep(.brain-markdown h3) {
    font-weight: 700;
    margin: 0.75em 0 0.35em;
    line-height: 1.3;
}
:deep(.brain-markdown h1) {
    font-size: 1.2em;
}
:deep(.brain-markdown h2) {
    font-size: 1.1em;
}
:deep(.brain-markdown h3) {
    font-size: 1.05em;
}
:deep(.brain-markdown ul),
:deep(.brain-markdown ol) {
    margin: 0.4em 0 0.4em 1.5em;
}
:deep(.brain-markdown ul) {
    list-style: disc;
}
:deep(.brain-markdown ol) {
    list-style: decimal;
}
:deep(.brain-markdown li) {
    margin-bottom: 0.2em;
}
:deep(.brain-markdown hr) {
    border: none;
    border-top: 1px solid rgba(148, 163, 184, 0.3);
    margin: 0.75em 0;
}
:deep(.brain-markdown code) {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 3px;
    padding: 0.1em 0.35em;
    font-size: 0.9em;
    font-family: monospace;
}
.dark :deep(.brain-markdown code) {
    background: rgba(255, 255, 255, 0.1);
}
:deep(.brain-markdown pre) {
    background: rgba(0, 0, 0, 0.06);
    border-radius: 8px;
    padding: 0.75em 1em;
    margin: 0.5em 0;
    overflow-x: auto;
}
.dark :deep(.brain-markdown pre) {
    background: rgba(0, 0, 0, 0.3);
}
:deep(.brain-markdown pre code) {
    background: none;
    padding: 0;
}
:deep(.brain-markdown strong) {
    font-weight: 700;
}
:deep(.brain-markdown em) {
    font-style: italic;
}
:deep(.brain-markdown a) {
    color: #06b6d4;
    text-decoration: underline;
}
:deep(.brain-markdown table) {
    border-collapse: collapse;
    width: 100%;
    margin: 0.5em 0;
    font-size: 0.9em;
}
:deep(.brain-markdown th),
:deep(.brain-markdown td) {
    border: 1px solid rgba(148, 163, 184, 0.3);
    padding: 0.35em 0.6em;
    text-align: left;
}
:deep(.brain-markdown th) {
    font-weight: 600;
    background: rgba(0, 0, 0, 0.03);
}
.dark :deep(.brain-markdown th) {
    background: rgba(255, 255, 255, 0.05);
}
:deep(.brain-markdown blockquote) {
    border-left: 3px solid #06b6d4;
    padding-left: 0.75em;
    margin: 0.5em 0;
    color: rgba(100, 116, 139, 1);
}
.dark :deep(.brain-markdown blockquote) {
    color: rgba(148, 163, 184, 1);
}

/* Streaming cursor */
.streaming-cursor {
    display: inline;
    animation: blink-cursor 0.8s step-end infinite;
    color: #06b6d4;
    font-size: 0.85em;
    line-height: 1;
    vertical-align: text-bottom;
}
@keyframes blink-cursor {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0;
    }
}
</style>
