/**
 * The look of a text field, a select or a textarea in the panel's forms, for pages that
 * write the element themselves (dynamic types, numbers, selects). Tailwind here has no
 * forms plugin: without the `border` class a field would draw no frame at all.
 */
export const fieldClass =
    "block min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400";

export const labelClass = "block text-sm font-medium text-slate-800 dark:text-slate-200";

export const hintClass = "mt-1 text-xs text-slate-600 dark:text-slate-400";

export const errorClass = "mt-1 text-sm text-rose-700 dark:text-rose-300";
