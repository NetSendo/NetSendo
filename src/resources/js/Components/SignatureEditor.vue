<script setup>
import { useEditor, EditorContent } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Link from "@tiptap/extension-link";
import Image from "@tiptap/extension-image";
import BulletList from "@tiptap/extension-bullet-list";
import OrderedList from "@tiptap/extension-ordered-list";
import ListItem from "@tiptap/extension-list-item";
import { Mark, mergeAttributes } from "@tiptap/core";

// Custom Image extension that preserves style properties via data attributes
const CustomImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            'data-width': {
                default: '100',
                parseHTML: element => element.getAttribute('data-width') || element.style?.width?.replace('%', '') || '100',
                renderHTML: attributes => {
                    return { 'data-width': attributes['data-width'] || '100' }
                },
            },
            'data-align': {
                default: 'center',
                parseHTML: element => {
                    const dataAlign = element.getAttribute('data-align')
                    if (dataAlign) return dataAlign
                    const style = element.getAttribute('style') || ''
                    if (style.includes('margin-left: auto') && style.includes('margin-right: auto')) return 'center'
                    if (style.includes('margin-left: auto')) return 'right'
                    if (style.includes('margin-right: auto')) return 'left'
                    return 'center'
                },
                renderHTML: attributes => {
                    return { 'data-align': attributes['data-align'] || 'center' }
                },
            },
            style: {
                default: null,
                parseHTML: element => element.getAttribute('style'),
                renderHTML: attributes => {
                    const width = attributes['data-width'] || '100'
                    const align = attributes['data-align'] || 'center'

                    let styleStr = `width: ${width}%; max-width: 100%; height: auto; display: block;`
                    if (align === 'center') {
                        styleStr += ' margin-left: auto; margin-right: auto;'
                    } else if (align === 'right') {
                        styleStr += ' margin-left: auto;'
                    } else if (align === 'left') {
                        styleStr += ' margin-right: auto;'
                    }

                    return { style: styleStr }
                },
            },
        }
    },
})
import Underline from "@tiptap/extension-underline";
import TextAlign from "@tiptap/extension-text-align";
import { FontFamily } from "@tiptap/extension-font-family";
import { TextStyle } from "@tiptap/extension-text-style";
import { Color } from "@tiptap/extension-color";
import { Highlight } from "@tiptap/extension-highlight";
import { FontSize } from "tiptap-extension-font-size";
import {
    watch,
    ref,
    onBeforeUnmount,
    computed,
    onMounted,
    nextTick,
} from "vue";

import { useI18n } from "vue-i18n";
import {
    Table,
    TableCell,
    TableHeader,
    TableRow,
} from "@tiptap/extension-table";
import axios from "axios";

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
    editable: {
        type: Boolean,
        default: true,
    },
    minHeight: {
        type: String,
        default: "200px",
    },
});

const emit = defineEmits(["update:modelValue"]);

// Editor modes: visual, source, preview
const editorMode = ref("visual");

// sourceCode is the SINGLE SOURCE OF TRUTH for content
const sourceCode = ref(props.modelValue || "");

const showLinkModal = ref(false);
const linkUrl = ref("");
const showImageModal = ref(false);
const imageUrl = ref("");
const activeImageTab = ref("upload");
const isUploading = ref(false);

// Enhanced image settings
const imageAlignment = ref("center");
const imageWidth = ref("100");
const imagePreviewLoaded = ref(false);
const imagePreviewError = ref(false);

// Font/Color picker states
const showFontPicker = ref(false);
const showSizePicker = ref(false);
const showColorPicker = ref(false);
const showHighlightPicker = ref(false);
const showTextTransformPicker = ref(false);

// Emoji picker state
const showEmojiPicker = ref(false);
const activeEmojiCategory = ref("faces");
const emojiPickerRef = ref(null);
const emojiButtonRef = ref(null);

// Media browser state
const showMediaBrowser = ref(false);
const showLogoBrowser = ref(false);
const mediaLibrary = ref([]);
const isLoadingMedia = ref(false);

// Advanced image formatting
const imageFloat = ref("none");
const imageMargin = ref("10");
const imageBorderRadius = ref("0");
const imageLink = ref("");
const isEditingImage = ref(false);
const imageUploadError = ref("");

// Available fonts
const fontOptions = [
    { name: "Arial", value: "Arial, sans-serif" },
    { name: "Georgia", value: "Georgia, serif" },
    { name: "Times New Roman", value: "Times New Roman, serif" },
    { name: "Verdana", value: "Verdana, sans-serif" },
    { name: "Courier New", value: "Courier New, monospace" },
    { name: "Roboto", value: "Roboto, sans-serif" },
    { name: "Helvetica", value: "Helvetica, Arial, sans-serif" },
];

// Available font sizes
const fontSizeOptions = [
    "12px",
    "14px",
    "16px",
    "18px",
    "20px",
    "24px",
    "28px",
    "32px",
];

// Color palette for text
const colorPalette = [
    "#000000",
    "#434343",
    "#666666",
    "#999999",
    "#CCCCCC",
    "#FFFFFF",
    "#FF0000",
    "#FF6600",
    "#FFCC00",
    "#00FF00",
    "#00CCFF",
    "#0066FF",
    "#9900FF",
    "#FF00FF",
    "#FF6699",
    "#996633",
    "#003366",
    "#339966",
];

