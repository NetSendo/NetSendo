import { ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

/**
 * One short message at the corner of the screen that goes away by itself.
 *
 * With `flash: true` it also shows what the server put in the session ("success" / "error")
 * after a redirect, so a page does not have to repeat the wording of every outcome. With
 * `flash: "error"` only failures come from the server: the page words its own successes, in
 * the user's language, where the server's are not translated.
 */
export function useToast({ flash = false, duration = 4000 } = {}) {
    const toast = ref(null);
    let timer = null;

    const dismiss = () => {
        clearTimeout(timer);
        toast.value = null;
    };

    // A failure stays twice as long: it usually has to be read, not just noticed
    const showToast = (message, success = true) => {
        if (!message) return;
        clearTimeout(timer);
        toast.value = { message, success };
        timer = setTimeout(dismiss, success ? duration : duration * 2);
    };

    if (flash) {
        const page = usePage();
        watch(
            () => page.props.flash,
            (value) => {
                if (value?.error) showToast(value.error, false);
                else if (value?.success && flash !== "error") showToast(value.success, true);
            },
            { immediate: true, deep: true },
        );
    }

    return { toast, showToast, dismiss };
}
