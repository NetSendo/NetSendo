<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import { FontFamily } from '@tiptap/extension-font-family'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import { Highlight } from '@tiptap/extension-highlight'
import { FontSize } from 'tiptap-extension-font-size'
import { watch, ref, onBeforeUnmount, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    editable: {
        type: Boolean,
        default: true,
    },
    minHeight: {
        type: String,
        default: '200px',
    },
})

const emit = defineEmits(['update:modelValue'])

// Editor modes: visual, source, preview
const editorMode = ref('visual')

// sourceCode is the SINGLE SOURCE OF TRUTH for content
const sourceCode = ref(props.modelValue || '')

const showLinkModal = ref(false)
const linkUrl = ref('')
const showImageModal = ref(false)
const imageUrl = ref('')

// Enhanced image settings
const imageAlignment = ref('center')
const imageWidth = ref('100')
const imagePreviewLoaded = ref(false)
const imagePreviewError = ref(false)

// Font/Color picker states
const showFontPicker = ref(false)
const showSizePicker = ref(false)
const showColorPicker = ref(false)

// Available fonts
const fontOptions = [
    { name: 'Arial', value: 'Arial, sans-serif' },
    { name: 'Georgia', value: 'Georgia, serif' },
    { name: 'Times New Roman', value: 'Times New Roman, serif' },
    { name: 'Verdana', value: 'Verdana, sans-serif' },
    { name: 'Courier New', value: 'Courier New, monospace' },
    { name: 'Roboto', value: 'Roboto, sans-serif' },
    { name: 'Helvetica', value: 'Helvetica, Arial, sans-serif' },
]

// Available font sizes
const fontSizeOptions = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px']

// Color palette for text
const colorPalette = [
    '#000000', '#434343', '#666666', '#999999', '#CCCCCC', '#FFFFFF',
    '#FF0000', '#FF6600', '#FFCC00', '#00FF00', '#00CCFF', '#0066FF',
    '#9900FF', '#FF00FF', '#FF6699', '#996633', '#003366', '#339966',
]

// Check if content is a full HTML document
const isFullHtmlDocument = computed(() => {
    const content = sourceCode.value?.trim().toLowerCase() || ''
    return content.startsWith('<!doctype') ||
           content.startsWith('<html') ||
           content.includes('<table') ||
           content.includes('<body')
})

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3],
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-indigo-600 hover:text-indigo-800 underline',
            },
        }),
        Image.configure({
            inline: true,
            allowBase64: true,
        }),
        Underline,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        TextStyle,
        FontFamily.configure({
            types: ['textStyle'],
        }),
        Color.configure({
            types: ['textStyle'],
        }),
        Highlight.configure({
            multicolor: true,
        }),
        FontSize,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm focus:outline-none dark:prose-invert min-h-[150px] text-slate-900 dark:text-white p-4',
        },
    },
    editable: props.editable,
    onUpdate: () => {
        if (!isFullHtmlDocument.value && editorMode.value === 'visual') {
            const html = editor.value?.getHTML() || ''
            sourceCode.value = html
            emit('update:modelValue', html)
        }
    },
})

// Watch for external content changes
watch(() => props.modelValue, (value) => {
    if (value !== sourceCode.value) {
        sourceCode.value = value || ''

        nextTick(() => {
            const content = (value || '').trim().toLowerCase()
            const isFullHtml = content.startsWith('<!doctype') ||
                               content.startsWith('<html') ||
                               content.includes('<table') ||
                               content.includes('<body')
            if (isFullHtml && editorMode.value === 'visual') {
                editorMode.value = 'preview'
            }
        })
    }

    if (!isFullHtmlDocument.value && editorMode.value === 'visual') {
        const isSame = editor.value?.getHTML() === value
        if (!isSame) {
            editor.value?.commands.setContent(value || '', false)
        }
    }
}, { immediate: false })

// Initialize on mount
onMounted(() => {
    sourceCode.value = props.modelValue || ''

    if (isFullHtmlDocument.value) {
        editorMode.value = 'preview'
    }
})

