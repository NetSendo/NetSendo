<script setup>
import { useEditor, EditorContent, NodeViewWrapper, VueNodeViewRenderer } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import BulletList from '@tiptap/extension-bullet-list'
import OrderedList from '@tiptap/extension-ordered-list'
import ListItem from '@tiptap/extension-list-item'
import { defineComponent, ref, computed, onMounted, onBeforeUnmount, h, watch, nextTick } from 'vue'

// Resizable Image NodeView Component
const ResizableImageView = defineComponent({
    name: 'ResizableImageView',
    props: {
        node: { type: Object, required: true },
        updateAttributes: { type: Function, required: true },
        selected: { type: Boolean, default: false },
    },
    setup(props) {
        const containerRef = ref(null)
        const isResizing = ref(false)
        const startX = ref(0)
        const startWidth = ref(0)
        const currentWidthPercent = ref(parseInt(props.node.attrs['data-width']) || 100)

        // Computed style for the image - basic styling only (float/align handled by wrapper)
        const imageStyle = computed(() => {
            const margin = props.node.attrs['data-margin'] || '10'
            const borderRadius = props.node.attrs['data-border-radius'] || '0'

            // Image fills 100% of wrapper - wrapper controls actual size
            let styleStr = `width: 100%; max-width: 100%; height: auto; display: block;`

            if (parseInt(margin) > 0) {
                styleStr += ` margin-bottom: ${margin}px;`
            }

            if (parseInt(borderRadius) > 0) {
                styleStr += ` border-radius: ${borderRadius}px;`
            }

            return styleStr
        })

        // Handle resize start
        const onResizeStart = (e) => {
            e.preventDefault()
            e.stopPropagation()
            isResizing.value = true
            startX.value = e.clientX

            // Get the container width as reference (containerRef is a component, so use $el)
            const el = containerRef.value?.$el || containerRef.value
            const container = el?.closest?.('.ProseMirror')
            if (container) {
                startWidth.value = container.offsetWidth * (currentWidthPercent.value / 100)
            }

            document.addEventListener('mousemove', onResizeMove)
            document.addEventListener('mouseup', onResizeEnd)
        }

        // Handle resize move
        const onResizeMove = (e) => {
            if (!isResizing.value) return

            // containerRef is a component, so use $el to get the DOM element
            const el = containerRef.value?.$el || containerRef.value
            const container = el?.closest?.('.ProseMirror')
            if (!container) return

            const containerWidth = container.offsetWidth
            const deltaX = e.clientX - startX.value
            const newWidth = startWidth.value + deltaX
            const newPercent = Math.round(Math.min(100, Math.max(10, (newWidth / containerWidth) * 100)))

            currentWidthPercent.value = newPercent
        }

        // Handle resize end
        const onResizeEnd = () => {
            if (!isResizing.value) return
            isResizing.value = false

            // Update the node attributes
            props.updateAttributes({
                'data-width': String(currentWidthPercent.value)
            })

            document.removeEventListener('mousemove', onResizeMove)
            document.removeEventListener('mouseup', onResizeEnd)
        }

        // Cleanup on unmount
        onBeforeUnmount(() => {
            document.removeEventListener('mousemove', onResizeMove)
            document.removeEventListener('mouseup', onResizeEnd)
        })

        // Update local state when node changes
        const updateFromNode = () => {
            currentWidthPercent.value = parseInt(props.node.attrs['data-width']) || 100
        }

        onMounted(updateFromNode)

        // Watch for external changes to node attributes (e.g., from modal slider)
        watch(() => props.node.attrs['data-width'], (newWidth) => {
            if (!isResizing.value) {
                currentWidthPercent.value = parseInt(newWidth) || 100
            }
        })

        // Computed style for the wrapper - handles float and alignment
        const wrapperStyle = computed(() => {
            const float = props.node.attrs['data-float'] || 'none'
            const align = props.node.attrs['data-align'] || 'center'

            let style = {
                width: `${currentWidthPercent.value}%`,
                maxWidth: '100%'
            }

            if (float === 'left') {
                style.float = 'left'
                style.marginRight = '10px'
            } else if (float === 'right') {
                style.float = 'right'
                style.marginLeft = '10px'
            } else {
                // No float - use block display with margin for alignment
                style.display = 'block'
                if (align === 'center') {
                    style.marginLeft = 'auto'
                    style.marginRight = 'auto'
                } else if (align === 'right') {
                    style.marginLeft = 'auto'
                } else if (align === 'left') {
                    style.marginRight = 'auto'
                }
            }

            return style
        })

        return () => h(NodeViewWrapper, {
            class: 'resizable-image-wrapper',
            ref: containerRef,
            style: wrapperStyle.value
        }, [
            h('div', {
                class: ['resizable-image-container', { 'is-selected': props.selected, 'is-resizing': isResizing.value }],
                style: { display: 'block', position: 'relative', width: '100%' }
            }, [
                h('img', {
                    src: props.node.attrs.src,
                    alt: props.node.attrs.alt || '',
                    title: props.node.attrs.title || '',
                    style: imageStyle.value,
                    class: 'resizable-image',
                    draggable: false
                }),
                // Resize handles - only show when selected
                props.selected ? h('div', {
                    class: 'resize-handle resize-handle-se',
                    onMousedown: onResizeStart
                }) : null,
                props.selected ? h('div', {
                    class: 'resize-handle resize-handle-sw',
                    onMousedown: onResizeStart
                }) : null,
                // Width indicator during resize
                isResizing.value ? h('div', {
                    class: 'resize-width-indicator'
                }, `${currentWidthPercent.value}%`) : null
            ])
        ])
    }
})

// Custom Image extension that preserves style properties via data attributes and generates inline styles
const CustomImage = Image.extend({
    addNodeView() {
        return VueNodeViewRenderer(ResizableImageView)
    },
    addAttributes() {
        return {
            ...this.parent?.(),
            // Store style as data attributes for reliable parsing
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
                    if (style.includes('margin-left: auto') && !style.includes('margin-right: auto')) return 'right'
                    if (style.includes('margin-right: auto') && !style.includes('margin-left: auto')) return 'left'
                    return 'center'
                },
                renderHTML: attributes => {
                    return { 'data-align': attributes['data-align'] || 'center' }
                },
            },
            'data-float': {
                default: 'none',
                parseHTML: element => {
                    const dataFloat = element.getAttribute('data-float')
                    if (dataFloat) return dataFloat
                    const style = element.getAttribute('style') || ''
                    if (style.includes('float: left')) return 'left'
                    if (style.includes('float: right')) return 'right'
                    return 'none'
                },
                renderHTML: attributes => {
                    return { 'data-float': attributes['data-float'] || 'none' }
                },
            },
            'data-margin': {
                default: '10',
                parseHTML: element => {
                    const dataMargin = element.getAttribute('data-margin')
                    if (dataMargin) return dataMargin
                    const style = element.getAttribute('style') || ''
                    const match = style.match(/margin:\s*(\d+)px/)
                    return match ? match[1] : '10'
                },
                renderHTML: attributes => {
                    return { 'data-margin': attributes['data-margin'] || '10' }
                },
            },
            'data-border-radius': {
                default: '0',
                parseHTML: element => {
                    const dataBr = element.getAttribute('data-border-radius')
                    if (dataBr) return dataBr
                    const style = element.getAttribute('style') || ''
                    const match = style.match(/border-radius:\s*(\d+)px/)
                    return match ? match[1] : '0'
                },
                renderHTML: attributes => {
                    return { 'data-border-radius': attributes['data-border-radius'] || '0' }
                },
            },
            style: {
                default: null,
                parseHTML: element => element.getAttribute('style'),
                renderHTML: attributes => {
                    // Generate style from data attributes
                    const width = attributes['data-width'] || '100'
                    const align = attributes['data-align'] || 'center'
                    const float = attributes['data-float'] || 'none'
                    const margin = attributes['data-margin'] || '10'
                    const borderRadius = attributes['data-border-radius'] || '0'

                    let styleStr = `width: ${width}%; max-width: 100%; height: auto;`
                    styleStr += ` margin: ${margin}px;`

                    if (float === 'left') {
                        styleStr += ' float: left;'
                    } else if (float === 'right') {
                        styleStr += ' float: right;'
                    } else {
                        styleStr += ' display: block;'
                        if (align === 'center') {
                            styleStr += ' margin-left: auto; margin-right: auto;'
                        } else if (align === 'right') {
                            styleStr += ' margin-left: auto;'
                        } else if (align === 'left') {
                            styleStr += ' margin-right: auto;'
                        }
                    }

                    if (parseInt(borderRadius) > 0) {
                        styleStr += ` border-radius: ${borderRadius}px;`
                    }

                    return { style: styleStr }
                },
            },
        }
    },
})
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import { FontFamily } from '@tiptap/extension-font-family'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import { Highlight } from '@tiptap/extension-highlight'
import { FontSize } from 'tiptap-extension-font-size'
import { Mark, mergeAttributes } from '@tiptap/core'
import {
    Table,
    TableCell,
    TableHeader,
    TableRow,
} from '@tiptap/extension-table'
import { useI18n } from 'vue-i18n'

// Custom TextTransform Extension for text-transform CSS property
const TextTransform = Mark.create({
    name: 'textTransform',

    addOptions() {
        return {
            types: ['textStyle'],
        }
    },

    addAttributes() {
        return {
            textTransform: {
                default: null,
                parseHTML: element => element.style.textTransform || null,
                renderHTML: attributes => {
                    if (!attributes.textTransform) {
                        return {}
                    }
                    return {
                        style: `text-transform: ${attributes.textTransform}`,
                    }
                },
            },
        }
    },

    parseHTML() {
        return [
            {
                tag: 'span',
                getAttrs: element => {
                    const hasTextTransform = element.style.textTransform
                    if (!hasTextTransform) {
                        return false
                    }
                    return { textTransform: element.style.textTransform }
                },
            },
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0]
    },

    addCommands() {
        return {
            setTextTransform: textTransform => ({ chain }) => {
                return chain().setMark(this.name, { textTransform }).run()
            },
            unsetTextTransform: () => ({ chain }) => {
                return chain().unsetMark(this.name).run()
            },
            toggleTextTransform: textTransform => ({ chain, editor }) => {
                const currentTransform = editor.getAttributes(this.name)?.textTransform
                if (currentTransform === textTransform) {
                    return chain().unsetMark(this.name).run()
                }
                return chain().setMark(this.name, { textTransform }).run()
            },
        }
    },
})

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
        default: '300px',
    },
    externalPreviewContent: {
        type: String,
        default: null,
    },
})

const emit = defineEmits(['update:modelValue'])

// Editor modes: visual, source, preview
const editorMode = ref('visual')

// sourceCode is the SINGLE SOURCE OF TRUTH for content
const sourceCode = ref(props.modelValue || '')

const showLinkModal = ref(false)
const linkUrl = ref('')
const linkText = ref('')
const linkTitle = ref('') // For accessibility/tooltip
const linkTarget = ref('_blank') // _self or _blank
const isEditingLink = ref(false) // Track if editing existing link
const showImageModal = ref(false)
const imageUrl = ref('')
const isEditingImage = ref(false) // Track if editing existing image
const editingImageElement = ref(null) // Reference to image being edited

