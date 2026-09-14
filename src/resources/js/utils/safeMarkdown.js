import { Marked } from "marked";

export const escapeHtml = (text) =>
    String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

const isSafeUrl = (url) => /^(https?:|mailto:)/i.test(url.trim());

const linkTag = (href, title, label) => {
    const titleAttr = title ? ` title="${escapeHtml(title)}"` : "";
    return `<a href="${escapeHtml(href)}"${titleAttr} target="_blank" rel="noopener noreferrer">${label}</a>`;
};

// marked has no sanitizer, and its output goes into v-html. The markdown comes
// from sources we don't control (GitHub release notes, Brain replies echoing
// emails, web research and subscriber data), so raw HTML is shown as text and
// only http(s)/mailto links and images are kept; anything else (javascript:,
// data:, ...) renders as its label or alt text.
//
// images: false renders an image as a link to it instead. An <img> loads as
// soon as the message is shown, so a reply steered by injected content could
// leak data in the image URL without anyone clicking.
export const createSafeMarkdown = ({ images = true, ...options } = {}) => {
    let inLink = false;

    const renderer = {
        html({ text }) {
            return escapeHtml(text);
        },
        link({ href, title, tokens }) {
            inLink = true;
            try {
                const label = this.parser.parseInline(tokens);
                return isSafeUrl(href) ? linkTag(href, title, label) : label;
            } finally {
                inLink = false;
            }
        },
        image({ href, title, text }) {
            if (!isSafeUrl(href)) return escapeHtml(text);
            if (images) {
                return `<img src="${escapeHtml(href)}" alt="${escapeHtml(text)}" loading="lazy">`;
            }
            // Inside a link the image is its label; a nested <a> is invalid HTML
            if (inLink) return escapeHtml(text || href);
            return linkTag(href, title, escapeHtml(text || href));
        },
    };

    const markdown = new Marked({ gfm: true, ...options, renderer });
    return (content) => (content ? markdown.parse(content) : "");
};