// Switch between modes
const switchMode = (mode) => {
    const previousMode = editorMode.value

    if (previousMode === 'visual' && !isFullHtmlDocument.value) {
        const tiptapHtml = editor.value?.getHTML()
        if (tiptapHtml) {
            sourceCode.value = tiptapHtml
        }
    }

    if (mode === 'visual' && !isFullHtmlDocument.value) {
        nextTick(() => {
            editor.value?.commands.setContent(sourceCode.value || '', false)
        })
    }

    emit('update:modelValue', sourceCode.value)
    editorMode.value = mode
}

// Handle source code changes from textarea
const updateFromSource = () => {
    emit('update:modelValue', sourceCode.value)
}

// Format source code (basic prettify)
const formatSource = () => {
    try {
        let formatted = sourceCode.value
            .replace(/></g, '>\n<')
            .replace(/\n\s*\n/g, '\n')
        sourceCode.value = formatted
        emit('update:modelValue', formatted)
    } catch (e) {
        console.warn('Could not format HTML')
    }
}

// Link handling
const openLinkModal = () => {
    const previousUrl = editor.value?.getAttributes('link').href
    linkUrl.value = previousUrl || ''
    showLinkModal.value = true
}

const setLink = () => {
    if (linkUrl.value) {
        editor.value?.chain().focus().extendMarkRange('link').setLink({ href: linkUrl.value }).run()
    } else {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    }
    showLinkModal.value = false
    linkUrl.value = ''
}

// Image handling
const openImageModal = () => {
    imageUrl.value = ''
    imageAlignment.value = 'center'
    imageWidth.value = '100'
    imagePreviewLoaded.value = false
    imagePreviewError.value = false
    showImageModal.value = true
}

const onImageUrlChange = () => {
    imagePreviewLoaded.value = false
    imagePreviewError.value = false
}

const onImageLoad = () => {
    imagePreviewLoaded.value = true
    imagePreviewError.value = false
}

const onImageError = () => {
    imagePreviewLoaded.value = false
    imagePreviewError.value = true
}