// Emojis organized by categories
const emojiCategories = {
    faces: {
        icon: "😀",
        emojis: ["😀", "😃", "😄", "😁", "😆", "😅", "🤣", "😂", "🙂", "😊", "😇", "🥰", "😍", "😘", "😗", "😋", "😛", "😜", "🤪", "😎", "🤩", "🥳", "😏", "😌", "😴"]
    },
    symbols: {
        icon: "🎉",
        emojis: ["🎉", "🎊", "🎁", "🎀", "🎈", "🎗️", "✨", "🌟", "⭐", "💫", "🔥", "💥", "💢", "💯", "🏆", "🥇", "🥈", "🥉", "🎯", "🚀", "⚡", "💎", "🔔", "📣", "📢"]
    },
    gestures: {
        icon: "👍",
        emojis: ["👍", "👎", "👏", "🙌", "👐", "🤲", "🤝", "🙏", "✌️", "🤞", "🤟", "🤘", "👌", "👈", "👉", "👆", "👇", "✋", "🖐️", "💪"]
    },
    business: {
        icon: "💼",
        emojis: ["💼", "📧", "📬", "💌", "📝", "📊", "📈", "📉", "💰", "💵", "💳", "🏦", "🏢", "📅", "⏰", "🔒", "🔓", "📱", "💻", "🖥️"]
    },
    hearts: {
        icon: "❤️",
        emojis: ["❤️", "🧡", "💛", "💚", "💙", "💜", "🖤", "🤍", "🤎", "💝", "💖", "💗", "💓", "💕", "💞", "💘", "💔", "❣️", "💟", "♥️"]
    },
    nature: {
        icon: "🌟",
        emojis: ["☀️", "🌙", "🌈", "⛅", "🌤️", "🌧️", "❄️", "🌸", "🌺", "🌻", "🌷", "🌹", "🌲", "🌴", "🍀", "🍁", "🍂", "🌊", "💧", "🌍"]
    }
};

// TextTransform Extension
const TextTransform = Mark.create({
    name: "textTransform",
    addOptions() {
        return { types: ["textStyle"] };
    },
    addAttributes() {
        return {
            textTransform: {
                default: null,
                parseHTML: element => element.style.textTransform || null,
                renderHTML: attributes => {
                    if (!attributes.textTransform) return {};
                    return { style: `text-transform: ${attributes.textTransform}` };
                },
            },
        };
    },
    parseHTML() {
        return [{
            tag: "span",
            getAttrs: element => {
                const hasTextTransform = element.style.textTransform;
                if (!hasTextTransform) return false;
                return { textTransform: element.style.textTransform };
            },
        }];
    },
    renderHTML({ HTMLAttributes }) {
        return ["span", mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0];
    },
    addCommands() {
        return {
            setTextTransform: textTransform => ({ chain }) => chain().setMark(this.name, { textTransform }).run(),
            unsetTextTransform: () => ({ chain }) => chain().unsetMark(this.name).run(),
            toggleTextTransform: textTransform => ({ chain, editor }) => {
                const currentTransform = editor.getAttributes(this.name)?.textTransform;
                if (currentTransform === textTransform) {
                    return chain().unsetMark(this.name).run();
                }
                return chain().setMark(this.name, { textTransform }).run();
            },
        };
    },
});

// Emoji picker positioning
const emojiPickerStyle = computed(() => {
    if (!emojiButtonRef.value) return { top: "100px", left: "100px" };
    const rect = emojiButtonRef.value.getBoundingClientRect();
    const viewportWidth = typeof window !== "undefined" ? window.innerWidth : 1024;
    return {
        top: (rect.bottom + 8) + "px",
        left: Math.min(rect.left, viewportWidth - 336) + "px"
    };
});

// Check if content is a full HTML document
const isFullHtmlDocument = computed(() => {
    const content = sourceCode.value?.trim().toLowerCase() || "";
    return (
        content.startsWith("<!doctype") ||
        content.startsWith("<html") ||
        content.includes("<body")
    );
});

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3],
            },
            // Disable lists from StarterKit - we add them separately for indent/outdent support
            bulletList: false,
            orderedList: false,
            listItem: false,
        }),
        // Add list extensions separately for proper indent/outdent support
        BulletList,
        OrderedList,
        ListItem,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: "text-indigo-600 hover:text-indigo-800 underline",
            },
        }),
        CustomImage.configure({
            inline: true,
            allowBase64: true,
        }),
        Underline,
        TextAlign.configure({
            types: ["heading", "paragraph"],
        }),
        TextStyle,
        FontFamily.configure({
            types: ["textStyle"],
        }),
        Color.configure({
            types: ["textStyle"],
        }),
        Highlight.configure({
            multicolor: true,
        }),
        FontSize,
        TextTransform,
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableHeader,
        TableCell,
    ],
    editorProps: {
        attributes: {
            class: "prose prose-sm focus:outline-none dark:prose-invert min-h-[150px] text-slate-900 dark:text-white p-4",
        },
    },
    editable: props.editable,
    onUpdate: () => {
        if (!isFullHtmlDocument.value && editorMode.value === "visual") {
            const html = editor.value?.getHTML() || "";
            sourceCode.value = html;
            emit("update:modelValue", html);
        }
    },
});

// Watch for external content changes
watch(
    () => props.modelValue,
    (value) => {
        if (value !== sourceCode.value) {
            sourceCode.value = value || "";

            nextTick(() => {
                const content = (value || "").trim().toLowerCase();
                const isFullHtml =
                    content.startsWith("<!doctype") ||
                    content.startsWith("<html") ||
                    content.includes("<body");
                if (isFullHtml && editorMode.value === "visual") {
                    editorMode.value = "preview";
                }
            });
        }

        if (!isFullHtmlDocument.value && editorMode.value === "visual") {
            const isSame = editor.value?.getHTML() === value;
            if (!isSame) {
                editor.value?.commands.setContent(value || "", false);
            }
        }
    },
    { immediate: false }
);

// Initialize on mount
onMounted(() => {
    sourceCode.value = props.modelValue || "";

    if (isFullHtmlDocument.value) {
        editorMode.value = "preview";
    }
});

// Switch between modes
const switchMode = (mode) => {
    const previousMode = editorMode.value;

    if (previousMode === "visual" && !isFullHtmlDocument.value) {
        const tiptapHtml = editor.value?.getHTML();
        if (tiptapHtml) {
            sourceCode.value = tiptapHtml;
        }
    }

    if (mode === "visual" && !isFullHtmlDocument.value) {
        nextTick(() => {
            editor.value?.commands.setContent(sourceCode.value || "", false);
        });
    }

    emit("update:modelValue", sourceCode.value);
    editorMode.value = mode;
};

// Handle source code changes from textarea
const updateFromSource = () => {
    emit("update:modelValue", sourceCode.value);
};

// Format source code (basic prettify)
const formatSource = () => {
    try {
        let formatted = sourceCode.value
            .replace(/></g, ">\n<")
            .replace(/\n\s*\n/g, "\n");
        sourceCode.value = formatted;
        emit("update:modelValue", formatted);
    } catch (e) {
        console.warn("Could not format HTML");
    }
};

