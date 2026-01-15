<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    defaultCountry: {
        type: String,
        default: 'PL'
    }
});

const emit = defineEmits(['update:modelValue']);

// Common countries with dial codes and emoji flags
const countries = [
    { code: 'PL', name: 'Polska', dial: '+48', flag: '🇵🇱' },
    { code: 'DE', name: 'Deutschland', dial: '+49', flag: '🇩🇪' },
    { code: 'GB', name: 'United Kingdom', dial: '+44', flag: '🇬🇧' },
    { code: 'US', name: 'United States', dial: '+1', flag: '🇺🇸' },
    { code: 'FR', name: 'France', dial: '+33', flag: '🇫🇷' },
    { code: 'ES', name: 'España', dial: '+34', flag: '🇪🇸' },
    { code: 'IT', name: 'Italia', dial: '+39', flag: '🇮🇹' },
    { code: 'NL', name: 'Nederland', dial: '+31', flag: '🇳🇱' },
    { code: 'BE', name: 'België', dial: '+32', flag: '🇧🇪' },
    { code: 'AT', name: 'Österreich', dial: '+43', flag: '🇦🇹' },
    { code: 'CH', name: 'Schweiz', dial: '+41', flag: '🇨🇭' },
    { code: 'CZ', name: 'Česko', dial: '+420', flag: '🇨🇿' },
    { code: 'SK', name: 'Slovensko', dial: '+421', flag: '🇸🇰' },
    { code: 'UA', name: 'Україна', dial: '+380', flag: '🇺🇦' },
    { code: 'RU', name: 'Россия', dial: '+7', flag: '🇷🇺' },
    { code: 'SE', name: 'Sverige', dial: '+46', flag: '🇸🇪' },
    { code: 'NO', name: 'Norge', dial: '+47', flag: '🇳🇴' },
    { code: 'DK', name: 'Danmark', dial: '+45', flag: '🇩🇰' },
    { code: 'FI', name: 'Suomi', dial: '+358', flag: '🇫🇮' },
    { code: 'IE', name: 'Ireland', dial: '+353', flag: '🇮🇪' },
    { code: 'PT', name: 'Portugal', dial: '+351', flag: '🇵🇹' },
    { code: 'GR', name: 'Ελλάδα', dial: '+30', flag: '🇬🇷' },
    { code: 'HU', name: 'Magyarország', dial: '+36', flag: '🇭🇺' },
    { code: 'RO', name: 'România', dial: '+40', flag: '🇷🇴' },
    { code: 'BG', name: 'България', dial: '+359', flag: '🇧🇬' },
    { code: 'HR', name: 'Hrvatska', dial: '+385', flag: '🇭🇷' },
    { code: 'SI', name: 'Slovenija', dial: '+386', flag: '🇸🇮' },
    { code: 'LT', name: 'Lietuva', dial: '+370', flag: '🇱🇹' },
    { code: 'LV', name: 'Latvija', dial: '+371', flag: '🇱🇻' },
    { code: 'EE', name: 'Eesti', dial: '+372', flag: '🇪🇪' },
    { code: 'AU', name: 'Australia', dial: '+61', flag: '🇦🇺' },
    { code: 'CA', name: 'Canada', dial: '+1', flag: '🇨🇦' },
    { code: 'BR', name: 'Brasil', dial: '+55', flag: '🇧🇷' },
    { code: 'MX', name: 'México', dial: '+52', flag: '🇲🇽' },
    { code: 'JP', name: '日本', dial: '+81', flag: '🇯🇵' },
    { code: 'CN', name: '中国', dial: '+86', flag: '🇨🇳' },
    { code: 'IN', name: 'भारत', dial: '+91', flag: '🇮🇳' },
    { code: 'TR', name: 'Türkiye', dial: '+90', flag: '🇹🇷' },
    { code: 'ZA', name: 'South Africa', dial: '+27', flag: '🇿🇦' },
    { code: 'IL', name: 'ישראל', dial: '+972', flag: '🇮🇱' },
    { code: 'AE', name: 'الإمارات', dial: '+971', flag: '🇦🇪' },
    { code: 'SA', name: 'السعودية', dial: '+966', flag: '🇸🇦' },
    { code: 'EG', name: 'مصر', dial: '+20', flag: '🇪🇬' },
    { code: 'KR', name: '대한민국', dial: '+82', flag: '🇰🇷' },
    { code: 'SG', name: 'Singapore', dial: '+65', flag: '🇸🇬' },
    { code: 'NZ', name: 'New Zealand', dial: '+64', flag: '🇳🇿' },
    { code: 'AR', name: 'Argentina', dial: '+54', flag: '🇦🇷' },
    { code: 'CL', name: 'Chile', dial: '+56', flag: '🇨🇱' },
    { code: 'CO', name: 'Colombia', dial: '+57', flag: '🇨🇴' },
];

