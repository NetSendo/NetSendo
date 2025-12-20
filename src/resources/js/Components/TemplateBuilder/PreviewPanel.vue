<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import mjml2html from 'mjml-browser';

const { t } = useI18n();

const props = defineProps({
    blocks: Array,
    settings: Object,
    mode: {
        type: String,
        default: 'desktop',
    },
    theme: {
        type: String,
        default: 'light',
    },
});

const emit = defineEmits(['update:mode', 'update:theme']);

const iframeRef = ref(null);
const isCompiling = ref(false);
const compiledHtml = ref('');

// Device dimensions
const deviceDimensions = {
    desktop: { width: '100%', frame: null },
    tablet: { width: '768px', frame: 'tablet' },
    mobile: { width: '375px', frame: 'mobile' },
};

// Convert JSON blocks to MJML
const blocksToMjml = (blocks, settings, theme) => {
    const s = settings || {};
    const isDark = theme === 'dark';
    
    const bgColor = isDark 
        ? (s.dark_mode?.background_color || '#1e293b')
        : (s.background_color || '#f4f4f4');
    const contentBg = isDark
        ? (s.dark_mode?.content_background || '#0f172a')
        : (s.content_background || '#ffffff');
    const textColor = isDark
        ? (s.dark_mode?.text_color || '#f1f5f9')
        : (s.text_color || '#1e293b');
    const fontFamily = s.font_family || 'Arial, Helvetica, sans-serif';
    const width = s.width || 600;
    const primaryColor = s.primary_color || '#6366f1';

    let blocksContent = '';
    for (const block of (blocks || [])) {
        blocksContent += blockToMjml(block, { textColor, primaryColor });
    }

    return `
<mjml>
  <mj-head>
    <mj-attributes>
      <mj-all font-family="${fontFamily}" />
      <mj-text font-size="16px" color="${textColor}" line-height="1.6" />
      <mj-button font-size="16px" />
    </mj-attributes>
    <mj-style inline="inline">
      a { color: inherit; }
    </mj-style>
  </mj-head>
  <mj-body background-color="${bgColor}" width="${width}px">
    <mj-wrapper background-color="${contentBg}">
      ${blocksContent}
    </mj-wrapper>
  </mj-body>
</mjml>`;
};