// Enhanced image settings
const imageAlignment = ref('center') // left, center, right
const imageWidth = ref('100') // percentage
const imageLink = ref('')
const imagePreviewLoaded = ref(false)
const imagePreviewError = ref(false)

// Image upload state
const isUploadingImage = ref(false)
const imageUploadError = ref('')

// Advanced image formatting
const imageFloat = ref('none') // none, left, right (for text wrapping)
const imageMargin = ref('10') // margin in pixels
const imageBorderRadius = ref('0') // border-radius in pixels

// Media browser state
const showMediaBrowser = ref(false)
const mediaLibrary = ref([])
const isLoadingMedia = ref(false)

// Content width control (default 600px for email compatibility)
const contentWidth = ref(600)

// Content alignment (center or left)
const contentAlign = ref('center')

// Preview device mode: desktop or mobile
const previewDevice = ref('desktop')

// Emoji picker state
const showEmojiPicker = ref(false)
const activeEmojiCategory = ref('faces')
const emojiPickerRef = ref(null)
const emojiButtonRef = ref(null)

// Font/Color picker states
const showFontPicker = ref(false)
const showSizePicker = ref(false)
const showColorPicker = ref(false)
const showHighlightPicker = ref(false)
const showTextTransformPicker = ref(false)

// Text editing modal for full HTML documents
const showTextEditModal = ref(false)
const editingElement = ref(null)
const editingText = ref('')
const editingElementTag = ref('')
const textEditCursorPos = ref(0)

// Image editing from iframe (preview mode for full HTML documents)
const editingImageFromIframe = ref(null) // Stores the data-img-edit-id when editing from iframe

// Common variables for insertion
const commonVariables = [
    { code: '[[first_name]]', label: 'placeholders.first_name' },
    { code: '[[last_name]]', label: 'placeholders.last_name' },
    { code: '[[email]]', label: 'placeholders.email' },
    { code: '[[phone]]', label: 'placeholders.phone' },
    { code: '[[company]]', label: 'placeholders.company' },
    { code: '[[unsubscribe_link]]', label: 'placeholders.unsubscribe_link' },
]

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
const fontSizeOptions = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '36px', '48px']

// Color palette for text and highlight
const colorPalette = [
    '#000000', '#434343', '#666666', '#999999', '#CCCCCC', '#FFFFFF',
    '#FF0000', '#FF6600', '#FFCC00', '#00FF00', '#00CCFF', '#0066FF',
    '#9900FF', '#FF00FF', '#FF6699', '#996633', '#003366', '#339966',
]

// Emojis organized by categories
const emojiCategories = {
    faces: {
        icon: '😀',
        emojis: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '😊', '😇', '🥰', '😍', '😘', '😗', '😋', '😛', '😜', '🤪', '😎', '🤩', '🥳', '😏', '😌', '😴']
    },
    symbols: {
        icon: '🎉',
        emojis: ['🎉', '🎊', '🎁', '🎀', '🎈', '🎗️', '✨', '🌟', '⭐', '💫', '🔥', '💥', '💢', '💯', '🏆', '🥇', '🥈', '🥉', '🎯', '🚀', '⚡', '💎', '🔔', '📣', '📢']
    },
    gestures: {
        icon: '👍',
        emojis: ['👍', '👎', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✌️', '🤞', '🤟', '🤘', '👌', '👈', '👉', '👆', '👇', '✋', '🖐️', '💪']
    },
    business: {
        icon: '💼',
        emojis: ['💼', '📧', '📬', '💌', '📝', '📊', '📈', '📉', '💰', '💵', '💳', '🏦', '🏢', '📅', '⏰', '🔒', '🔓', '📱', '💻', '🖥️']
    },
    hearts: {
        icon: '❤️',
        emojis: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💝', '💖', '💗', '💓', '💕', '💞', '💘', '💔', '❣️', '💟', '♥️']
    },
    nature: {
        icon: '🌟',
        emojis: ['☀️', '🌙', '🌈', '⛅', '🌤️', '🌧️', '❄️', '🌸', '🌺', '🌻', '🌷', '🌹', '🌲', '🌴', '🍀', '🍁', '🍂', '🌊', '💧', '🌍']
    }
}

// Flat list for backward compatibility
const commonEmojis = Object.values(emojiCategories).flatMap(cat => cat.emojis.slice(0, 5))

// Computed style for emoji picker positioning (avoids window access in template)
const emojiPickerStyle = computed(() => {
    if (!emojiButtonRef.value) {
        return { top: '100px', left: '100px' }
    }
    const rect = emojiButtonRef.value.getBoundingClientRect()
    const viewportWidth = typeof window !== 'undefined' ? window.innerWidth : 1024
    return {
        top: (rect.bottom + 8) + 'px',
        left: Math.min(rect.left, viewportWidth - 336) + 'px'
    }
})

// Check if content is a full HTML document (email template with doctype, etc.)
// NOTE: We do NOT include <table> here - tables are valid WYSIWYG content
const isFullHtmlDocument = computed(() => {
    const content = sourceCode.value?.trim().toLowerCase() || ''
    return content.startsWith('<!doctype') ||
           content.startsWith('<html') ||
           content.includes('<body')
})

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3, 4],
            },
            // Disable lists from StarterKit - we add them separately for sinkListItem/liftListItem support
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
                class: 'text-indigo-600 hover:text-indigo-800 underline cursor-pointer',
            },
        }),
        CustomImage.configure({
            inline: true,
            allowBase64: true,
            HTMLAttributes: {
                class: 'cursor-pointer hover:outline hover:outline-2 hover:outline-indigo-500',
            },
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
            class: 'prose prose-sm sm:prose lg:prose-lg xl:prose-2xl focus:outline-none dark:prose-invert min-h-[150px] text-slate-900 dark:text-white p-4',
        },
        // Strip inline color styles from pasted content to prevent black text on dark background
        transformPastedHTML(html) {
            // Create a temporary DOM element to parse and clean the HTML
            const doc = new DOMParser().parseFromString(html, 'text/html')

            // Find all elements with inline styles
            doc.querySelectorAll('[style]').forEach(el => {
                const style = el.getAttribute('style')
                if (style) {
                    // Remove color property from inline styles (keeps other styles like font-size, etc.)
                    const cleanedStyle = style
                        .split(';')
                        .filter(prop => {
                            const propName = prop.split(':')[0]?.trim().toLowerCase()
                            // Remove color and background-color to ensure readability
                            return propName !== 'color' && propName !== 'background-color' && propName !== 'background'
                        })
                        .join(';')
                        .trim()

                    if (cleanedStyle) {
                        el.setAttribute('style', cleanedStyle)
                    } else {
                        el.removeAttribute('style')
                    }
                }
            })

            // Also remove any <font> tags color attribute (old Word format)
            doc.querySelectorAll('font[color]').forEach(el => {
                el.removeAttribute('color')
            })

            return doc.body.innerHTML
        },
        handleClick: (view, pos, event) => {
            // Check if clicked on an image (including through NodeView wrapper)
            const target = event.target
            const imgElement = target.tagName === 'IMG' ? target : target.closest('.resizable-image-container')?.querySelector('img')

            // For images in NodeView, single click is handled by NodeView for selection
            // Check if clicked on a link
            if (target.tagName === 'A' || target.closest('a')) {
                event.preventDefault()
                const linkElement = target.tagName === 'A' ? target : target.closest('a')
                openLinkEditModal(linkElement)
                return true
            }
            return false
        },
        handleDoubleClick: (view, pos, event) => {
            // Check if double-clicked on an image (opens edit modal)
            const target = event.target
            const imgElement = target.tagName === 'IMG' ? target : target.closest('.resizable-image-container')?.querySelector('img')

            if (imgElement) {
                event.preventDefault()
                openImageEditModal(imgElement)
                return true
            }
            return false
        },
    },
    editable: props.editable,
    onUpdate: () => {
        // Only emit from Tiptap if NOT a full HTML document
        if (!isFullHtmlDocument.value && editorMode.value === 'visual') {
            const html = editor.value?.getHTML() || ''
            sourceCode.value = html
            emit('update:modelValue', html)
        }
    },
})

// Watch for external content changes (from parent component)
watch(() => props.modelValue, (value) => {
    // Always update sourceCode - it's the source of truth
    if (value !== sourceCode.value) {
        sourceCode.value = value || ''

        // If it's now a full HTML document, switch to preview mode
        nextTick(() => {
            const content = (value || '').trim().toLowerCase()
            // NOTE: We do NOT check for <table> - tables are valid WYSIWYG content
            const isFullHtml = content.startsWith('<!doctype') ||
                               content.startsWith('<html') ||
                               content.includes('<body')
            if (isFullHtml && editorMode.value === 'visual') {
                editorMode.value = 'preview'
            }
        })
    }

    // Only update Tiptap if NOT full HTML document and in visual mode
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

    // If full HTML document, start in preview mode (not source)
    if (isFullHtmlDocument.value) {
        editorMode.value = 'preview'
    }
})

// Switch between modes - sourceCode is ALWAYS the source of truth
const switchMode = (mode) => {
    const previousMode = editorMode.value

    // When leaving visual mode (and content is NOT full HTML), save Tiptap to sourceCode
    if (previousMode === 'visual' && !isFullHtmlDocument.value) {
        const tiptapHtml = editor.value?.getHTML()
        if (tiptapHtml) {
            sourceCode.value = tiptapHtml
        }
    }

    // When entering visual mode (and content is NOT full HTML), load sourceCode into Tiptap
    if (mode === 'visual' && !isFullHtmlDocument.value) {
        nextTick(() => {
            editor.value?.commands.setContent(sourceCode.value || '', false)
        })
    }

    // Always emit the current sourceCode
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
    const attrs = editor.value?.getAttributes('link') || {}
    linkUrl.value = attrs.href || ''
    linkTitle.value = attrs.title || ''
    linkTarget.value = attrs.target || '_blank'
    isEditingLink.value = !!attrs.href

    // Get the selected text or existing link text
    const { from, to } = editor.value?.state.selection || {}
    const selectedText = editor.value?.state.doc.textBetween(from, to, '') || ''
    linkText.value = selectedText

    showLinkModal.value = true
}

// Open link edit modal when clicking on existing link
const openLinkEditModal = (linkElement) => {
    linkUrl.value = linkElement.getAttribute('href') || ''
    linkText.value = linkElement.textContent || ''
    linkTitle.value = linkElement.getAttribute('title') || ''
    linkTarget.value = linkElement.getAttribute('target') || '_blank'
    isEditingLink.value = true

    // Select the link in editor
    editor.value?.chain().focus().extendMarkRange('link').run()

    showLinkModal.value = true
}

const setLink = () => {
    if (linkUrl.value) {
        const { from, to } = editor.value?.state.selection || {}
        const currentText = editor.value?.state.doc.textBetween(from, to, '') || ''

        // Build link attributes
        const linkAttrs = {
            href: linkUrl.value,
            target: linkTarget.value || '_blank',
        }
        if (linkTitle.value) {
            linkAttrs.title = linkTitle.value
        }

        // If text has changed, replace the selection with new text and link
        if (linkText.value && linkText.value !== currentText) {
            const targetAttr = linkTarget.value ? ` target="${linkTarget.value}"` : ''
            const titleAttr = linkTitle.value ? ` title="${linkTitle.value}"` : ''
            editor.value?.chain()
                .focus()
                .deleteSelection()
                .insertContent(`<a href="${linkUrl.value}"${targetAttr}${titleAttr}>${linkText.value}</a>`)
                .run()
        } else {
            // Just update the link attributes on existing text
            editor.value?.chain().focus().extendMarkRange('link').setLink(linkAttrs).run()
        }
    } else {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    }
    closeLinkModal()
}

