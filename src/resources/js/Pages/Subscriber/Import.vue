<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";
import ContactListPicker from "@/Components/ContactListPicker.vue";

const { t } = useI18n();

const props = defineProps({
    lists: Array,
    customFields: Array,
    mappableFields: { type: Array, default: () => [] },
    limits: { type: Object, default: () => ({ max_rows: 50000, max_file_mb: 20 }) },
});

const form = useForm({
    file: null,
    contact_list_id: props.lists.length > 0 ? props.lists[0].id : "",
    separator: ",",
    has_header: true,
    column_mapping: {},

    // How an existing contact is treated. fill_empty never overwrites data
    // already in the account; overwrite makes the file the source of truth,
    // which is what a corrected export needs.
    update_mode: "fill_empty",
    // new_only keeps a repeated import from restarting anyone's autoresponder
    // sequence — see SubscriberFileImportService.
    signup_events: "new_only",
    restore_memberships: false,
    create_missing_lists: false,
    apply_tags: true,
    replace_tags: false,
    apply_dates: false,
    detect_gender: true,
});

const fileColumns = ref([]);
const hasHeader = ref(true);
const columnMapping = reactive({});
const isJsonFile = ref(false);
const detectedNetsendoFormat = ref(false);

const preview = ref(null);
const previewError = ref(null);
const previewing = ref(false);

const hasMapping = computed(() =>
    Object.values(columnMapping).some((value) => value && value !== "ignore"),
);

// Header aliases, kept in step with SubscriberFileImportService::HEADER_ALIASES
// so what the page detects is what the server will detect.
const HEADER_MAP = {
    netsendo_id: ["netsendo_id", "id", "subscriber_id", "id_subskrybenta"],
    email: ["email", "e-mail", "e_mail", "mail", "adres email", "adres e-mail", "correo"],
    phone: ["phone", "telefon", "tel", "mobile", "komorka", "komórka", "phone_number", "numer_telefonu", "numer", "telefono"],
    first_name: ["first_name", "firstname", "first name", "imie", "imię", "name", "nazwa", "vorname", "nombre"],
    last_name: ["last_name", "lastname", "last name", "nazwisko", "surname", "nachname", "apellido"],
    gender: ["gender", "plec", "płeć", "sex", "geschlecht", "genero"],
    language: ["language", "lang", "jezyk", "język", "locale", "sprache", "idioma"],
    timezone: ["timezone", "time_zone", "strefa_czasowa", "tz"],
    source: ["source", "zrodlo", "źródło", "quelle", "fuente"],
    status: ["status", "status_globalny"],
    list_status: ["list_status", "status_na_liscie", "status_na_liście"],
    lists: ["lists", "listy", "contact_lists"],
    tags: ["tags", "tagi", "etykiety"],
    subscribed_at: ["subscribed_at", "data_zapisu", "data_dolaczenia", "data_dołączenia"],
    confirmed_at: ["confirmed_at", "data_potwierdzenia"],
    unsubscribed_at: ["unsubscribed_at", "data_wypisania"],
};

const mappingOptions = computed(() =>
    (props.mappableFields.length ? props.mappableFields : Object.keys(HEADER_MAP)).map(
        (field) => ({
            value: field,
            label: t(`subscribers.import.mapping.${field}`),
        }),
    ),
);

const listRequired = computed(() => !form.restore_memberships);

const resetMapping = () => {
    Object.keys(columnMapping).forEach((key) => delete columnMapping[key]);
};

const getSeparator = () => (form.separator === "tab" ? "\t" : form.separator);