// Convert single block to MJML
const blockToMjml = (block, options) => {
    const type = block.type;
    const c = block.content || {};
    const { textColor, primaryColor } = options;

    switch (type) {
        case 'header':
            const logoHtml = c.logo 
                ? `<mj-image src="${c.logo}" width="${c.logoWidth || 150}px" align="${c.alignment || 'center'}" />`
                : '';
            return `
    <mj-section background-color="${c.backgroundColor || primaryColor}" padding="${c.padding || '20px'}">
      ${logoHtml}
    </mj-section>`;

        case 'text':
            return `
    <mj-section padding="0">
      <mj-column>
        <mj-text padding="${c.padding || '20px'}" align="${c.alignment || 'left'}">
          ${c.html || ''}
        </mj-text>
      </mj-column>
    </mj-section>`;

        case 'image':
            if (!c.src) return '';
            const hrefAttr = c.href ? `href="${c.href}"` : '';
            return `
    <mj-section padding="0">
      <mj-column>
        <mj-image src="${c.src}" alt="${c.alt || ''}" width="${c.width || '100%'}" align="${c.alignment || 'center'}" padding="${c.padding || '10px'}" ${hrefAttr} />
      </mj-column>
    </mj-section>`;

        case 'button':
            return `
    <mj-section padding="10px 0">
      <mj-column>
        <mj-button href="${c.href || '#'}" background-color="${c.backgroundColor || primaryColor}" color="${c.textColor || '#ffffff'}" border-radius="${c.borderRadius || '8px'}" inner-padding="${c.padding || '12px 24px'}" align="${c.alignment || 'center'}">
          ${c.text || t('template_builder.default_button')}
        </mj-button>
      </mj-column>
    </mj-section>`;

        case 'divider':
            return `
    <mj-section padding="${c.padding || '10px 0'}">
      <mj-column>
        <mj-divider border-color="${c.color || '#e2e8f0'}" border-width="1px" />
      </mj-column>
    </mj-section>`;

        case 'spacer':
            return `
    <mj-section padding="0">
      <mj-column>
        <mj-spacer height="${c.height || '30px'}" />
      </mj-column>
    </mj-section>`;

        case 'columns':
            return columnsToMjml(c, options);

        case 'product':
            const productImage = c.image ? `<mj-image src="${c.image}" padding="0 0 10px 0" />` : '';
            const priceHtml = c.oldPrice 
                ? `<span style="text-decoration: line-through; color: #94a3b8;">${c.oldPrice} ${c.currency || t('template_builder.preview_panel.currency_fallback')}</span> <strong style="color: #ef4444;">${c.price || t('template_builder.default_price')} ${c.currency || t('template_builder.preview_panel.currency_fallback')}</strong>`
                : `<strong>${c.price || t('template_builder.default_price')} ${c.currency || t('template_builder.preview_panel.currency_fallback')}</strong>`;
            return `
    <mj-section background-color="#ffffff" padding="20px" border-radius="8px">
      <mj-column>
        ${productImage}
        <mj-text font-size="18px" font-weight="bold" padding="10px 0 5px 0">
          ${c.title || t('template_builder.preview_panel.product_name_fallback')}
        </mj-text>
        <mj-text padding="0 0 10px 0">
          ${c.description || ''}
        </mj-text>
        <mj-text font-size="20px" padding="10px 0">
          ${priceHtml}
        </mj-text>
        <mj-button href="${c.buttonUrl || '#'}" background-color="${primaryColor}" border-radius="8px">
          ${c.buttonText || t('template_builder.preview_panel.buy_now')}
        </mj-button>
      </mj-column>
    </mj-section>`;

        case 'social':
            if (!c.icons || c.icons.length === 0) return '';
            let socialHtml = '';
            for (const icon of c.icons) {
                socialHtml += `<mj-social-element name="${icon.type}" href="${icon.url || '#'}" icon-size="${c.iconSize || 32}px" />`;
            }
            return `
    <mj-section padding="${c.padding || '20px'}">
      <mj-column>
        <mj-social mode="horizontal" align="${c.alignment || 'center'}">
          ${socialHtml}
        </mj-social>
      </mj-column>
    </mj-section>`;

        case 'product_grid':
            const gridColumns = c.columns || 2;
            const products = c.products || [];
            if (products.length === 0) {
                return `
    <mj-section padding="20px">
      <mj-column>
        <mj-text align="center" color="#94a3b8">${t('template_builder.preview_panel.product_grid_empty')}</mj-text>
      </mj-column>
    </mj-section>`;
            }
            const colWidth = gridColumns === 2 ? '50%' : '33.33%';
            let gridProductsHtml = '';
            for (const product of products) {
                const prodImage = product.image ? `<mj-image src="${product.image}" padding="0 0 10px 0" />` : `<mj-image src="https://via.placeholder.com/150x150?text=${t('template_builder.blocks.product')}" padding="0 0 10px 0" />`;
                gridProductsHtml += `
      <mj-column width="${colWidth}" padding="10px">
        ${prodImage}
        <mj-text font-weight="bold" align="center">${product.title || t('template_builder.blocks.product')}</mj-text>
        <mj-text align="center">${product.price || t('template_builder.default_price')} ${product.currency || t('template_builder.preview_panel.currency_fallback')}</mj-text>
        <mj-button href="${product.buttonUrl || '#'}" background-color="${primaryColor}" font-size="14px">${t('template_builder.preview_panel.buy')}</mj-button>
      </mj-column>`;
            }
            return `
    <mj-section padding="20px">
      ${gridProductsHtml}
    </mj-section>`;

        case 'footer':
            return `
    <mj-section background-color="${c.backgroundColor || '#1e293b'}" padding="${c.padding || '30px 20px'}">
      <mj-column>
        <mj-text color="${c.textColor || '#94a3b8'}" align="center" font-size="14px">
          <strong>${c.companyName || ''}</strong><br/>
          ${c.address || ''}
        </mj-text>
        <mj-text color="${c.textColor || '#94a3b8'}" align="center" font-size="12px" padding-top="15px">
          <a href="${c.unsubscribeUrl || '#'}" style="color: ${c.textColor || '#94a3b8'};">${c.unsubscribeText || t('messages.unsubscribe_link')}</a>
        </mj-text>
        <mj-text color="${c.textColor || '#94a3b8'}" align="center" font-size="12px" padding-top="10px">
          ${c.copyright || ''}
        </mj-text>
      </mj-column>
    </mj-section>`;

        default:
            return `
    <mj-section padding="20px">
      <mj-column>
        <mj-text align="center" color="#94a3b8">${type}</mj-text>
      </mj-column>
    </mj-section>`;
    }
};

