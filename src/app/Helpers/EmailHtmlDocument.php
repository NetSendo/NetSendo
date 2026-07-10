<?php

namespace App\Helpers;

/**
 * Guarantees that outgoing e-mails carry a valid, top-level HTML document
 * structure (<!DOCTYPE html>, <html>, <head>, <body>).
 *
 * The GUI editor and the API can produce "fragment" content — a bare
 * preheader <div>, or content that starts straight with a <div>/<table> —
 * with no document wrapper. Sent as-is, such messages are penalised by spam
 * filters (SpamAssassin's HTML_MIME_NO_HTML_TAG — "HTML-only message, but
 * there is no HTML tag", ~-0.6 deliverability points). See issue #22.
 *
 * Content that is ALREADY a full document (it contains an <html> or <body>
 * tag) is returned untouched, so hand-authored templates and Blade-rendered
 * mailables keep working exactly as before — no nested or duplicated tags.
 */
class EmailHtmlDocument
{
    /**
     * Wrap fragment HTML in a full document, or return a full document as-is.
     *
     * Wrapping can be disabled globally via config('netsendo.email.wrap_html_document').
     *
     * @param string      $content The (already placeholder-processed) HTML body/fragment.
     * @param string|null $subject Used for the <title> element (helps some clients & tools).
     * @param string|null $lang    Language code for the <html lang="…"> attribute; falls back to app locale.
     */
    public static function wrap(string $content, ?string $subject = null, ?string $lang = null): string
    {
        if (!config('netsendo.email.wrap_html_document', true)) {
            return $content;
        }

        if (self::isFullDocument($content)) {
            return $content;
        }

        $lang = self::normalizeLang($lang);

        $title = trim((string) $subject);
        if ($title === '') {
            $title = (string) config('app.name', 'Email');
        }
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="' . $lang . '" xmlns="http://www.w3.org/1999/xhtml">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
            . '<meta name="x-apple-disable-message-reformatting">' . "\n"
            . '<title>' . $title . '</title>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . $content . "\n"
            . '</body>' . "\n"
            . '</html>';
    }

    /**
     * Whether $content already carries a document-level <html> or <body> tag.
     * When true, wrapping is skipped to avoid nested/duplicated structures.
     */
    public static function isFullDocument(string $content): bool
    {
        return (bool) preg_match('/<html[\s>]/i', $content)
            || (bool) preg_match('/<body[\s>]/i', $content);
    }

    /**
     * Sanitise a language code for safe use inside the lang="…" attribute,
     * falling back to the application locale (then "en") when absent.
     */
    private static function normalizeLang(?string $lang): string
    {
        $lang = trim((string) $lang);

        if ($lang === '') {
            $lang = (string) config('app.locale', 'en');
        }

        // Keep only ISO subtag characters so user-controlled input cannot break
        // out of the attribute.
        $lang = preg_replace('/[^a-zA-Z\-]/', '', $lang);

        return strtolower($lang !== '' ? $lang : 'en');
    }
}
