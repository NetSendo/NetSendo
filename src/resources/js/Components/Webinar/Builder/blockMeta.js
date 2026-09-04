/**
 * Presentation metadata for the webinar page builder blocks: icon, i18n label
 * key and which prop editors the properties panel should render.
 *
 * The source of truth for available blocks and their default props is the
 * server catalog (WebinarPageService::blockCatalog); this only decorates it.
 */
export const BLOCK_ICONS = {
    heading: 'M4 6h16M4 12h10M4 18h7',
    text: 'M4 6h16M4 10h16M4 14h12M4 18h8',
    image: 'M4 5h16v14H4z M8 11l3 3 2-2 3 4',
    video: 'M4 6h16v12H4z M10 9l5 3-5 3z',
    button: 'M5 9h14a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 012-2z',
    divider: 'M3 12h18',
    spacer: 'M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4',
    html: 'M9 8l-4 4 4 4M15 8l4 4-4 4',
    form: 'M4 5h16v14H4z M7 9h10M7 13h10M7 17h5',
    sessions: 'M4 6h16v14H4z M4 10h16M9 3v4M15 3v4',
    date_card: 'M4 6h16v14H4z M4 10h16M9 3v4M15 3v4M8 14h3',
    countdown: 'M12 8v5l3 2M12 3a9 9 0 100 18 9 9 0 000-18z',
    registration_details: 'M4 5h16v14H4z M8 9h8M8 13h8M8 17h4',
    calendly: 'M7 3v4M17 3v4M4 9h16M5 5h14v16H5z M9 14h2v2H9z',
    benefits: 'M5 12l4 4L19 7M5 6h.01M5 18h.01',
    steps: 'M4 7h4v4H4z M10 9h10M4 15h4v4H4z M10 17h10',
    testimonials: 'M7 8h10v8H7z M9 16l-2 4M12 10h3M12 13h3',
    faq: 'M12 18h.01M9.1 9a3 3 0 115.8 1c0 2-2.9 2.5-2.9 4M12 3a9 9 0 100 18 9 9 0 000-18z',
    speaker: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 20c0-3.3 3.6-6 8-6s8 2.7 8 6',
    stats: 'M4 19V9M10 19V5M16 19v-7M22 19H2',
};

/**
 * Which editors the properties panel renders for a block's props, in order.
 * `items` prop editors declare the fields of one repeatable entry.
 */