// Columns block to MJML
const columnsToMjml = (content, options) => {
    const columnsCount = content.columns || 2;
    const columnBlocks = content.columnBlocks || [];
    const gap = content.gap || '20px';

    const columnWidth = {
        2: '50%',
        3: '33.33%',
        4: '25%',
    }[columnsCount] || '50%';

    let columnsHtml = '';
    for (let i = 0; i < columnsCount; i++) {
        const colBlocks = columnBlocks[i] || [];
        let columnContent = '';
        
        for (const block of colBlocks) {
            columnContent += nestedBlockToMjml(block, options);
        }
        
        if (!columnContent) {
            columnContent = '<mj-text>&nbsp;</mj-text>';
        }

        columnsHtml += `
      <mj-column width="${columnWidth}">
        ${columnContent}
      </mj-column>`;
    }

    return `
    <mj-section padding="20px">
      ${columnsHtml}
    </mj-section>`;
};

// Nested block to MJML (without section wrapper)
const nestedBlockToMjml = (block, options) => {
    const type = block.type;
    const c = block.content || {};
    const { primaryColor } = options;

    switch (type) {
        case 'text':
            return `<mj-text padding="${c.padding || '10px'}">${c.html || ''}</mj-text>`;
        case 'image':
            if (!c.src) return '';
            return `<mj-image src="${c.src}" alt="${c.alt || ''}" />`;
        case 'button':
            return `<mj-button href="${c.href || '#'}" background-color="${c.backgroundColor || primaryColor}">${c.text || t('template_builder.default_button')}</mj-button>`;
        default:
            return '';
    }
};

// Generate preview HTML using MJML
const generatePreviewHtml = () => {
    try {
        const mjml = blocksToMjml(props.blocks, props.settings, props.theme);
        const result = mjml2html(mjml, { validationLevel: 'soft' });
        return result.html;
    } catch (error) {
        console.warn('MJML compilation failed:', error);
        // Fallback to simple HTML
        return generateFallbackHtml();
    }
};

// Fallback simple HTML preview
const generateFallbackHtml = () => {
    const s = props.settings || {};
    const bgColor = props.theme === 'dark' 
        ? (s.dark_mode?.background_color || '#1e293b')
        : (s.background_color || '#f4f4f4');
    const contentBg = props.theme === 'dark'
        ? (s.dark_mode?.content_background || '#0f172a')
        : (s.content_background || '#ffffff');
    const textColor = props.theme === 'dark'
        ? (s.dark_mode?.text_color || '#f1f5f9')
        : (s.text_color || '#1e293b');

    let blocksHtml = '';
    for (const block of (props.blocks || [])) {
        blocksHtml += blockToHtml(block, textColor);
    }

    return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background-color: ${bgColor}; 
            font-family: ${s.font_family || 'Arial, Helvetica, sans-serif'}; 
            color: ${textColor};
            padding: 20px;
        }
        .email-container {
            max-width: ${s.width || 600}px;
            margin: 0 auto;
            background-color: ${contentBg};
        }
        img { max-width: 100%; height: auto; }
        a { color: ${s.link_color || s.primary_color || '#6366f1'}; }
    </style>
</head>
<body>
    <div class="email-container">
        ${blocksHtml}
    </div>