// Link handling
const openLinkModal = () => {
    const previousUrl = editor.value?.getAttributes("link").href;
    linkUrl.value = previousUrl || "";
    showLinkModal.value = true;
};

const setLink = () => {
    if (linkUrl.value) {
        editor.value
            ?.chain()
            .focus()
            .extendMarkRange("link")
            .setLink({ href: linkUrl.value })
            .run();
    } else {
        editor.value?.chain().focus().extendMarkRange("link").unsetLink().run();
    }
    showLinkModal.value = false;
    linkUrl.value = "";
};

// Image handling
const openImageModal = () => {
    imageUrl.value = "";
    imageAlignment.value = "center";
    imageWidth.value = "100";
    imageFloat.value = "none";
    imageMargin.value = "10";
    imageBorderRadius.value = "0";
    imageLink.value = "";
    imagePreviewLoaded.value = false;
    imagePreviewError.value = false;
    imageUploadError.value = "";
    isEditingImage.value = false;
    showMediaBrowser.value = false;
    showLogoBrowser.value = false;
    showImageModal.value = true;
};

const handleImageUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    isUploading.value = true;
    imageUploadError.value = "";
    const formData = new FormData();
    formData.append("image", file);

    try {
        const response = await axios.post(
            route("api.templates.upload-image"),
            formData
        );
        imageUrl.value = response.data.url;
        imagePreviewError.value = false;
    } catch (error) {
        console.error("Image upload failed:", error);
        imageUploadError.value = error.response?.data?.message || error.message;
    } finally {
        isUploading.value = false;
    }
};

const onImageUrlChange = () => {
    imagePreviewLoaded.value = false;
    imagePreviewError.value = false;
};

const onImageLoad = () => {
    imagePreviewLoaded.value = true;
    imagePreviewError.value = false;
};

const onImageError = () => {
    imagePreviewLoaded.value = false;
    imagePreviewError.value = true;
};

const insertImage = () => {
    if (imageUrl.value) {
        const width = imageWidth.value;
        const align = imageAlignment.value;
        const float = imageFloat.value;
        const margin = imageMargin.value;
        const borderRadius = imageBorderRadius.value;

        // Build data attributes
        const dataAttrs = `data-width="${width}" data-align="${align}" data-float="${float}" data-margin="${margin}" data-border-radius="${borderRadius}"`;

        // Build inline style for email compatibility
        let styleStr = `width: ${width}%; max-width: 100%; height: auto;`;
        styleStr += ` margin: ${margin}px;`;

        if (float === "left") {
            styleStr += " float: left;";
        } else if (float === "right") {
            styleStr += " float: right;";
        } else {
            styleStr += " display: block;";
            if (align === "center") {
                styleStr += " margin-left: auto; margin-right: auto;";
            } else if (align === "right") {
                styleStr += " margin-left: auto;";
            } else if (align === "left") {
                styleStr += " margin-right: auto;";
            }
        }

        if (parseInt(borderRadius) > 0) {
            styleStr += ` border-radius: ${borderRadius}px;`;
        }

        let imgHtml = `<img src="${imageUrl.value}" alt="" ${dataAttrs} style="${styleStr}" />`;

        // Wrap in link if provided
        if (imageLink.value) {
            imgHtml = `<a href="${imageLink.value}" target="_blank">${imgHtml}</a>`;
        }

        editor.value?.chain().focus().insertContent(imgHtml).run();
    }
    showImageModal.value = false;
    imageUrl.value = "";
    imageAlignment.value = "center";
    imageWidth.value = "100";
    imageFloat.value = "none";
    imageMargin.value = "10";
    imageBorderRadius.value = "0";
    imageLink.value = "";
    isEditingImage.value = false;
};

// Wrap content in a basic HTML document for preview
const wrapInDocument = (content) => {
    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 16px; line-height: 1.5; }</style>