export const BLOCK_FIELDS = {
    heading: [
        { key: 'text', type: 'text' },
        { key: 'level', type: 'select', options: [1, 2, 3, 4] },
        { key: 'size', type: 'segment', options: ['sm', 'md', 'lg', 'xl'] },
        { key: 'align', type: 'align' },
        { key: 'color', type: 'select', options: ['heading', 'text', 'muted', 'primary'] },
    ],
    text: [
        { key: 'body', type: 'textarea' },
        { key: 'size', type: 'segment', options: ['sm', 'md', 'lg'] },
        { key: 'align', type: 'align' },
        { key: 'color', type: 'select', options: ['text', 'muted', 'heading', 'primary'] },
    ],
    image: [
        { key: 'url', type: 'text' },
        { key: 'alt', type: 'text' },
        { key: 'link', type: 'text' },
        { key: 'width', type: 'range', min: 20, max: 100, step: 5 },
        { key: 'radius', type: 'select', options: ['none', 'sm', 'md', 'lg', 'xl', 'full'] },
        { key: 'align', type: 'align' },
    ],
    video: [
        { key: 'url', type: 'text' },
        { key: 'caption', type: 'text' },
    ],
    button: [
        { key: 'label', type: 'text' },
        { key: 'url', type: 'text' },
        { key: 'style', type: 'segment', options: ['solid', 'outline'] },
        { key: 'size', type: 'segment', options: ['sm', 'md', 'lg'] },
        { key: 'align', type: 'align' },
        { key: 'full_width', type: 'toggle' },
    ],
    divider: [
        { key: 'style', type: 'segment', options: ['line', 'space'] },
        { key: 'size', type: 'segment', options: ['sm', 'md', 'lg', 'xl'] },
    ],
    spacer: [{ key: 'size', type: 'segment', options: ['sm', 'md', 'lg', 'xl'] }],
    html: [{ key: 'code', type: 'code' }],
    form: [
        { key: 'title', type: 'text' },
        { key: 'button_label', type: 'text' },
        { key: 'consent', type: 'textarea' },
        { key: 'show_first_name', type: 'toggle' },
        { key: 'show_last_name', type: 'toggle' },
        { key: 'show_phone', type: 'toggle' },
        { key: 'show_timezone', type: 'toggle' },
        { key: 'require_name', type: 'toggle' },
    ],
    sessions: [
        { key: 'title', type: 'text' },
        { key: 'subtitle', type: 'text' },
    ],
    date_card: [{ key: 'title', type: 'text' }],
    countdown: [
        { key: 'title', type: 'text' },
        { key: 'target', type: 'select', options: ['auto', 'custom'] },
        { key: 'custom_at', type: 'datetime', showIf: { target: 'custom' } },
        { key: 'expired_text', type: 'text' },
    ],
    registration_details: [
        { key: 'show_time', type: 'toggle' },
        { key: 'show_link', type: 'toggle' },
        { key: 'show_calendar', type: 'toggle' },
    ],
    calendly: [
        { key: 'url', type: 'text' },
        { key: 'title', type: 'text' },
        { key: 'description', type: 'textarea' },
    ],
    benefits: [
        { key: 'title', type: 'text' },
        { key: 'icon', type: 'segment', options: ['check', 'star', 'arrow'] },
        { key: 'columns', type: 'segment', options: [1, 2] },
        { key: 'items', type: 'items', fields: [{ key: 'text', type: 'text' }] },
    ],
    steps: [
        { key: 'title', type: 'text' },
        { key: 'items', type: 'items', fields: [{ key: 'title', type: 'text' }, { key: 'body', type: 'textarea' }] },
    ],
    testimonials: [
        { key: 'title', type: 'text' },
        { key: 'columns', type: 'segment', options: [1, 2, 3] },
        {
            key: 'items',
            type: 'items',
            fields: [
                { key: 'quote', type: 'textarea' },
                { key: 'author', type: 'text' },
                { key: 'role', type: 'text' },
                { key: 'avatar', type: 'text' },
            ],
        },
    ],
    faq: [
        { key: 'title', type: 'text' },
        { key: 'items', type: 'items', fields: [{ key: 'question', type: 'text' }, { key: 'answer', type: 'textarea' }] },
    ],
    speaker: [
        { key: 'name', type: 'text' },
        { key: 'role', type: 'text' },
        { key: 'bio', type: 'textarea' },
        { key: 'avatar', type: 'text' },
        { key: 'layout', type: 'segment', options: ['side', 'stacked'] },
    ],
    stats: [
        { key: 'items', type: 'items', fields: [{ key: 'value', type: 'text' }, { key: 'label', type: 'text' }] },
    ],
};

export const GROUP_ORDER = ['basic', 'conversion', 'content'];

/**
 * Short human summary of a block, shown on the canvas card.
 */
export function blockSummary(block) {
    const p = block.props || {};

    switch (block.type) {
        case 'heading':
            return p.text;
        case 'text':
            return p.body;
        case 'button':
            return p.label;
        case 'image':
        case 'video':
            return p.url;
        case 'speaker':
            return p.name;
        case 'calendly':
            return p.url;
        case 'benefits':
        case 'steps':
        case 'faq':
        case 'testimonials':
        case 'stats':
            return Array.isArray(p.items) && p.items.length ? `${p.items.length}` : '';
        default:
            return p.title || '';
    }
}

export function newId(prefix = 'b') {
    return `${prefix}_${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Deep clone that is safe for the plain JSON structures the builder edits.
 */
export function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

export function columnsForLayout(layout) {
    return String(layout).split('-').length;
}
