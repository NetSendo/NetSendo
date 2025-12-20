<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { watch, onBeforeUnmount } from 'vue'

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    editable: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm sm:prose lg:prose-lg xl:prose-2xl m-5 focus:outline-none dark:prose-invert min-h-[150px] text-slate-900 dark:text-white',
        },
    },
    editable: props.editable,
    onUpdate: () => {
        emit('update:modelValue', editor.value.getHTML())
    },
})

watch(() => props.modelValue, (value) => {
    // HTML
    const isSame = editor.value.getHTML() === value

    // JSON
    // const isSame = JSON.stringify(this.editor.getJSON()) === JSON.stringify(value)

    if (isSame) {
        return
    }

    editor.value.commands.setContent(value, false)
})

onBeforeUnmount(() => {
    editor.value.destroy()
})
</script>

<template>
    <div v-if="editor" class="overflow-hidden rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
            <button 
                @click="editor.chain().focus().toggleBold().run()"
                :disabled="!editor.can().chain().focus().toggleBold().run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('bold') }"
                class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Pogrubienie"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h8a4 4 0 100-8H6v8zm0 0h8a4 4 0 110 8H6v-8z" /></svg>
            </button>
            <button 
                @click="editor.chain().focus().toggleItalic().run()"
                :disabled="!editor.can().chain().focus().toggleItalic().run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('italic') }"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Kursywa"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /><path d="M19 4h-9l-4 16h9" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" /></svg>
            </button>
            <button 
                @click="editor.chain().focus().toggleStrike().run()"
                :disabled="!editor.can().chain().focus().toggleStrike().run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('strike') }"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Przekreślenie"
            >
                 <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L15 9M5.00002 12H19" /></svg>
            </button>

            <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

            <button 
                @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('heading', { level: 1 }) }"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Nagłówek 1"
            >
                <span class="font-bold text-xs">H1</span>
            </button>
            <button 
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('heading', { level: 2 }) }"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Nagłówek 2"
            >
                <span class="font-bold text-xs">H2</span>
            </button>

            <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

             <button 
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('bulletList') }"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Lista punktowana"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <button 
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('orderedList') }"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Lista numerowana"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h12M7 12h12M7 17h12M3 7v.01M3 12v.01M3 17v.01" /></svg>
            </button>

             <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

             <button 
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white': editor.isActive('blockquote') }"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Cytat"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.9 2.1c.3.9-.2 1.9-.9 2.3-1.6.8-2 2.5-1.9 3.6h2.9c1.1 0 2 .9 2 2v6c0 1.1-.9 2-2 2h-4c-1.1 0-2-.9-2-2V9.8c-.1-2.9 2-5.7 5.9-7.7zM20.9 2.1c.3.9-.2 1.9-.9 2.3-1.6.8-2 2.5-1.9 3.6h2.9c1.1 0 2 .9 2 2v6c0 1.1-.9 2-2 2h-4c-1.1 0-2-.9-2-2V9.8c-.1-2.9 2-5.7 5.9-7.7z" /></svg>
            </button>

             <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

             <button 
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor.can().chain().focus().undo().run()"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Cofnij"
            >
                 <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
            </button>
            <button 
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor.can().chain().focus().redo().run()"
                 class="rounded p-1 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white"
                title="Ponów"
            >
                 <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6" /></svg>
            </button>
        </div>

        <editor-content :editor="editor" class="p-2 min-h-[300px]" />
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
.ProseMirror h3,
.ProseMirror h4,
.ProseMirror h5,
.ProseMirror h6 {
  line-height: 1.1;
}

.ProseMirror code {
  background-color: rgba(#616161, 0.1);
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
