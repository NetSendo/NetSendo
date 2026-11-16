<script setup>
import { nextTick, onBeforeUnmount, ref } from "vue";

/**
 * The "more" button of a table row: actions with their names, instead of a row of icons to
 * decode. The menu is teleported and placed by hand, so a table that scrolls sideways
 * cannot clip it; it opens upwards when there is no room below.
 */
defineProps({
    label: { type: String, required: true },
});

const MENU_WIDTH = 224;
const MARGIN = 8;

const open = ref(false);
const trigger = ref(null);
const menu = ref(null);
const position = ref({});

const close = () => {
    if (!open.value) return;
    open.value = false;
    document.removeEventListener("pointerdown", onPointerDown, true);
    document.removeEventListener("keydown", onKeydown);
    window.removeEventListener("scroll", close, true);
    window.removeEventListener("resize", close);
};

const onPointerDown = (event) => {
    if (menu.value?.contains(event.target) || trigger.value?.contains(event.target)) return;
    close();
};

const onKeydown = (event) => {
    if (event.key === "Escape") {
        close();
        trigger.value?.focus();
    }
};

const show = async () => {
    const rect = trigger.value.getBoundingClientRect();
    const left = Math.min(
        Math.max(MARGIN, rect.right - MENU_WIDTH),
        window.innerWidth - MENU_WIDTH - MARGIN,
    );

    position.value = { left: `${left}px`, top: `${rect.bottom + 6}px` };
    open.value = true;

    await nextTick();

    // Not enough room below: open above the button instead
    const height = menu.value?.offsetHeight || 0;
    if (rect.bottom + 6 + height > window.innerHeight - MARGIN) {
        position.value = {
            left: `${left}px`,
            top: `${Math.max(MARGIN, rect.top - 6 - height)}px`,
        };
    }

    // Without preventScroll the focus can scroll the page, and a scroll closes the menu
    menu.value?.querySelector("[role=menuitem]")?.focus({ preventScroll: true });

    document.addEventListener("pointerdown", onPointerDown, true);
    document.addEventListener("keydown", onKeydown);
    window.addEventListener("scroll", close, true);
    window.addEventListener("resize", close);
};

const toggle = () => (open.value ? close() : show());

onBeforeUnmount(close);
</script>

<template>
    <button
        ref="trigger"
        type="button"
        aria-haspopup="menu"
        :aria-expanded="open"
        :aria-label="label"
        :title="label"
        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white"
        @click="toggle"
    >
        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
            <circle cx="5" cy="12" r="1.75" />
            <circle cx="12" cy="12" r="1.75" />
            <circle cx="19" cy="12" r="1.75" />
        </svg>
    </button>

    <Teleport to="body">
        <div
            v-if="open"
            ref="menu"
            role="menu"
            class="fixed z-50 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800"
            :style="position"
            @click="close"
        >
            <slot />
        </div>
    </Teleport>
</template>
