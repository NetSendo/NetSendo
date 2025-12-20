<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    email: String,
});
</script>

<template>
    <Head title="Wypisano z listy" />
    
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-100 dark:bg-gray-900 p-4">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 shadow-md rounded-lg p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900 mb-6">
                <svg class="h-8 w-8 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Zostałeś wypisany
            </h2>
            
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                Twój adres email <strong>{{ email }}</strong> został pomyślnie usunięty z naszej listy mailingowej.
            </p>
            
            <p class="text-xs text-gray-500 dark:text-gray-500">
                Jeśli to pomyłka, skontaktuj się z administratorem.
            </p>
        </div>
    </div>
</template>