const closeLinkModal = () => {
    showLinkModal.value = false
    linkUrl.value = ''
    linkText.value = ''
    linkTitle.value = ''
    linkTarget.value = '_blank'
    isEditingLink.value = false
}

// Image handling
const openImageModal = () => {
    imageUrl.value = ''
    imageAlignment.value = 'center'
    imageWidth.value = '100'
    imageLink.value = ''
    imageFloat.value = 'none'
    imageMargin.value = '10'
    imageBorderRadius.value = '0'
    imagePreviewLoaded.value = false
    imagePreviewError.value = false
    isUploadingImage.value = false
    imageUploadError.value = ''
    isEditingImage.value = false
    editingImageElement.value = null
    showImageModal.value = true
}

// Open image edit modal when clicking on existing image
const openImageEditModal = (imgElement) => {
    isEditingImage.value = true
    editingImageElement.value = imgElement

    // Extract current image properties from the image element
    imageUrl.value = imgElement.getAttribute('src') || ''
    imagePreviewLoaded.value = true
    imagePreviewError.value = false
    isUploadingImage.value = false
    imageUploadError.value = ''

    // Try to get attributes from the currently selected node in Tiptap (works with NodeView)
    const editorInstance = editor.value
    let nodeAttrs = null

    if (editorInstance) {
        const { from } = editorInstance.state.selection
        const resolvedPos = editorInstance.state.doc.resolve(from)
        const node = resolvedPos.nodeAfter || resolvedPos.nodeBefore

        if (node && node.type.name === 'image' && node.attrs.src === imgElement.getAttribute('src')) {
            nodeAttrs = node.attrs
        } else {
            // Search for the image node with matching src
            editorInstance.state.doc.descendants((n, pos) => {
                if (n.type.name === 'image' && n.attrs.src === imgElement.getAttribute('src')) {
                    nodeAttrs = n.attrs
                    return false // stop searching
                }
            })
        }
    }

    // Parse style attribute as fallback
    const style = imgElement.getAttribute('style') || ''

    // Read from node attrs first (when using NodeView), then data attributes, then style parsing
    // Extract width
    if (nodeAttrs?.['data-width']) {
        imageWidth.value = nodeAttrs['data-width']
    } else {
        const dataWidth = imgElement.getAttribute('data-width')
        if (dataWidth) {
            imageWidth.value = dataWidth
        } else {
            const widthMatch = style.match(/width:\s*(\d+)%/)
            imageWidth.value = widthMatch ? widthMatch[1] : '100'
        }
    }

    // Extract float
    if (nodeAttrs?.['data-float']) {
        imageFloat.value = nodeAttrs['data-float']
    } else {
        const dataFloat = imgElement.getAttribute('data-float')
        if (dataFloat) {
            imageFloat.value = dataFloat
        } else if (style.includes('float: left')) {
            imageFloat.value = 'left'
        } else if (style.includes('float: right')) {
            imageFloat.value = 'right'
        } else {
            imageFloat.value = 'none'
        }
    }

    // Extract alignment
    if (nodeAttrs?.['data-align']) {
        imageAlignment.value = nodeAttrs['data-align']
    } else {
        const dataAlign = imgElement.getAttribute('data-align')
        if (dataAlign) {
            imageAlignment.value = dataAlign
        } else if (style.includes('margin-left: auto') && style.includes('margin-right: auto')) {
            imageAlignment.value = 'center'
        } else if (style.includes('margin-left: auto')) {
            imageAlignment.value = 'right'
        } else if (style.includes('margin-right: auto')) {
            imageAlignment.value = 'left'
        } else {
            imageAlignment.value = 'center'
        }
    }

    // Extract margin
    if (nodeAttrs?.['data-margin']) {
        imageMargin.value = nodeAttrs['data-margin']
    } else {
        const dataMargin = imgElement.getAttribute('data-margin')
        if (dataMargin) {
            imageMargin.value = dataMargin
        } else {
            const marginMatch = style.match(/margin:\s*(\d+)px/)
            imageMargin.value = marginMatch ? marginMatch[1] : '10'
        }
    }

    // Extract border-radius
    if (nodeAttrs?.['data-border-radius']) {
        imageBorderRadius.value = nodeAttrs['data-border-radius']
    } else {
        const dataBorderRadius = imgElement.getAttribute('data-border-radius')
        if (dataBorderRadius) {
            imageBorderRadius.value = dataBorderRadius
        } else {
            const borderRadiusMatch = style.match(/border-radius:\s*(\d+)px/)
            imageBorderRadius.value = borderRadiusMatch ? borderRadiusMatch[1] : '0'
        }
    }

    // Check if image is wrapped in a link
    const parentLink = imgElement.closest('a')
    imageLink.value = parentLink ? parentLink.getAttribute('href') || '' : ''

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

// Handle image file upload
const handleImageUpload = async (event) => {
    const file = event.target.files[0]
    if (!file) return

    imageUploadError.value = ''

    // Client-side validation
    const maxSize = 5 * 1024 * 1024 // 5MB
    if (file.size > maxSize) {
        imageUploadError.value = t('editor.image_too_large')
        event.target.value = ''
        return
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
    if (!allowedTypes.includes(file.type)) {
        imageUploadError.value = t('editor.image_invalid_format')
        event.target.value = ''
        return
    }

    isUploadingImage.value = true
    const formData = new FormData()
    formData.append('image', file)

    try {
        const response = await axios.post(
            route('api.templates.upload-image'),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )

        if (response.data.success) {
            imageUrl.value = response.data.url
            imagePreviewLoaded.value = true
            imagePreviewError.value = false
        } else {
            imageUploadError.value = response.data.message || t('editor.image_upload_error')
        }
    } catch (error) {
        console.error('Image upload failed:', error)
        if (error.response?.status === 422) {
            const errors = error.response.data.errors
            imageUploadError.value = errors?.image?.[0] || t('editor.image_upload_error')
        } else {
            imageUploadError.value = t('editor.image_upload_error')
        }
    } finally {
        isUploadingImage.value = false
        event.target.value = ''
    }
}

// Load media from library
// Load media from library
const openMediaBrowser = async (arg = null) => {
    // Handle potential Event object from @click
    const filterType = (typeof arg === 'string') ? arg : null

    showMediaBrowser.value = true
    showLogoBrowser.value = false
    isLoadingMedia.value = true
    try {
        const response = await axios.get(route('media.search'))
        let media = response.data.media || []
        // Filter by type if specified
        if (filterType) {
            media = media.filter(m => m.type === filterType)
        }
        mediaLibrary.value = media
    } catch (error) {
        console.error('Failed to load media:', error)
        mediaLibrary.value = []
    } finally {
        isLoadingMedia.value = false
    }
}

// Load logos from library
const showLogoBrowser = ref(false)
const openLogoBrowser = async () => {
    showLogoBrowser.value = true
    showMediaBrowser.value = false
    isLoadingMedia.value = true
    try {
        const response = await axios.get(route('media.search'))
        let media = response.data.media || []
        // Filter only logos
        media = media.filter(m => m.type === 'logo')
        mediaLibrary.value = media
    } catch (error) {
        console.error('Failed to load logos:', error)
        mediaLibrary.value = []
    } finally {
        isLoadingMedia.value = false
    }
}

// Select image from media browser
const selectFromMediaBrowser = (media) => {
    imageUrl.value = media.url
    imagePreviewLoaded.value = true
    imagePreviewError.value = false
    showMediaBrowser.value = false
    showLogoBrowser.value = false
}
const insertImage = () => {
    if (imageUrl.value) {
        // Build data attributes that will be parsed by CustomImage extension
        const dataAttrs = `data-width="${imageWidth.value}" data-align="${imageAlignment.value}" data-float="${imageFloat.value}" data-margin="${imageMargin.value}" data-border-radius="${imageBorderRadius.value}"`

        // Build inline style for immediate visual feedback and email compatibility
        let styleStr = `width: ${imageWidth.value}%; max-width: 100%; height: auto;`
        styleStr += ` margin: ${imageMargin.value}px;`

        if (imageFloat.value === 'left') {
            styleStr += ' float: left;'
        } else if (imageFloat.value === 'right') {
            styleStr += ' float: right;'
        } else {
            styleStr += ' display: block;'
            if (imageAlignment.value === 'center') {
                styleStr += ' margin-left: auto; margin-right: auto;'
            } else if (imageAlignment.value === 'right') {
                styleStr += ' margin-left: auto;'
            } else if (imageAlignment.value === 'left') {
                styleStr += ' margin-right: auto;'
            }
        }

        if (parseInt(imageBorderRadius.value) > 0) {
            styleStr += ` border-radius: ${imageBorderRadius.value}px;`
        }

        let imgHtml = `<img src="${imageUrl.value}" alt="" ${dataAttrs} style="${styleStr}" />`

        // Wrap in link if provided
        if (imageLink.value) {
            imgHtml = `<a href="${imageLink.value}" target="_blank">${imgHtml}</a>`
        }

        // Check if editing from iframe (preview mode for full HTML documents)
        if (isEditingImage.value && editingImageFromIframe.value) {
            // Send update message to iframe
            const iframe = visualEditorIframe.value
            if (iframe?.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'updateImage',
                    editId: editingImageFromIframe.value,
                    src: imageUrl.value,
                    style: styleStr,
                    link: imageLink.value || null
                }, '*')
            }
        } else if (isEditingImage.value && editingImageElement.value) {
            // For editing in Tiptap, we need to find and replace the image in the document
            // Use a more reliable approach - delete and reinsert at current position
            const editorInstance = editor.value

            // Find all images and locate the one we're editing
            const state = editorInstance?.state
            if (state) {
                let foundPos = null
                state.doc.descendants((node, pos) => {
                    if (node.type.name === 'image' && node.attrs.src === editingImageElement.value.getAttribute('src')) {
                        foundPos = pos
                        return false // stop searching
                    }
                })

                if (foundPos !== null) {
                    // Delete old image and insert new one at the same position
                    editorInstance.chain()
                        .focus()
                        .setNodeSelection(foundPos)
                        .deleteSelection()
                        .insertContent(imgHtml)
                        .run()
                } else {
                    // Fallback: just insert at current cursor
                    editorInstance.chain().focus().insertContent(imgHtml).run()
                }
            }

            // Emit update
            const updatedHtml = editor.value?.getHTML() || ''
            sourceCode.value = updatedHtml
            emit('update:modelValue', updatedHtml)
        } else {
            // Insert as new HTML
            editor.value?.chain().focus().insertContent(imgHtml).run()
        }
    }
    closeImageModal()
}

const closeImageModal = () => {
    showImageModal.value = false
    imageUrl.value = ''
    imageAlignment.value = 'center'
    imageWidth.value = '100'
    imageLink.value = ''
    imageFloat.value = 'none'
    imageMargin.value = '10'
    imageBorderRadius.value = '0'
    isEditingImage.value = false
    editingImageElement.value = null
    editingImageFromIframe.value = null
}

