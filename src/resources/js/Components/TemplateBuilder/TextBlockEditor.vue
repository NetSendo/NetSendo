<script setup>
// Rich-text editor for the email template builder's text block.
//
// v-model is the block's HTML (content.html). The editor writes it back only
// after the user changed the text: loading it, clicking into it, selecting and
// leaving it emit nothing, so an untouched block keeps its HTML byte for byte.
// When the edits are undone back to the loaded state, the loaded HTML is
// emitted again as it was, not the editor's re-serialised copy of it.
//
// The schema only knows email-safe markup (paragraphs, line breaks, bold,
// italic, underline, strike, links, lists, headings, quotes, rules, styled
// spans and divs, images and tables), keeps inline styles, and drops anything
// else (scripts, iframes, forms, classes) when content is loaded or inserted.
import { ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useEditor, EditorContent } from "@tiptap/vue-3";
import { Extension, Node, createDocument } from "@tiptap/core";
import { DOMSerializer } from "@tiptap/pm/model";
import { EditorState } from "@tiptap/pm/state";
import StarterKit from "@tiptap/starter-kit";
import Image from "@tiptap/extension-image";
import { isAllowedUri } from "@tiptap/extension-link";
import { TextStyle } from "@tiptap/extension-text-style";
import {
    Table,
    TableCell,
    TableHeader,
    TableRow,
} from "@tiptap/extension-table";

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
    placeholder: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["update:modelValue"]);

const BLOCK_TAGS = new Set([
    "ADDRESS", "ARTICLE", "ASIDE", "BLOCKQUOTE", "CENTER", "DIV", "DL",
    "FIELDSET", "FIGURE", "FOOTER", "FORM", "H1", "H2", "H3", "H4", "H5", "H6",
    "HEADER", "HR", "LI", "MAIN", "NAV", "OL", "P", "PRE", "SECTION", "TABLE",
    "UL",
]);

const hasBlockChildren = (element) =>
    Array.from(element.children).some((child) => BLOCK_TAGS.has(child.tagName));

// <div> that wraps paragraphs, lists or tables, e.g. a coloured box from the
// block library: <div style="background:#f0fdf4;padding:20px">…</div>
const DivBlock = Node.create({
    name: "divBlock",
    group: "block",
    content: "block+",
    defining: true,
    parseHTML() {
        return [
            {
                tag: "div",
                getAttrs: (element) => (hasBlockChildren(element) ? null : false),
            },
        ];
    },
    renderHTML({ HTMLAttributes }) {
        return ["div", HTMLAttributes, 0];
    },
});

// <div> that holds text directly, e.g. one line of a signature. It stays a
// <div> instead of becoming a <p>, which would add paragraph margins.
const DivText = Node.create({
    name: "divText",
    group: "block",
    content: "inline*",
    parseHTML() {
        return [
            {
                tag: "div",
                getAttrs: (element) => (hasBlockChildren(element) ? false : null),
            },
        ];
    },
    renderHTML({ HTMLAttributes }) {
        return ["div", HTMLAttributes, 0];
    },
});

const plainAttribute = (name) => ({
    default: null,
    parseHTML: (element) => element.getAttribute(name),
    renderHTML: (attributes) =>
        attributes[name] ? { [name]: attributes[name] } : {},
});

// ProseMirror writes a style attribute through the browser's CSS parser,
// which turns #1e293b into rgb(30, 41, 59) and drops what the browser does
// not know (mso-line-height-rule and other email client properties). The
// style is shown that way in the editor; the stored HTML gets the original
// text back from RAW_STYLE (see serialize below).
const RAW_STYLE = "data-raw-style";

const styleAttribute = {
    default: null,
    parseHTML: (element) => element.getAttribute("style"),
    renderHTML: (attributes) =>
        attributes.style
            ? { style: attributes.style, [RAW_STYLE]: attributes.style }
            : {},
};

// Email HTML is styled inline; keep the style attribute (and the old table
// layout attributes) on everything the schema knows
const EmailAttributes = Extension.create({
    name: "emailAttributes",
    addGlobalAttributes() {
        return [
            {
                types: [
                    "paragraph",
                    "heading",
                    "blockquote",
                    "bulletList",
                    "orderedList",
                    "listItem",
                    "horizontalRule",
                    "divBlock",
                    "divText",
                    "link",
                    "textStyle",
                    "image",
                    "table",
                    "tableRow",
                    "tableCell",
                    "tableHeader",
                ],
                attributes: {
                    style: styleAttribute,
                },
            },
            {
                types: ["table", "tableCell", "tableHeader"],
                attributes: {
                    width: plainAttribute("width"),
                    align: plainAttribute("align"),
                    valign: plainAttribute("valign"),
                    bgcolor: plainAttribute("bgcolor"),
                },
            },
            {
                types: ["table"],
                attributes: {
                    border: plainAttribute("border"),
                    cellpadding: plainAttribute("cellpadding"),
                    cellspacing: plainAttribute("cellspacing"),
                },
            },
        ];
    },
});

