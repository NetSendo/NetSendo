<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    funnel: Object,
    lists: Array,
    forms: Array,
    messages: Array,
    stepTypes: Object,
    delayUnits: Object,
    conditionTypes: Object,
    actionTypes: Object,
    goalTypes: Object,
    waitUntilTypes: Object,
    // Fields a "field has value" condition can test: { standard: [name], custom: [{ name, label }] }
    conditionFields: {
        type: Object,
        default: () => ({ standard: [], custom: [] }),
    },
    triggerTypes: Object,
    nodes: {
        type: Array,
        default: () => [],
    },
    edges: {
        type: Array,
        default: () => [],
    },
});

const isEditing = computed(() => !!props.funnel?.id);

// Form data
const formData = useForm({
    name: props.funnel?.name || '',
    trigger_type: props.funnel?.trigger_type || 'list_signup',
    trigger_list_id: props.funnel?.trigger_list_id || '',
    trigger_form_id: props.funnel?.trigger_form_id || '',
    trigger_tag: props.funnel?.trigger_tag || '',
    settings: props.funnel?.settings || {},
    nodes: props.nodes || [],
    edges: props.edges || [],
});

// Flow state
const nodes = ref(JSON.parse(JSON.stringify(props.nodes || [])));
const edges = ref(JSON.parse(JSON.stringify(props.edges || [])));
const selectedNode = ref(null);
const isDragging = ref(false);
const canvasRef = ref(null);
const canvasOffset = ref({ x: 0, y: 0 });
const zoom = ref(1);

// Next node ID
const nextNodeId = ref(
    Math.max(
        ...nodes.value.map(n => parseInt(n.id) || 0),
        0
    ) + 1
);

// Node types configuration (enterprise-grade with all step types)
const nodeConfig = {
    start: { icon: '🚀', color: 'bg-green-500', label: 'funnels.builder.nodes.start', gradient: 'from-green-400 to-green-600' },
    email: { icon: '✉️', color: 'bg-blue-500', label: 'funnels.builder.nodes.email', gradient: 'from-blue-400 to-blue-600' },
    sms: { icon: '📱', color: 'bg-cyan-500', label: 'funnels.builder.nodes.sms', gradient: 'from-cyan-400 to-cyan-600' },
    delay: { icon: '⏱️', color: 'bg-yellow-500', label: 'funnels.builder.nodes.delay', gradient: 'from-yellow-400 to-yellow-600' },
    wait_until: { icon: '📅', color: 'bg-amber-500', label: 'funnels.builder.nodes.wait_until', gradient: 'from-amber-400 to-amber-600' },
    condition: { icon: '🔀', color: 'bg-purple-500', label: 'funnels.builder.nodes.condition', gradient: 'from-purple-400 to-purple-600' },
    action: { icon: '⚡', color: 'bg-orange-500', label: 'funnels.builder.nodes.action', gradient: 'from-orange-400 to-orange-600' },
    split: { icon: '🎯', color: 'bg-pink-500', label: 'funnels.builder.nodes.split', gradient: 'from-pink-400 to-pink-600' },
    goal: { icon: '🏆', color: 'bg-emerald-500', label: 'funnels.builder.nodes.goal', gradient: 'from-emerald-400 to-emerald-600' },
    end: { icon: '🏁', color: 'bg-gray-500', label: 'funnels.builder.nodes.end', gradient: 'from-gray-400 to-gray-600' },
};