const selectedCountry = ref(null);
const phoneNumber = ref('');
const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);

// Parse initial value and set country/number
const parsePhoneNumber = (value) => {
    if (!value) {
        selectedCountry.value = countries.find(c => c.code === props.defaultCountry) || countries[0];
        phoneNumber.value = '';
        return;
    }

    // Try to match country dial code from the start
    let matched = null;
    let remaining = value;

    // Sort by dial code length descending for proper matching
    const sortedCountries = [...countries].sort((a, b) => b.dial.length - a.dial.length);

    for (const country of sortedCountries) {
        if (value.startsWith(country.dial)) {
            matched = country;
            remaining = value.slice(country.dial.length).trim();
            break;
        }
    }

    if (matched) {
        selectedCountry.value = matched;
        phoneNumber.value = remaining;
    } else {
        selectedCountry.value = countries.find(c => c.code === props.defaultCountry) || countries[0];
        phoneNumber.value = value.replace(/^\+?\d{1,3}\s*/, ''); // Try to strip any prefix
    }
};

// Filter countries based on search
const filteredCountries = computed(() => {
    if (!searchQuery.value) return countries;
    const q = searchQuery.value.toLowerCase();
    return countries.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.code.toLowerCase().includes(q) ||
        c.dial.includes(q)
    );
});

// Combined phone value to emit
const fullPhone = computed(() => {
    if (!phoneNumber.value) return '';
    return `${selectedCountry.value?.dial || ''} ${phoneNumber.value}`.trim();
});

// Emit whenever country or number changes
watch([selectedCountry, phoneNumber], () => {
    emit('update:modelValue', fullPhone.value);
});

// Watch for external changes to modelValue
watch(() => props.modelValue, (newVal) => {
    if (newVal !== fullPhone.value) {
        parsePhoneNumber(newVal);
    }
}, { immediate: true });

const selectCountry = (country) => {
    selectedCountry.value = country;
    isOpen.value = false;
    searchQuery.value = '';
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
    }
};

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    parsePhoneNumber(props.modelValue);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="phone-input" ref="dropdownRef">
        <div class="flex">
            <!-- Country selector button -->
            <button
                type="button"
                @click="toggleDropdown"
                class="flex items-center gap-1.5 rounded-l-xl border border-r-0 border-slate-200 bg-slate-100 px-3 py-3 text-slate-700 hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
            >
                <span class="text-lg">{{ selectedCountry?.flag }}</span>
                <span class="text-sm font-medium">{{ selectedCountry?.dial }}</span>
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <!-- Phone number input -->
            <input
                v-model="phoneNumber"
                type="tel"
                inputmode="numeric"
                class="block w-full rounded-r-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                placeholder="123 456 789"
            >
        </div>

        <!-- Country dropdown -->
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute z-50 mt-1 w-72 max-h-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
                <!-- Search input -->
                <div class="border-b border-slate-200 p-2 dark:border-slate-700">
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2 text-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-400"
                        placeholder="Search country..."
                        @click.stop
                    >
                </div>

                <!-- Countries list -->
                <div class="max-h-48 overflow-y-auto">
                    <button
                        v-for="country in filteredCountries"
                        :key="country.code"
                        type="button"
                        @click="selectCountry(country)"
                        class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700"
                        :class="{ 'bg-indigo-50 dark:bg-indigo-900/30': selectedCountry?.code === country.code }"
                    >
                        <span class="text-xl">{{ country.flag }}</span>
                        <span class="flex-1">{{ country.name }}</span>
                        <span class="text-slate-500 dark:text-slate-400">{{ country.dial }}</span>
                    </button>

                    <div v-if="filteredCountries.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                        No countries found
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.phone-input {
    position: relative;
}
</style>