// Pasted content keeps its structure (bold, links, lists) but not the styles
// of the page or document it came from
const stripPastedStyles = (html) => {
    const doc = new DOMParser().parseFromString(html, "text/html");
    doc.body
        .querySelectorAll("[style], [class]")
        .forEach((element) => {
            element.removeAttribute("style");
            element.removeAttribute("class");
        });
    return doc.body.innerHTML;
};

// The HTML that was loaded into the editor, as stored in the block, and what
// the editor makes of it. A document that serialises back to loadedNormalized
// is unchanged, so loadedHtml is emitted for it.
let loadedHtml = props.modelValue || "";
let loadedNormalized = null;
let lastEmitted = loadedHtml;

// Where to insert a variable: at the caret once the user has been in the
// editor, otherwise at the end
let caretPlaced = false;

const editor = useEditor({
    content: loadedHtml,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3, 4, 5, 6] },
            code: false,
            codeBlock: false,
            // It appends an empty paragraph after a closing list or table on
            // any transaction, a click included, which would change the HTML
            trailingNode: false,
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: "https",
                HTMLAttributes: {
                    target: "_blank",
                    rel: "noopener noreferrer",
                    class: null,
                },
            },
        }),
        TextStyle,
        Image.configure({ inline: true, allowBase64: true }),
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
        DivBlock,
        DivText,
        EmailAttributes,
    ],
    editorProps: {
        attributes: {
            class: "text-block-editor-content",
        },
        transformPastedHTML: stripPastedStyles,
    },
    onFocus: () => {
        caretPlaced = true;
    },
    onUpdate: ({ editor: instance }) => {
        if (loadedNormalized === null) {
            loadedNormalized = serialize(
                createDocument(
                    loadedHtml,
                    instance.schema,
                    instance.options.parseOptions
                ),
                instance.schema
            );
        }
        const html = serialize(instance.state.doc, instance.schema);
        const value = html === loadedNormalized ? loadedHtml : html;
        if (value === lastEmitted) {
            return;
        }
        lastEmitted = value;
        emit("update:modelValue", value);
    },
});

// The document as HTML, like editor.getHTML(), with each style attribute
// written exactly as it was loaded or inserted (see RAW_STYLE above)
const serialize = (doc, schema) => {
    const container = document
        .implementation.createHTMLDocument()
        .createElement("div");
    container.appendChild(
        DOMSerializer.fromSchema(schema).serializeFragment(doc.content, {
            document: container.ownerDocument,
        })
    );
    // The editor keeps the text of a list item or table cell in a <p>, and
    // email clients (and MJML) give every <p> paragraph margins, which would
    // space a list or a signature out: <li><p>text</p></li> is stored as
    // <li>text</li>, <td><p><img></p></td> as <td><img></td>
    container.querySelectorAll("li, td, th").forEach((item) => {
        const paragraph = item.firstChild;
        if (
            paragraph?.nodeName === "P" &&
            paragraph.attributes.length === 0 &&
            item.querySelectorAll(":scope > p").length === 1
        ) {
            paragraph.replaceWith(...paragraph.childNodes);
        }
    });
    container.querySelectorAll(`[${RAW_STYLE}]`).forEach((element) => {
        element.setAttribute("style", element.getAttribute(RAW_STYLE));
        element.removeAttribute(RAW_STYLE);
    });
    return container.innerHTML;
};

// The block's HTML was changed outside the editor (AI generate or improve,
// the AI assistant panel): show it, with a fresh undo history
const load = (html) => {
    const instance = editor.value;
    if (!instance) {
        return;
    }
    loadedHtml = html;
    lastEmitted = html;
    caretPlaced = false;
    const doc = createDocument(
        html,
        instance.schema,
        instance.options.parseOptions
    );
    instance.view.updateState(
        EditorState.create({ doc, plugins: instance.state.plugins })
    );
    loadedNormalized = serialize(doc, instance.schema);
    // Let the toolbar and the placeholder see the new state; no doc change,
    // so no update is emitted
    instance.view.dispatch(
        instance.state.tr
            .setMeta("preventUpdate", true)
            .setMeta("addToHistory", false)
    );
};

