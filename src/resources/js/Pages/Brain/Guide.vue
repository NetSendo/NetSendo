<script setup>
import { computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

// --- The autonomous loop ---
const loopSteps = computed(() => [
    { icon: "🔍", title: t("brain.guide.loop_1_title", "Analiza sytuacji"), desc: t("brain.guide.loop_1_desc", "Brain cyklicznie analizuje Twoje listy, kampanie, CRM i przychód — sam znajduje priorytety.") },
    { icon: "🗺️", title: t("brain.guide.loop_2_title", "Planowanie"), desc: t("brain.guide.loop_2_desc", "Priorytety zamieniają się w konkretne plany działania z krokami — od treści maila po wybór odbiorców.") },
    { icon: "✅", title: t("brain.guide.loop_3_title", "Zatwierdzenie"), desc: t("brain.guide.loop_3_desc", "Zależnie od trybu pracy plan wykonuje się sam albo czeka na Twoją zgodę w Centrum Zatwierdzeń lub na Telegramie.") },
    { icon: "🚀", title: t("brain.guide.loop_4_title", "Wykonanie"), desc: t("brain.guide.loop_4_desc", "Kampanie realnie się wysyłają — przez ten sam bezpieczny pipeline co wysyłki ręczne, z limitami i kontrolą spamu.") },
    { icon: "💰", title: t("brain.guide.loop_5_title", "Pomiar pieniędzy"), desc: t("brain.guide.loop_5_desc", "Każdy zakup (Stripe, Polar, Tpay, WooCommerce, webhooki, lejki) trafia do jednego rejestru i jest przypisywany do kampanii, która go wywołała.") },
    { icon: "🧠", title: t("brain.guide.loop_6_title", "Nauka"), desc: t("brain.guide.loop_6_desc", "Wyniki każdej wysyłki stają się danymi: najlepsze godziny, cechy tematów, RPM typów kampanii — i sterują kolejnymi decyzjami.") },
]);

// --- Work modes ---
const workModes = computed(() => [
    { icon: "💡", title: t("brain.guide.mode_manual_title", "Manualny"), desc: t("brain.guide.mode_manual_desc", "Brain tylko doradza — wszystkie akcje wykonujesz sam. Idealny na poznanie systemu.") },
    { icon: "🤝", title: t("brain.guide.mode_semi_title", "Półautomat"), desc: t("brain.guide.mode_semi_desc", "Brain proponuje gotowe plany i czeka na Twoje zatwierdzenie. Ty decydujesz, on wykonuje.") },
    { icon: "🚀", title: t("brain.guide.mode_auto_title", "Pełna autonomia"), desc: t("brain.guide.mode_auto_desc", "Brain planuje i wykonuje sam, raportując wyniki. Akcje destrukcyjne ZAWSZE wymagają Twojej zgody.") },
]);

// --- Agents ---
const agents = computed(() => [
    { icon: "📧", name: "Campaign", desc: t("brain.guide.agent_campaign", "Planuje i realnie wysyła kampanie e-mail/SMS, prowadzi testy A/B z automatycznym wyborem zwycięzcy.") },
    { icon: "✉️", name: "Message", desc: t("brain.guide.agent_message", "Pisze treści: tematy, treści maili i SMS-ów, warianty A/B — w Twoim tonie i języku, z personalizacją.") },
    { icon: "📋", name: "List", desc: t("brain.guide.agent_list", "Zarządza listami: tworzenie, tagowanie, czyszczenie odbitych adresów (zawsze za Twoją zgodą).") },
    { icon: "🤝", name: "CRM", desc: t("brain.guide.agent_crm", "Prowadzi kontakty, deale, zadania i pipeline sprzedażowy; analizuje scoring leadów.") },
    { icon: "📊", name: "Analytics", desc: t("brain.guide.agent_analytics", "Raporty i trendy: open rate, kliknięcia, porównania kampanii — zawsze tylko do odczytu.") },
    { icon: "🎯", name: "Segmentation", desc: t("brain.guide.agent_segmentation", "Segmenty, tagi i reguły automatyzacji (30+ wyzwalaczy, np. porzucony koszyk czy urodziny).") },
    { icon: "🔍", name: "Research", desc: t("brain.guide.agent_research", "Szuka w internecie: trendy rynkowe, konkurencja, pomysły na treści — z zapisem do bazy wiedzy.") },
    { icon: "💰", name: "Revenue", desc: t("brain.guide.agent_revenue", "Pokazuje, które kampanie ZARABIAJĄ: przychód per kampania, RPM, wartość życiowa subskrybentów.") },
    { icon: "🌀", name: "Funnel", desc: t("brain.guide.agent_funnel", "Buduje wielokrokowe lejki z gotowych playbooków przychodowych — z treściami wygenerowanymi przez AI.") },
    { icon: "📮", name: "Deliverability", desc: t("brain.guide.agent_deliverability", "Pilnuje reputacji nadawcy: SPF/DKIM/DMARC, czarne listy, wskaźniki odbić — i blokuje wysyłki, gdy coś się psuje.") },
]);

// --- Playbooks ---
const playbooks = computed(() => [
    { icon: "👋", name: t("brain.guide.pb_welcome_name", "Powitanie → pierwsza oferta"), desc: t("brain.guide.pb_welcome_desc", "3 maile dla nowych subskrybentów: powitanie, budowa zaufania, oferta. Cel: pierwszy zakup.") },
    { icon: "🛒", name: t("brain.guide.pb_cart_name", "Porzucony koszyk"), desc: t("brain.guide.pb_cart_desc", "Przypomnienie po godzinie + oferta zbijająca obiekcje po dniu. Cel: odzyskane zamówienie.") },
    { icon: "🔄", name: t("brain.guide.pb_winback_name", "Win-back"), desc: t("brain.guide.pb_winback_desc", "Reaktywacja nieaktywnych: przypomnienie wartości + ekskluzywna oferta powrotu.") },
    { icon: "📈", name: t("brain.guide.pb_upsell_name", "Po zakupie"), desc: t("brain.guide.pb_upsell_desc", "Podziękowanie i wskazówki po 3 dniach, dosprzedaż komplementarnego produktu po tygodniu.") },
]);

// --- Safety ---
const safetyItems = computed(() => [
    t("brain.guide.safety_1", "Pipeline bezpieczeństwa przed KAŻDĄ wysyłką: kontrola spamu, tygodniowy limit kampanii, limit wielkości audiencji, lista supresji."),
    t("brain.guide.safety_2", "Circuit breaker dostarczalności: skok odbić lub skarg spamowych automatycznie wstrzymuje wysyłki Brain — reputacja nadawcy jest ważniejsza niż pojedyncza kampania."),
    t("brain.guide.safety_3", "Tiery uprawnień: akcje odczytu wykonują się swobodnie, wysyłki zgodnie z trybem pracy, a akcje destrukcyjne (masowe wypisania, automatyzacje) ZAWSZE czekają na Twoją zgodę."),
    t("brain.guide.safety_4", "Okno wysyłek: Brain respektuje Twoje preferowane godziny i wykluczone dni — przesunie wysyłkę zamiast złamać zasady."),
    t("brain.guide.safety_5", "Tryb symulacji (dry-run): Brain wykonuje pełny cykl, ale raportuje 'co by zrobił' bez wysyłania czegokolwiek. Idealny na start."),
]);

// --- Money ---
const moneyItems = computed(() => [
    t("brain.guide.money_1", "Jeden rejestr przychodu: płatności ze Stripe, Polar, Tpay, WooCommerce, webhooków zakupowych i celów lejków w jednym miejscu."),
    t("brain.guide.money_2", "Atrybucja klik→zakup: ostatnie kliknięcie z maila w oknie 7 dni wskazuje kampanię, która zarobiła."),
    t("brain.guide.money_3", "RPM — przychód na 1000 dostarczonych maili — jako główna miara skuteczności, obok open rate."),
    t("brain.guide.money_4", "Tygodniowy P&L: przychód z kampanii kontra koszt tokenów AI, per źródło — w cotygodniowym digeście."),
]);

// --- Learning ---
const learningItems = computed(() => [
    t("brain.guide.learn_1", "Każda wysyłka to eksperyment: dzień, godzina, długość tematu, emoji, personalizacja — wszystko mierzone."),
    t("brain.guide.learn_2", "Najlepsza godzina wysyłki wyliczana z Twoich danych i automatycznie stosowana przy planowanych kampaniach."),
    t("brain.guide.learn_3", "Cele rozliczane z metryk: cel 'open rate 25%' zamyka się, gdy metryka faktycznie osiągnie 25% — nie wcześniej."),
    t("brain.guide.learn_4", "Alokator budżetu: tygodniowa pula wysyłek trafia do typów kampanii z najwyższym zmierzonym RPM, z miejscem na eksplorację nowych."),
]);

// --- Quick start ---
const quickStart = computed(() => [
    t("brain.guide.qs_1", "Skonfiguruj integrację AI (Ustawienia → AI) i wybierz model dla Brain."),
    t("brain.guide.qs_2", "Uzupełnij bazę wiedzy: kim jesteś, co sprzedajesz, do kogo mówisz — Brain pisze wtedy w Twoim głosie."),
    t("brain.guide.qs_3", "Ustaw strategię kampanii: ton, limit wysyłek/tydzień, godziny i dni — Brain będzie ich pilnował w kodzie, nie na słowo."),
    t("brain.guide.qs_4", "Zacznij od półautomatu z włączonym dry-run: obserwuj w Monitorze, co Brain planuje, i zatwierdzaj świadomie."),
    t("brain.guide.qs_5", "Gdy zaufasz systemowi — wyłącz dry-run, przełącz wybranych agentów na pełną autonomię i patrz na kartę Przychód."),
]);
</script>

<template>
    <Head :title="t('brain.guide.page_title', 'Brain — Przewodnik')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-xl text-white"
                    >
                        🧭
                    </div>
                    <div>
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-gray-100"
                        >
                            {{ t("brain.guide.page_title", "Brain — Przewodnik") }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{
                                t(
                                    "brain.guide.page_subtitle",
                                    "Jak działa Twój autonomiczny system marketingu i jak wycisnąć z niego maksimum",
                                )
                            }}
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('brain.index')"
                    class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:from-cyan-600 hover:to-blue-700"
                >
                    💬 {{ t("brain.guide.cta_chat", "Otwórz Chat AI") }}
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 px-4 py-8">
            <!-- Hero -->
            <div
                class="rounded-2xl border border-cyan-200 bg-gradient-to-br from-cyan-50 via-white to-blue-50 p-8 dark:border-cyan-900 dark:from-cyan-950/40 dark:via-gray-900 dark:to-blue-950/40"
            >
                <h3
                    class="text-2xl font-bold text-gray-900 dark:text-white"
                >
                    {{
                        t(
                            "brain.guide.hero_title",
                            "Autonomiczny zespół marketingowy, który mierzy się w pieniądzach",
                        )
                    }}
                </h3>
                <p class="mt-3 max-w-3xl text-gray-600 dark:text-gray-300">
                    {{
                        t(
                            "brain.guide.hero_desc",
                            "NetSendo Brain to 10 wyspecjalizowanych agentów AI, którzy analizują Twoją sytuację, planują i wysyłają kampanie, budują lejki sprzedażowe, pilnują reputacji nadawcy i uczą się z każdej wysyłki. Cel jest jeden: przychód z e-mail marketingu — mierzony, przypisany do kampanii i optymalizowany co tydzień.",
                        )
                    }}
                </p>
                <div class="mt-5 flex flex-wrap gap-3 text-sm">
                    <span class="rounded-full bg-white/80 px-3 py-1 font-medium text-cyan-700 shadow-sm dark:bg-gray-800 dark:text-cyan-400">🤖 10 {{ t("brain.guide.badge_agents", "agentów") }}</span>
                    <span class="rounded-full bg-white/80 px-3 py-1 font-medium text-cyan-700 shadow-sm dark:bg-gray-800 dark:text-cyan-400">📚 4 {{ t("brain.guide.badge_playbooks", "playbooki przychodowe") }}</span>
                    <span class="rounded-full bg-white/80 px-3 py-1 font-medium text-cyan-700 shadow-sm dark:bg-gray-800 dark:text-cyan-400">💰 {{ t("brain.guide.badge_revenue", "atrybucja przychodu") }}</span>
                    <span class="rounded-full bg-white/80 px-3 py-1 font-medium text-cyan-700 shadow-sm dark:bg-gray-800 dark:text-cyan-400">🛡️ {{ t("brain.guide.badge_safety", "wysyłki pod ochroną") }}</span>
                </div>
            </div>

            <!-- The loop -->
            <div>
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    🔁 {{ t("brain.guide.loop_title", "Pętla autonomiczna — jak Brain pracuje") }}
                </h3>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(step, i) in loopSteps"
                        :key="i"
                        class="rounded-2xl border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-100 text-sm font-bold text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-400">{{ i + 1 }}</span>
                            <span class="text-2xl">{{ step.icon }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ step.title }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ step.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- Work modes -->
            <div>
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    🎛️ {{ t("brain.guide.modes_title", "Ty decydujesz, ile autonomii dać") }}
                </h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="mode in workModes"
                        :key="mode.title"
                        class="rounded-2xl border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="text-2xl">{{ mode.icon }}</div>
                        <div class="mt-2 font-semibold text-gray-900 dark:text-white">{{ mode.title }}</div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ mode.desc }}</p>
                    </div>
                </div>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    💡 {{ t("brain.guide.modes_note", "Tryb ustawisz globalnie lub osobno dla każdego agenta — np. analizy w pełni automatyczne, a kampanie za zatwierdzeniem. Do tego tryb symulacji (dry-run), w którym nic nie zostaje wysłane.") }}
                </p>
            </div>

            <!-- Agents -->
            <div>
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    🤖 {{ t("brain.guide.agents_title", "10 agentów — Twój zespół") }}
                </h3>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="agent in agents"
                        :key="agent.name"
                        class="rounded-2xl border bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                            <span class="text-xl">{{ agent.icon }}</span> {{ agent.name }} Agent
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ agent.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- Playbooks -->
            <div>
                <h3 class="mb-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    📚 {{ t("brain.guide.playbooks_title", "Playbooki przychodowe — sprawdzone maszynki") }}
                </h3>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ t("brain.guide.playbooks_intro", "Powiedz w chacie np. „zbuduj mi lejek porzuconego koszyka” — Brain wygeneruje treści w Twoim głosie, złoży lejek z celem zakupowym i poprosi o zgodę na aktywację.") }}
                </p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="pb in playbooks"
                        :key="pb.name"
                        class="rounded-2xl border bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="text-2xl">{{ pb.icon }}</div>
                        <div class="mt-2 font-semibold text-gray-900 dark:text-white">{{ pb.name }}</div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ pb.desc }}</p>
                    </div>
                </div>
            </div>

            <!-- Money + Learning (two columns) -->
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-3 text-lg font-semibold text-gray-800 dark:text-gray-100">
                        💰 {{ t("brain.guide.money_title", "Brain widzi pieniądze") }}
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <li v-for="(item, i) in moneyItems" :key="i" class="flex gap-2">
                            <span class="text-emerald-500">✔</span> {{ item }}
                        </li>
                    </ul>
                </div>
                <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-3 text-lg font-semibold text-gray-800 dark:text-gray-100">
                        🧠 {{ t("brain.guide.learning_title", "Brain uczy się z każdej wysyłki") }}
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <li v-for="(item, i) in learningItems" :key="i" class="flex gap-2">
                            <span class="text-cyan-500">✔</span> {{ item }}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Safety -->
            <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-6 dark:border-amber-900 dark:bg-amber-950/20">
                <h3 class="mb-3 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    🛡️ {{ t("brain.guide.safety_title", "Autonomia pod ochroną") }}
                </h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li v-for="(item, i) in safetyItems" :key="i" class="flex gap-2">
                        <span class="text-amber-500">🔒</span> {{ item }}
                    </li>
                </ul>
            </div>

            <!-- Quick start -->
            <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    🚀 {{ t("brain.guide.qs_title", "Szybki start — 5 kroków") }}
                </h3>
                <ol class="space-y-3">
                    <li
                        v-for="(step, i) in quickStart"
                        :key="i"
                        class="flex gap-3 text-sm text-gray-600 dark:text-gray-300"
                    >
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-500 text-xs font-bold text-white">{{ i + 1 }}</span>
                        {{ step }}
                    </li>
                </ol>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Link
                        :href="route('brain.settings')"
                        class="rounded-lg border border-cyan-500 px-4 py-2 text-sm font-semibold text-cyan-600 hover:bg-cyan-50 dark:text-cyan-400 dark:hover:bg-cyan-950/30"
                    >
                        ⚙️ {{ t("brain.guide.cta_settings", "Skonfiguruj Brain") }}
                    </Link>
                    <Link
                        :href="route('brain.monitor')"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        📊 {{ t("brain.guide.cta_monitor", "Otwórz Monitor") }}
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