// Delete the currently editing image from the editor
const deleteImage = () => {
    if (!isEditingImage.value || !editingImageElement.value) {
        closeImageModal()
        return
    }

    const editorInstance = editor.value
    const state = editorInstance?.state

    if (state) {
        let foundPos = null
        state.doc.descendants((node, pos) => {
            if (node.type.name === 'image' && node.attrs.src === editingImageElement.value.getAttribute('src')) {
                foundPos = pos
                return false // stop searching
            }
        })

        if (foundPos !== null) {
            // Delete the image at the found position
            editorInstance.chain()
                .focus()
                .setNodeSelection(foundPos)
                .deleteSelection()
                .run()

            // Emit update
            const updatedHtml = editor.value?.getHTML() || ''
            sourceCode.value = updatedHtml
            emit('update:modelValue', updatedHtml)
        }
    }

    closeImageModal()
}

// Computed for preview content
const previewContent = computed(() => {
    if (props.externalPreviewContent) {
        return props.externalPreviewContent
    }
    if (editorMode.value === 'source') {
        return sourceCode.value
    }
    return editor.value?.getHTML() || props.modelValue
})

// Check if content is a full HTML document
const isFullDocument = computed(() => {
    const content = previewContent.value?.trim().toLowerCase() || ''
    return content.startsWith('<!doctype') || content.startsWith('<html')
})

// Wrap content in a basic HTML document for preview
// Also adds inline styles to lists for email client compatibility
const wrapInDocument = (content) => {
    // Process content to add inline styles to list elements for email compatibility
    let processedContent = content || ''

    // Add inline styles to unordered lists
    processedContent = processedContent.replace(
        /<ul>/gi,
        '<ul style="list-style-type: disc; padding-left: 20px; margin: 0.5em 0;">'
    )

    // Add inline styles to ordered lists
    processedContent = processedContent.replace(
        /<ol>/gi,
        '<ol style="list-style-type: decimal; padding-left: 20px; margin: 0.5em 0;">'
    )

    // Add inline styles to list items
    processedContent = processedContent.replace(
        /<li>/gi,
        '<li style="margin-bottom: 0.25em;">'
    )

    return `<!DOCTYPE html>
<html lang="${useI18n().locale.value || 'en'}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 16px; line-height: 1.5; }
        ul { list-style-type: disc; padding-left: 20px; margin: 0.5em 0; }
        ol { list-style-type: decimal; padding-left: 20px; margin: 0.5em 0; }
        li { margin-bottom: 0.25em; }
        ul ul { list-style-type: circle; }
        ul ul ul { list-style-type: square; }
        ol ol { list-style-type: lower-alpha; }
        ol ol ol { list-style-type: lower-roman; }
    </style>
</head>
<body>
${processedContent}
</body>
</html>`
}

// Generate preview content with optional mobile scaling
const getPreviewSrcdoc = computed(() => {
    // Use externalPreviewContent if available (e.g., from live preview with substituted placeholders)
    const baseContent = props.externalPreviewContent || sourceCode.value
    const content = isFullHtmlDocument.value ? baseContent : wrapInDocument(baseContent)

    if (previewDevice.value === 'mobile' && isFullHtmlDocument.value) {
        // Inject CSS to scale content for mobile preview
        const mobileScaleCSS = `<style>
            html { zoom: 0.58; -moz-transform: scale(0.58); -moz-transform-origin: 0 0; }
            body { max-width: 100% !important; overflow-x: hidden !important; }
            table { max-width: 100% !important; }
        </style>`
        // Insert CSS before closing </head> or at beginning
        if (content.toLowerCase().includes('</head>')) {
            return content.replace(/<\/head>/i, mobileScaleCSS + '</head>')
        } else {
            return mobileScaleCSS + content
        }
    }
    return content
})

// Editable iframe reference
const visualEditorIframe = ref(null)

// Generate editable version of HTML content for visual editing
const getEditableSrcdoc = computed(() => {
    const content = isFullHtmlDocument.value ? sourceCode.value : wrapInDocument(sourceCode.value)

    // Add click handlers and styling for editable elements
    // Note: script tags are split to avoid Vue parser issues
    const editableCSS = '<sty' + 'le>' +
        '[data-edit-id] { cursor: pointer !important; transition: all 0.2s ease !important; }' +
        '[data-edit-id]:hover { outline: 2px solid #6366f1 !important; outline-offset: 2px !important; background-color: rgba(99, 102, 241, 0.05) !important; }' +
        '[data-edit-id].editing-active { outline: 2px solid #10b981 !important; outline-offset: 2px !important; background-color: rgba(16, 185, 129, 0.1) !important; }' +
        // Image editing styles
        '[data-img-edit-id] { cursor: pointer !important; transition: all 0.2s ease !important; }' +
        '[data-img-edit-id]:hover { outline: 3px solid #8b5cf6 !important; outline-offset: 2px !important; }' +
        '[data-img-edit-id].editing-active { outline: 3px solid #10b981 !important; outline-offset: 2px !important; }' +
        '.netsendo-edit-hint { position: fixed; bottom: 10px; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 8px 16px; border-radius: 8px; font-size: 12px; z-index: 9999; opacity: 0.9; pointer-events: none; }' +
    '</sty' + 'le>' +
    '<scr' + 'ipt>' +
        'document.addEventListener("DOMContentLoaded", function() {' +
            'var editableSelectors = "h1, h2, h3, h4, h5, h6, p, span, a, td, th, li, strong, em, b, i, u, div";' +
            'var elementIndex = 0;' +
            'var imageIndex = 0;' +
            'var elements = document.querySelectorAll(editableSelectors);' +

            // Create hint element
            'var hint = document.createElement("div");' +
            'hint.className = "netsendo-edit-hint";' +
            'hint.textContent = "Kliknij, aby edytować tekst";' +
            'hint.style.display = "none";' +
            'document.body.appendChild(hint);' +

            // Process text elements
            'elements.forEach(function(el) {' +
                // Only make elements with actual text content editable
                'var hasDirectText = false;' +
                'for (var i = 0; i < el.childNodes.length; i++) {' +
                    'if (el.childNodes[i].nodeType === Node.TEXT_NODE && el.childNodes[i].textContent.trim()) {' +
                        'hasDirectText = true;' +
                        'break;' +
                    '}' +
                '}' +

                // Also include elements with no children but with text
                'if (hasDirectText || (el.children.length === 0 && el.textContent.trim())) {' +
                    'var dataId = "edit-" + (elementIndex++);' +
                    'el.setAttribute("data-edit-id", dataId);' +

                    // Click handler to select element for editing
                    'el.addEventListener("click", function(e) {' +
                        'e.preventDefault();' +
                        'e.stopPropagation();' +

                        // Remove active class from all elements
                        'document.querySelectorAll(".editing-active").forEach(function(active) {' +
                            'active.classList.remove("editing-active");' +
                        '});' +

                        // Add active class to clicked element
                        'this.classList.add("editing-active");' +

                        // Send message to parent
                        'window.parent.postMessage({' +
                            'type: "elementClicked",' +
                            'editId: this.getAttribute("data-edit-id"),' +
                            'text: this.innerHTML,' +
                            'tagName: this.tagName.toLowerCase()' +
                        '}, "*");' +
                    '});' +

                    // Show hint on hover
                    'el.addEventListener("mouseenter", function() {' +
                        'hint.style.display = "block";' +
                    '});' +
                    'el.addEventListener("mouseleave", function() {' +
                        'hint.style.display = "none";' +
                    '});' +
                '}' +
            '});' +

            // Process images for editing
            'var images = document.querySelectorAll("img");' +
            'images.forEach(function(img) {' +
                'var imgId = "img-edit-" + (imageIndex++);' +
                'img.setAttribute("data-img-edit-id", imgId);' +

                // Double-click handler to edit image
                'img.addEventListener("dblclick", function(e) {' +
                    'e.preventDefault();' +
                    'e.stopPropagation();' +

                    // Remove active class from all elements
                    'document.querySelectorAll(".editing-active").forEach(function(active) {' +
                        'active.classList.remove("editing-active");' +
                    '});' +

                    // Add active class to clicked image
                    'this.classList.add("editing-active");' +

                    // Extract image properties
                    'var style = this.getAttribute("style") || "";' +
                    'var src = this.getAttribute("src") || "";' +

                    // Parse width
                    'var widthMatch = style.match(/width:\\s*(\\d+)%/);' +
                    'var width = widthMatch ? widthMatch[1] : "100";' +

                    // Parse float
                    'var float = "none";' +
                    'if (style.indexOf("float: left") > -1) float = "left";' +
                    'else if (style.indexOf("float: right") > -1) float = "right";' +

                    // Parse alignment
                    'var align = "center";' +
                    'if (style.indexOf("margin-left: auto") > -1 && style.indexOf("margin-right: auto") > -1) align = "center";' +
                    'else if (style.indexOf("margin-left: auto") > -1) align = "right";' +
                    'else if (style.indexOf("margin-right: auto") > -1) align = "left";' +

                    // Parse margin
                    'var marginMatch = style.match(/margin:\\s*(\\d+)px/);' +
                    'var margin = marginMatch ? marginMatch[1] : "10";' +

                    // Parse border-radius
                    'var borderRadiusMatch = style.match(/border-radius:\\s*(\\d+)px/);' +
                    'var borderRadius = borderRadiusMatch ? borderRadiusMatch[1] : "0";' +

                    // Check for parent link
                    'var parentLink = this.closest("a");' +
                    'var link = parentLink ? parentLink.getAttribute("href") || "" : "";' +

                    // Send message to parent
                    'window.parent.postMessage({' +
                        'type: "imageClicked",' +
                        'editId: imgId,' +
                        'src: src,' +
                        'width: width,' +
                        'float: float,' +
                        'align: align,' +
                        'margin: margin,' +
                        'borderRadius: borderRadius,' +
                        'link: link' +
                    '}, "*");' +
                '});' +

                // Show hint on hover
                'img.addEventListener("mouseenter", function() {' +
                    'hint.textContent = "Kliknij dwukrotnie, aby edytować obraz";' +
                    'hint.style.display = "block";' +
                '});' +
                'img.addEventListener("mouseleave", function() {' +
                    'hint.style.display = "none";' +
                    'hint.textContent = "Kliknij, aby edytować tekst";' +
                '});' +
            '});' +

            // Listen for update from parent (after saving edit)
            'window.addEventListener("message", function(e) {' +
                'if (e.data && e.data.type === "updateElement" && e.data.editId && e.data.newContent !== undefined) {' +
                    'var el = document.querySelector("[data-edit-id=\\"" + e.data.editId + "\\"]");' +
                    'if (el) {' +
                        'el.innerHTML = e.data.newContent;' +
                        'el.classList.remove("editing-active");' +
                        // Send updated HTML back
                        'window.parent.postMessage({ type: "htmlUpdate", html: document.documentElement.outerHTML }, "*");' +
                    '}' +
                '}' +
                // Handle image update from parent
                'if (e.data && e.data.type === "updateImage" && e.data.editId) {' +
                    'var img = document.querySelector("[data-img-edit-id=\\"" + e.data.editId + "\\"]");' +
                    'if (img) {' +
                        // Update image src
                        'if (e.data.src) img.setAttribute("src", e.data.src);' +
                        // Update style
                        'if (e.data.style) img.setAttribute("style", e.data.style);' +
                        'img.classList.remove("editing-active");' +
                        // Handle link wrapping
                        'var parentLink = img.closest("a");' +
                        'if (e.data.link) {' +
                            'if (parentLink) {' +
                                'parentLink.setAttribute("href", e.data.link);' +
                            '} else {' +
                                // Wrap in new link
                                'var newLink = document.createElement("a");' +
                                'newLink.setAttribute("href", e.data.link);' +
                                'newLink.setAttribute("target", "_blank");' +
                                'img.parentNode.insertBefore(newLink, img);' +
                                'newLink.appendChild(img);' +
                            '}' +
                        '} else if (parentLink) {' +
                            // Remove link wrapper
                            'parentLink.parentNode.insertBefore(img, parentLink);' +
                            'parentLink.remove();' +
                        '}' +
                        // Send updated HTML back
                        'window.parent.postMessage({ type: "htmlUpdate", html: document.documentElement.outerHTML }, "*");' +
                    '}' +
                '}' +
                // Handle scroll to element request
                'if (e.data && e.data.type === "scrollToElement" && e.data.editId) {' +
                    'var el = document.querySelector("[data-edit-id=\\"" + e.data.editId + "\\"]");' +
                    'if (el) {' +
                        // Scroll element into view with smooth behavior
                        'el.scrollIntoView({ behavior: "smooth", block: "center" });' +
                        // Add temporary highlight effect
                        'el.style.transition = "background-color 0.3s ease";' +
                        'el.style.backgroundColor = "rgba(99, 102, 241, 0.2)";' +
                        'setTimeout(function() {' +
                            'el.style.backgroundColor = "";' +
                        '}, 1500);' +
                    '}' +
                '}' +
            '});' +
        '});' +
    '</scr' + 'ipt>'

    // Insert the script and CSS before closing </head> or at the end
    if (content.toLowerCase().includes('</head>')) {
        return content.replace(/<\/head>/i, editableCSS + '</head>')
    } else if (content.toLowerCase().includes('</body>')) {
        return content.replace(/<\/body>/i, editableCSS + '</body>')
    } else {
        return content + editableCSS
    }
})