watch(
    () => props.modelValue,
    (value) => {
        const html = value || "";
        if (html !== lastEmitted) {
            load(html);
        }
    }
);

const focusForInsert = () => {
    const chain = editor.value.chain();
    return caretPlaced ? chain.focus() : chain.focus("end");
};

// Insert a variable such as [[fname]] as text at the caret (replacing the
// selection); it takes on the formatting around it
const insertText = (text) => {
    if (!editor.value || !text) {
        return;
    }
    focusForInsert()
        .command(({ tr }) => {
            tr.insertText(text);
            return true;
        })
        .run();
    caretPlaced = true;
};

// Insert a snippet or signature (HTML) at the caret
const insertHtml = (html) => {
    if (!editor.value || !html) {
        return;
    }
    focusForInsert().insertContent(html).run();
    caretPlaced = true;
};

defineExpose({ insertText, insertHtml });

// Link editing
const showLinkInput = ref(false);
const linkUrl = ref("");
const linkInput = ref(null);

const openLinkInput = () => {
    linkUrl.value = editor.value.getAttributes("link").href || "";
    showLinkInput.value = true;
    setTimeout(() => linkInput.value?.focus(), 0);
};

const closeLinkInput = () => {
    showLinkInput.value = false;
    editor.value.commands.focus();
};

// "netsendo.com" becomes https://netsendo.com and "jan@firma.pl" a mailto:
// link; addresses with a scheme, anchors, paths and placeholders such as
// [[unsubscribe]] or {unwrap_link} stay as they are
const normalizeHref = (value) => {
    const href = value.trim();
    if (!href || /^[a-z][a-z0-9+.-]*:/i.test(href) || /^[#/[{]/.test(href)) {
        return href;
    }
    if (/^[^\s@/]+@[^\s@/]+\.[^\s@/]+$/.test(href)) {
        return "mailto:" + href;
    }
    return "https://" + href;
};

const applyLink = () => {
    const href = normalizeHref(linkUrl.value);
    // javascript: and other schemes a link may not use; the field stays open
    if (href && !isAllowedUri(href)) {
        return;
    }
    const chain = editor.value.chain().focus().extendMarkRange("link");
    if (!href) {
        chain.unsetLink().run();
    } else if (
        editor.value.state.selection.empty &&
        !editor.value.isActive("link")
    ) {
        chain
            .insertContent({
                type: "text",
                text: href.replace(/^mailto:/, ""),
                marks: [{ type: "link", attrs: { href } }],
            })
            .run();
    } else {
        chain.setLink({ href }).run();
    }
    showLinkInput.value = false;
};

const removeLink = () => {
    editor.value.chain().focus().extendMarkRange("link").unsetLink().run();
    showLinkInput.value = false;
};

const buttonClass = (active = false) => [
    "rounded p-1 disabled:opacity-40",
    active
        ? "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-200"
        : "text-slate-500 hover:bg-slate-200 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white",
];
</script>

<template>
    <div
        v-if="editor"
        class="text-block-editor overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
    >
        <div
            class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800"
        >
            <button
                type="button"
                :class="buttonClass(editor.isActive('bold'))"
                :title="t('template_builder.text_editor.bold')"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 5h6a3.5 3.5 0 010 7H7zm0 7h7a3.5 3.5 0 010 7H7z" />
                </svg>
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('italic'))"
                :title="t('template_builder.text_editor.italic')"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 5h8M6 19h8M14 5l-4 14" />
                </svg>
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('underline'))"
                :title="t('template_builder.text_editor.underline')"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v7a5 5 0 0010 0V4M5 20h14" />
                </svg>
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('link') || showLinkInput)"
                :title="t('template_builder.text_editor.link')"
                @click="showLinkInput ? closeLinkInput() : openLinkInput()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            </button>

            <span class="mx-0.5 h-5 w-px bg-slate-300 dark:bg-slate-600"></span>

            <button
                type="button"
                :class="buttonClass(editor.isActive('bulletList'))"
                :title="t('template_builder.text_editor.bullet_list')"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="4.5" cy="6" r="1.25" fill="currentColor" stroke="none" />
                    <circle cx="4.5" cy="12" r="1.25" fill="currentColor" stroke="none" />
                    <circle cx="4.5" cy="18" r="1.25" fill="currentColor" stroke="none" />
                    <path stroke-linecap="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11" />
                </svg>
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('orderedList'))"
                :title="t('template_builder.text_editor.ordered_list')"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.5 4.5h1v4M3.5 8.5h2M3.5 14.5a1 1 0 012 0c0 .8-2 1.5-2 3h2" />
                    <path stroke-linecap="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11" />
                </svg>
            </button>
            <button
                type="button"
                :class="buttonClass()"
                :title="t('template_builder.text_editor.line_break')"
                @click="editor.chain().focus().setHardBreak().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 5v7a3 3 0 01-3 3H6m0 0l4-4m-4 4l4 4" />
                </svg>
            </button>

            <span class="mx-0.5 h-5 w-px bg-slate-300 dark:bg-slate-600"></span>

            <button
                type="button"
                :class="buttonClass()"
                :disabled="!editor.can().undo()"
                :title="t('template_builder.text_editor.undo')"
                @click="editor.chain().focus().undo().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-3" />
                </svg>
            </button>
            <button
                type="button"
                :class="buttonClass()"
                :disabled="!editor.can().redo()"
                :title="t('template_builder.text_editor.redo')"
                @click="editor.chain().focus().redo().run()"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 14l5-5-5-5M20 9H10a6 6 0 000 12h3" />
                </svg>
            </button>
        </div>

        <div
            v-if="showLinkInput"
            class="flex items-center gap-1 border-b border-slate-200 bg-white p-1.5 dark:border-slate-700 dark:bg-slate-900"
        >
            <input
                ref="linkInput"
                v-model="linkUrl"
                type="text"
                class="min-w-0 flex-1 rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                :placeholder="t('template_builder.text_editor.link_placeholder')"
                @keydown.enter.prevent="applyLink"
                @keydown.esc.prevent.stop="closeLinkInput"
            />
            <button
                type="button"
                class="rounded bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                @click="applyLink"
            >
                {{ t("template_builder.text_editor.link_apply") }}
            </button>
            <button
                v-if="editor.isActive('link')"
                type="button"
                class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                @click="removeLink"
            >
                {{ t("template_builder.text_editor.link_remove") }}
            </button>
        </div>

        <!-- White like the email itself, so the block's own colours stay readable -->
        <div class="relative bg-white">
            <div
                v-if="editor.isEmpty && placeholder"
                class="pointer-events-none absolute left-3 top-2 text-sm text-slate-400"
            >
                {{ placeholder }}
            </div>
            <EditorContent :editor="editor" />
        </div>
    </div>
