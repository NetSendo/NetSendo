import { Marked } from "marked";

export const escapeHtml = (text) =>
    String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

const isSafeUrl = (url) => /^(https?:|mailto:)/i.test(url.trim());

// marked has no sanitizer, and its output goes into v-html. The markdown comes
// from sources we don't control (GitHub release notes, Brain replies echoing
// emails, web research and subscriber data), so raw HTML is shown as text and
// only http(s)/mailto links and images are kept; anything else (javascript:,
// data:, ...) renders as its label or alt text.
const safeRenderer = {
    html({ text }) {
        return escapeHtml(text);
    },
    link({ href, title, tokens }) {
        const label = this.parser.parseInline(tokens);
        if (!isSafeUrl(href)) return label;
        const titleAttr = title ? ` title="${escapeHtml(title)}"` : "";
        return `<a href="${escapeHtml(href)}"${titleAttr} target="_blank" rel="noopener noreferrer">${label}</a>`;
    },
    image({ href, text }) {
        if (!isSafeUrl(href)) return escapeHtml(text);
        return `<img src="${escapeHtml(href)}" alt="${escapeHtml(text)}" loading="lazy">`;
    },
};

// Returns a render function: markdown string in, HTML safe for v-html out.
export const createSafeMarkdown = (options = {}) => {
    const markdown = new Marked({ gfm: true, ...options, renderer: safeRenderer });
    return (content) => (content ? markdown.parse(content) : "");
};