// Setup editable iframe message listener
const setupEditableIframe = () => {
    // Listen for messages from the editable iframe
    const handleMessage = (event) => {
        // Handle element click - open edit modal
        if (event.data?.type === 'elementClicked') {
            editingElement.value = event.data.editId
            editingText.value = event.data.text
            editingElementTag.value = event.data.tagName
            showTextEditModal.value = true
            return
        }

        // Handle image click from iframe - open image edit modal
        if (event.data?.type === 'imageClicked') {
            isEditingImage.value = true
            editingImageFromIframe.value = event.data.editId
            editingImageElement.value = null // Clear Tiptap reference since we're editing from iframe
            imageUrl.value = event.data.src || ''
            imageWidth.value = event.data.width || '100'
            imageFloat.value = event.data.float || 'none'
            imageAlignment.value = event.data.align || 'center'
            imageMargin.value = event.data.margin || '10'
            imageBorderRadius.value = event.data.borderRadius || '0'
            imageLink.value = event.data.link || ''
            imagePreviewLoaded.value = true
            imagePreviewError.value = false
            isUploadingImage.value = false
            imageUploadError.value = ''
            showImageModal.value = true
            return
        }

        if (event.data?.type === 'htmlUpdate' && event.data.html) {
            // Extract just the content we care about (remove our injected scripts/styles)
            let newHtml = event.data.html

            // Clean up our added attributes
            newHtml = newHtml.replace(/\s*data-edit-id="[^"]*"/g, '')
            newHtml = newHtml.replace(/\s*data-img-edit-id="[^"]*"/g, '')
            newHtml = newHtml.replace(/\s*class="editing-active"/g, '')

            // Remove our injected style and script blocks
            newHtml = newHtml.replace(/<style>\s*\[data-edit-id\][\s\S]*?<\/style>/gi, '')
            newHtml = newHtml.replace(/<script>\s*document\.addEventListener\("DOMContentLoaded"[\s\S]*?<\/script>/gi, '')

            // Remove hint element
            newHtml = newHtml.replace(/<div class="netsendo-edit-hint"[^>]*>[\s\S]*?<\/div>/gi, '')

            // Update sourceCode with cleaned HTML
            sourceCode.value = newHtml.trim()
            emit('update:modelValue', sourceCode.value)
        }
    }

    window.addEventListener('message', handleMessage)

    // Cleanup on unmount will be handled in onBeforeUnmount
}

// Save edited text and update iframe
const saveTextEdit = () => {
    if (!editingElement.value || !visualEditorIframe.value) return

    const editedElementId = editingElement.value

    // Send update message to iframe
    visualEditorIframe.value.contentWindow?.postMessage({
        type: 'updateElement',
        editId: editedElementId,
        newContent: editingText.value
    }, '*')

    // Scroll to the edited element after a short delay to allow DOM update
    setTimeout(() => {
        visualEditorIframe.value?.contentWindow?.postMessage({
            type: 'scrollToElement',
            editId: editedElementId
        }, '*')
    }, 100)

    // Close modal
    showTextEditModal.value = false
    editingElement.value = null
    editingText.value = ''
    editingElementTag.value = ''
}

// Cancel text editing
const cancelTextEdit = () => {
    showTextEditModal.value = false
    editingElement.value = null
    editingText.value = ''
    editingElementTag.value = ''

    // Remove active class from iframe element
    if (visualEditorIframe.value?.contentWindow) {
        visualEditorIframe.value.contentWindow.postMessage({
            type: 'updateElement',
            editId: editingElement.value,
            newContent: editingText.value
        }, '*')
    }
}

// Insert variable at cursor position in text edit textarea
const insertVariableInTextEdit = (variable) => {
    const textarea = document.getElementById('textEditTextarea')
    if (textarea) {
        const start = textarea.selectionStart
        const end = textarea.selectionEnd
        const text = editingText.value
        editingText.value = text.substring(0, start) + variable + text.substring(end)

        // Restore cursor position after variable
        nextTick(() => {
            textarea.focus()
            const newPos = start + variable.length
            textarea.setSelectionRange(newPos, newPos)
        })
    } else {
        // Fallback: append to end
        editingText.value += variable
    }
}

// Insert emoji at cursor position in Tiptap editor
const insertEmoji = (emoji) => {
    if (editorMode.value === 'visual' && !isFullHtmlDocument.value && editor.value) {
        editor.value.chain().focus().insertContent(emoji).run()
    } else if (editorMode.value === 'source') {
        // For source mode, we need to handle textarea insertion
        const textarea = document.querySelector('textarea')
        if (textarea) {
            const start = textarea.selectionStart
            const end = textarea.selectionEnd
            const text = sourceCode.value
            sourceCode.value = text.substring(0, start) + emoji + text.substring(end)
            emit('update:modelValue', sourceCode.value)
            // Restore cursor position after emoji
            nextTick(() => {
                textarea.focus()
                textarea.setSelectionRange(start + emoji.length, start + emoji.length)
            })
        }
    }
    showEmojiPicker.value = false
}

// Expose methods for parent component
defineExpose({
    switchMode,
    getSourceCode: () => sourceCode.value,
    setSourceCode: (code) => {
        sourceCode.value = code
        emit('update:modelValue', code)
    },
    getCurrentMode: () => editorMode.value,
    insertEmoji,
    commonEmojis,
    showEmojiPicker,
    // Layout settings
    contentWidth,
    contentAlign,
    // Insert content at cursor position
    insertAtCursor: (content) => {
        if (editorMode.value === 'visual' && !isFullHtmlDocument.value && editor.value) {
            // For Tiptap editor, insert at cursor and maintain focus
            editor.value.chain().focus().insertContent(content).run()
        } else if (editorMode.value === 'source') {
            // For source mode, insert into textarea
            const textarea = document.querySelector('textarea')
            if (textarea) {
                const start = textarea.selectionStart
                const end = textarea.selectionEnd
                const text = sourceCode.value
                sourceCode.value = text.substring(0, start) + content + text.substring(end)
                emit('update:modelValue', sourceCode.value)
                // Restore cursor position after inserted content
                nextTick(() => {
                    textarea.focus()
                    const newPos = start + content.length
                    textarea.setSelectionRange(newPos, newPos)
                })
            }
        }
    },
    // Get content wrapped with layout container for email sending
    getWrappedContent: () => {
        const content = sourceCode.value || ''
        // Don't wrap if already a full HTML document
        if (content.trim().toLowerCase().startsWith('<!doctype') ||
            content.trim().toLowerCase().startsWith('<html')) {
            return content
        }
        // Wrap with container div for proper email layout
        const alignStyle = contentAlign.value === 'center'
            ? 'margin: 0 auto;'
            : 'margin: 0;'
        return `<div style="max-width: ${contentWidth.value}px; ${alignStyle} font-family: system-ui, -apple-system, sans-serif; line-height: 1.6;">${content}</div>`
    },
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

            <!-- Visual Mode Toolbar - only show for simple content, not full HTML documents -->
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
                <button
                    type="button"
                    @click="editor.chain().focus().toggleStrike().run()"
                    :disabled="!editor.can().chain().focus().toggleStrike().run()"
                    :class="btnClass(editor.isActive('strike'))"
                    :title="$t('editor.strikethrough')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L15 9M5 12h14" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Font Family Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="showFontPicker = !showFontPicker; showSizePicker = false; showColorPicker = false; showHighlightPicker = false"
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
                        @click="showSizePicker = !showSizePicker; showFontPicker = false; showColorPicker = false; showHighlightPicker = false"
                        :class="btnClass(showSizePicker)"
                        :title="$t('editor.font_size') || 'Rozmiar czcionki'"
                        class="flex items-center gap-1"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7V5a2 2 0 012-2h4m6 0h4a2 2 0 012 2v2M3 17v2a2 2 0 002 2h4m6 0h4a2 2 0 002-2v-2" />
                        </svg>
                        <span class="text-xs font-medium min-w-[28px]">{{ editor.getAttributes('textStyle')?.fontSize || '16px' }}</span>
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div
                        v-if="showSizePicker"
                        class="absolute top-full left-0 mt-1 p-1 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 w-[100px]"
                    >
                        <button
                            v-for="size in fontSizeOptions"
                            :key="size"
                            type="button"
                            @click="editor.chain().focus().setFontSize(size).run(); showSizePicker = false"
                            class="w-full text-center px-2 py-1 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300"
                            :class="{ 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300': editor.getAttributes('textStyle')?.fontSize === size }"
                        >
                            {{ size }}
                        </button>
                        <hr class="my-1 border-slate-200 dark:border-slate-700" />
                        <button
                            type="button"
                            @click="editor.chain().focus().unsetFontSize().run(); showSizePicker = false"
                            class="w-full text-center px-2 py-1 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                        >
                            {{ $t('editor.clear_size') || 'Domyślny' }}
                        </button>
                    </div>
                </div>

                <!-- Text Color Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="showColorPicker = !showColorPicker; showFontPicker = false; showSizePicker = false; showHighlightPicker = false"
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

                <!-- Highlight Color Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="showHighlightPicker = !showHighlightPicker; showFontPicker = false; showSizePicker = false; showColorPicker = false"
                        :class="btnClass(showHighlightPicker || editor.isActive('highlight'))"
                        :title="$t('editor.highlight') || 'Podświetlenie'"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span class="absolute bottom-0 left-1/2 -translate-x-1/2 w-3 h-1 rounded" :style="{ backgroundColor: editor.getAttributes('highlight')?.color || '#FFCC00' }"></span>
                    </button>
                    <div
                        v-if="showHighlightPicker"
                        class="absolute top-full left-0 mt-1 p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 w-[156px]"
                    >
                        <div class="grid grid-cols-6 gap-1">
                            <button
                                v-for="color in colorPalette"
                                :key="color"
                                type="button"
                                @click="editor.chain().focus().toggleHighlight({ color }).run(); showHighlightPicker = false"
                                class="w-5 h-5 rounded border border-slate-300 dark:border-slate-600 hover:scale-110 transition-transform"
                                :style="{ backgroundColor: color }"
                                :title="color"
                            ></button>
                        </div>
                        <hr class="my-2 border-slate-200 dark:border-slate-700" />
                        <button
                            type="button"
                            @click="editor.chain().focus().unsetHighlight().run(); showHighlightPicker = false"
                            class="w-full text-center px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded"
                        >
                            {{ $t('editor.clear_highlight') || 'Usuń podświetlenie' }}
                        </button>
                    </div>
                </div>

                <!-- Text Transform Picker -->
                <div class="relative">
                    <button
                        type="button"
                        @click="showTextTransformPicker = !showTextTransformPicker; showFontPicker = false; showSizePicker = false; showColorPicker = false; showHighlightPicker = false"
                        :class="btnClass(showTextTransformPicker || editor.isActive('textTransform'))"
                        :title="$t('editor.text_transform') || 'Wielkość liter'"
                    >
                        <span class="text-xs font-bold">Aa</span>
                    </button>
                    <div
                        v-if="showTextTransformPicker"
                        class="absolute top-full left-0 mt-1 p-1 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-50 min-w-[140px]"
                    >
                        <button
                            type="button"
                            @click="editor.chain().focus().setTextTransform('uppercase').run(); showTextTransformPicker = false"
                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300"
                            :class="{ 'bg-indigo-100 dark:bg-indigo-900/30': editor.getAttributes('textTransform')?.textTransform === 'uppercase' }"
                        >
                            <span class="uppercase">{{ $t('editor.text_transform_uppercase') || 'WIELKIE LITERY' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().setTextTransform('lowercase').run(); showTextTransformPicker = false"
                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300"
                            :class="{ 'bg-indigo-100 dark:bg-indigo-900/30': editor.getAttributes('textTransform')?.textTransform === 'lowercase' }"
                        >
                            <span class="lowercase">{{ $t('editor.text_transform_lowercase') || 'małe litery' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="editor.chain().focus().setTextTransform('capitalize').run(); showTextTransformPicker = false"
                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors text-slate-700 dark:text-slate-300"
                            :class="{ 'bg-indigo-100 dark:bg-indigo-900/30': editor.getAttributes('textTransform')?.textTransform === 'capitalize' }"
                        >
                            <span class="capitalize">{{ $t('editor.text_transform_capitalize') || 'Pierwsza Wielka' }}</span>
                        </button>
                        <hr class="my-1 border-slate-200 dark:border-slate-700" />
                        <button
                            type="button"
                            @click="editor.chain().focus().unsetTextTransform().run(); showTextTransformPicker = false"
                            class="w-full text-left px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                        >
                            {{ $t('editor.clear_text_transform') || 'Normalne' }}
                        </button>
                    </div>
                </div>

                <!-- Headings -->
                <button
                    type="button"
                    @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                    :class="btnClass(editor.isActive('heading', { level: 1 }))"
                    :title="$t('editor.heading1')"
                >
                    <span class="font-bold text-xs">H1</span>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                    :class="btnClass(editor.isActive('heading', { level: 2 }))"
                    :title="$t('editor.heading2')"
                >
                    <span class="font-bold text-xs">H2</span>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                    :class="btnClass(editor.isActive('heading', { level: 3 }))"
                    :title="$t('editor.heading3')"
                >
                    <span class="font-bold text-xs">H3</span>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Text alignment -->
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('left').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'left' }))"
                    :title="$t('editor.align_left')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('center').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'center' }))"
                    :title="$t('editor.align_center')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().setTextAlign('right').run()"
                    :class="btnClass(editor.isActive({ textAlign: 'right' }))"
                    :title="$t('editor.align_right')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Lists -->
                <button
                    type="button"
                    @click="editor.chain().focus().toggleBulletList().run()"
                    :class="btnClass(editor.isActive('bulletList'))"
                    :title="$t('editor.bullet_list')"
                >
                    <!-- Bullet list icon with dots -->
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="4" cy="6" r="1.5" fill="currentColor"/>
                        <circle cx="4" cy="12" r="1.5" fill="currentColor"/>
                        <circle cx="4" cy="18" r="1.5" fill="currentColor"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleOrderedList().run()"
                    :class="btnClass(editor.isActive('orderedList'))"
                    :title="$t('editor.ordered_list')"
                >
                    <!-- Ordered list icon with numbers -->
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <text x="2" y="8" font-size="6" font-weight="bold">1.</text>
                        <text x="2" y="14" font-size="6" font-weight="bold">2.</text>
                        <text x="2" y="20" font-size="6" font-weight="bold">3.</text>
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11" fill="none"/>
                    </svg>
                </button>

                <!-- List indent/outdent -->
                <button
                    type="button"
                    @click="editor.chain().focus().sinkListItem('listItem').run()"
                    :disabled="!editor.can().sinkListItem('listItem')"
                    :class="[btnClass(), { 'opacity-40 cursor-not-allowed': !editor.can().sinkListItem('listItem') }]"
                    :title="$t('editor.indent') || 'Zwiększ wcięcie'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M9 9h12M9 14h12M3 19h18M3 9l4 2.5L3 14" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().liftListItem('listItem').run()"
                    :disabled="!editor.can().liftListItem('listItem')"
                    :class="[btnClass(), { 'opacity-40 cursor-not-allowed': !editor.can().liftListItem('listItem') }]"
                    :title="$t('editor.outdent') || 'Zmniejsz wcięcie'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M9 9h12M9 14h12M3 19h18M7 9l-4 2.5L7 14" />
                    </svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Blockquote & Code -->
                <button
                    type="button"
                    @click="editor.chain().focus().toggleBlockquote().run()"
                    :class="btnClass(editor.isActive('blockquote'))"
                    :title="$t('editor.blockquote')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleCodeBlock().run()"
                    :class="btnClass(editor.isActive('codeBlock'))"
                    :title="$t('editor.code_block')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Link & Image -->
                <button
                    type="button"
                    @click="openLinkModal"
                    :class="btnClass(editor.isActive('link'))"
                    :title="$t('editor.link')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                </button>
                <button
                    type="button"
                    @click="openImageModal"
                    :class="btnClass()"
                    :title="$t('editor.image')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Table Controls -->
                <button
                    type="button"
                    @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
                    :class="btnClass(editor.isActive('table'))"
                    :title="$t('editor.insert_table') || 'Wstaw tabelę'"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7-12h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V4a2 2 0 012-2z" /></svg>
                </button>

                <!-- Table edit controls (only visible when table is active) -->
                <div v-if="editor.isActive('table')" class="flex items-center gap-1 border-l border-slate-300 dark:border-slate-600 pl-1 ml-1">
                    <button
                        type="button"
                        @click="editor.chain().focus().addColumnBefore().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_col_before') || 'Kolumna przed'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().addColumnAfter().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_col_after') || 'Kolumna po'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteColumn().run()"
                        :class="btnClass()"
                        :title="$t('editor.delete_col') || 'Usuń kolumnę'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="mx-0.5 h-4 w-px bg-slate-300 dark:bg-slate-600"></div>
                    <button
                        type="button"
                        @click="editor.chain().focus().addRowBefore().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_row_before') || 'Wiersz przed'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7" /></svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().addRowAfter().run()"
                        :class="btnClass()"
                        :title="$t('editor.add_row_after') || 'Wiersz po'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" /></svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteRow().run()"
                        :class="btnClass()"
                        :title="$t('editor.delete_row') || 'Usuń wiersz'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="mx-0.5 h-4 w-px bg-slate-300 dark:bg-slate-600"></div>
                    <button
                        type="button"
                        @click="editor.chain().focus().mergeCells().run()"
                        :class="btnClass()"
                        :title="$t('editor.merge_cells') || 'Scal komórki'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    </button>
                    <button
                        type="button"
                        @click="editor.chain().focus().deleteTable().run()"
                        class="rounded p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 transition-colors"
                        :title="$t('editor.delete_table') || 'Usuń tabelę'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </div>

                <!-- Emoji Picker -->
                <div class="relative">
                    <button
                        ref="emojiButtonRef"
                        type="button"
                        @click="showEmojiPicker = !showEmojiPicker"
                        :class="btnClass(showEmojiPicker)"
                        :title="$t('editor.emoji') || 'Emoji'"
                    >
                        <span class="text-lg">😀</span>
                    </button>
                </div>

                <Teleport to="body">
                    <div
                        v-if="showEmojiPicker"
                        ref="emojiPickerRef"
                        class="fixed p-2 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 z-[9999] w-[320px]"
                        :style="emojiPickerStyle"
                    >
                        <!-- Category tabs -->
                        <div class="flex gap-1 mb-2 border-b border-slate-200 dark:border-slate-700 pb-2">
                            <button
                                v-for="(category, key) in emojiCategories"
                                :key="key"
                                type="button"
                                @click="activeEmojiCategory = key"
                                :class="[
                                    'p-1.5 rounded transition-colors text-lg',
                                    activeEmojiCategory === key
                                        ? 'bg-indigo-100 dark:bg-indigo-900/50'
                                        : 'hover:bg-slate-100 dark:hover:bg-slate-700'
                                ]"
                                :title="$t('editor.emoji_categories.' + key) || key"
                            >
                                {{ category.icon }}
                            </button>
                        </div>
                        <!-- Emoji grid -->
                        <div class="grid grid-cols-10 gap-0.5 max-h-[200px] overflow-y-auto">
                            <button
                                v-for="emoji in emojiCategories[activeEmojiCategory]?.emojis || []"
                                :key="emoji"
                                type="button"
                                @click="insertEmoji(emoji)"
                                class="p-1 text-xl hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                            >
                                {{ emoji }}
                            </button>
                        </div>
                    </div>
                    <!-- Backdrop to close emoji picker -->
                    <div
                        v-if="showEmojiPicker"
                        class="fixed inset-0 z-[9998]"
                        @click="showEmojiPicker = false"
                    ></div>
                </Teleport>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Horizontal rule -->
                <button
                    type="button"
                    @click="editor.chain().focus().setHorizontalRule().run()"
                    :class="btnClass()"
                    :title="$t('editor.horizontal_rule')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" /></svg>
                </button>

                <div class="mx-1 h-6 w-px bg-slate-300 dark:bg-slate-600"></div>

                <!-- Content Width Control -->
                <div class="flex items-center gap-1">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                    <select
                        v-model="contentWidth"
                        class="rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-2 py-1 text-xs text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :title="$t('editor.content_width')"
                    >
                        <option :value="400">400px</option>
                        <option :value="500">500px</option>
                        <option :value="600">600px</option>
                        <option :value="700">700px</option>
                        <option :value="800">800px</option>
                    </select>
                </div>

                <!-- Content Alignment Control -->
                <div class="flex items-center gap-0.5 rounded border border-slate-300 dark:border-slate-600 p-0.5">
                    <button
                        type="button"
                        @click="contentAlign = 'left'"
                        :class="[
                            'rounded px-2 py-1 text-xs transition-colors',
                            contentAlign === 'left'
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'
                        ]"
                        :title="$t('editor.align_left')"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="contentAlign = 'center'"
                        :class="[
                            'rounded px-2 py-1 text-xs transition-colors',
                            contentAlign === 'center'
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'
                        ]"
                        :title="$t('editor.align_center')"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1"></div>

                <!-- Undo/Redo -->
                <button
                    type="button"
                    @click="editor.chain().focus().undo().run()"
                    :disabled="!editor.can().chain().focus().undo().run()"
                    :class="btnClass()"
                    :title="$t('editor.undo')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().redo().run()"
                    :disabled="!editor.can().chain().focus().redo().run()"
                    :class="btnClass()"
                    :title="$t('editor.redo')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6" /></svg>
                </button>
            </div>
        </div>

        <!-- Visual Editor -->
        <div v-show="editorMode === 'visual'" :style="{ minHeight }">
            <!-- For full HTML documents, show editable visual preview -->
            <div v-if="isFullHtmlDocument" class="bg-white dark:bg-slate-900">
                <div class="p-2 border-b border-slate-200 dark:border-slate-700 bg-emerald-50 dark:bg-emerald-900/20">
                    <p class="text-xs text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        {{ $t('editor.visual_edit_notice') }}
                    </p>
                </div>
                <div class="p-4 flex justify-center bg-slate-50 dark:bg-slate-900/50">
                    <div class="bg-white shadow-lg rounded-lg overflow-hidden w-full max-w-2xl">
                        <iframe
                            ref="visualEditorIframe"
                            :srcdoc="getEditableSrcdoc"
                            class="w-full min-h-[500px] border-0"
                            sandbox="allow-same-origin allow-scripts"
                            @load="setupEditableIframe"
                        ></iframe>
                    </div>
                </div>
            </div>
            <!-- For simple content, show Tiptap editor with width control -->
            <div v-else class="bg-slate-100 dark:bg-slate-900 p-4">
                <div
                    :class="[
                        'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden',
                        contentAlign === 'center' ? 'mx-auto' : 'mr-auto'
                    ]"
                    :style="{ maxWidth: contentWidth + 'px' }"
                >
                    <editor-content :editor="editor" />
                </div>
            </div>
        </div>

        <!-- Source Code Editor -->
        <div v-show="editorMode === 'source'" :style="{ minHeight }">
            <textarea
                v-model="sourceCode"
                @input="updateFromSource"
                class="w-full h-full min-h-[400px] p-4 font-mono text-sm bg-slate-950 text-green-400 focus:outline-none resize-none"
                spellcheck="false"
                :placeholder="$t('editor.source_placeholder')"
            ></textarea>
        </div>

        <!-- Preview Mode -->
        <div v-show="editorMode === 'preview'" :style="{ minHeight }" class="bg-white">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
                <div class="flex items-center justify-center gap-2">
                    <button
                        type="button"
                        @click="previewDevice = 'desktop'"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 text-sm rounded-lg transition-colors',
                            previewDevice === 'desktop'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'
                        ]"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Desktop
                    </button>
                    <button
                        type="button"
                        @click="previewDevice = 'mobile'"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 text-sm rounded-lg transition-colors',
                            previewDevice === 'mobile'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'
                        ]"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Mobile
                    </button>
                </div>
            </div>
            <div class="p-4 flex justify-center bg-slate-100 dark:bg-slate-900">
                <div
                    class="bg-white shadow-lg rounded-lg overflow-hidden transition-all duration-300"
                    :class="previewDevice === 'mobile' ? 'w-[375px]' : 'w-full max-w-2xl'"
                >
                    <!-- Mobile: use CSS zoom to scale content -->
                    <iframe
                        :srcdoc="getPreviewSrcdoc"
                        :class="[
                            'w-full border-0',
                            previewDevice === 'mobile' ? 'min-h-[600px]' : 'min-h-[500px]'
                        ]"
                        :style="previewDevice === 'mobile' ? 'transform-origin: top center;' : ''"
                        sandbox="allow-same-origin allow-scripts"
                    ></iframe>
                </div>
            </div>
        </div>

        <!-- Text Edit Modal for Full HTML Documents -->
        <div v-if="showTextEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click="cancelTextEdit">
            <div class="bg-white dark:bg-slate-800 rounded-xl p-6 w-full max-w-xl shadow-2xl" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        {{ $t('editor.edit_text_modal.title') || 'Edytuj tekst' }}
                    </h3>
                    <span class="text-xs text-slate-500 bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded-full font-mono">
                        {{ editingElementTag }}
                    </span>
                </div>

                <!-- Content Textarea -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ $t('editor.edit_text_modal.content_label') || 'Zawartość' }}
                    </label>
                    <textarea
                        id="textEditTextarea"
                        v-model="editingText"
                        rows="6"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm resize-y"
                        :placeholder="$t('editor.edit_text_modal.placeholder') || 'Wprowadź treść...'"
                    ></textarea>
                </div>

                <!-- Variable Insertion -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $t('editor.edit_text_modal.insert_variable') || 'Wstaw zmienną' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="variable in commonVariables"
                            :key="variable.code"
                            type="button"
                            @click="insertVariableInTextEdit(variable.code)"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-indigo-100 dark:hover:bg-indigo-900 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors border border-slate-200 dark:border-slate-600"
                        >
                            <code class="text-indigo-600 dark:text-indigo-400">{{ variable.code }}</code>
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="cancelTextEdit"
                        class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="saveTextEdit"
                        class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-2"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ $t('common.save') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Link Modal -->
        <div v-if="showLinkModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white dark:bg-slate-800 rounded-lg p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    {{ isEditingLink ? $t('editor.update_link') : $t('editor.insert_link') }}
                </h3>

                <!-- Link Text Field -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ $t('editor.link_text') }}
                    </label>
                    <input
                        v-model="linkText"
                        type="text"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :placeholder="$t('editor.link_text_placeholder')"
                    />
                </div>

                <!-- Link URL Field -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ $t('editor.link_url_label') }}
                    </label>
                    <input
                        v-model="linkUrl"
                        type="url"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :placeholder="$t('editor.link_placeholder')"
                        @keydown.enter="setLink"
                    />
                </div>

                <!-- Link Title Field (for accessibility/tooltip) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ $t('editor.link_title') }}
                    </label>
                    <input
                        v-model="linkTitle"
                        type="text"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :placeholder="$t('editor.link_title_placeholder')"
                    />
                </div>

                <!-- Link Target Field -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ $t('editor.link_target') }}
                    </label>
                    <select
                        v-model="linkTarget"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="_blank">{{ $t('editor.link_target_blank') }}</option>
                        <option value="_self">{{ $t('editor.link_target_self') }}</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        @click="closeLinkModal"
                        class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        @click="setLink"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    >
                        {{ linkUrl ? $t('common.save') : $t('editor.remove_link') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click="closeImageModal">
            <div class="bg-white dark:bg-slate-800 rounded-lg p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto" @click.stop>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    {{ isEditingImage ? $t('editor.edit_image') : $t('editor.insert_image') }}
                </h3>

                <!-- Image Upload Section -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.upload_image') }}</label>
                    <label
                        :class="[
                            'flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed p-4 text-sm transition-colors',
                            isUploadingImage ? 'cursor-wait opacity-50' : '',
                            imageUploadError
                                ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20'
                                : 'border-slate-300 bg-slate-50 hover:border-indigo-400 hover:bg-indigo-50 dark:border-slate-600 dark:bg-slate-800 dark:hover:border-indigo-500'
                        ]"
                    >
                        <svg
                            v-if="isUploadingImage"
                            class="h-5 w-5 animate-spin text-indigo-600"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg v-else class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span :class="imageUploadError ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400'">
                            {{ isUploadingImage ? $t('common.uploading') : $t('editor.click_to_upload') }}
                        </span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                            class="hidden"
                            @change="handleImageUpload"
                            :disabled="isUploadingImage"
                        />
                    </label>
                    <p v-if="imageUploadError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ imageUploadError }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('editor.image_upload_hint') }}</p>
                </div>

                <!-- Browse Media Library Button -->
                <div class="mb-4">
                    <button
                        type="button"
                        @click="openMediaBrowser"
                        class="w-full flex items-center justify-center gap-2 rounded-lg border-2 border-indigo-300 bg-indigo-50 p-4 text-sm font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50 transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ $t('common.browse_media') }}
                    </button>
                </div>

                <!-- Media Browser Dropdown -->
                <div v-if="showMediaBrowser" class="mb-4 border rounded-lg p-3 bg-slate-50 dark:bg-slate-900 max-h-48 overflow-y-auto">
                    <div v-if="isLoadingMedia" class="flex items-center justify-center py-4">
                        <svg class="animate-spin h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                    <div v-else-if="mediaLibrary.length === 0" class="text-center py-4 text-slate-500 dark:text-slate-400 text-sm">
                        {{ $t('media.no_media_found') }}
                    </div>
                    <div v-else class="grid grid-cols-4 gap-2">
                        <button
                            v-for="media in mediaLibrary"
                            :key="media.id"
                            type="button"
                            @click="selectFromMediaBrowser(media)"
                            class="aspect-square overflow-hidden rounded-lg border-2 border-transparent hover:border-indigo-500 transition-colors"
                        >
                            <img :src="media.url" :alt="media.name" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <!-- Browse Logos Button -->
                <div class="mb-4">
                    <button
                        type="button"
                        @click="openLogoBrowser"
                        class="w-full flex items-center justify-center gap-2 rounded-lg border-2 border-purple-300 bg-purple-50 p-4 text-sm font-medium text-purple-700 hover:bg-purple-100 dark:border-purple-600 dark:bg-purple-900/30 dark:text-purple-300 dark:hover:bg-purple-900/50 transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        {{ $t('common.browse_logos') }}
                    </button>
                </div>

                <!-- Logo Browser Dropdown -->
                <div v-if="showLogoBrowser" class="mb-4 border rounded-lg p-3 bg-purple-50 dark:bg-purple-900/20 max-h-48 overflow-y-auto">
                    <div v-if="isLoadingMedia" class="flex items-center justify-center py-4">
                        <svg class="animate-spin h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                    <div v-else-if="mediaLibrary.length === 0" class="text-center py-4 text-purple-600 dark:text-purple-400 text-sm">
                        {{ $t('media.no_logos_found') }}
                    </div>
                    <div v-else class="grid grid-cols-4 gap-2">
                        <button
                            v-for="media in mediaLibrary"
                            :key="media.id"
                            type="button"
                            @click="selectFromMediaBrowser(media)"
                            class="aspect-square overflow-hidden rounded-lg border-2 border-transparent hover:border-purple-500 bg-white dark:bg-slate-800 transition-colors"
                        >
                            <img :src="media.url" :alt="media.name" class="h-full w-full object-contain p-1" />
                        </button>
                    </div>
                </div>

                <!-- Separator -->
                <div class="relative mb-4">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-slate-300 dark:border-slate-600"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="bg-white dark:bg-slate-800 px-2 text-slate-500 dark:text-slate-400">{{ $t('editor.or_paste_url') }}</span>
                    </div>
                </div>

                <!-- Image URL Input -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $t('editor.image_properties.url') }}</label>
                    <input
                        v-model="imageUrl"
                        type="url"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :placeholder="$t('editor.image_placeholder')"
                        @input="onImageUrlChange"
                    />
                </div>

                <!-- Image Preview -->
                <div v-if="imageUrl" class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.image_properties.preview') }}</label>
                    <div class="relative bg-slate-100 dark:bg-slate-700 rounded-lg p-4 min-h-[120px] flex items-center justify-center">
                        <!-- Loading state -->
                        <div v-if="!imagePreviewLoaded && !imagePreviewError" class="text-slate-400 flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ $t('common.loading') }}
                        </div>
                        <!-- Error state -->
                        <div v-if="imagePreviewError" class="text-red-500 flex items-center gap-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ $t('editor.image_properties.load_error') }}
                        </div>
                        <!-- Image preview with alignment -->
                        <img
                            :src="imageUrl"
                            @load="onImageLoad"
                            @error="onImageError"
                            :class="[
                                'max-h-[150px] rounded transition-opacity',
                                imagePreviewLoaded ? 'opacity-100' : 'opacity-0 absolute',
                                imageAlignment === 'left' ? 'mr-auto' : '',
                                imageAlignment === 'center' ? 'mx-auto' : '',
                                imageAlignment === 'right' ? 'ml-auto' : ''
                            ]"
                            :style="{ width: imageWidth + '%', maxWidth: '100%' }"
                        />
                    </div>
                </div>

                <!-- Settings (only show when image is loaded) -->
                <div v-if="imagePreviewLoaded" class="space-y-4">
                    <!-- Alignment -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.image_properties.alignment') }}</label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="imageAlignment = 'left'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors',
                                    imageAlignment === 'left'
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'
                                        : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'
                                ]"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14" /></svg>
                                {{ $t('editor.image_properties.left') }}
                            </button>
                            <button
                                type="button"
                                @click="imageAlignment = 'center'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors',
                                    imageAlignment === 'center'
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'
                                        : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'
                                ]"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14" /></svg>
                                {{ $t('editor.image_properties.center') }}
                            </button>
                            <button
                                type="button"
                                @click="imageAlignment = 'right'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors',
                                    imageAlignment === 'right'
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'
                                        : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'
                                ]"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14" /></svg>
                                {{ $t('editor.image_properties.right') }}
                            </button>
                        </div>
                    </div>

                    <!-- Width -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ $t('editor.image_properties.width') }}: {{ imageWidth }}%
                        </label>
                        <input
                            v-model="imageWidth"
                            type="range"
                            min="10"
                            max="100"
                            step="5"
                            class="w-full h-2 bg-slate-200 dark:bg-slate-600 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                        />
                        <div class="flex justify-between text-xs text-slate-500 mt-1">
                            <span>10%</span>
                            <span>50%</span>
                            <span>100%</span>
                        </div>
                    </div>

                    <!-- Link -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $t('editor.image_properties.link') }}</label>
                        <input
                            v-model="imageLink"
                            type="url"
                            class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="https://example.com"
                        />
                    </div>

                    <!-- Text Wrapping (Float) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ $t('editor.image_float') }}</label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="imageFloat = 'none'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors',
                                    imageFloat === 'none'
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'
                                        : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'
                                ]"
                            >
                                {{ $t('editor.float_none') }}
                            </button>
                            <button
                                type="button"
                                @click="imageFloat = 'left'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors',
                                    imageFloat === 'left'
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'
                                        : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'
                                ]"
                            >
                                {{ $t('editor.float_left') }}
                            </button>
                            <button
                                type="button"
                                @click="imageFloat = 'right'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors',
                                    imageFloat === 'right'
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'
                                        : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700'
                                ]"
                            >
                                {{ $t('editor.float_right') }}
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $t('editor.float_hint') }}</p>
                    </div>

                    <!-- Margin -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ $t('editor.image_margin') }}: {{ imageMargin }}px
                        </label>
                        <input
                            v-model="imageMargin"
                            type="range"
                            min="0"
                            max="50"
                            step="5"
                            class="w-full h-2 bg-slate-200 dark:bg-slate-600 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                        />
                        <div class="flex justify-between text-xs text-slate-500 mt-1">
                            <span>0px</span>
                            <span>25px</span>
                            <span>50px</span>
                        </div>
                    </div>

                    <!-- Border Radius -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ $t('editor.image_border_radius') }}: {{ imageBorderRadius }}px
                        </label>
                        <input
                            v-model="imageBorderRadius"
                            type="range"
                            min="0"
                            max="50"
                            step="5"
                            class="w-full h-2 bg-slate-200 dark:bg-slate-600 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                        />
                        <div class="flex justify-between text-xs text-slate-500 mt-1">
                            <span>0px</span>
                            <span>25px</span>
                            <span>50px</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-between">
                    <!-- Delete button (only shows when editing existing image) -->
                    <div>
                        <button
                            v-if="isEditingImage"
                            type="button"
                            @click="deleteImage"
                            class="px-4 py-2 text-sm rounded-lg transition-colors bg-red-600 text-white hover:bg-red-700"
                        >
                            {{ $t('editor.delete_image') }}
                        </button>
                    </div>
                    <!-- Cancel and Insert buttons -->
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="showImageModal = false"
                            class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="insertImage"
                            :disabled="!imageUrl || !imagePreviewLoaded"
                            :class="[
                                'px-4 py-2 text-sm rounded-lg transition-colors',
                                (!imageUrl || !imagePreviewLoaded)
                                    ? 'bg-slate-300 text-slate-500 cursor-not-allowed'
                                    : 'bg-indigo-600 text-white hover:bg-indigo-700'
                            ]"
                        >
                            {{ $t('editor.insert') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Basic editor styles */
.ProseMirror {
    outline: none;
    min-height: 200px;
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

.ProseMirror h1 { font-size: 2em; font-weight: bold; }
.ProseMirror h2 { font-size: 1.5em; font-weight: bold; }
.ProseMirror h3 { font-size: 1.25em; font-weight: bold; }
.ProseMirror h4 { font-size: 1.1em; font-weight: bold; }

.ProseMirror h1,
.ProseMirror h2,
.ProseMirror h3,
.ProseMirror h4,
.ProseMirror h5,
.ProseMirror h6 {
    line-height: 1.2;
    margin-top: 1em;
    margin-bottom: 0.5em;
}

.ProseMirror code {
    background-color: rgba(97, 97, 97, 0.1);
    color: #616161;
    padding: 0.2em 0.4em;
    border-radius: 0.25em;
    font-family: monospace;
}

.ProseMirror pre {
    background: #0D0D0D;
    color: #FFF;
    font-family: 'JetBrainsMono', 'Fira Code', monospace;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
}

.ProseMirror pre code {
    color: inherit;
    padding: 0;
    background: none;
    font-size: 0.85rem;
}

.ProseMirror img {
    max-width: 100%;
    height: auto;
}

.ProseMirror blockquote {
    padding-left: 1rem;
    border-left: 3px solid #6366f1;
    color: #64748b;
    font-style: italic;
}

.ProseMirror hr {
    border: none;
    border-top: 2px solid rgba(13, 13, 13, 0.1);
    margin: 2rem 0;
}

.ProseMirror a {
    color: #6366f1;
    text-decoration: underline;
}

.ProseMirror a:hover {
    color: #4f46e5;
}

/* Text alignment */
.ProseMirror p.has-text-align-center,
.ProseMirror h1.has-text-align-center,
.ProseMirror h2.has-text-align-center,
.ProseMirror h3.has-text-align-center {
    text-align: center;
}

.ProseMirror p.has-text-align-right,
.ProseMirror h1.has-text-align-right,
.ProseMirror h2.has-text-align-right,
.ProseMirror h3.has-text-align-right {
    text-align: right;
}

/* Dark mode adjustments */
.dark .ProseMirror code {
    background-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

.dark .ProseMirror blockquote {
    color: #94a3b8;
}

/* Resizable Image Styles */
.resizable-image-wrapper {
    display: inline-block;
    max-width: 100%;
}

.resizable-image-container {
    position: relative;
    display: inline-block;
    transition: outline 0.15s ease;
}

.resizable-image-container.is-selected {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

.resizable-image-container.is-resizing {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

.resizable-image {
    display: block;
    max-width: 100%;
    height: auto;
    cursor: pointer;
}

/* Resize handles */
.resize-handle {
    position: absolute;
    width: 12px;
    height: 12px;
    background-color: #6366f1;
    border: 2px solid white;
    border-radius: 2px;
    z-index: 10;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.resize-handle-se {
    bottom: -6px;
    right: -6px;
    cursor: se-resize;
}

.resize-handle-sw {
    bottom: -6px;
    left: -6px;
    cursor: sw-resize;
}

.resize-handle:hover {
    background-color: #4f46e5;
    transform: scale(1.1);
}

/* Width indicator during resize */
.resize-width-indicator {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    pointer-events: none;
    z-index: 20;
}

/* Dark mode adjustments for resize handles */
.dark .resize-handle {
    border-color: #1e293b;
}

.dark .resize-width-indicator {
    background-color: rgba(255, 255, 255, 0.9);
    color: #1e293b;
}

/* Table styles */
.ProseMirror table {
    border-collapse: collapse;
    margin: 0;
    overflow: hidden;
    table-layout: fixed;
    width: 100%;
}

.ProseMirror table td,
.ProseMirror table th {
    border: 1px solid #cbd5e1;
    box-sizing: border-box;
    min-width: 1em;
    padding: 6px 8px;
    position: relative;
    vertical-align: top;
}

.dark .ProseMirror table td,
.dark .ProseMirror table th {
    border-color: #475569;
}

.ProseMirror table th {
    background-color: #f1f5f9;
    font-weight: bold;
    text-align: left;
}

.dark .ProseMirror table th {
    background-color: #334155;
}

.ProseMirror table .selectedCell:after {
    background: rgba(99, 102, 241, 0.2);
    content: "";
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    pointer-events: none;
    position: absolute;
    z-index: 2;
}

.ProseMirror table .column-resize-handle {
    background-color: #6366f1;
    bottom: -2px;
    pointer-events: none;
    position: absolute;
    right: -2px;
    top: 0;
    width: 4px;
}

.ProseMirror .tableWrapper {
    margin: 1.5rem 0;
    overflow-x: auto;
}

.ProseMirror.resize-cursor {
    cursor: ew-resize;
    cursor: col-resize;
}
</style>