const parseCsvLine = (line, separator) => {
    const values = [];
    let current = "";
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (char === '"') {
            if (inQuotes && line[i + 1] === '"') {
                current += '"';
                i += 1;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (char === separator && !inQuotes) {
            values.push(current);
            current = "";
        } else {
            current += char;
        }
    }

    values.push(current);
    return values.map((value) => value.trim());
};

const normalizeHeader = (value) => value.toLowerCase().trim();

const customFieldMap = computed(() => {
    const map = new Map();
    (props.customFields ?? []).forEach((field) => {
        if (field.label) map.set(normalizeHeader(field.label), field.id);
        if (field.name) {
            map.set(normalizeHeader(field.name), field.id);
            map.set(`cf:${normalizeHeader(field.name)}`, field.id);
        }
    });
    return map;
});

const detectHeaderRow = (columns) => {
    const normalized = columns.map(normalizeHeader);

    return normalized.some(
        (value) =>
            Object.values(HEADER_MAP).some((aliases) => aliases.includes(value)) ||
            customFieldMap.value.has(value),
    );
};

const applyDefaultMapping = (columns, detectedHeader) => {
    resetMapping();

    if (!columns.length) {
        return;
    }

    columns.forEach((_, index) => {
        columnMapping[index] = "ignore";
    });

    if (!detectedHeader) {
        columns.forEach((value, index) => {
            if (value.includes("@")) {
                columnMapping[index] = "email";
            }
        });
        return;
    }

    columns.map(normalizeHeader).forEach((value, index) => {
        Object.entries(HEADER_MAP).forEach(([field, aliases]) => {
            if (aliases.includes(value)) {
                columnMapping[index] = field;
            }
        });

        const customFieldId = customFieldMap.value.get(value);
        if (customFieldId) {
            columnMapping[index] = `custom_field:${customFieldId}`;
        }
    });
};

const syncMappingWithColumns = (columns) => {
    Object.keys(columnMapping).forEach((key) => {
        if (Number(key) >= columns.length) {
            delete columnMapping[key];
        }
    });
};

/**
 * A file that carries identity plus a relational column came out of this app,
 * so the round-trip options are switched on for it up front.
 */
const detectNetsendoFormat = () => {
    const mapped = Object.values(columnMapping);

    return (
        mapped.includes("netsendo_id") &&
        mapped.some((field) => ["lists", "tags", "list_status", "status"].includes(field))
    );
};

const parseFilePreview = async (reset = false) => {
    preview.value = null;
    previewError.value = null;

    if (!form.file) {
        fileColumns.value = [];
        hasHeader.value = true;
        isJsonFile.value = false;
        detectedNetsendoFormat.value = false;
        resetMapping();
        return;
    }

    const name = form.file.name.toLowerCase();
    isJsonFile.value =
        name.endsWith(".json") || name.endsWith(".ndjson") || name.endsWith(".jsonl");

    if (isJsonFile.value) {
        // JSON records are keyed by name; the server maps them without a
        // column picker, so there is nothing to preview here.
        fileColumns.value = [];
        resetMapping();
        detectedNetsendoFormat.value = true;
        form.restore_memberships = true;
        form.update_mode = "overwrite";
        return;
    }

    const text = await form.file.text();
    const firstLine =
        text.split(/\r?\n/).find((line) => line.trim() !== "") ?? "";
    const cleanedLine = firstLine.replace(/^\uFEFF/, "");
    const columns = parseCsvLine(cleanedLine, getSeparator());

    fileColumns.value = columns;

    if (reset) {
        const detectedHeader = detectHeaderRow(columns);
        hasHeader.value = detectedHeader;
        applyDefaultMapping(columns, detectedHeader);
    } else {
        syncMappingWithColumns(columns);
    }

    detectedNetsendoFormat.value = detectNetsendoFormat();

    if (detectedNetsendoFormat.value && reset) {
        // Its own export coming back: the file is the source of truth and the
        // memberships it carries are worth restoring.
        form.update_mode = "overwrite";
        form.restore_memberships = true;
        form.apply_tags = true;
    }
};

watch(
    () => form.file,
    () => {
        parseFilePreview(true);
    },
);

watch(
    () => form.separator,
    () => {
        if (form.file) {
            parseFilePreview(false);
        }
    },
);

const buildFormData = () => {
    const data = new FormData();
    data.append("file", form.file);

    if (form.contact_list_id) {
        data.append("contact_list_id", form.contact_list_id);
    }

    data.append("separator", form.separator);
    data.append("has_header", hasHeader.value ? "1" : "0");
    data.append("update_mode", form.update_mode);
    data.append("signup_events", form.signup_events);
    data.append("restore_memberships", form.restore_memberships ? "1" : "0");
    data.append("create_missing_lists", form.create_missing_lists ? "1" : "0");
    data.append("apply_tags", form.apply_tags ? "1" : "0");
    data.append("replace_tags", form.replace_tags ? "1" : "0");
    data.append("apply_dates", form.apply_dates ? "1" : "0");
    data.append("detect_gender", form.detect_gender ? "1" : "0");

    if (hasMapping.value) {
        Object.entries(columnMapping).forEach(([index, field]) => {
            data.append(`column_mapping[${index}]`, field ?? "");
        });
    }

    return data;
};

/**
 * Dry run. Nothing is written — the response says how many contacts would be
 * created, updated or skipped, and which rows would be rejected.
 */
const runPreview = async () => {
    if (!form.file) return;

    previewing.value = true;
    previewError.value = null;
    preview.value = null;

    try {
        const response = await axios.post(
            route("subscribers.import.preview"),
            buildFormData(),
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        preview.value = response.data;
    } catch (error) {
        previewError.value =
            error.response?.data?.message ??
            Object.values(error.response?.data?.errors ?? {})
                .flat()
                .join(" ") ??
            t("subscribers.import.preview.failed");
    } finally {
        previewing.value = false;
    }
};

const submit = () => {
    if (listRequired.value && !form.contact_list_id) {
        form.setError("contact_list_id", t("messages.validation.list_required"));
        return;
    }

    form.clearErrors("contact_list_id");
    form.has_header = hasHeader.value;
    form.column_mapping = hasMapping.value ? { ...columnMapping } : {};

    form.post(route("subscribers.import.store"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset("file");
            fileColumns.value = [];
            hasHeader.value = true;
            preview.value = null;
            resetMapping();
        },
    });
};

const actionLabel = (action) => t(`subscribers.import.preview.actions.${action}`);
const reasonLabel = (reason) => t(`subscribers.import.preview.reasons.${reason}`);
</script>

<template>
    <Head :title="$t('subscribers.import.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('subscribers.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
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
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t("subscribers.import.title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("subscribers.import.subtitle") }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <!-- Format Instructions -->
                <div
                    class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 dark:border-indigo-900/30 dark:bg-indigo-900/20"
                >
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg
                                class="h-5 w-5 text-indigo-400"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3
                                class="text-sm font-medium text-indigo-800 dark:text-indigo-300"
                            >
                                {{ $t("subscribers.import.instructions.title") }}
                            </h3>
                            <div class="mt-2 text-sm text-indigo-700 dark:text-indigo-400">
                                <p class="mb-2">
                                    {{ $t("subscribers.import.instructions.line1") }}
                                </p>
                                <p class="mb-2">
                                    {{
                                        $t("subscribers.import.instructions.round_trip", {
                                            limit: limits.max_rows,
                                        })
                                    }}
                                </p>
                                <p
                                    class="mt-2 rounded bg-white/50 p-2 font-mono text-xs dark:bg-black/20"
                                >
                                    netsendo_id,email,first_name,last_name,lists,tags<br />
                                    1042,jan@example.com,Jan,Kowalski,Newsletter:active,vip|klient<br />
                                    1043,anna@test.com,Anna,Nowak,Newsletter:unsubscribed,
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- List Selection -->
                        <div>
                            <p
                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ $t("subscribers.import.fields.target_list") }}
                                <span v-if="listRequired" class="text-red-500">*</span>
                            </p>
                            <ContactListPicker
                                v-model="form.contact_list_id"
                                :lists="lists"
                                :multiple="false"
                                :error="form.errors.contact_list_id"
                            />
                            <p
                                v-if="!listRequired"
                                class="mt-1.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("subscribers.import.fields.target_list_optional") }}
                            </p>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <!-- Separator -->
                            <div>
                                <label
                                    for="separator"
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ $t("subscribers.import.fields.separator") }}
                                </label>
                                <select
                                    id="separator"
                                    v-model="form.separator"
                                    :disabled="isJsonFile"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 disabled:opacity-50 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400"
                                >
                                    <option value=",">
                                        {{ $t("subscribers.import.fields.comma") }}
                                    </option>
                                    <option value=";">
                                        {{ $t("subscribers.import.fields.semicolon") }}
                                    </option>
                                    <option value="tab">
                                        {{ $t("subscribers.import.fields.tab") }}
                                    </option>
                                    <option value="|">
                                        {{ $t("subscribers.import.fields.pipe") }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- File Input -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ $t("subscribers.import.fields.select_file") }}
                                <span class="text-red-500">*</span>
                            </label>
                            <div
                                class="mt-1 flex justify-center rounded-xl border-2 border-dashed border-slate-300 px-6 pt-5 pb-6 transition-colors hover:border-indigo-400 dark:border-slate-700 dark:hover:border-indigo-500"
                            >
                                <div class="space-y-1 text-center">
                                    <svg
                                        class="mx-auto h-12 w-12 text-slate-400"
                                        stroke="currentColor"
                                        fill="none"
                                        viewBox="0 0 48 48"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <div class="flex text-sm text-slate-600 dark:text-slate-400">
                                        <label
                                            for="file-upload"
                                            class="relative cursor-pointer rounded-md bg-transparent font-medium text-indigo-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            <span>{{
                                                $t("subscribers.import.fields.choose_file")
                                            }}</span>
                                            <input
                                                id="file-upload"
                                                name="file-upload"
                                                type="file"
                                                class="sr-only"
                                                accept=".csv,.txt,.tsv,.json,.ndjson,.jsonl"
                                                @input="form.file = $event.target.files[0]"
                                            />
                                        </label>
                                        <p class="pl-1">
                                            {{ $t("subscribers.import.fields.or_drag_drop") }}
                                        </p>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-500">
                                        {{
                                            $t("subscribers.import.fields.accepted", {
                                                size: limits.max_file_mb,
                                            })
                                        }}
                                    </p>
                                    <p
                                        v-if="form.file"
                                        class="mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400"
                                    >
                                        {{
                                            $t("subscribers.import.fields.selected", {
                                                name: form.file.name,
                                            })
                                        }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="form.errors.file"
                                class="mt-2 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ form.errors.file }}
                            </p>
                            <progress
                                v-if="form.progress"
                                :value="form.progress.percentage"
                                max="100"
                                class="mt-2 h-1 w-full"
                            >
                                {{ form.progress.percentage }}%
                            </progress>
                        </div>

                        <!-- Recognised own export -->
                        <div
                            v-if="detectedNetsendoFormat"
                            class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300"
                        >
                            <p class="font-semibold">
                                {{ $t("subscribers.import.detected.title") }}
                            </p>
                            <p class="mt-1 text-xs">
                                {{ $t("subscribers.import.detected.desc") }}
                            </p>
                        </div>

                        <!-- Import behaviour -->
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"
                        >
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $t("subscribers.import.options.title") }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ $t("subscribers.import.options.subtitle") }}
                            </p>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("subscribers.import.options.update_mode") }}
                                    </label>
                                    <select
                                        v-model="form.update_mode"
                                        class="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="fill_empty">
                                            {{
                                                $t(
                                                    "subscribers.import.options.update_modes.fill_empty",
                                                )
                                            }}
                                        </option>
                                        <option value="overwrite">
                                            {{
                                                $t(
                                                    "subscribers.import.options.update_modes.overwrite",
                                                )
                                            }}
                                        </option>
                                        <option value="skip">
                                            {{
                                                $t(
                                                    "subscribers.import.options.update_modes.skip",
                                                )
                                            }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("subscribers.import.options.signup_events") }}
                                    </label>
                                    <select
                                        v-model="form.signup_events"
                                        class="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="new_only">
                                            {{
                                                $t(
                                                    "subscribers.import.options.signup_modes.new_only",
                                                )
                                            }}
                                        </option>
                                        <option value="all">
                                            {{
                                                $t(
                                                    "subscribers.import.options.signup_modes.all",
                                                )
                                            }}
                                        </option>
                                        <option value="none">
                                            {{
                                                $t(
                                                    "subscribers.import.options.signup_modes.none",
                                                )
                                            }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <label
                                    class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    <input
                                        v-model="form.restore_memberships"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    />
                                    <span>{{
                                        $t("subscribers.import.options.restore_memberships")
                                    }}</span>
                                </label>
                                <label
                                    v-if="form.restore_memberships"
                                    class="flex items-start gap-2 pl-6 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    <input
                                        v-model="form.create_missing_lists"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    />
                                    <span>{{
                                        $t("subscribers.import.options.create_missing_lists")
                                    }}</span>
                                </label>
                                <label
                                    class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    <input
                                        v-model="form.apply_tags"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    />
                                    <span>{{
                                        $t("subscribers.import.options.apply_tags")
                                    }}</span>
                                </label>
                                <label
                                    v-if="form.apply_tags"
                                    class="flex items-start gap-2 pl-6 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    <input
                                        v-model="form.replace_tags"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    />
                                    <span>{{
                                        $t("subscribers.import.options.replace_tags")
                                    }}</span>
                                </label>
                                <label
                                    class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    <input
                                        v-model="form.apply_dates"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    />
                                    <span>{{
                                        $t("subscribers.import.options.apply_dates")
                                    }}</span>
                                </label>
                                <label
                                    class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    <input
                                        v-model="form.detect_gender"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    />
                                    <span>{{
                                        $t("subscribers.import.options.detect_gender")
                                    }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Column mapping -->
                        <div
                            v-if="isJsonFile"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400"
                        >
                            {{ $t("subscribers.import.mapping.json_auto") }}
                        </div>

                        <div
                            v-else-if="fileColumns.length"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ $t("subscribers.import.mapping.title") }}
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $t("subscribers.import.mapping.subtitle") }}
                                    </p>
                                </div>
                                <label
                                    class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300"
                                >
                                    <input
                                        v-model="hasHeader"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                    />
                                    {{ $t("subscribers.import.mapping.first_row_header") }}
                                </label>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="(column, index) in fileColumns"
                                    :key="index"
                                    class="grid gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-[140px_1fr]"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            {{ $t("subscribers.import.mapping.column") }}
                                            {{ index + 1 }}
                                        </p>
                                        <p
                                            class="text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            {{ $t("subscribers.import.mapping.preview") }}:
                                            {{ column || "—" }}
                                        </p>
                                    </div>
                                    <select
                                        v-model="columnMapping[index]"
                                        class="w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="">
                                            {{ $t("subscribers.import.mapping.select_field") }}
                                        </option>
                                        <option value="ignore">
                                            {{ $t("subscribers.import.mapping.ignore") }}
                                        </option>
                                        <option
                                            v-for="option in mappingOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                        <option
                                            v-for="field in customFields"
                                            :key="field.id"
                                            :value="`custom_field:${field.id}`"
                                        >
                                            {{
                                                $t("subscribers.import.mapping.custom_field", {
                                                    name: field.label || field.name,
                                                })
                                            }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Dry-run result -->
                        <div
                            v-if="previewError"
                            class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-300"
                        >
                            {{ previewError }}
                        </div>

                        <div
                            v-if="preview"
                            class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $t("subscribers.import.preview.title") }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{
                                    $t("subscribers.import.preview.inspected", {
                                        inspected: preview.inspected_rows,
                                        total: preview.total_rows,
                                    })
                                }}
                            </p>

                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                                <div
                                    v-for="key in [
                                        'create',
                                        'update',
                                        'reactivate',
                                        'unchanged',
                                        'invalid',
                                    ]"
                                    :key="key"
                                    class="rounded-lg bg-slate-50 px-3 py-2 text-center dark:bg-slate-800"
                                >
                                    <p
                                        class="text-lg font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ preview.summary[key] }}
                                    </p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ actionLabel(key) }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="preview.problem_rows?.length"
                                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20"
                            >
                                <p
                                    class="text-xs font-semibold text-amber-800 dark:text-amber-300"
                                >
                                    {{ $t("subscribers.import.preview.problems") }}
                                </p>
                                <ul
                                    class="mt-1 space-y-0.5 text-[11px] text-amber-700 dark:text-amber-400"
                                >
                                    <li
                                        v-for="problem in preview.problem_rows.slice(0, 10)"
                                        :key="problem.row"
                                    >
                                        {{ $t("subscribers.import.preview.row") }}
                                        {{ problem.row }}:
                                        {{ problem.email || problem.phone || "—" }} —
                                        {{ reasonLabel(problem.reason) }}
                                    </li>
                                </ul>
                            </div>

                            <ul
                                v-if="preview.warnings?.length"
                                class="mt-3 space-y-0.5 text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                <li v-for="(warning, i) in preview.warnings" :key="i">
                                    {{ warning }}
                                </li>
                            </ul>
                        </div>

                        <!-- Actions -->
                        <div
                            class="flex flex-wrap items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800"
                        >
                            <Link
                                :href="route('subscribers.index')"
                                class="rounded-xl px-6 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                            >
                                {{ $t("common.cancel") }}
                            </Link>
                            <button
                                type="button"
                                @click="runPreview"
                                :disabled="previewing || !form.file"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition-colors disabled:opacity-50 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                {{
                                    previewing
                                        ? $t("subscribers.import.preview.checking")
                                        : $t("subscribers.import.preview.button")
                                }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing || !form.file"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25 disabled:opacity-50"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="h-4 w-4 animate-spin text-white"
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
                                <span>{{
                                    form.processing
                                        ? $t("subscribers.import.importing")
                                        : $t("subscribers.import.import_button")
                                }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