</template>

<style scoped>
.text-block-editor :deep(.text-block-editor-content) {
    min-height: 9rem;
    max-height: 24rem;
    overflow-y: auto;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.6;
    color: #1e293b;
    outline: none;
    overflow-wrap: anywhere;
}

.text-block-editor :deep(.text-block-editor-content > * + *) {
    margin-top: 0.75em;
}

.text-block-editor :deep(.text-block-editor-content h1) {
    font-size: 1.5em;
    font-weight: 700;
    line-height: 1.25;
}

.text-block-editor :deep(.text-block-editor-content h2) {
    font-size: 1.3em;
    font-weight: 700;
    line-height: 1.3;
}

.text-block-editor :deep(.text-block-editor-content h3),
.text-block-editor :deep(.text-block-editor-content h4),
.text-block-editor :deep(.text-block-editor-content h5),
.text-block-editor :deep(.text-block-editor-content h6) {
    font-size: 1.1em;
    font-weight: 700;
}

.text-block-editor :deep(.text-block-editor-content ul) {
    list-style: disc;
    padding-left: 1.5em;
}

.text-block-editor :deep(.text-block-editor-content ol) {
    list-style: decimal;
    padding-left: 1.5em;
}

.text-block-editor :deep(.text-block-editor-content li > p) {
    margin: 0;
}

.text-block-editor :deep(.text-block-editor-content a) {
    color: #4f46e5;
    text-decoration: underline;
}

.text-block-editor :deep(.text-block-editor-content blockquote) {
    border-left: 3px solid #cbd5e1;
    padding-left: 0.75em;
}

.text-block-editor :deep(.text-block-editor-content hr) {
    border-top: 1px solid #cbd5e1;
}

.text-block-editor :deep(.text-block-editor-content img) {
    display: inline;
    max-width: 100%;
    height: auto;
}

.text-block-editor :deep(.text-block-editor-content table) {
    border-collapse: collapse;
}

.text-block-editor :deep(.text-block-editor-content td),
.text-block-editor :deep(.text-block-editor-content th) {
    vertical-align: top;
}
</style>