const insertImage = () => {
    if (imageUrl.value) {
        const alignStyle = {
            'left': 'margin-right: auto;',
            'center': 'margin-left: auto; margin-right: auto;',
            'right': 'margin-left: auto;'
        }[imageAlignment.value] || 'margin-left: auto; margin-right: auto;'

        const widthStyle = `width: ${imageWidth.value}%; max-width: 100%; height: auto;`
        const style = `display: block; ${alignStyle} ${widthStyle}`

        const imgHtml = `<img src="${imageUrl.value}" alt="" style="${style}" />`

        editor.value?.chain().focus().insertContent(imgHtml).run()
    }
    showImageModal.value = false
    imageUrl.value = ''
    imageAlignment.value = 'center'
    imageWidth.value = '100'
}

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
${content || ''}
</body>
</html>`
}

// Generate preview content
const getPreviewSrcdoc = computed(() => {
    const content = isFullHtmlDocument.value ? sourceCode.value : wrapInDocument(sourceCode.value)
    return content
})

// Expose methods for parent component
defineExpose({
    switchMode,
    getSourceCode: () => sourceCode.value,
    setSourceCode: (code) => {
        sourceCode.value = code
        emit('update:modelValue', code)
    },
    getCurrentMode: () => editorMode.value,
})

onBeforeUnmount(() => {
    editor.value?.destroy()
})

// Toolbar button class
const btnClass = (isActive = false) => {
    const base = 'rounded p-1.5 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white transition-colors'
    return isActive ? `${base} bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white` : base
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <!-- Mode Tabs & Toolbar -->
        <div class="border-b border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
            <!-- Mode Switcher -->
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-2 py-1">
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        @click="switchMode('visual')"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            editorMode === 'visual'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            {{ $t('editor.mode_visual') }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="switchMode('source')"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            editorMode === 'source'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            {{ $t('editor.mode_source') }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="switchMode('preview')"
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            editorMode === 'preview'
                                ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                        ]"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            {{ $t('editor.mode_preview') }}
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
                        {{ $t('editor.format') }}
                    </button>
                </div>
            </div>

            <!-- Visual Mode Toolbar -->
            <div v-if="editorMode === 'visual' && editor && !isFullHtmlDocument" class="flex flex-wrap items-center gap-1 p-2">
                <!-- Text formatting -->
                <button
                    type="button"
                    @click="editor.chain().focus().toggleBold().run()"
                    :disabled="!editor.can().chain().focus().toggleBold().run()"
                    :class="btnClass(editor.isActive('bold'))"
                    :title="$t('editor.bold')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h8a4 4 0 100-8H6v8zm0 0h8a4 4 0 110 8H6v-8z" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleItalic().run()"
                    :disabled="!editor.can().chain().focus().toggleItalic().run()"
                    :class="btnClass(editor.isActive('italic'))"
                    :title="$t('editor.italic')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-6-4h12" transform="skewX(-12)" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleUnderline().run()"
                    :class="btnClass(editor.isActive('underline'))"
                    :title="$t('editor.underline')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8v4a5 5 0 0010 0V8M5 20h14" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Font Family Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="showFontPicker = !showFontPicker; showSizePicker = false; showColorPicker = false"
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
                            @click="editor.chain().focus().setFontFamily(font.value).run(); showFontPicker = false"
                            :style="{ fontFamily: font.value }"
                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300"
                        >
                            {{ font.name }}
                        </button>
                        <hr class="my-1 border-slate-200 dark:border-slate-700" />
                        <button
                            type="button"
                            @click="editor.chain().focus().unsetFontFamily().run(); showFontPicker = false"
                            class="w-full text-left px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                        >
                            {{ $t('editor.clear_font') || 'Domyślna' }}
                        </button>
                    </div>
                </div>

                <!-- Font Size Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="showSizePicker = !showSizePicker; showFontPicker = false; showColorPicker = false"
                        :class="btnClass(showSizePicker)"
                        :title="$t('editor.font_size') || 'Rozmiar'"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
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
                            @click="editor.chain().focus().setFontSize(size).run(); showSizePicker = false"
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
                        @click="showColorPicker = !showColorPicker; showFontPicker = false; showSizePicker = false"
                        :class="btnClass(showColorPicker)"
                        :title="$t('editor.text_color') || 'Kolor tekstu'"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                        <span class="absolute bottom-0 left-1/2 -translate-x-1/2 w-3 h-0.5 rounded" :style="{ backgroundColor: editor.getAttributes('textStyle')?.color || '#000' }"></span>
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
                                @click="editor.chain().focus().setColor(color).run(); showColorPicker = false"
                                class="w-5 h-5 rounded border border-slate-300 dark:border-slate-600 hover:scale-110 transition-transform"
                                :style="{ backgroundColor: color }"
                                :title="color"
                            ></button>
                        </div>
                        <hr class="my-2 border-slate-200 dark:border-slate-700" />
                        <button
                            type="button"
                            @click="editor.chain().focus().unsetColor().run(); showColorPicker = false"
                            class="w-full text-center px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded"
                        >
                            {{ $t('editor.clear_color') || 'Usuń kolor' }}
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
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('center').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'center' }))"
                    :title="$t('editor.align_center') || 'Wyśrodkuj'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M4 18h16" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('right').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'right' }))"
                    :title="$t('editor.align_right') || 'Do prawej'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M4 18h16" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Link -->
                <button
                    type="button"
                    @click="openLinkModal"
                    :class="btnClass(editor.isActive('link'))"
                    :title="$t('editor.link') || 'Link'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                </button>

                <!-- Image -->
                <button
                    type="button"
                    @click="openImageModal"
                    :class="btnClass()"
                    :title="$t('editor.image') || 'Obraz'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
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
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().redo().run()"
                    :disabled="!editor.can().chain().focus().redo().run()"
                    :class="btnClass()"
                    :title="$t('editor.redo') || 'Ponów'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6" /></svg>
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
                    :placeholder="$t('inserts.paste_html_hint') || 'Wklej tutaj kod HTML podpisu lub wpisz ręcznie...'"
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
            <div v-if="editorMode === 'visual' && isFullHtmlDocument" class="p-6 text-center">
                <div class="inline-flex items-center gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-amber-700 dark:text-amber-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $t('editor.full_html_message') || 'Zawartość HTML wykryta. Użyj trybu kodu źródłowego lub podglądu.' }}</span>
                </div>
            </div>
        </div>

        <!-- Link Modal -->
        <Teleport to="body">
            <div v-if="showLinkModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-800 p-6 shadow-2xl">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">{{ $t('editor.insert_link') || 'Wstaw link' }}</h3>
                    <input
                        v-model="linkUrl"
                        type="url"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :placeholder="$t('editor.link_placeholder') || 'https://example.com'"
                        @keyup.enter="setLink"
                    />
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showLinkModal = false"
                            class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="setLink"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            {{ $t('common.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Image Modal -->
        <Teleport to="body">
            <div v-if="showImageModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
                <div class="w-full max-w-lg rounded-xl bg-white dark:bg-slate-800 p-6 shadow-2xl">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">{{ $t('editor.insert_image') || 'Wstaw obraz' }}</h3>

                    <!-- Image URL -->
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('editor.image_url') || 'URL obrazu' }}</label>
                        <input
                            v-model="imageUrl"
                            type="url"
                            @input="onImageUrlChange"
                            class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            placeholder="https://example.com/image.jpg"
                        />
                    </div>

                    <!-- Image Preview -->
                    <div v-if="imageUrl" class="mb-4">
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-2 bg-slate-50 dark:bg-slate-900 min-h-[100px] flex items-center justify-center">
                            <img
                                v-if="!imagePreviewError"
                                :src="imageUrl"
                                @load="onImageLoad"
                                @error="onImageError"
                                class="max-h-[150px] max-w-full object-contain"
                                :style="{ width: imageWidth + '%' }"
                            />
                            <span v-if="imagePreviewError" class="text-sm text-red-500">{{ $t('editor.image_load_error') || 'Nie można załadować obrazu' }}</span>
                        </div>
                    </div>

                    <!-- Alignment -->
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('editor.alignment') || 'Wyrównanie' }}</label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="imageAlignment = 'left'"
                                :class="['flex-1 rounded-lg border px-3 py-2 text-sm transition-colors', imageAlignment === 'left' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'border-slate-300 dark:border-slate-600']"
                            >
                                {{ $t('editor.align_left') || 'Lewo' }}
                            </button>
                            <button
                                type="button"
                                @click="imageAlignment = 'center'"
                                :class="['flex-1 rounded-lg border px-3 py-2 text-sm transition-colors', imageAlignment === 'center' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'border-slate-300 dark:border-slate-600']"
                            >
                                {{ $t('editor.align_center') || 'Środek' }}
                            </button>
                            <button
                                type="button"
                                @click="imageAlignment = 'right'"
                                :class="['flex-1 rounded-lg border px-3 py-2 text-sm transition-colors', imageAlignment === 'right' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'border-slate-300 dark:border-slate-600']"
                            >
                                {{ $t('editor.align_right') || 'Prawo' }}
                            </button>
                        </div>
                    </div>

                    <!-- Width -->
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('editor.width') || 'Szerokość' }}: {{ imageWidth }}%</label>
                        <input
                            v-model="imageWidth"
                            type="range"
                            min="10"
                            max="100"
                            class="w-full"
                        />
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showImageModal = false"
                            class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="insertImage"
                            :disabled="!imageUrl || imagePreviewError"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            {{ $t('editor.insert') || 'Wstaw' }}
                        </button>
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
    background: #0D0D0D;
    color: #FFF;
    font-family: 'JetBrainsMono', monospace;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
}

.ProseMirror pre code {
    color: inherit;
    padding: 0;
    background: none;
    font-size: 0.8rem;
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
