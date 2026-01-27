<script setup>
import { ref, computed, watch } from "vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import axios from "axios";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    contact: {
        type: Object,
        required: true,
    },
    mailboxes: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["close", "sent"]);

const form = ref({
    subject: "",
    body: "",
    mailbox_id: null,
});

const sending = ref(false);
const error = ref(null);
const success = ref(false);

// Set default mailbox when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        resetForm();
        // Set default mailbox
        const defaultMailbox = props.mailboxes.find(m => m.is_default) || props.mailboxes[0];
        if (defaultMailbox) {
            form.value.mailbox_id = defaultMailbox.id;
        }
    }
});

const recipientEmail = computed(() => {
    return props.contact?.subscriber?.email || props.contact?.email || "";
});

const recipientName = computed(() => {
    const firstName = props.contact?.subscriber?.first_name || "";
    const lastName = props.contact?.subscriber?.last_name || "";
    return `${firstName} ${lastName}`.trim() || recipientEmail.value;
});

const canSend = computed(() => {
    return form.value.subject.trim() && form.value.body.trim() && !sending.value;
});

const hasMultipleMailboxes = computed(() => {
    return props.mailboxes.length > 1;
});

const resetForm = () => {
    form.value = {
        subject: "",
        body: "",
        mailbox_id: null,
    };
    error.value = null;
    success.value = false;
};

const close = () => {
    if (!sending.value) {
        resetForm();
        emit("close");
    }
};

const sendEmail = async () => {
    if (!canSend.value) return;

    sending.value = true;
    error.value = null;

    try {
        const response = await axios.post(
            `/crm/contacts/${props.contact.id}/send-email`,
            {
                subject: form.value.subject.trim(),
                body: form.value.body.trim(),
                mailbox_id: form.value.mailbox_id,
            }
        );

        if (response.data.success) {
            success.value = true;
            emit("sent", response.data);
            setTimeout(() => {
                close();
            }, 1500);
        } else {
            error.value = response.data.message || "Wystąpił błąd podczas wysyłania emaila.";
        }
    } catch (e) {
        error.value = e.response?.data?.message || "Wystąpił błąd podczas wysyłania emaila.";
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <Modal :show="show" @close="close" max-width="2xl">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                    {{ $t('crm.email_modal.title', 'Wyślij email') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $t('crm.email_modal.to', 'Do:') }} <span class="font-medium text-slate-700 dark:text-slate-300">{{ recipientName }}</span>
                    <span class="text-slate-400">&lt;{{ recipientEmail }}&gt;</span>
                </p>
            </div>

            <!-- Success Message -->
            <div v-if="success" class="mb-4 rounded-lg bg-emerald-50 p-4 dark:bg-emerald-900/30">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-emerald-700 dark:text-emerald-300">{{ $t('crm.email_modal.success', 'Email został wysłany pomyślnie!') }}</span>
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mb-4 rounded-lg bg-red-50 p-4 dark:bg-red-900/30">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span class="text-red-700 dark:text-red-300">{{ error }}</span>
                </div>
            </div>

            <!-- Form -->
            <div v-if="!success" class="space-y-4">
                <!-- Mailbox Selection (only if multiple) -->
                <div v-if="hasMultipleMailboxes">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ $t('crm.email_modal.mailbox', 'Skrzynka nadawcza') }}
                    </label>
                    <select
                        v-model="form.mailbox_id"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    >
                        <option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id">
                            {{ mailbox.name }} ({{ mailbox.from_email }})
                        </option>
                    </select>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ $t('crm.email_modal.subject', 'Temat') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.subject"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        :placeholder="$t('crm.email_modal.subject_placeholder', 'Temat wiadomości')"
                        :disabled="sending"
                    />
                </div>

                <!-- Body -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ $t('crm.email_modal.body', 'Treść') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="form.body"
                        rows="8"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        :placeholder="$t('crm.email_modal.body_placeholder', 'Treść wiadomości...')"
                        :disabled="sending"
                    ></textarea>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('crm.email_modal.plain_text_hint', 'Wiadomość zostanie wysłana jako zwykły tekst.') }}
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="close" :disabled="sending">
                    {{ success ? $t('common.close', 'Zamknij') : $t('common.cancel', 'Anuluj') }}
                </SecondaryButton>
                <PrimaryButton
                    v-if="!success"
                    @click="sendEmail"
                    :disabled="!canSend"
                    :class="{ 'opacity-50 cursor-not-allowed': !canSend }"
                >
                    <svg v-if="sending" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ sending ? $t('common.sending', 'Wysyłanie...') : $t('crm.email_modal.send_button', 'Wyślij email') }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