</head>
<body>
${content || ""}
</body>
</html>`;
};

// Generate preview content
const getPreviewSrcdoc = computed(() => {
    const content = isFullHtmlDocument.value
        ? sourceCode.value
        : wrapInDocument(sourceCode.value);
    return content;
});

// Insert emoji
const insertEmoji = (emoji) => {
    editor.value?.chain().focus().insertContent(emoji).run();
    showEmojiPicker.value = false;
};

// Media browser functions
const openMediaBrowser = async () => {
    showMediaBrowser.value = true;
    showLogoBrowser.value = false;
    isLoadingMedia.value = true;
    try {
        const response = await axios.get(route("media.search"));
        let media = response.data.media || [];
        // Filter only images (not logos)
        media = media.filter(m => m.type !== 'logo');
        mediaLibrary.value = media;
    } catch (error) {
        console.error("Failed to load media:", error);
        mediaLibrary.value = [];
    } finally {
        isLoadingMedia.value = false;
    }
};

const openLogoBrowser = async () => {
    showLogoBrowser.value = true;
    showMediaBrowser.value = false;
    isLoadingMedia.value = true;
    try {
        const response = await axios.get(route("media.search"));
        let media = response.data.media || [];
        // Filter only logos
        media = media.filter(m => m.type === 'logo');
        mediaLibrary.value = media;
    } catch (error) {
        console.error("Failed to load logos:", error);
        mediaLibrary.value = [];
    } finally {
        isLoadingMedia.value = false;
    }
};

const selectFromMediaBrowser = (media) => {
    imageUrl.value = media.url;
    imagePreviewLoaded.value = true;
    imagePreviewError.value = false;
    showMediaBrowser.value = false;
    showLogoBrowser.value = false;
};

// Delete image (for edit mode)
const deleteImage = () => {
    editor.value?.chain().focus().deleteSelection().run();
    showImageModal.value = false;
    isEditingImage.value = false;
};

// Expose methods for parent component
defineExpose({
    switchMode,
    getSourceCode: () => sourceCode.value,
    setSourceCode: (code) => {
        sourceCode.value = code;
        emit("update:modelValue", code);
    },
    getCurrentMode: () => editorMode.value,
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

// Toolbar button class
const btnClass = (isActive = false) => {
    const base =
        "rounded p-1.5 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white transition-colors";
    return isActive
        ? `${base} bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white`
        : base;
};
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900"
    >
        <!-- Mode Tabs & Toolbar -->
        <!-- Mode Tabs & Toolbar -->
        <div
            class="border-b border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 sticky top-0 z-10"
        >
            <!-- Mode Switcher -->
            <div
                class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-2 py-1"
            >
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        @click="switchMode('visual')"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            editorMode === 'visual'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                        ]"
                    >
                        <span class="flex items-center gap-1.5">
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
                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                />
                            </svg>
                            {{ $t("editor.mode_visual") }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="switchMode('source')"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            editorMode === 'source'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                        ]"
                    >
                        <span class="flex items-center gap-1.5">
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
                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                                />
                            </svg>
                            {{ $t("editor.mode_source") }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="switchMode('preview')"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            editorMode === 'preview'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                        ]"
                    >
                        <span class="flex items-center gap-1.5">
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
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            {{ $t("editor.mode_preview") }}
                        </span>
                    </button>
                </div>

                <!-- Additional actions -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        v-if="editorMode === 'source'"
                        @click="formatSource"
                        class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700"
                    >
                        {{ $t("editor.format") }}
                    </button>
                </div>
            </div>

            <!-- Visual Mode Toolbar -->
            <div
                v-if="editorMode === 'visual' && editor"
                class="flex flex-wrap items-center gap-1 p-2"
            >
                <!-- Text formatting -->
                <button
                    type="button"
                    @click="editor.chain().focus().toggleBold().run()"
                    :disabled="!editor.can().chain().focus().toggleBold().run()"
                    :class="btnClass(editor.isActive('bold'))"
                    :title="$t('editor.bold')"
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
                            d="M6 12h8a4 4 0 100-8H6v8zm0 0h8a4 4 0 110 8H6v-8z"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleItalic().run()"
                    :disabled="
                        !editor.can().chain().focus().toggleItalic().run()
                    "
                    :class="btnClass(editor.isActive('italic'))"
                    :title="$t('editor.italic')"
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
                            d="M10 4h4m-2 0v16m-6-4h12"
                            transform="skewX(-12)"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleUnderline().run()"
                    :class="btnClass(editor.isActive('underline'))"
                    :title="$t('editor.underline')"
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
                            d="M7 8v4a5 5 0 0010 0V8M5 20h14"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleStrike().run()"
                    :disabled="!editor.can().chain().focus().toggleStrike().run()"
                    :class="btnClass(editor.isActive('strike'))"
                    :title="$t('editor.strikethrough') || 'Przekreślenie'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L15 9M5 12h14" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Font Family Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="
                            showFontPicker = !showFontPicker;
                            showSizePicker = false;
                            showColorPicker = false;
                        "
                        :class="btnClass(showFontPicker)"
                        :title="$t('editor.font_family') || 'Czcionka'"
                    >
                        <span class="text-xs font-medium">Aa</span>
                    </button>
                    <div
                        v-if="showFontPicker"
                        class="absolute top-full left-0 mt-1 p-1 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 min-w-[140px]"
                    >
                        <button
                            v-for="font in fontOptions"
                            :key="font.value"
                            type="button"
                            @click="
                                editor
                                    .chain()
                                    .focus()
                                    .setFontFamily(font.value)
                                    .run();
                                showFontPicker = false;
                            "
                            :style="{ fontFamily: font.value }"
                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300"
                        >
                            {{ font.name }}
                        </button>
                        <hr
                            class="my-1 border-slate-200 dark:border-slate-700"
                        />
                        <button
                            type="button"
                            @click="
                                editor.chain().focus().unsetFontFamily().run();
                                showFontPicker = false;
                            "
                            class="w-full text-left px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                        >
                            {{ $t("editor.clear_font") || "Domyślna" }}
                        </button>
                    </div>
                </div>

                <!-- Font Size Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="
                            showSizePicker = !showSizePicker;
                            showFontPicker = false;
                            showColorPicker = false;
                        "
                        :class="btnClass(showSizePicker)"
                        :title="$t('editor.font_size') || 'Rozmiar'"
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
                                d="M4 6h16M4 12h8m-8 6h16"
                            />
                        </svg>
                    </button>
                    <div
                        v-if="showSizePicker"
                        class="absolute top-full left-0 mt-1 p-1 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 w-[80px]"
                    >
                        <button
                            v-for="size in fontSizeOptions"
                            :key="size"
                            type="button"
                            @click="
                                editor.chain().focus().setFontSize(size).run();
                                showSizePicker = false;
                            "
                            class="w-full text-center px-2 py-1 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300"
                        >
                            {{ size }}
                        </button>
                    </div>
                </div>

                <!-- Text Color Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="
                            showColorPicker = !showColorPicker;
                            showFontPicker = false;
                            showSizePicker = false;
                        "
                        :class="btnClass(showColorPicker)"
                        :title="$t('editor.text_color') || 'Kolor tekstu'"
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
                                d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
                            />
                        </svg>
                        <span
                            class="absolute bottom-0 left-1/2 -translate-x-1/2 w-3 h-0.5 rounded"
                            :style="{
                                backgroundColor:
                                    editor.getAttributes('textStyle')?.color ||
                                    '#000',
                            }"
                        ></span>
                    </button>
                    <div
                        v-if="showColorPicker"
                        class="absolute top-full left-0 mt-1 p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 w-[156px]"
                    >
                        <div class="grid grid-cols-6 gap-1">
                            <button
                                v-for="color in colorPalette"
                                :key="color"
                                type="button"
                                @click="
                                    editor
                                        .chain()
                                        .focus()
                                        .setColor(color)
                                        .run();
                                    showColorPicker = false;
                                "
                                class="w-5 h-5 rounded border border-slate-300 dark:border-slate-600 hover:scale-110 transition-transform"
                                :style="{ backgroundColor: color }"
                                :title="color"
                            ></button>
                        </div>
                        <hr
                            class="my-2 border-slate-200 dark:border-slate-700"
                        />
                        <button
                            type="button"
                            @click="
                                editor.chain().focus().unsetColor().run();
                                showColorPicker = false;
                            "
                            class="w-full text-center px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded"
                        >
                            {{ $t("editor.clear_color") || "Usuń kolor" }}
                        </button>
                    </div>
                </div>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Text alignment -->
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('left').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'left' }))"
                    :title="$t('editor.align_left') || 'Do lewej'"
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
                            d="M4 6h16M4 12h10M4 18h16"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('center').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'center' }))"
                    :title="$t('editor.align_center') || 'Wyśrodkuj'"
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
                            d="M4 6h16M7 12h10M4 18h16"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('right').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'right' }))"
                    :title="$t('editor.align_right') || 'Do prawej'"
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
                            d="M4 6h16M10 12h10M4 18h16"
                        />
                    </svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Highlight Color Picker -->
                <div class="relative">
                    <button type="button" @click="showHighlightPicker = !showHighlightPicker; showFontPicker = false; showSizePicker = false; showColorPicker = false; showTextTransformPicker = false" :class="btnClass(showHighlightPicker || editor.isActive('highlight'))" :title="$t('editor.highlight') || 'Podświetlenie'">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        <span class="absolute bottom-0 left-1/2 -translate-x-1/2 w-3 h-1 rounded" :style="{ backgroundColor: editor.getAttributes('highlight')?.color || '#FFCC00' }"></span>
                    </button>
                    <div v-if="showHighlightPicker" class="absolute top-full left-0 mt-1 p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 w-[156px]">
                        <div class="grid grid-cols-6 gap-1">
                            <button v-for="color in colorPalette" :key="color" type="button" @click="editor.chain().focus().toggleHighlight({ color }).run(); showHighlightPicker = false" class="w-5 h-5 rounded border border-slate-300 dark:border-slate-600 hover:scale-110 transition-transform" :style="{ backgroundColor: color }" :title="color"></button>
                        </div>
                        <hr class="my-2 border-slate-200 dark:border-slate-700" />
                        <button type="button" @click="editor.chain().focus().unsetHighlight().run(); showHighlightPicker = false" class="w-full text-center px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded">{{ $t('editor.clear_highlight') || 'Usuń podświetlenie' }}</button>
                    </div>
                </div>

                <!-- Text Transform Picker -->
                <div class="relative">
                    <button type="button" @click="showTextTransformPicker = !showTextTransformPicker; showFontPicker = false; showSizePicker = false; showColorPicker = false; showHighlightPicker = false" :class="btnClass(showTextTransformPicker || editor.isActive('textTransform'))" :title="$t('editor.text_transform') || 'Wielkość liter'">
                        <span class="text-xs font-bold">Aa</span>
                    </button>
                    <div v-if="showTextTransformPicker" class="absolute top-full left-0 mt-1 p-1 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 min-w-[140px]">
                        <button type="button" @click="editor.chain().focus().setTextTransform('uppercase').run(); showTextTransformPicker = false" class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300" :class="{ 'bg-indigo-100 dark:bg-indigo-900/30': editor.getAttributes('textTransform')?.textTransform === 'uppercase' }"><span class="uppercase">{{ $t('editor.text_transform_uppercase') || 'WIELKIE LITERY' }}</span></button>
                        <button type="button" @click="editor.chain().focus().setTextTransform('lowercase').run(); showTextTransformPicker = false" class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300" :class="{ 'bg-indigo-100 dark:bg-indigo-900/30': editor.getAttributes('textTransform')?.textTransform === 'lowercase' }"><span class="lowercase">{{ $t('editor.text_transform_lowercase') || 'małe litery' }}</span></button>
                        <button type="button" @click="editor.chain().focus().setTextTransform('capitalize').run(); showTextTransformPicker = false" class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300" :class="{ 'bg-indigo-100 dark:bg-indigo-900/30': editor.getAttributes('textTransform')?.textTransform === 'capitalize' }"><span class="capitalize">{{ $t('editor.text_transform_capitalize') || 'Pierwsza Wielka' }}</span></button>
                        <hr class="my-1 border-slate-200 dark:border-slate-700" />
                        <button type="button" @click="editor.chain().focus().unsetTextTransform().run(); showTextTransformPicker = false" class="w-full text-left px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors">{{ $t('editor.clear_text_transform') || 'Normalne' }}</button>
                    </div>
                </div>

                <!-- Headings -->
                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="btnClass(editor.isActive('heading', { level: 1 }))" :title="$t('editor.heading1') || 'Nagłówek 1'"><span class="font-bold text-xs">H1</span></button>
                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="btnClass(editor.isActive('heading', { level: 2 }))" :title="$t('editor.heading2') || 'Nagłówek 2'"><span class="font-bold text-xs">H2</span></button>
                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="btnClass(editor.isActive('heading', { level: 3 }))" :title="$t('editor.heading3') || 'Nagłówek 3'"><span class="font-bold text-xs">H3</span></button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Lists -->
                <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="btnClass(editor.isActive('bulletList'))" :title="$t('editor.bullet_list') || 'Lista punktowa'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="btnClass(editor.isActive('orderedList'))" :title="$t('editor.ordered_list') || 'Lista numerowana'">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><text x="2" y="8" font-size="6" font-weight="bold">1.</text><text x="2" y="14" font-size="6" font-weight="bold">2.</text><text x="2" y="20" font-size="6" font-weight="bold">3.</text><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11" fill="none"/></svg>
                </button>
                <button type="button" @click="editor.chain().focus().sinkListItem('listItem').run()" :disabled="!editor.can().sinkListItem('listItem')" :class="[btnClass(), { 'opacity-40 cursor-not-allowed': !editor.can().sinkListItem('listItem') }]" :title="$t('editor.indent') || 'Zwiększ wcięcie'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M9 9h12M9 14h12M3 19h18M3 9l4 2.5L3 14" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().liftListItem('listItem').run()" :disabled="!editor.can().liftListItem('listItem')" :class="[btnClass(), { 'opacity-40 cursor-not-allowed': !editor.can().liftListItem('listItem') }]" :title="$t('editor.outdent') || 'Zmniejsz wcięcie'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M9 9h12M9 14h12M3 19h18M7 9l-4 2.5L7 14" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Blockquote & Code -->
                <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="btnClass(editor.isActive('blockquote'))" :title="$t('editor.blockquote') || 'Cytat'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()" :class="btnClass(editor.isActive('codeBlock'))" :title="$t('editor.code_block') || 'Blok kodu'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                </button>

                <!-- Emoji Picker -->
                <div class="relative">
                    <button ref="emojiButtonRef" type="button" @click="showEmojiPicker = !showEmojiPicker" :class="btnClass(showEmojiPicker)" :title="$t('editor.emoji') || 'Emoji'"><span class="text-lg">😀</span></button>
                </div>
                <Teleport to="body">
                    <div v-if="showEmojiPicker" ref="emojiPickerRef" class="fixed p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-[9999] w-[320px]" :style="emojiPickerStyle">
                        <div class="flex gap-1 mb-2 border-b border-slate-200 dark:border-slate-700 pb-2">
                            <button v-for="(category, key) in emojiCategories" :key="key" type="button" @click="activeEmojiCategory = key" :class="['p-1.5 rounded transition-colors text-lg', activeEmojiCategory === key ? 'bg-indigo-100 dark:bg-indigo-900/50' : 'hover:bg-slate-100 dark:hover:bg-slate-700']" :title="key">{{ category.icon }}</button>
                        </div>
                        <div class="grid grid-cols-10 gap-0.5 max-h-[200px] overflow-y-auto">
                            <button v-for="emoji in emojiCategories[activeEmojiCategory]?.emojis || []" :key="emoji" type="button" @click="insertEmoji(emoji)" class="p-1 text-xl hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors">{{ emoji }}</button>
                        </div>
                    </div>
                    <div v-if="showEmojiPicker" class="fixed inset-0 z-[9998]" @click="showEmojiPicker = false"></div>
                </Teleport>

                <!-- Horizontal Rule -->
                <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" :class="btnClass()" :title="$t('editor.horizontal_rule') || 'Linia pozioma'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Table Controls -->
                <button
                    type="button"
                    @click="
                        editor
                            .chain()
                            .focus()
                            .insertTable({
                                rows: 3,
                                cols: 3,
                                withHeaderRow: true,
                            })
                            .run()
                    "
                    :class="btnClass()"
                    :title="$t('editor.insert_table') || 'Wstaw tabelę'"
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
                            d="M3 10h18M3 14h18m-9-4v8m-7-12h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V4a2 2 0 012-2z"
                        />
                    </svg>
                </button>

                <div
                    v-if="editor.isActive('table')"
                    class="flex items-center gap-1 border-l border-slate-300 dark:border-slate-600 pl-1 ml-1"
                >
                    <button
                        type="button"
                        @click="editor.chain().focus().addColumnBefore().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_col_before') || 'Kolumna przed'"
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
                                d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().addColumnAfter().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_col_after') || 'Kolumna po'"
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
                                d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteColumn().run()"
                        :class="btnClass()"
                        :title="$t('editor.delete_col') || 'Usuń kolumnę'"
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
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                    <div
                        class="mx-1 h-4 w-px bg-slate-300 dark:bg-slate-600"
                    ></div>
                    <button
                        type="button"
                        @click="editor.chain().focus().addRowBefore().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_row_before') || 'Wiersz przed'"
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
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().addRowAfter().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_row_after') || 'Wiersz po'"
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
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                transform="rotate(180 12 12)"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteRow().run()"
                        :class="btnClass()"
                        :title="$t('editor.delete_row') || 'Usuń wiersz'"
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
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                    <div
                        class="mx-1 h-4 w-px bg-slate-300 dark:bg-slate-600"
                    ></div>
                    <button
                        type="button"
                        @click="editor.chain().focus().mergeCells().run()"
                        :class="btnClass()"
                        :title="$t('editor.merge_cells') || 'Scal komórki'"
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
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteTable().run()"
                        :class="btnClass()"
                        :title="$t('editor.delete_table') || 'Usuń tabelę'"
                        class="text-red-500 hover:text-red-600"
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
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                </div>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Link -->
                <button
                    type="button"
                    @click="openLinkModal"
                    :class="btnClass(editor.isActive('link'))"
                    :title="$t('editor.link') || 'Link'"
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
                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                        />
                    </svg>
                </button>

                <!-- Image -->
                <button
                    type="button"
                    @click="openImageModal"
                    :class="btnClass()"
                    :title="$t('editor.image') || 'Obraz'"
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
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Undo/Redo -->
                <button
                    type="button"
                    @click="editor.chain().focus().undo().run()"
                    :disabled="!editor.can().chain().focus().undo().run()"
                    :class="btnClass()"
                    :title="$t('editor.undo') || 'Cofnij'"
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
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().redo().run()"
                    :disabled="!editor.can().chain().focus().redo().run()"
                    :class="btnClass()"
                    :title="$t('editor.redo') || 'Ponów'"
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
                            d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"
                        />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Editor Content Area -->
        <div :style="{ minHeight: minHeight }">
            <!-- Visual Editor (Tiptap) -->
            <div v-show="editorMode === 'visual' && !isFullHtmlDocument">
                <editor-content :editor="editor" class="min-h-[200px]" />
            </div>

            <!-- Source Code Editor -->
            <div v-show="editorMode === 'source'" class="relative">
                <textarea
                    v-model="sourceCode"
                    @input="updateFromSource"
                    class="w-full min-h-[250px] p-4 font-mono text-sm bg-slate-950 text-emerald-400 focus:outline-none resize-y"
                    :style="{ minHeight: minHeight }"
                    :placeholder="
                        $t('inserts.paste_html_hint') ||
                        'Wklej tutaj kod HTML podpisu lub wpisz ręcznie...'
                    "
                    spellcheck="false"
                ></textarea>
            </div>

            <!-- Preview (iframe) -->
            <div v-show="editorMode === 'preview'" class="bg-white">
                <iframe
                    :srcdoc="getPreviewSrcdoc"
                    class="w-full border-0"
                    :style="{ minHeight: minHeight }"
                    sandbox="allow-same-origin"
                ></iframe>
            </div>

            <!-- Full HTML message for visual mode -->
            <div
                v-if="editorMode === 'visual' && isFullHtmlDocument"
                class="p-6 text-center"
            >
                <div
                    class="inline-flex items-center gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-amber-700 dark:text-amber-300"
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
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span>{{
                        $t("editor.full_html_message") ||
                        "Zawartość HTML wykryta. Użyj trybu kodu źródłowego lub podglądu."
                    }}</span>
                </div>
            </div>
        </div>

        <!-- Link Modal -->
        <Teleport to="body">
            <div
                v-if="showLinkModal"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4"
            >
                <div
                    class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 p-6 shadow-2xl"
                >
                    <h3
                        class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        {{ $t("editor.insert_link") || "Wstaw link" }}
                    </h3>
                    <input
                        v-model="linkUrl"
                        type="url"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :placeholder="
                            $t('editor.link_placeholder') ||
                            'https://example.com'
                        "
                        @keyup.enter="setLink"
                    />
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showLinkModal = false"
                            class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"
                        >
                            {{ $t("common.cancel") }}
                        </button>
                        <button
                            type="button"
                            @click="setLink"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            {{ $t("common.save") }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Image Modal (Advanced) -->
        <Teleport to="body">
            <div v-if="showImageModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
                <div class="w-full max-w-lg rounded-xl bg-white dark:bg-slate-800 p-6 shadow-2xl my-4 max-h-[90vh] overflow-y-auto">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                        {{ isEditingImage ? $t('editor.edit_image') : $t('editor.insert_image') || 'Wstaw obraz' }}
                    </h3>

                    <!-- Upload Zone -->
                    <div class="mb-4">
                        <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:hover:border-slate-500 dark:hover:bg-slate-600 transition-colors">
                            <div class="flex flex-col items-center justify-center py-4">
                                <div v-if="isUploading">
                                    <svg class="animate-spin h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </div>
                                <div v-else class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="text-sm font-medium">{{ $t('editor.click_to_upload') || 'Kliknij, aby wgrać' }}</span>
                                </div>
                            </div>
                            <input type="file" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp" @change="handleImageUpload" :disabled="isUploading" />
                        </label>
                        <p v-if="imageUploadError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ imageUploadError }}</p>
                    </div>

                    <!-- Media Browser Button -->
                    <div class="mb-4">
                        <button type="button" @click="openMediaBrowser" class="w-full flex items-center justify-center gap-2 rounded-lg border-2 border-indigo-300 bg-indigo-50 p-3 text-sm font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50 transition-colors">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            {{ $t('common.browse_media') || 'Przeglądaj multimedia' }}
                        </button>
                    </div>

                    <!-- Media Browser Grid -->
                    <div v-if="showMediaBrowser" class="mb-4 border rounded-lg p-3 bg-slate-50 dark:bg-slate-900 max-h-40 overflow-y-auto">
                        <div v-if="isLoadingMedia" class="flex items-center justify-center py-4">
                            <svg class="animate-spin h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </div>
                        <div v-else-if="mediaLibrary.length === 0" class="text-center py-4 text-slate-500 dark:text-slate-400 text-sm">{{ $t('media.no_media_found') || 'Brak plików' }}</div>
                        <div v-else class="grid grid-cols-4 gap-2">
                            <button v-for="media in mediaLibrary" :key="media.id" type="button" @click="selectFromMediaBrowser(media)" class="aspect-square overflow-hidden rounded-lg border-2 border-transparent hover:border-indigo-500 transition-colors">
                                <img :src="media.url" :alt="media.name" class="h-full w-full object-cover" />
                            </button>
                        </div>
                    </div>

                    <!-- Logo Browser Button -->
                    <div class="mb-4">
                        <button type="button" @click="openLogoBrowser" class="w-full flex items-center justify-center gap-2 rounded-lg border-2 border-purple-300 bg-purple-50 p-3 text-sm font-medium text-purple-700 hover:bg-purple-100 dark:border-purple-600 dark:bg-purple-900/30 dark:text-purple-300 dark:hover:bg-purple-900/50 transition-colors">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                            {{ $t('common.browse_logos') || 'Przeglądaj loga' }}
                        </button>
                    </div>

                    <!-- Logo Browser Grid -->
                    <div v-if="showLogoBrowser" class="mb-4 border rounded-lg p-3 bg-purple-50 dark:bg-purple-900/20 max-h-40 overflow-y-auto">
                        <div v-if="isLoadingMedia" class="flex items-center justify-center py-4">
                            <svg class="animate-spin h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </div>
                        <div v-else-if="mediaLibrary.length === 0" class="text-center py-4 text-purple-600 dark:text-purple-400 text-sm">{{ $t('media.no_logos_found') || 'Brak logotypów' }}</div>
                        <div v-else class="grid grid-cols-4 gap-2">
                            <button v-for="media in mediaLibrary" :key="media.id" type="button" @click="selectFromMediaBrowser(media)" class="aspect-square overflow-hidden rounded-lg border-2 border-transparent hover:border-purple-500 bg-white dark:bg-slate-800 transition-colors">
                                <img :src="media.url" :alt="media.name" class="h-full w-full object-contain p-1" />
                            </button>
                        </div>
                    </div>

                    <!-- Separator -->
                    <div class="relative mb-4">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-300 dark:border-slate-600"></div></div>
                        <div class="relative flex justify-center text-sm"><span class="bg-white dark:bg-slate-800 px-2 text-slate-500 dark:text-slate-400">{{ $t('editor.or_paste_url') || 'lub wklej URL' }}</span></div>
                    </div>

                    <!-- URL Input -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $t('editor.image_url') || 'URL obrazu' }}</label>
                        <input v-model="imageUrl" type="url" @input="onImageUrlChange" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="https://example.com/image.jpg" />
                    </div>

                    <!-- Image Preview -->
                    <div v-if="imageUrl" class="mb-4">
                        <div class="relative bg-slate-100 dark:bg-slate-700 rounded-lg p-4 min-h-[100px] flex items-center justify-center">
                            <div v-if="!imagePreviewLoaded && !imagePreviewError" class="text-slate-400 flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </div>
                            <div v-if="imagePreviewError" class="text-red-500 flex items-center gap-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ $t('editor.image_load_error') || 'Błąd ładowania' }}
                            </div>
                            <img :src="imageUrl" @load="onImageLoad" @error="onImageError" :class="['max-h-[120px] rounded transition-opacity', imagePreviewLoaded ? 'opacity-100' : 'opacity-0 absolute']" :style="{ width: imageWidth + '%', maxWidth: '100%' }" />
                        </div>
                    </div>

                    <!-- Settings (only show when image is loaded) -->
                    <div v-if="imagePreviewLoaded" class="space-y-4">
                        <!-- Alignment -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.alignment') || 'Wyrównanie' }}</label>
                            <div class="flex gap-2">
                                <button type="button" @click="imageAlignment = 'left'" :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors', imageAlignment === 'left' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14" /></svg>
                                </button>
                                <button type="button" @click="imageAlignment = 'center'" :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors', imageAlignment === 'center' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14" /></svg>
                                </button>
                                <button type="button" @click="imageAlignment = 'right'" :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors', imageAlignment === 'right' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Width -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.width') || 'Szerokość' }}: {{ imageWidth }}%</label>
                            <input v-model="imageWidth" type="range" min="10" max="100" step="5" class="w-full h-2 bg-slate-200 dark:bg-slate-600 rounded-lg appearance-none cursor-pointer accent-indigo-600" />
                        </div>

                        <!-- Float (Text Wrapping) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.image_float') || 'Opływanie tekstu' }}</label>
                            <div class="flex gap-2">
                                <button type="button" @click="imageFloat = 'none'" :class="['flex-1 px-3 py-2 rounded-lg border text-sm transition-colors', imageFloat === 'none' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']">{{ $t('editor.float_none') || 'Brak' }}</button>
                                <button type="button" @click="imageFloat = 'left'" :class="['flex-1 px-3 py-2 rounded-lg border text-sm transition-colors', imageFloat === 'left' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']">{{ $t('editor.float_left') || 'Lewo' }}</button>
                                <button type="button" @click="imageFloat = 'right'" :class="['flex-1 px-3 py-2 rounded-lg border text-sm transition-colors', imageFloat === 'right' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']">{{ $t('editor.float_right') || 'Prawo' }}</button>
                            </div>
                        </div>

                        <!-- Margin -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.image_margin') || 'Margines' }}: {{ imageMargin }}px</label>
                            <input v-model="imageMargin" type="range" min="0" max="50" step="5" class="w-full h-2 bg-slate-200 dark:bg-slate-600 rounded-lg appearance-none cursor-pointer accent-indigo-600" />
                        </div>

                        <!-- Border Radius -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.image_border_radius') || 'Zaokrąglenie rogów' }}: {{ imageBorderRadius }}px</label>
                            <input v-model="imageBorderRadius" type="range" min="0" max="50" step="5" class="w-full h-2 bg-slate-200 dark:bg-slate-600 rounded-lg appearance-none cursor-pointer accent-indigo-600" />
                        </div>

                        <!-- Link -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $t('editor.image_link') || 'Link do obrazu' }}</label>
                            <input v-model="imageLink" type="url" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="https://example.com" />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex justify-between">
                        <div>
                            <button v-if="isEditingImage" type="button" @click="deleteImage" class="px-4 py-2 text-sm rounded-lg transition-colors bg-red-600 text-white hover:bg-red-700">{{ $t('editor.delete_image') || 'Usuń obraz' }}</button>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="showImageModal = false" class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">{{ $t('common.cancel') }}</button>
                            <button type="button" @click="insertImage" :disabled="!imageUrl || !imagePreviewLoaded" :class="['px-4 py-2 text-sm rounded-lg transition-colors', (!imageUrl || !imagePreviewLoaded) ? 'bg-slate-300 text-slate-500 cursor-not-allowed' : 'bg-indigo-600 text-white hover:bg-indigo-700']">{{ $t('editor.insert') || 'Wstaw' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style>
/* Basic editor styles */
.ProseMirror {
    outline: none;
    min-height: 150px;
}

.ProseMirror > * + * {
    margin-top: 0.75em;
}

.ProseMirror ul,
.ProseMirror ol {
    padding: 0 1rem;
    margin-left: 1rem;
}

/* Unordered list styles */
.ProseMirror ul {
    list-style-type: disc;
}

.ProseMirror ul ul {
    list-style-type: circle;
}

.ProseMirror ul ul ul {
    list-style-type: square;
}

/* Ordered list styles */
.ProseMirror ol {
    list-style-type: decimal;
}

.ProseMirror ol ol {
    list-style-type: lower-alpha;
}

.ProseMirror ol ol ol {
    list-style-type: lower-roman;
}

/* List item spacing */
.ProseMirror li {
    margin-bottom: 0.25em;
}

.ProseMirror li > p {
    margin: 0;
}

.ProseMirror li::marker {
    color: inherit;
}

.ProseMirror h1,
.ProseMirror h2,
.ProseMirror h3 {
    line-height: 1.1;
}

.ProseMirror code {
    background-color: rgba(97, 97, 97, 0.1);
    color: #616161;
}

.ProseMirror pre {
    background: #0d0d0d;
    color: #fff;
    font-family: "JetBrainsMono", monospace;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
}

.ProseMirror pre code {
    background: none;
    color: inherit;
    font-size: 0.8em;
    padding: 0;
}

/* Table styles */
.ProseMirror table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    margin: 0;
    overflow: hidden;
}

.ProseMirror td,
.ProseMirror th {
    min-width: 1em;
    border: 2px solid #ced4da;
    padding: 3px 5px;
    vertical-align: top;
    box-sizing: border-box;
    position: relative;
}

.ProseMirror th {
    font-weight: bold;
    text-align: left;
    background-color: #f1f3f5;
}

.ProseMirror .selectedCell:after {
    z-index: 2;
    position: absolute;
    content: "";
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    background: rgba(200, 200, 255, 0.4);
    pointer-events: none;
}

.ProseMirror .column-resize-handle {
    position: absolute;
    right: -2px;
    top: 0;
    bottom: -2px;
    width: 4px;
    background-color: #adf;
    pointer-events: none;
}

.dark .ProseMirror td,
.dark .ProseMirror th {
    border-color: #475569;
}

.dark .ProseMirror th {
    background-color: #1e293b;
}

.ProseMirror img {
    max-width: 100%;
    height: auto;
}

.ProseMirror blockquote {
    padding-left: 1rem;
    border-left: 2px solid rgba(13, 13, 13, 0.1);
}

.ProseMirror hr {
    border: none;
    border-top: 2px solid rgba(13, 13, 13, 0.1);
    margin: 2rem 0;
}
</style>