</body>
</html>`;
};

// Convert block to HTML for fallback preview
const blockToHtml = (block, textColor) => {
    const c = block.content || {};
    const s = props.settings || {};
    
    switch (block.type) {
        case 'header':
            const logoHtml = c.logo 
                ? `<img src="${c.logo}" style="width: ${c.logoWidth || 150}px; margin: 0 auto; display: block;" />`
                : `<div style="padding: 20px; text-align: center; color: rgba(255,255,255,0.5);">${t('template_builder.logo')}</div>`;
            return `<div style="background-color: ${c.backgroundColor || '#6366f1'}; padding: ${c.padding || '20px'}; text-align: ${c.alignment || 'center'};">${logoHtml}</div>`;
        
        case 'text':
            return `<div style="padding: ${c.padding || '20px'}; text-align: ${c.alignment || 'left'}; color: ${textColor};">${c.html || ''}</div>`;
        
        case 'image':
            if (!c.src) return `<div style="background: #f1f5f9; height: 150px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">${t('template_builder.blocks.image')}</div>`;
            const imgHtml = `<img src="${c.src}" alt="${c.alt || ''}" style="display: block; margin: 0 auto; max-width: 100%;" />`;
            return c.href ? `<a href="${c.href}">${imgHtml}</a>` : imgHtml;
        
        case 'button':
            return `<div style="padding: 10px 0; text-align: ${c.alignment || 'center'};">
                <a href="${c.href || '#'}" style="display: inline-block; background-color: ${c.backgroundColor || '#6366f1'}; color: ${c.textColor || '#ffffff'}; padding: ${c.padding || '12px 24px'}; border-radius: ${c.borderRadius || '8px'}; text-decoration: none; font-weight: 500;">${c.text || t('template_builder.default_button')}</a>
            </div>`;
        
        case 'divider':
            return `<div style="padding: ${c.padding || '10px 0'};"><hr style="border: none; border-top: 1px solid ${c.color || '#e2e8f0'};" /></div>`;
        
        case 'spacer':
            return `<div style="height: ${c.height || '30px'};"></div>`;

        case 'columns':
            return columnsToHtml(c, textColor);
        
        case 'product':
            return `<div style="padding: 20px; display: flex; gap: 16px;">
                <div style="width: 100px; height: 100px; background: #f1f5f9; flex-shrink: 0;"></div>
                <div style="flex: 1;">
                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 8px; color: ${textColor};">${c.title || t('template_builder.preview_panel.product_name_fallback')}</h3>
                    <p style="font-size: 14px; color: #64748b; margin-bottom: 8px;">${c.description || ''}</p>
                    <p style="font-size: 18px; font-weight: bold; color: ${s?.primary_color || '#6366f1'};">${c.price || t('template_builder.default_price')} ${c.currency || t('template_builder.preview_panel.currency_fallback')}</p>
                </div>
            </div>`;
        
        case 'footer':
            return `<div style="background-color: ${c.backgroundColor || '#1e293b'}; color: ${c.textColor || '#94a3b8'}; padding: ${c.padding || '30px 20px'}; text-align: center;">
                <p style="font-weight: 600;">${c.companyName || t('template_builder.company_name')}</p>
                <p style="font-size: 12px; margin-top: 5px;">${c.address || ''}</p>
                <p style="font-size: 12px; margin-top: 15px;"><a href="${c.unsubscribeUrl || '#'}" style="color: ${c.textColor || '#94a3b8'};">${c.unsubscribeText || t('messages.unsubscribe_link')}</a></p>
                <p style="font-size: 12px; margin-top: 10px; opacity: 0.5;">${c.copyright || ''}</p>
            </div>`;
        
        default:
            return `<div style="padding: 20px; text-align: center; color: #94a3b8;">${block.type}</div>`;
    }
};

// Columns to HTML for fallback
const columnsToHtml = (content, textColor) => {
    const columnsCount = content.columns || 2;
    const columnBlocks = content.columnBlocks || [];
    const gap = content.gap || '20px';
    
    let columnsHtml = '';
    for (let i = 0; i < columnsCount; i++) {
        const colBlocks = columnBlocks[i] || [];
        let columnContent = '';
        
        for (const block of colBlocks) {
            columnContent += nestedBlockToHtml(block, textColor);
        }
        
        if (!columnContent) {
            columnContent = `<div style="padding: 10px; color: #94a3b8; font-size: 12px;">${t('template_builder.preview_panel.drop_block_here')}</div>`;
        }
        
        columnsHtml += `<div style="flex: 1; min-width: 0;">${columnContent}</div>`;
    }
    
    return `<div style="display: flex; gap: ${gap}; padding: 20px;">${columnsHtml}</div>`;
};

// Nested block to HTML for fallback
const nestedBlockToHtml = (block, textColor) => {
    const c = block.content || {};
    
    switch (block.type) {
        case 'text':
            return `<div style="padding: 10px; color: ${textColor};">${c.html || ''}</div>`;
        case 'image':
            if (!c.src) return '';
            return `<img src="${c.src}" alt="${c.alt || ''}" style="max-width: 100%;" />`;
        case 'button':
            return `<a href="${c.href || '#'}" style="display: inline-block; background-color: ${c.backgroundColor || '#6366f1'}; color: ${c.textColor || '#ffffff'}; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px;">${c.text || t('template_builder.default_button')}</a>`;
        default:
            return '';
    }
};

// Update iframe content
const updatePreview = () => {
    if (!iframeRef.value) return;
    
    const html = generatePreviewHtml();
    const iframe = iframeRef.value;
    const doc = iframe.contentDocument || iframe.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();
};

// Watch for changes
watch([() => props.blocks, () => props.settings, () => props.theme], () => {
    updatePreview();
}, { deep: true });

onMounted(() => {
    updatePreview();
});
</script>

<template>
    <div class="flex h-full flex-col bg-slate-100 dark:bg-slate-950">
        <!-- Toolbar -->
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 py-2 dark:border-slate-800 dark:bg-slate-900">
            <!-- Device selector -->
            <div class="flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                <button 
                    @click="$emit('update:mode', 'desktop')"
                    :class="mode === 'desktop' ? 'bg-white shadow dark:bg-slate-700' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                >
                    <svg class="mx-auto h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </button>
                <button 
                    @click="$emit('update:mode', 'tablet')"
                    :class="mode === 'tablet' ? 'bg-white shadow dark:bg-slate-700' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                >
                    <svg class="mx-auto h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </button>
                <button 
                    @click="$emit('update:mode', 'mobile')"
                    :class="mode === 'mobile' ? 'bg-white shadow dark:bg-slate-700' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                >
                    <svg class="mx-auto h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </button>
            </div>

            <!-- Theme toggle -->
            <div class="flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                <button 
                    @click="$emit('update:theme', 'light')"
                    :class="theme === 'light' ? 'bg-white shadow dark:bg-slate-700' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    {{ $t('template_builder.light') }}
                </button>
                <button 
                    @click="$emit('update:theme', 'dark')"
                    :class="theme === 'dark' ? 'bg-white shadow dark:bg-slate-700' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    {{ $t('template_builder.dark') }}
                </button>
            </div>
        </div>

        <!-- Preview area -->
        <div class="flex flex-1 items-start justify-center overflow-auto p-6">
            <div 
                class="relative transition-all duration-300"
                :style="{ width: deviceDimensions[mode].width, maxWidth: '100%' }"
            >
                <!-- Device frame for mobile/tablet -->
                <div 
                    v-if="mode !== 'desktop'"
                    class="rounded-3xl border-4 border-slate-800 bg-slate-800 p-2 shadow-xl dark:border-slate-600"
                >
                    <!-- Notch for mobile -->
                    <div v-if="mode === 'mobile'" class="mx-auto mb-2 h-6 w-24 rounded-full bg-slate-700"></div>
                    
                    <iframe 
                        ref="iframeRef"
                        class="h-[600px] w-full rounded-2xl bg-white"
                        :style="{ height: mode === 'mobile' ? '600px' : '800px' }"
                    ></iframe>
                </div>

                <!-- Desktop preview (no frame) -->
                <iframe 
                    v-else
                    ref="iframeRef"
                    class="min-h-[600px] w-full rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700"
                ></iframe>
            </div>
        </div>
    </div>
</template>
