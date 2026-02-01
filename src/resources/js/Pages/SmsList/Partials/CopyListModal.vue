<script setup>
import { ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import Checkbox from "@/Components/Checkbox.vue";

const props = defineProps({
    show: Boolean,
    list: Object,
});

const emit = defineEmits(["close"]);

const form = useForm({
    name: "",
    is_public: false,
    copy_subscribers: false,
});

// Initialize form with copied name suggestion
watch(
    () => props.show,
    (newVal) => {
        if (newVal && props.list) {
            form.name = `${props.list.name} (kopia)`;
            form.is_public = props.list.is_public || false;
            form.copy_subscribers = false;
        }
    },
);

const closeModal = () => {
    emit("close");
    form.reset();
};

const copyList = () => {
    form.post(route("sms-lists.copy", props.list.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Modal :show="show" @close="closeModal" max-width="lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-slate-900 dark:text-white">
                {{ $t("sms_lists.copy_modal.title", { name: list?.name }) }}
            </h2>

            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $t("sms_lists.copy_modal.description") }}
            </p>

            <div class="mt-6 space-y-5">
                <!-- Name -->
                <div>
                    <InputLabel
                        for="copy-name"
                        :value="$t('sms_lists.copy_modal.new_name')"
                    />
                    <TextInput
                        id="copy-name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        :placeholder="$t('sms_lists.fields.name_placeholder')"
                    />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <!-- Visibility -->
                <div>
                    <InputLabel
                        :value="$t('sms_lists.copy_modal.visibility')"
                    />
                    <div class="mt-2 flex gap-6">
                        <label class="flex items-center cursor-pointer">
                            <input
                                type="radio"
                                v-model="form.is_public"
                                :value="false"
                                class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900"
                            />
                            <span
                                class="ml-2 text-sm text-slate-700 dark:text-slate-300"
                            >
                                {{ $t("sms_lists.private") }}
                            </span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input
                                type="radio"
                                v-model="form.is_public"
                                :value="true"
                                class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900"
                            />
                            <span
                                class="ml-2 text-sm text-slate-700 dark:text-slate-300"
                            >
                                {{ $t("sms_lists.public") }}
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Copy options -->
                <div
                    class="border-t border-slate-200 dark:border-slate-700 pt-5"
                >
                    <InputLabel
                        :value="$t('sms_lists.copy_modal.options_title')"
                        class="mb-3"
                    />
                    <div class="space-y-3">
                        <label class="flex items-start cursor-pointer">
                            <Checkbox
                                v-model:checked="form.copy_subscribers"
                                class="mt-0.5"
                            />
                            <div class="ml-3">
                                <span
                                    class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        $t(
                                            "sms_lists.copy_modal.copy_subscribers",
                                        )
                                    }}
                                </span>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        $t(
                                            "sms_lists.copy_modal.copy_subscribers_desc",
                                            {
                                                count:
                                                    list?.subscribers_count ||
                                                    0,
                                            },
                                        )
                                    }}
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Info about what's always copied -->
                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-4">
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        <strong
                            >{{
                                $t("sms_lists.copy_modal.always_copied")
                            }}:</strong
                        >
                        {{ $t("sms_lists.copy_modal.always_copied_list") }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="closeModal">
                    {{ $t("common.cancel") }}
                </SecondaryButton>
                <PrimaryButton
                    @click="copyList"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing || !form.name"
                >
                    <svg
                        v-if="form.processing"
                        class="animate-spin -ml-1 mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
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
                    {{ $t("sms_lists.copy_modal.submit") }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
