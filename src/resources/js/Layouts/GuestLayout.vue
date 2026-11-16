<script setup>
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";
import DeliveryCanvas from "@/Components/Auth/DeliveryCanvas.vue";
import { Link } from "@inertiajs/vue3";

/**
 * Every screen a signed-out person sees: sign in, first administrator, password reset,
 * two-factor code, invitation. Two panes on a wide screen: the brand on a dark ground (the
 * logo has white lettering, so it always sits on dark), and the form in the visitor's theme.
 * On a narrow screen the brand pane shrinks to a strip with the logo.
 */
</script>

<template>
    <div
        class="grid min-h-screen grid-cols-1 bg-white text-slate-900 max-lg:grid-rows-[auto_1fr] lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] dark:bg-slate-900 dark:text-white"
    >
        <!-- Brand: half of a wide screen; on a phone a strip as tall as the logo, so the form
             gets the rest of the height -->
        <aside
            class="relative isolate flex flex-col justify-between gap-10 overflow-hidden bg-slate-950 px-6 py-5 text-white lg:px-12 lg:py-10"
        >
            <!-- Messages leaving "your server" for their recipients; wide screens only -->
            <DeliveryCanvas class="-z-10 max-lg:hidden" />

            <Link href="/" class="inline-flex self-start rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-400">
                <img
                    src="/images/brand/netsendo-logo.png"
                    alt="NetSendo"
                    class="h-9 w-auto lg:h-11"
                />
            </Link>

            <!-- A soft dark haze behind the words: the arcs pass under it instead of through the text -->
            <div
                class="relative hidden max-w-md flex-col gap-8 before:absolute before:-inset-10 before:-z-10 before:rounded-[3rem] before:bg-slate-950/85 before:blur-2xl lg:flex"
            >
                <p class="text-3xl font-semibold leading-tight tracking-tight">
                    {{ $t("auth.brand.headline") }}
                </p>
                <ul class="flex flex-col gap-4 text-base text-slate-300">
                    <li
                        v-for="point in ['channels', 'automation', 'ownership']"
                        :key="point"
                        class="flex items-start gap-3"
                    >
                        <svg
                            aria-hidden="true"
                            class="mt-1 h-4 w-4 shrink-0 text-emerald-400"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            viewBox="0 0 24 24"
                        >
                            <path d="M5 13l4 4L19 7" />
                        </svg>
                        {{ $t(`auth.brand.points.${point}`) }}
                    </li>
                </ul>
            </div>

            <p class="hidden text-sm text-slate-400 lg:block">
                NetSendo v{{ $page.props.appVersion || "1.0.0" }} © {{ new Date().getFullYear() }}
            </p>
        </aside>

        <!-- Form -->
        <div class="flex min-w-0 flex-col px-6 py-5 lg:px-12 lg:py-8">
            <div class="flex justify-end">
                <LanguageSwitcher />
            </div>

            <main class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-sm">
                    <slot />
                </div>
            </main>

            <p class="text-center text-xs text-slate-600 lg:hidden dark:text-slate-400">
                NetSendo v{{ $page.props.appVersion || "1.0.0" }} © {{ new Date().getFullYear() }}
            </p>
        </div>
    </div>
</template>
