<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
    blocks: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    thumbnail: {
        type: String,
        default: null,
    },
});

const iframeRef = ref(null);
const containerRef = ref(null);

// Default settings
const defaultSettings = {
    width: 600,
    background_color: '#f4f4f4',
    content_background: '#ffffff',
    font_family: 'Arial, Helvetica, sans-serif',
    primary_color: '#6366f1',
    text_color: '#1e293b',
    link_color: '#6366f1',
};

const mergedSettings = computed(() => ({
    ...defaultSettings,
    ...props.settings,
}));

// Convert block to HTML
const blockToHtml = (block) => {
    const c = block.content || {};
    const textColor = mergedSettings.value.text_color;
    
    switch (block.type) {
        case 'header':
            const logoHtml = c.logo 
                ? `<img src="${c.logo}" style="width: ${c.logoWidth || 150}px; margin: 0 auto; display: block;" />`
                : `<div style="padding: 15px; text-align: center; color: rgba(255,255,255,0.7); font-size: 14px;">Logo</div>`;
            return `<div style="background-color: ${c.backgroundColor || '#6366f1'}; padding: ${c.padding || '15px'}; text-align: ${c.alignment || 'center'};">${logoHtml}</div>`;
        
        case 'text':
            return `<div style="padding: ${c.padding || '12px 15px'}; text-align: ${c.alignment || 'left'}; color: ${textColor}; font-size: 13px; line-height: 1.5;">${c.html || '<p style="color: #94a3b8;">Kliknij, aby edytować tekst...</p>'}</div>`;
        
        case 'image':
            if (!c.src) return `<div style="background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%); height: 100px; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px;"><svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>`;
            const imgHtml = `<img src="${c.src}" alt="${c.alt || ''}" style="display: block; margin: 0 auto; max-width: 100%; height: auto;" />`;
            return c.href ? `<a href="${c.href}">${imgHtml}</a>` : imgHtml;
        
        case 'button':
            return `<div style="padding: 12px; text-align: ${c.alignment || 'center'};">
                <a href="${c.href || '#'}" style="display: inline-block; background-color: ${c.backgroundColor || '#6366f1'}; color: ${c.textColor || '#ffffff'}; padding: ${c.padding || '10px 20px'}; border-radius: ${c.borderRadius || '6px'}; text-decoration: none; font-weight: 500; font-size: 13px;">${c.text || 'Przycisk'}</a>
            </div>`;
        
        case 'divider':
            return `<div style="padding: 8px 15px;"><hr style="border: none; border-top: 1px solid ${c.color || '#e2e8f0'};" /></div>`;
        
        case 'spacer':
            return `<div style="height: ${c.height || '20px'};"></div>`;

        case 'columns':
            const columnCount = c.columns || 2;
            const columnBlocks = c.columnBlocks || [];
            let columnsHtml = '<div style="display: flex; gap: 10px; padding: 10px;">';
            for (let i = 0; i < columnCount; i++) {
                const colContent = columnBlocks[i] || [];
                columnsHtml += `<div style="flex: 1; min-width: 0; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 6px; padding: 8px;">`;
                if (colContent.length > 0) {
                    colContent.forEach(nestedBlock => {
                        columnsHtml += blockToHtml(nestedBlock);
                    });
                } else {
                    columnsHtml += `<div style="height: 40px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 11px;">Kolumna ${i + 1}</div>`;
                }
                columnsHtml += '</div>';
            }
            columnsHtml += '</div>';
            return columnsHtml;
        
        case 'product':
            return `<div style="padding: 12px; display: flex; gap: 10px; align-items: flex-start;">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%); border-radius: 6px; flex-shrink: 0;">
                    ${c.image ? `<img src="${c.image}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;" />` : ''}
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 13px; font-weight: 600; color: ${textColor}; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${c.title || 'Opis produktu...'}</div>
                    <div style="font-size: 14px; font-weight: 700; color: ${mergedSettings.value.primary_color};">${c.price || '99.00'} ${c.currency || 'PLN'}</div>
                </div>
            </div>`;
        
        case 'social':
            const icons = c.icons || [];
            let socialHtml = '<div style="padding: 12px; text-align: center;">';
            if (icons.length > 0) {
                socialHtml += '<div style="display: inline-flex; gap: 8px;">';
                icons.forEach(() => {
                    socialHtml += '<div style="width: 28px; height: 28px; background: #64748b; border-radius: 50%;"></div>';
                });
                socialHtml += '</div>';
            } else {
                socialHtml += '<div style="display: inline-flex; gap: 8px;"><div style="width: 28px; height: 28px; background: #cbd5e1; border-radius: 50%;"></div><div style="width: 28px; height: 28px; background: #cbd5e1; border-radius: 50%;"></div><div style="width: 28px; height: 28px; background: #cbd5e1; border-radius: 50%;"></div></div>';
            }
            socialHtml += '</div>';
            return socialHtml;
        
        case 'footer':
            return `<div style="background-color: ${c.backgroundColor || '#1e293b'}; color: ${c.textColor || '#94a3b8'}; padding: 15px; text-align: center; font-size: 11px;">
                <div style="font-weight: 600; margin-bottom: 4px;">${c.companyName || 'Nazwa firmy'}</div>
                ${c.address ? `<div style="opacity: 0.7; margin-bottom: 8px;">${c.address}</div>` : ''}
                <div style="opacity: 0.5;">${c.unsubscribeText || 'Wypisz się'}</div>
            </div>`;
        
        default:
            return `<div style="padding: 12px; text-align: center; color: #94a3b8; font-size: 12px; background: #f8fafc;">${block.type}</div>`;
    }
};