// A/B variants: a stable key names each one's handle (`variant-<key>`) and the
// test results kept for it, so renaming or removing another variant keeps both
const newVariantKey = () => `v${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
const variantLetter = (index) => String.fromCharCode(65 + (index % 26));
const variantHandle = (variant) => `variant-${variant.key}`;
const newVariant = (index, weight) => ({
    key: newVariantKey(),
    name: t('funnels.builder.variant_default_name', { letter: variantLetter(index) }),
    weight,
});

// Undo/Redo history
const history = ref([]);
const historyIndex = ref(-1);
const maxHistory = 50;

const saveHistory = () => {
    const state = {
        nodes: JSON.parse(JSON.stringify(nodes.value)),
        edges: JSON.parse(JSON.stringify(edges.value)),
    };

    // Remove any future states if we're not at the end
    if (historyIndex.value < history.value.length - 1) {
        history.value = history.value.slice(0, historyIndex.value + 1);
    }

    history.value.push(state);
    if (history.value.length > maxHistory) {
        history.value.shift();
    }
    historyIndex.value = history.value.length - 1;
};

const undo = () => {
    if (historyIndex.value > 0) {
        historyIndex.value--;
        const state = history.value[historyIndex.value];
        nodes.value = JSON.parse(JSON.stringify(state.nodes));
        edges.value = JSON.parse(JSON.stringify(state.edges));
        selectedNode.value = null;
    }
};

const redo = () => {
    if (historyIndex.value < history.value.length - 1) {
        historyIndex.value++;
        const state = history.value[historyIndex.value];
        nodes.value = JSON.parse(JSON.stringify(state.nodes));
        edges.value = JSON.parse(JSON.stringify(state.edges));
        selectedNode.value = null;
    }
};

const canUndo = computed(() => historyIndex.value > 0);
const canRedo = computed(() => historyIndex.value < history.value.length - 1);

// Initialize with start node if empty
onMounted(() => {
    if (nodes.value.length === 0) {
        addNode('start', 250, 50);
    }
});

// Add node with proper defaults for each type
const addNode = (type, x = null, y = null) => {
    saveHistory();

    const id = `new-${nextNodeId.value++}`;
    const posX = x ?? 250;
    const posY = y ?? (nodes.value.length * 120 + 50);

    // Default data based on step type
    const defaultData = {
        name: null,
        message_id: null,
        // Delay defaults
        delay_value: type === 'delay' ? 1 : null,
        delay_unit: type === 'delay' ? 'days' : null,
        // Condition defaults
        condition_type: type === 'condition' ? 'email_opened' : null,
        condition_config: {},
        // Wait & retry defaults (condition steps)
        wait_for_condition: false,
        retry_enabled: false,
        retry_max_attempts: 3,
        retry_interval_value: 24,
        retry_interval_unit: 'hours',
        retry_message_id: null,
        retry_exhausted_action: 'continue',
        // Action defaults
        action_type: type === 'action' ? 'add_tag' : null,
        action_config: {},
        // SMS defaults
        sms_content: type === 'sms' ? '' : null,
        // Wait Until defaults
        wait_until_type: type === 'wait_until' ? 'specific_date' : null,
        wait_until_date: null,
        wait_until_time: null,
        wait_until_day: null,
        wait_until_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        // Goal defaults
        goal_name: type === 'goal' ? '' : null,
        goal_type: type === 'goal' ? 'purchase' : null,
        goal_value: null,
        goal_config: {},
        // Split (A/B) defaults
        split_variants: type === 'split' ? [newVariant(0, 50), newVariant(1, 50)] : null,
    };

    const newNode = {
        id,
        type,
        position: { x: posX, y: posY },
        data: defaultData,
    };

    nodes.value.push(newNode);

    // Auto-select new node
    selectedNode.value = newNode;

    return id;
};

// Remove node
const removeNode = (nodeId) => {
    const index = nodes.value.findIndex(n => n.id === nodeId);
    if (index !== -1) {
        // Don't remove start node
        if (nodes.value[index].type === 'start') return;

        saveHistory();
        nodes.value.splice(index, 1);

        // Remove related edges
        edges.value = edges.value.filter(
            e => e.source !== nodeId && e.target !== nodeId
        );

        if (selectedNode.value?.id === nodeId) {
            selectedNode.value = null;
        }
    }
};

// Select node
const selectNode = (node) => {
    selectedNode.value = node;
};

// Update selected node data
const updateNodeData = (key, value) => {
    if (selectedNode.value) {
        selectedNode.value.data[key] = value;
    }
};

// Merge changes into the selected action's config; an emptied key is removed
const updateActionConfig = (changes) => {
    if (!selectedNode.value) return;

    const config = { ...selectedNode.value.data.action_config, ...changes };
    Object.keys(changes).forEach((key) => {
        if (config[key] === '' || config[key] === null) {
            delete config[key];
        }
    });

    updateNodeData('action_config', config);
};

// Merge changes into the selected condition's config; an emptied key is removed
const updateConditionConfig = (changes) => {
    if (!selectedNode.value) return;

    const config = { ...selectedNode.value.data.condition_config, ...changes };
    Object.keys(changes).forEach((key) => {
        if (config[key] === '' || config[key] === null) {
            delete config[key];
        }
    });

    updateNodeData('condition_config', config);
};

// Split (A/B) variants of the selected node
const addVariant = () => {
    const variants = selectedNode.value?.data.split_variants || [];
    if (variants.length >= 5) return;

    updateNodeData('split_variants', [...variants, newVariant(variants.length, Math.floor(100 / (variants.length + 1)))]);
};

const updateVariant = (index, changes) => {
    const variants = [...selectedNode.value.data.split_variants];
    variants[index] = { ...variants[index], ...changes };
    updateNodeData('split_variants', variants);
};

// A removed variant takes its path along
const removeVariant = (index) => {
    const node = selectedNode.value;
    const handle = variantHandle(node.data.split_variants[index]);

    saveHistory();
    edges.value = edges.value.filter((e) => !(e.source === node.id && e.sourceHandle === handle));
    updateNodeData('split_variants', node.data.split_variants.filter((_, i) => i !== index));
};

// Where a variant's subscribers go: its own path, else the default one
const getVariantPath = (node, variant) => {
    const own = edges.value.find((e) => e.source === node.id && e.sourceHandle === variantHandle(variant));
    if (own) return getTargetName(own.target);

    const fallback = edges.value.find((e) => e.source === node.id && (e.sourceHandle || 'default') === 'default');
    if (fallback) return `${t('funnels.builder.split_default_path')}: ${getTargetName(fallback.target)}`;

    return t('funnels.builder.split_path_ends');
};

const notifyPlaceholders = ['{{subscriber_email}}', '{{subscriber_name}}', '{{funnel_name}}'].join(', ');
const fieldOperators = ['equals', 'not_equals', 'contains', 'not_empty', 'empty'];
const valuelessFieldOperators = ['empty', 'not_empty'];

// A field saved before the builder offered a list (typed by hand, or since deleted)
// stays selectable instead of being silently replaced
const unlistedConditionField = computed(() => {
    const field = selectedNode.value?.data?.condition_config?.field;
    if (!field) return null;

    const listed = (props.conditionFields?.standard ?? []).includes(field)
        || (props.conditionFields?.custom ?? []).some((custom) => custom.name === field);

    return listed ? null : field;
});

// What a standard field holds, where the value to type is not obvious
const conditionValueHint = computed(() => {
    const config = selectedNode.value?.data?.condition_config ?? {};
    if (!(props.conditionFields?.standard ?? []).includes(config.field)) return null;

    if (['subscribed_at', 'confirmed_at'].includes(config.field)) {
        return t('funnels.builder.condition_config.value_hints.date');
    }

    return ['gender', 'language'].includes(config.field)
        ? t(`funnels.builder.condition_config.value_hints.${config.field}`)
        : null;
});
const weekdays = [1, 2, 3, 4, 5, 6, 7];

// A "move to list" step without its own source list moves from this list — only a
// list signup funnel has one (a trigger list kept after switching the type is ignored)
const signupListName = computed(() => {
    if (formData.trigger_type !== 'list_signup' || !formData.trigger_list_id) return null;

    return props.lists?.find((list) => String(list.id) === String(formData.trigger_list_id))?.name ?? null;
});

// Connect nodes. A step follows one path per handle (a condition has "yes" and
// "no"), so a new connection replaces the one it had on that handle
const connectNodes = (sourceId, targetId, handleId = 'default') => {
    const sameHandle = (e) => e.source === sourceId && (e.sourceHandle || 'default') === handleId;

    if (edges.value.some((e) => sameHandle(e) && e.target === targetId)) return;

    saveHistory();
    edges.value = edges.value.filter((e) => !sameHandle(e));
    edges.value.push({
        id: `e${sourceId}-${targetId}-${handleId}`,
        source: sourceId,
        target: targetId,
        sourceHandle: handleId,
    });
};

// Disconnect nodes
const disconnectEdge = (edgeId) => {
    edges.value = edges.value.filter(e => e.id !== edgeId);
};

// Drag and drop handlers
const onDragStart = (e, type) => {
    e.dataTransfer.setData('nodeType', type);
    e.dataTransfer.effectAllowed = 'copy';
};

const onDrop = (e) => {
    const type = e.dataTransfer.getData('nodeType');
    if (!type || type === 'start') return;

    const rect = canvasRef.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / zoom.value - 80;
    const y = (e.clientY - rect.top) / zoom.value;

    addNode(type, x, y);
};

const onDragOver = (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
};

// Node drag
const nodeOffsetRef = ref({ x: 0, y: 0 });

const onNodeMouseDown = (e, node) => {
    isDragging.value = true;
    selectNode(node);

    nodeOffsetRef.value = {
        x: e.clientX - node.position.x * zoom.value,
        y: e.clientY - node.position.y * zoom.value,
    };

    document.addEventListener('mousemove', onNodeMouseMove);
    document.addEventListener('mouseup', onNodeMouseUp);
};

const onNodeMouseMove = (e) => {
    if (!isDragging.value || !selectedNode.value) return;

    selectedNode.value.position.x = (e.clientX - nodeOffsetRef.value.x) / zoom.value;
    selectedNode.value.position.y = (e.clientY - nodeOffsetRef.value.y) / zoom.value;
};

const onNodeMouseUp = () => {
    isDragging.value = false;
    document.removeEventListener('mousemove', onNodeMouseMove);
    document.removeEventListener('mouseup', onNodeMouseUp);
};

// Save funnel
const saving = ref(false);

// Toast notification
const toast = ref({
    show: false,
    message: '',
    type: 'success',
});

const showToast = (message, type = 'success') => {
    toast.value = {
        show: true,
        message,
        type,
    };

    setTimeout(() => {
        toast.value.show = false;
    }, 3000);
};

const saveFunnel = () => {
    saving.value = true;

    formData.nodes = nodes.value;
    formData.edges = edges.value;

    if (isEditing.value) {
        formData.put(route('funnels.update', props.funnel.id), {
            onSuccess: () => showToast(t('funnels.notifications.saved')),
            onFinish: () => saving.value = false,
        });
    } else {
        formData.post(route('funnels.store'), {
            onSuccess: () => showToast(t('funnels.notifications.created')),
            onFinish: () => saving.value = false,
        });
    }
};

// Get edges for a node
const getNodeEdges = (nodeId) => {
    return edges.value.filter(e => e.source === nodeId);
};

const getEdgeLabel = (edge) => {
    if (edge.sourceHandle === 'yes') return `${t('common.yes')}: `;
    if (edge.sourceHandle === 'no') return `${t('common.no')}: `;

    const source = nodes.value.find((n) => n.id === edge.source);
    if (source?.type !== 'split') return '';

    const variant = (source.data.split_variants || []).find((v) => variantHandle(v) === edge.sourceHandle);
    return `${variant ? variant.name : t('funnels.builder.split_default_path')}: `;
};

// A node's connection handles. Each leads one path, so a new connection on a
// handle replaces its previous one: a condition has "yes" and "no", an A/B test
// one per variant plus the default path taken by variants without their own
const getNodeHandles = (node) => {
    if (node.type === 'end') return [];

    if (node.type === 'condition') {
        return [
            { id: 'yes', symbol: '✓', title: t('common.yes'), color: 'bg-green-500 hover:bg-green-600', offsetX: -120 },
            { id: 'no', symbol: '✗', title: t('common.no'), color: 'bg-red-500 hover:bg-red-600', offsetX: 120 },
        ];
    }

    const defaultHandle = { id: 'default', symbol: '+', title: null, color: 'bg-indigo-500 hover:bg-indigo-600', offsetX: 0 };

    if (node.type === 'split') {
        const variants = node.data.split_variants || [];

        return [
            ...variants.map((variant, index) => ({
                id: variantHandle(variant),
                symbol: variantLetter(index),
                title: variant.name,
                color: 'bg-pink-500 hover:bg-pink-600',
                offsetX: (index - (variants.length - 1) / 2) * 180,
            })),
            { ...defaultHandle, title: t('funnels.builder.split_default_path') },
        ];
    }

    return [defaultHandle];
};

// Get connected target for display
const getTargetName = (targetId) => {
    const node = nodes.value.find(n => n.id === targetId);
    return node?.data?.name || (node ? t(nodeConfig[node.type].label) : t('funnels.builder.step_name'));
};

// Quick connect dropdown
const showConnectDropdown = ref(null);

const toggleConnectDropdown = (nodeId) => {
    showConnectDropdown.value = showConnectDropdown.value === nodeId ? null : nodeId;
};

const quickConnect = (sourceId, targetId, handleId = 'default') => {
    connectNodes(sourceId, targetId, handleId);
    showConnectDropdown.value = null;
};

// Zoom controls
const zoomIn = () => {
    zoom.value = Math.min(zoom.value + 0.1, 2);
};

const zoomOut = () => {
    zoom.value = Math.max(zoom.value - 0.1, 0.5);
};

const zoomReset = () => {
    zoom.value = 1;
};

const fitToView = () => {
    if (nodes.value.length === 0) return;

    const padding = 50;
    const minX = Math.min(...nodes.value.map(n => n.position.x)) - padding;
    const maxX = Math.max(...nodes.value.map(n => n.position.x)) + 200 + padding;
    const minY = Math.min(...nodes.value.map(n => n.position.y)) - padding;
    const maxY = Math.max(...nodes.value.map(n => n.position.y)) + 100 + padding;

    const containerWidth = canvasRef.value?.clientWidth || 800;
    const containerHeight = canvasRef.value?.clientHeight || 600;

    const scaleX = containerWidth / (maxX - minX);
    const scaleY = containerHeight / (maxY - minY);

    zoom.value = Math.min(Math.max(Math.min(scaleX, scaleY), 0.5), 1.5);
};

// Keyboard shortcuts
onMounted(() => {
    if (nodes.value.length === 0) {
        addNode('start', 250, 50);
    }

    // Save initial state
    saveHistory();

    // Keyboard shortcuts
    const handleKeyboard = (e) => {
        // Ctrl/Cmd + Z = Undo
        if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
            e.preventDefault();
            undo();
        }
        // Ctrl/Cmd + Shift + Z or Ctrl + Y = Redo
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
            e.preventDefault();
            redo();
        }
        // Delete key removes selected node
        if (e.key === 'Delete' && selectedNode.value) {
            removeNode(selectedNode.value.id);
        }
        // Escape deselects
        if (e.key === 'Escape') {
            selectedNode.value = null;
            showConnectDropdown.value = null;
        }
    };

    document.addEventListener('keydown', handleKeyboard);
});

// Get node validation status
const getNodeValidationStatus = (node) => {
    if (node.type === 'start' || node.type === 'end') return 'valid';

    // Check required fields based on type
    switch (node.type) {
        case 'email':
            return node.data.message_id ? 'valid' : 'warning';
        case 'sms':
            return node.data.sms_content?.trim() ? 'valid' : 'warning';
        case 'delay':
            return node.data.delay_value && node.data.delay_unit ? 'valid' : 'warning';
        case 'condition': {
            const config = node.data.condition_config || {};
            const configured = {
                link_clicked: config.url,
                tag_exists: config.tag,
                field_value: config.field,
                task_completed: config.task_id,
            };
            const hasBranch = edges.value.some((e) => e.source === node.id && ['yes', 'no'].includes(e.sourceHandle));
            if (!node.data.condition_type || !hasBranch) return 'warning';
            return node.data.condition_type in configured && !configured[node.data.condition_type] ? 'warning' : 'valid';
        }
        case 'wait_until':
            if (node.data.wait_until_type === 'specific_date') return node.data.wait_until_date ? 'valid' : 'warning';
            if (node.data.wait_until_type === 'day_of_week') return node.data.wait_until_day ? 'valid' : 'warning';
            return 'valid';
        case 'action': {
            const config = node.data.action_config || {};
            switch (node.data.action_type) {
                case 'add_tag':
                case 'remove_tag':
                    return config.tag ? 'valid' : 'warning';
                case 'copy_to_list':
                case 'move_to_list':
                    return config.list_id || config.to_list_id ? 'valid' : 'warning';
                case 'webhook':
                    return config.url ? 'valid' : 'warning';
                default:
                    return node.data.action_type ? 'valid' : 'warning';
            }
        }
        case 'goal':
            return node.data.goal_type ? 'valid' : 'warning';
        case 'split': {
            // Every variant needs somewhere to go: its own path or the default one
            const variants = node.data.split_variants || [];
            const handles = edges.value.filter((e) => e.source === node.id).map((e) => e.sourceHandle || 'default');
            const routed = handles.includes('default') || variants.every((v) => handles.includes(variantHandle(v)));
            return variants.length >= 2 && routed ? 'valid' : 'warning';
        }
        default:
            return 'valid';
    }
};
</script>

<template>
    <Head :title="isEditing ? t('funnels.edit') : t('funnels.create')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <button
                        @click="router.visit(route('funnels.index'))"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <input
                        v-model="formData.name"
                        type="text"
                        :placeholder="t('funnels.builder.name_placeholder')"
                        class="text-xl font-bold bg-transparent border-none focus:ring-0 p-0 text-gray-900 dark:text-gray-100 min-w-[200px]"
                    />
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="funnel?.status" :class="[
                        'px-3 py-1 rounded-full text-sm font-medium',
                        funnel.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                        funnel.status === 'paused' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' :
                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                    ]">
                        {{ funnel.status }}
                    </span>
                    <button
                        @click="saveFunnel"
                        :disabled="saving"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-medium rounded-lg transition-colors"
                    >
                        <svg v-if="saving" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ t('common.save') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="h-[calc(100vh-140px)] flex">
            <!-- Left Sidebar: Block Library -->
            <div class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 overflow-y-auto p-4 flex-shrink-0">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    {{ t('funnels.builder.blocks') }}
                </h3>

                <div class="space-y-2">
                    <div
                        v-for="(config, type) in nodeConfig"
                        :key="type"
                        :draggable="type !== 'start'"
                        @dragstart="(e) => onDragStart(e, type)"
                        :class="[
                            'flex items-center gap-3 p-3 rounded-lg border-2 border-dashed transition-all',
                            type === 'start'
                                ? 'border-gray-200 dark:border-gray-700 opacity-50 cursor-not-allowed'
                                : 'border-gray-300 dark:border-gray-600 hover:border-indigo-400 dark:hover:border-indigo-500 cursor-grab'
                        ]"
                    >
                        <span class="text-2xl">{{ config.icon }}</span>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ t(config.label) }}</span>
                    </div>
                </div>

                <!-- Trigger Settings -->
                <div class="mt-8">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                        {{ t('funnels.builder.trigger') }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.trigger_type') }}
                            </label>
                            <select
                                v-model="formData.trigger_type"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                                <option v-for="(label, key) in triggerTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>

                        <!-- List trigger -->
                        <div v-if="formData.trigger_type === 'list_signup'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.trigger_list') }}
                            </label>
                            <select
                                v-model="formData.trigger_list_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                                <option value="">{{ t('common.select') }}</option>
                                <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.name }}</option>
                            </select>
                        </div>

                        <!-- Form trigger -->
                        <div v-if="formData.trigger_type === 'form_submit'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.trigger_form') }}
                            </label>
                            <select
                                v-model="formData.trigger_form_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                                <option value="">{{ t('common.select') }}</option>
                                <option v-for="form in forms" :key="form.id" :value="form.id">{{ form.name }}</option>
                            </select>
                        </div>

                        <!-- Tag trigger -->
                        <div v-if="formData.trigger_type === 'tag_added'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.trigger_tag') }}
                            </label>
                            <input
                                v-model="formData.trigger_tag"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                                placeholder="e.g. purchase"
                                :placeholder="t('funnels.builder.trigger_tag_placeholder')"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center: Canvas -->
            <div
                ref="canvasRef"
                class="flex-1 bg-gray-100 dark:bg-gray-900 overflow-auto relative"
                @drop="onDrop"
                @dragover="onDragOver"
            >
                <!-- Canvas Toolbar -->
                <div class="absolute top-4 left-4 z-50 flex items-center gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-2 border border-gray-200 dark:border-gray-700">
                    <!-- Undo/Redo -->
                    <button
                        @click="undo"
                        :disabled="!canUndo"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        :title="t('common.undo') + ' (Ctrl+Z)'"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                    </button>
                    <button
                        @click="redo"
                        :disabled="!canRedo"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        :title="t('common.redo') + ' (Ctrl+Y)'"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6" />
                        </svg>
                    </button>

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Zoom Controls -->
                    <button
                        @click="zoomOut"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        :title="t('funnels.builder.zoom_out')"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" />
                        </svg>
                    </button>
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400 min-w-[3rem] text-center">
                        {{ Math.round(zoom * 100) }}%
                    </span>
                    <button
                        @click="zoomIn"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        :title="t('funnels.builder.zoom_in')"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                        </svg>
                    </button>
                    <button
                        @click="fitToView"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        :title="t('funnels.builder.fit_to_view')"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </button>
                    <button
                        @click="zoomReset"
                        class="px-2 py-1 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                        title="Reset zoom"
                    >
                        Reset
                    </button>
                </div>

                <!-- Grid background -->
                <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle, #6366f1 1px, transparent 1px); background-size: 20px 20px;"></div>

                <!-- Nodes -->
                <div
                    v-for="node in nodes"
                    :key="node.id"
                    :class="[
                        'absolute w-40 rounded-lg shadow-lg cursor-move transition-shadow',
                        selectedNode?.id === node.id ? 'ring-2 ring-indigo-500 shadow-xl' : '',
                        'bg-white dark:bg-gray-800'
                    ]"
                    :style="{
                        left: node.position.x + 'px',
                        top: node.position.y + 'px',
                        transform: `scale(${zoom})`
                    }"
                    @mousedown="(e) => onNodeMouseDown(e, node)"
                >
                    <!-- Node header -->
                    <div :class="['px-3 py-2 rounded-t-lg text-white font-medium flex items-center gap-2', nodeConfig[node.type]?.color || 'bg-gray-500']">
                        <span class="text-lg">{{ nodeConfig[node.type]?.icon }}</span>
                        <span class="truncate">{{ node.data?.name || t(nodeConfig[node.type]?.label) }}</span>
                    </div>

                    <!-- Node content -->
                    <div class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">
                        <template v-if="node.type === 'email'">
                            {{ node.data?.message_subject || t('funnels.builder.select_email') }}
                        </template>
                        <template v-else-if="node.type === 'delay'">
                            {{ node.data?.delay_display || `${node.data?.delay_value || 1} ${delayUnits[node.data?.delay_unit] || t('funnels.builder.days')}` }}
                        </template>
                        <template v-else-if="node.type === 'condition'">
                            {{ conditionTypes[node.data?.condition_type] || t('funnels.builder.condition') }}
                        </template>
                        <template v-else-if="node.type === 'action'">
                            {{ actionTypes[node.data?.action_type] || t('funnels.builder.action') }}
                        </template>
                        <template v-else-if="node.type === 'split'">
                            {{ (node.data?.split_variants || []).map((variant, index) => `${variantLetter(index)} ${variant.weight || 0}%`).join(' · ') }}
                        </template>
                        <template v-else>
                            {{ t('funnels.builder.click_to_configure') }}
                        </template>
                    </div>

                    <!-- Connection handles: one path each (condition: yes/no, A/B test: one per variant and the default path) -->
                    <div
                        v-if="getNodeHandles(node).length"
                        :class="['absolute -bottom-3 flex left-1/2 -translate-x-1/2', node.type === 'condition' ? 'gap-8' : 'gap-1']"
                    >
                        <div v-for="handle in getNodeHandles(node)" :key="handle.id" class="relative">
                            <button
                                @click.stop="toggleConnectDropdown(`${node.id}-${handle.id}`)"
                                :class="['w-6 h-6 rounded-full text-white flex items-center justify-center text-xs shadow', handle.color]"
                                :title="handle.title"
                            >
                                {{ handle.symbol }}
                            </button>

                            <!-- Connect dropdown -->
                            <div
                                v-if="showConnectDropdown === `${node.id}-${handle.id}`"
                                class="absolute top-8 left-1/2 -translate-x-1/2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-2 min-w-[150px] z-50"
                            >
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 px-2">
                                    {{ handle.title ? `${handle.title} — ` : '' }}{{ t('funnels.builder.connect_to') }}
                                </div>
                                <button
                                    v-for="targetNode in nodes.filter(n => n.id !== node.id && n.type !== 'start')"
                                    :key="targetNode.id"
                                    @click="quickConnect(node.id, targetNode.id, handle.id)"
                                    class="w-full text-left px-2 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ nodeConfig[targetNode.type]?.icon }} {{ targetNode.data?.name || t(nodeConfig[targetNode.type]?.label) }}
                                </button>
                                <div class="border-t border-gray-200 dark:border-gray-700 mt-2 pt-2">
                                    <template v-for="(config, type) in nodeConfig" :key="type">
                                        <button
                                            v-if="type !== 'start'"
                                            @click="() => { const newId = addNode(type, node.position.x + handle.offsetX, node.position.y + 120); quickConnect(node.id, newId, handle.id); }"
                                            class="w-full text-left px-2 py-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            {{ config.icon }} {{ t('funnels.builder.new') }} {{ t(config.label) }}
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delete button -->
                    <button
                        v-if="node.type !== 'start'"
                        @click.stop="removeNode(node.id)"
                        class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center text-xs shadow opacity-0 hover:opacity-100 transition-opacity"
                    >
                        ×
                    </button>
                </div>

                <!-- Edges (SVG lines) -->
                <svg class="absolute inset-0 pointer-events-none" style="overflow: visible;">
                    <defs>
                        <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                            <polygon points="0 0, 10 3.5, 0 7" fill="#6366f1" />
                        </marker>
                    </defs>
                    <line
                        v-for="edge in edges"
                        :key="edge.id"
                        :x1="(nodes.find(n => n.id === edge.source)?.position?.x || 0) + 80"
                        :y1="(nodes.find(n => n.id === edge.source)?.position?.y || 0) + 60"
                        :x2="(nodes.find(n => n.id === edge.target)?.position?.x || 0) + 80"
                        :y2="(nodes.find(n => n.id === edge.target)?.position?.y || 0)"
                        stroke="#6366f1"
                        stroke-width="2"
                        marker-end="url(#arrowhead)"
                    />
                </svg>
            </div>

            <!-- Right Sidebar: Properties -->
            <div v-if="selectedNode" class="w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 overflow-y-auto p-4 flex-shrink-0">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ nodeConfig[selectedNode.type]?.icon }} {{ t(nodeConfig[selectedNode.type]?.label) }}
                    </h3>
                    <button @click="selectedNode = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('funnels.builder.step_name') }}
                        </label>
                        <input
                            :value="selectedNode.data.name"
                            @input="(e) => updateNodeData('name', e.target.value)"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            :placeholder="t(nodeConfig[selectedNode.type]?.label)"
                        />
                    </div>

                    <!-- Email step -->
                    <template v-if="selectedNode.type === 'email'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.select_email') }}
                            </label>
                            <select
                                :value="selectedNode.data.message_id"
                                @change="(e) => updateNodeData('message_id', e.target.value)"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                                <option value="">{{ t('common.select') }}</option>
                                <option v-for="msg in messages" :key="msg.id" :value="msg.id">{{ msg.subject }}</option>
                            </select>
                        </div>
                    </template>

                    <!-- Delay step -->
                    <template v-if="selectedNode.type === 'delay'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.delay_value') }}
                                </label>
                                <input
                                    :value="selectedNode.data.delay_value"
                                    @input="(e) => updateNodeData('delay_value', parseInt(e.target.value))"
                                    type="number"
                                    min="1"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.delay_unit') }}
                                </label>
                                <select
                                    :value="selectedNode.data.delay_unit"
                                    @change="(e) => updateNodeData('delay_unit', e.target.value)"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                >
                                    <option v-for="(label, key) in delayUnits" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                        </div>
                    </template>

                    <!-- Condition step -->
                    <template v-if="selectedNode.type === 'condition'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.condition_type') }}
                            </label>
                            <select
                                :value="selectedNode.data.condition_type"
                                @change="(e) => updateNodeData('condition_type', e.target.value)"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                                <option v-for="(label, key) in conditionTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>

                        <!-- Email the condition is about: empty means the last one this funnel sent -->
                        <div v-if="['email_opened', 'email_clicked', 'link_clicked'].includes(selectedNode.data.condition_type)">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.condition_config.message') }}
                            </label>
                            <select
                                :value="selectedNode.data.condition_config?.message_id || ''"
                                @change="(e) => updateConditionConfig({ message_id: e.target.value })"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                                <option value="">
                                    {{ selectedNode.data.condition_type === 'link_clicked' ? t('funnels.builder.condition_config.any_message') : t('funnels.builder.condition_config.last_message') }}
                                </option>
                                <option v-for="msg in messages" :key="msg.id" :value="msg.id">{{ msg.subject }}</option>
                            </select>
                        </div>

                        <div v-if="selectedNode.data.condition_type === 'link_clicked'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.condition_config.url') }}
                            </label>
                            <input
                                :value="selectedNode.data.condition_config?.url"
                                @input="(e) => updateConditionConfig({ url: e.target.value })"
                                type="url"
                                placeholder="https://..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <div v-if="selectedNode.data.condition_type === 'tag_exists'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.tag') }}
                            </label>
                            <input
                                :value="selectedNode.data.condition_config?.tag"
                                @input="(e) => updateConditionConfig({ tag: e.target.value })"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <template v-if="selectedNode.data.condition_type === 'field_value'">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.condition_config.field') }}
                                </label>
                                <select
                                    :value="selectedNode.data.condition_config?.field || ''"
                                    @change="(e) => updateConditionConfig({ field: e.target.value })"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                >
                                    <option value="">{{ t('funnels.builder.condition_config.choose_field') }}</option>
                                    <option v-if="unlistedConditionField" :value="unlistedConditionField">
                                        {{ t('funnels.builder.condition_config.unlisted_field', { field: unlistedConditionField }) }}
                                    </option>
                                    <optgroup v-if="conditionFields.standard?.length" :label="t('funnels.builder.condition_config.standard_fields_group')">
                                        <option v-for="name in conditionFields.standard" :key="name" :value="name">
                                            {{ t(`funnels.builder.condition_config.standard_fields.${name}`) }}
                                        </option>
                                    </optgroup>
                                    <optgroup v-if="conditionFields.custom?.length" :label="t('funnels.builder.condition_config.custom_fields_group')">
                                        <option v-for="field in conditionFields.custom" :key="field.name" :value="field.name">
                                            {{ field.label }} ({{ field.name }})
                                        </option>
                                    </optgroup>
                                </select>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('funnels.builder.condition_config.field_help') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.condition_config.operator') }}
                                </label>
                                <select
                                    :value="selectedNode.data.condition_config?.operator || 'equals'"
                                    @change="(e) => updateConditionConfig({ operator: e.target.value })"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                >
                                    <option v-for="operator in fieldOperators" :key="operator" :value="operator">
                                        {{ t(`funnels.builder.condition_config.operators.${operator}`) }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="!valuelessFieldOperators.includes(selectedNode.data.condition_config?.operator)">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.condition_config.value') }}
                                </label>
                                <input
                                    :value="selectedNode.data.condition_config?.value"
                                    @input="(e) => updateConditionConfig({ value: e.target.value })"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                />
                                <p v-if="conditionValueHint" class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ conditionValueHint }}</p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('funnels.builder.condition_config.comparison_help') }}</p>
                        </template>

                        <div v-if="selectedNode.data.condition_type === 'task_completed'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.task_config.task_id') }}
                            </label>
                            <input
                                :value="selectedNode.data.condition_config?.task_id"
                                @input="(e) => updateConditionConfig({ task_id: e.target.value })"
                                type="text"
                                :placeholder="t('funnels.builder.task_config.task_id_placeholder')"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('funnels.builder.task_config.task_id_help') }}</p>
                        </div>

                        <p class="text-xs text-amber-600 dark:text-amber-400">
                            {{ t('funnels.builder.condition_config.branches_help') }}
                        </p>

                        <!-- Wait & retry -->
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('funnels.builder.retry.title') }}
                            </h4>
                            <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    :checked="selectedNode.data.wait_for_condition"
                                    @change="(e) => updateNodeData('wait_for_condition', e.target.checked)"
                                    class="mt-0.5 rounded border-gray-300 dark:border-gray-600"
                                />
                                <span>
                                    {{ t('funnels.builder.retry.wait_for_condition') }}
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('funnels.builder.retry.wait_for_condition_desc') }}</span>
                                </span>
                            </label>

                            <template v-if="selectedNode.data.wait_for_condition">
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input
                                        type="checkbox"
                                        :checked="selectedNode.data.retry_enabled"
                                        @change="(e) => updateNodeData('retry_enabled', e.target.checked)"
                                        class="mt-0.5 rounded border-gray-300 dark:border-gray-600"
                                    />
                                    <span>
                                        {{ t('funnels.builder.retry.enabled') }}
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ t('funnels.builder.retry.enabled_desc') }}</span>
                                    </span>
                                </label>

                                <template v-if="selectedNode.data.retry_enabled">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ t('funnels.builder.retry.max_attempts') }}
                                        </label>
                                        <input
                                            :value="selectedNode.data.retry_max_attempts"
                                            @input="(e) => updateNodeData('retry_max_attempts', parseInt(e.target.value) || 1)"
                                            type="number"
                                            min="1"
                                            max="10"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                        />
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('funnels.builder.retry.max_attempts_help') }}</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                {{ t('funnels.builder.retry.interval') }}
                                            </label>
                                            <input
                                                :value="selectedNode.data.retry_interval_value"
                                                @input="(e) => updateNodeData('retry_interval_value', parseInt(e.target.value) || 1)"
                                                type="number"
                                                min="1"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                {{ t('funnels.builder.retry.interval_unit') }}
                                            </label>
                                            <select
                                                :value="selectedNode.data.retry_interval_unit || 'hours'"
                                                @change="(e) => updateNodeData('retry_interval_unit', e.target.value)"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                            >
                                                <option value="hours">{{ t('funnels.builder.retry.interval_hours') }}</option>
                                                <option value="days">{{ t('funnels.builder.retry.interval_days') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ t('funnels.builder.retry.reminder_message') }}
                                        </label>
                                        <select
                                            :value="selectedNode.data.retry_message_id || ''"
                                            @change="(e) => updateNodeData('retry_message_id', e.target.value || null)"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                        >
                                            <option value="">{{ t('funnels.builder.retry.same_as_original') }}</option>
                                            <option v-for="msg in messages" :key="msg.id" :value="msg.id">{{ msg.subject }}</option>
                                        </select>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ t('funnels.builder.retry.reminder_message_help') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ t('funnels.builder.retry.exhausted_action') }}
                                        </label>
                                        <select
                                            :value="selectedNode.data.retry_exhausted_action || 'continue'"
                                            @change="(e) => updateNodeData('retry_exhausted_action', e.target.value)"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                        >
                                            <option value="continue">{{ t('funnels.builder.retry.exhausted_continue') }}</option>
                                            <option value="exit">{{ t('funnels.builder.retry.exhausted_exit') }}</option>
                                            <option value="unsubscribe">{{ t('funnels.builder.retry.exhausted_unsubscribe') }}</option>
                                        </select>
                                    </div>
                                </template>
                            </template>
                        </div>
                    </template>

                    <!-- Action step -->
                    <template v-if="selectedNode.type === 'action'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.action_type') }}
                            </label>
                            <select
                                :value="selectedNode.data.action_type"
                                @change="(e) => updateNodeData('action_type', e.target.value)"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                                <option v-for="(label, key) in actionTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>

                        <!-- Tag input for add/remove tag -->
                        <div v-if="['add_tag', 'remove_tag'].includes(selectedNode.data.action_type)">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.tag') }}
                            </label>
                            <input
                                :value="selectedNode.data.action_config?.tag"
                                @input="(e) => updateNodeData('action_config', { ...selectedNode.data.action_config, tag: e.target.value })"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <!-- Source list for move: empty means the trigger list of a list signup funnel -->
                        <div v-if="selectedNode.data.action_type === 'move_to_list'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.source_list') }}
                            </label>
                            <select
                                :value="selectedNode.data.action_config?.from_list_id || ''"
                                @change="(e) => updateActionConfig({ from_list_id: e.target.value })"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                                <option value="">
                                    {{ signupListName ? t('funnels.builder.source_list_trigger', { list: signupListName }) : t('common.select') }}
                                </option>
                                <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.name }}</option>
                            </select>
                            <p
                                v-if="!signupListName && !selectedNode.data.action_config?.from_list_id"
                                class="text-xs text-amber-600 dark:text-amber-400 mt-1"
                            >
                                {{ t('funnels.builder.source_list_required') }}
                            </p>
                            <p v-else class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ t('funnels.builder.source_list_help') }}
                            </p>
                        </div>

                        <!-- Target list for move/copy -->
                        <div v-if="['move_to_list', 'copy_to_list'].includes(selectedNode.data.action_type)">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.target_list') }}
                            </label>
                            <select
                                :value="selectedNode.data.action_config?.list_id || selectedNode.data.action_config?.to_list_id || ''"
                                @change="(e) => updateActionConfig({ list_id: e.target.value, to_list_id: null })"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                                <option value="">{{ t('common.select') }}</option>
                                <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.name }}</option>
                            </select>
                        </div>

                        <!-- Webhook URL -->
                        <div v-if="selectedNode.data.action_type === 'webhook'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.webhook_url') }}
                            </label>
                            <input
                                :value="selectedNode.data.action_config?.url"
                                @input="(e) => updateNodeData('action_config', { ...selectedNode.data.action_config, url: e.target.value })"
                                type="url"
                                placeholder="https://..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <!-- Unsubscribe: empty means the trigger list of a list signup funnel -->
                        <div v-if="selectedNode.data.action_type === 'unsubscribe'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.unsubscribe_list') }}
                            </label>
                            <select
                                :value="selectedNode.data.action_config?.list_id || ''"
                                @change="(e) => updateActionConfig({ list_id: e.target.value })"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                                <option value="">
                                    {{ signupListName ? t('funnels.builder.source_list_trigger', { list: signupListName }) : t('common.select') }}
                                </option>
                                <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.name }}</option>
                            </select>
                            <p
                                v-if="!signupListName && !selectedNode.data.action_config?.list_id"
                                class="text-xs text-amber-600 dark:text-amber-400 mt-1"
                            >
                                {{ t('funnels.builder.unsubscribe_list_required') }}
                            </p>
                        </div>

                        <!-- Notification: empty recipient means the funnel owner -->
                        <template v-if="selectedNode.data.action_type === 'notify'">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.notify.email') }}
                                </label>
                                <input
                                    :value="selectedNode.data.action_config?.email"
                                    @input="(e) => updateActionConfig({ email: e.target.value })"
                                    type="email"
                                    :placeholder="t('funnels.builder.notify.email_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.notify.subject') }}
                                </label>
                                <input
                                    :value="selectedNode.data.action_config?.subject"
                                    @input="(e) => updateActionConfig({ subject: e.target.value })"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.notify.message') }}
                                </label>
                                <textarea
                                    :value="selectedNode.data.action_config?.message"
                                    @input="(e) => updateActionConfig({ message: e.target.value })"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                ></textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('funnels.builder.notify.placeholders_help', { placeholders: notifyPlaceholders }) }}</p>
                            </div>
                        </template>
                    </template>

                    <!-- SMS step -->
                    <template v-if="selectedNode.type === 'sms'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('funnels.builder.sms_content') }}
                            </label>
                            <textarea
                                :value="selectedNode.data.sms_content"
                                @input="(e) => updateNodeData('sms_content', e.target.value)"
                                rows="4"
                                maxlength="160"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                :placeholder="t('funnels.builder.sms_placeholder')"
                            ></textarea>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ selectedNode.data.sms_content?.length || 0 }}/160 {{ t('funnels.builder.characters') }}
                            </p>
                        </div>
                    </template>

                    <!-- Wait Until step -->
                    <template v-if="selectedNode.type === 'wait_until'">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.wait_until_type') }}
                                </label>
                                <select
                                    :value="selectedNode.data.wait_until_type"
                                    @change="(e) => updateNodeData('wait_until_type', e.target.value)"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                >
                                    <option v-for="(label, key) in waitUntilTypes" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>

                            <div v-if="selectedNode.data.wait_until_type === 'day_of_week'" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ t('funnels.builder.weekday') }}
                                    </label>
                                    <select
                                        :value="selectedNode.data.wait_until_day || ''"
                                        @change="(e) => updateNodeData('wait_until_day', parseInt(e.target.value) || null)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    >
                                        <option value="">{{ t('common.select') }}</option>
                                        <option v-for="day in weekdays" :key="day" :value="day">{{ t(`funnels.builder.weekdays.${day}`) }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ t('funnels.builder.time') }}
                                    </label>
                                    <input
                                        :value="selectedNode.data.wait_until_time"
                                        @input="(e) => updateNodeData('wait_until_time', e.target.value)"
                                        type="time"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    />
                                </div>
                            </div>

                            <p v-if="selectedNode.data.wait_until_type === 'business_hours'" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('funnels.builder.business_hours_help') }}
                            </p>

                            <div v-if="selectedNode.data.wait_until_type === 'specific_date'" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ t('funnels.builder.date') }}
                                    </label>
                                    <input
                                        :value="selectedNode.data.wait_until_date"
                                        @input="(e) => updateNodeData('wait_until_date', e.target.value)"
                                        type="date"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ t('funnels.builder.time') }}
                                    </label>
                                    <input
                                        :value="selectedNode.data.wait_until_time"
                                        @input="(e) => updateNodeData('wait_until_time', e.target.value)"
                                        type="time"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Goal step -->
                    <template v-if="selectedNode.type === 'goal'">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.goal_name') }}
                                </label>
                                <input
                                    :value="selectedNode.data.goal_name"
                                    @input="(e) => updateNodeData('goal_name', e.target.value)"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    :placeholder="t('funnels.builder.goal_name_placeholder')"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.goal_type') }}
                                </label>
                                <select
                                    :value="selectedNode.data.goal_type"
                                    @change="(e) => updateNodeData('goal_type', e.target.value)"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                >
                                    <option v-for="(label, key) in goalTypes" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div v-if="selectedNode.data.goal_type === 'purchase'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('funnels.builder.goal_value') }} (PLN)
                                </label>
                                <input
                                    :value="selectedNode.data.goal_value"
                                    @input="(e) => updateNodeData('goal_value', parseFloat(e.target.value) || null)"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Split (A/B Test) step -->
                    <template v-if="selectedNode.type === 'split'">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('funnels.builder.variants') }}
                                </label>
                                <button
                                    v-if="(selectedNode.data.split_variants?.length || 0) < 5"
                                    @click="addVariant"
                                    class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    + {{ t('funnels.builder.add_variant') }}
                                </button>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(variant, index) in (selectedNode.data.split_variants || [])"
                                    :key="variant.key"
                                    class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 flex-shrink-0 rounded-full bg-pink-500 text-white flex items-center justify-center text-xs">
                                            {{ variantLetter(index) }}
                                        </span>
                                        <input
                                            :value="variant.name"
                                            @input="(e) => updateVariant(index, { name: e.target.value })"
                                            type="text"
                                            class="flex-1 min-w-0 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                        />
                                        <input
                                            :value="variant.weight"
                                            @input="(e) => updateVariant(index, { weight: parseInt(e.target.value) || 0 })"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-16 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-center"
                                        />
                                        <span class="text-sm text-gray-500">%</span>
                                        <button
                                            v-if="(selectedNode.data.split_variants?.length || 0) > 2"
                                            @click="removeVariant(index)"
                                            class="p-1 text-red-500 hover:text-red-600"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="mt-1 ml-8 text-xs text-gray-500 dark:text-gray-400 truncate">
                                        → {{ getVariantPath(selectedNode, variant) }}
                                    </p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('funnels.builder.total_weight') }}: {{ (selectedNode.data.split_variants || []).reduce((sum, v) => sum + (v.weight || 0), 0) }}%
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('funnels.builder.split_paths_hint') }}
                            </p>
                        </div>
                    </template>

                    <!-- Connected edges -->
                    <div v-if="getNodeEdges(selectedNode.id).length > 0" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ t('funnels.builder.connections') }}
                        </h4>
                        <div class="space-y-2">
                            <div
                                v-for="edge in getNodeEdges(selectedNode.id)"
                                :key="edge.id"
                                class="flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-700 rounded-lg px-3 py-2"
                            >
                                <span class="text-gray-600 dark:text-gray-300">
                                    → {{ getEdgeLabel(edge) }}{{ getTargetName(edge.target) }}
                                </span>
                                <button @click="disconnectEdge(edge.id)" class="text-red-500 hover:text-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Teleport to="body">
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="toast.show" class="fixed bottom-4 right-4 z-50 max-w-sm w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden">
                <div class="p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg v-if="toast.type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ toast.message }}
                            </p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button @click="toast.show = false" class="bg-white dark:bg-gray-800 rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <span class="sr-only">Close</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
