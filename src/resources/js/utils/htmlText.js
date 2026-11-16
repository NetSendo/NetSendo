// Plain text of an HTML fragment, for showing it as text or copying it as text.
// Entities are decoded (&amp; becomes &), and <script>/<style> content is left
// out. A DOMParser document is inert: scripts don't run and images don't load.
// The result is text, not safe HTML: escape it before putting it into markup.
export const htmlToText = (html) => {
    if (!html) return "";
    const doc = new DOMParser().parseFromString(html, "text/html");
    doc.querySelectorAll("script, style").forEach((el) => el.remove());
    return doc.body.textContent || "";
};