// Generate preview HTML
const generatePreviewHtml = () => {
    const s = mergedSettings.value;
    
    let blocksHtml = '';
    for (const block of (props.blocks || [])) {
        blocksHtml += blockToHtml(block);
    }

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { 
            height: 100%;
            overflow: hidden;
        }
        body { 
            background-color: ${s.background_color}; 
            font-family: ${s.font_family}; 
            color: ${s.text_color};
            padding: 10px;
        }
        .email-container {
            max-width: ${s.width}px;
            margin: 0 auto;
            background-color: ${s.content_background};
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        img { max-width: 100%; height: auto; }
        a { color: ${s.link_color}; }
        p { margin: 0; }
    </style>
</head>
<body>
    <div class="email-container">
        ${blocksHtml || '<div style="padding: 40px; text-align: center; color: #94a3b8;">Pusty szablon</div>'}
    </div>
</body>
</html>`;
};

// Update iframe content
const updatePreview = async () => {
    await nextTick();
    if (!iframeRef.value) return;
    
    const html = generatePreviewHtml();
    const iframe = iframeRef.value;
    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    if (doc) {
        doc.open();
        doc.write(html);
        doc.close();
    }
};

// Has blocks to display
const hasBlocks = computed(() => props.blocks && props.blocks.length > 0);

// Watch for changes
watch([() => props.blocks, () => props.settings], () => {
    if (hasBlocks.value) {
        updatePreview();
    }
}, { deep: true });

onMounted(() => {
    if (hasBlocks.value) {
        updatePreview();
    }
});
</script>

<template>
    <div ref="containerRef" class="template-preview-card relative h-full w-full overflow-hidden">
        <!-- Dynamic preview from blocks -->
        <div v-if="hasBlocks" class="preview-wrapper absolute inset-0">
            <iframe 
                ref="iframeRef"
                class="preview-iframe pointer-events-none"
                scrolling="no"
                frameborder="0"
            ></iframe>
        </div>
        
        <!-- Static thumbnail fallback -->
        <div 
            v-else-if="thumbnail" 
            class="h-full w-full bg-cover bg-top"
            :style="{ backgroundImage: `url(${thumbnail})` }"
        ></div>
        
        <!-- Empty state -->
        <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
            <div class="flex flex-col items-center text-slate-300 dark:text-slate-600">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                </svg>
            </div>
        </div>
    </div>
</template>

<style scoped>
.template-preview-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.preview-wrapper {
    transform-origin: top left;
}

.preview-iframe {
    width: 620px;
    height: 800px;
    transform: scale(0.35);
    transform-origin: top left;
    border: none;
    background: transparent;
}

/* Responsive scaling for different card sizes */
@media (min-width: 1280px) {
    .preview-iframe {
        transform: scale(0.38);
    }
}

@media (max-width: 768px) {
    .preview-iframe {
        transform: scale(0.32);
    }
}
</style>
