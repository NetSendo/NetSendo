/**
 * The panel's navigation as data: nine sections under the dashboard, each a short list of pages.
 *
 * Rules the list follows:
 * - a page appears once (the old menu listed subscribers, statistics, fields and API keys twice);
 * - the menu links to places, not to actions: "add a message" is a button on the message list;
 * - settings are grouped by what they are for (account, sending, integrations, system).
 *
 * `match` and `except` are route-name patterns for `route().current()`; they decide which item
 * is highlighted and which section opens. `gold` names the licence feature behind a page.
 */

const ICONS = {
    dashboard: [
        "M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z",
    ],
    messages: [
        "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
    ],
    contacts: [
        "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
    ],
    automation: ["M13 10V3L4 14h7v7l9-11h-7z"],
    crm: [
        "M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z",
    ],
    brain: [
        "M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z",
    ],
    sales: [
        "M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
    ],
    analytics: ["M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"],
    library: [
        "M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z",
    ],
    settings: [
        "M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z",
        "M15 12a3 3 0 11-6 0 3 3 0 016 0z",
    ],
};

/**
 * @param {(key: string) => string} t  translator
 * @param {{ isAdmin: boolean }} options
 */
export function buildNavigation(t, { isAdmin = false } = {}) {
    const sections = [
        {
            key: "dashboard",
            label: t("navigation.dashboard"),
            href: route("dashboard"),
            match: ["dashboard"],
        },
        {
            key: "messages",
            label: t("navigation.messages"),
            items: [
                { label: t("navigation.items.email"), href: route("messages.index"), match: ["messages.*"] },
                { label: t("navigation.items.sms"), href: route("sms.index"), match: ["sms.*"] },
                { label: t("system_emails.title"), href: route("settings.system-emails.index"), match: ["settings.system-emails.*"] },
                { label: t("system_pages.title"), href: route("settings.system-pages.index"), match: ["settings.system-pages.*"] },
            ],
        },
        {
            key: "contacts",
            label: t("navigation.groups.contacts"),
            items: [
                { label: t("navigation.subscribers"), href: route("subscribers.index"), match: ["subscribers.*"], except: ["subscribers.import*"] },
                { label: t("navigation.address_lists"), href: route("mailing-lists.index"), match: ["mailing-lists.*"] },
                { label: t("sms_lists.title"), href: route("sms-lists.index"), match: ["sms-lists.*"] },
                { label: t("navigation.groups_nav"), href: route("groups.index"), match: ["groups.*"] },
                { label: t("navigation.tags"), href: route("tags.index"), match: ["tags.*"] },
                { label: t("navigation.items.segmentation"), href: route("segmentation.index"), match: ["segmentation.*"], gold: "segmentation_dashboard" },
                { label: t("navigation.forms"), href: route("forms.index"), match: ["forms.*"] },
                { label: t("navigation.field_management"), href: route("settings.fields.index"), match: ["settings.fields.*"] },
                { label: t("navigation.import"), href: route("subscribers.import"), match: ["subscribers.import*"] },
            ],
        },
        {
            key: "automation",
            label: t("navigation.groups.automation"),
            items: [
                { label: t("automations.title"), href: route("automations.index"), match: ["automations.*"] },
                { label: t("funnels.title"), href: route("funnels.index"), match: ["funnels.*"] },
                { label: t("campaign_architect.title"), href: route("campaign-architect.index"), match: ["campaign-architect.*"] },
                { label: t("navigation.external_pages"), href: route("external-pages.index"), match: ["external-pages.*"] },
            ],
        },
        {
            key: "crm",
            label: t("navigation.groups.crm"),
            items: [
                { label: t("crm.dashboard.title"), href: route("crm.dashboard"), match: ["crm.dashboard"] },
                { label: t("crm.contacts.title"), href: route("crm.contacts.index"), match: ["crm.contacts.*"] },
                { label: t("crm.companies.title"), href: route("crm.companies.index"), match: ["crm.companies.*"] },
                { label: t("crm.deals.title"), href: route("crm.deals.index"), match: ["crm.deals.*"] },
                { label: t("crm.tasks.title"), href: route("crm.tasks.index"), match: ["crm.tasks.*"] },
                { label: t("crm.sequences.title"), href: route("crm.sequences.index"), match: ["crm.sequences.*"] },
                { label: t("crm.scoring.title"), href: route("crm.scoring.index"), match: ["crm.scoring.*"] },
                { label: t("crm.cardintel.title"), href: route("crm.cardintel.index"), match: ["crm.cardintel.*"], isNew: true },
                { label: t("navigation.crm_import"), href: route("crm.import.index"), match: ["crm.import.*"] },
                { label: t("crm.guide.title"), href: route("crm.guide"), match: ["crm.guide"] },
            ],
        },
        {
            key: "brain",
            label: t("navigation.groups.brain"),
            items: [
                { label: t("brain.chat"), href: route("brain.index"), match: ["brain.index"] },
                { label: t("brain.monitor_label"), href: route("brain.monitor"), match: ["brain.monitor"] },
                { label: t("brain.settings_label"), href: route("brain.settings"), match: ["brain.settings"] },
                { label: t("brain.guide_label"), href: route("brain.guide"), match: ["brain.guide"] },
            ],
        },
        {
            key: "sales",
            label: t("navigation.groups.sales"),
            items: [
                { label: t("navigation.webinars"), href: route("webinars.index"), match: ["webinars.*"], isNew: true },
                { label: t("stripe.products"), href: route("settings.stripe-products.index"), match: ["settings.stripe-products.*"] },
                { label: t("polar.products_title"), href: route("settings.polar-products.index"), match: ["settings.polar-products.*"] },
                { label: t("tpay.products_title"), href: route("settings.tpay-products.index"), match: ["settings.tpay-products.*"] },
                { label: t("navigation.affiliate_program"), href: route("affiliate.index"), match: ["affiliate.*"] },
            ],
        },
        {
            key: "analytics",
            label: t("navigation.groups.analytics"),
            items: [
                { label: t("settings.global_stats"), href: route("settings.stats.index"), match: ["settings.stats.*"] },
                { label: t("campaign_stats.title"), href: route("campaign-stats.index"), match: ["campaign-stats.*"] },
                { label: t("campaign_auditor.title"), href: route("campaign-auditor.index"), match: ["campaign-auditor.*"], gold: "campaign_auditor" },
                { label: t("navigation.tracked_links"), href: route("settings.tracked-links.index"), match: ["settings.tracked-links.*"] },
                { label: t("settings.activity_logs"), href: route("settings.activity-logs.index"), match: ["settings.activity-logs.*"] },
            ],
        },
        {
            key: "library",
            label: t("navigation.groups.library"),
            items: [
                { label: t("navigation.message_templates"), href: route("templates.index"), match: ["templates.*"] },
                { label: t("navigation.insert_templates"), href: route("inserts.index"), match: ["inserts.*"] },
                { label: t("media.title"), href: route("media.index"), match: ["media.*"] },
                { label: t("brands.title"), href: route("brands.index"), match: ["brands.*"] },
            ],
        },
        {
            key: "settings",
            label: t("navigation.groups.settings"),
            items: [
                { heading: t("navigation.settings_sections.account") },
                { label: t("navigation.security_settings"), href: route("profile.edit"), match: ["profile.*"] },
                { label: t("navigation.users"), href: route("settings.users.index"), match: ["settings.users.*"], adminOnly: true },
                { label: t("navigation.license"), href: route("license.index"), match: ["license.*"] },

                { heading: t("navigation.settings_sections.sending") },
                { label: t("navigation.mailboxes"), href: route("settings.mailboxes.index"), match: ["settings.mailboxes.*"] },
                { label: t("navigation.sms_providers"), href: route("settings.sms-providers.index"), match: ["settings.sms-providers.*"] },
                { label: t("navigation.deliverability_shield"), href: route("deliverability.index"), match: ["deliverability.*"], gold: "deliverability_shield" },
                { label: t("navigation.nmi"), href: route("settings.nmi.dashboard"), match: ["settings.nmi.*"], gold: "mail_infrastructure" },
                { label: t("navigation.default_settings"), href: "/defaults", match: ["defaults.*"] },
                { label: t("navigation.name_database"), href: route("settings.names.index"), match: ["settings.names.*"] },
                { label: t("navigation.cron_settings"), href: route("settings.cron.index"), match: ["settings.cron.*"] },

                { heading: t("navigation.settings_sections.integrations") },
                { label: t("navigation.marketplace"), href: "/marketplace", match: ["marketplace.*"] },
                { label: t("navigation.ai_integrations"), href: route("settings.ai-integrations.index"), match: ["settings.ai-integrations.*"] },
                { label: t("navigation.api_keys"), href: route("settings.api-keys.index"), match: ["settings.api-keys.*"] },
                { label: t("navigation.connector"), href: route("settings.connector.index"), match: ["settings.connector.*"] },
                { label: t("stripe.settings_title"), href: route("settings.stripe.index"), match: ["settings.stripe.*"] },
                { label: t("polar.settings_title"), href: route("settings.polar.index"), match: ["settings.polar.*"] },
                { label: t("tpay.settings_title"), href: route("settings.tpay.index"), match: ["settings.tpay.*"] },
                { label: t("settings.woocommerce.title"), href: route("settings.woocommerce.index"), match: ["settings.woocommerce.*"] },
                { label: t("pixel.settings_title"), href: route("settings.pixel.index"), match: ["settings.pixel.*"] },
                { label: t("navigation.items.google"), href: route("settings.integrations.index"), match: ["settings.integrations.*"] },
                { label: t("calendar.title"), href: route("settings.calendar.index"), match: ["settings.calendar.*"] },

                { heading: t("navigation.settings_sections.system") },
                { label: t("navigation.updates"), href: "/update", match: ["update.*"] },
                { label: t("navigation.backups"), href: route("settings.backup.index"), match: ["settings.backup.*"] },
                { label: t("navigation.telemetry"), href: route("settings.telemetry.index"), match: ["settings.telemetry.*"], adminOnly: true },
            ],
        },
    ];

    return sections.map((section) => ({
        ...section,
        icon: ICONS[section.key],
        items: (section.items || []).filter((item) => !item.adminOnly || isAdmin),
    }));
}

const current = (pattern) => {
    try {
        return route().current(pattern);
    } catch {
        return false;
    }
};

/** Whether the page being shown belongs to this section link or item. */
export function isCurrent(entry) {
    if (!entry.match) return false;
    if ((entry.except || []).some(current)) return false;

    return entry.match.some(current);
}
