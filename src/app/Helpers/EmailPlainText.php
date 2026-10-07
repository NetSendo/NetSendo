<?php

namespace App\Helpers;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Builds the text/plain alternative of an e-mail from its HTML.
 *
 * HTML-only mail is scored down by spam filters (SpamAssassin MIME_HTML_ONLY)
 * and unreadable in text-only clients, so every send carries a text part: the
 * message's own plain text when it has one, else the text produced here.
 *
 * The conversion keeps what a reader needs — paragraphs, line breaks, list
 * items, headings, link targets ("label (https://…)") and image alt texts —
 * and drops what only renders in HTML: <head>, styles, scripts, comments,
 * elements hidden with display:none (the preheader) and tracking pixels.
 */
class EmailPlainText
{
    private const BLOCK_TAGS = [
        'address', 'article', 'aside', 'blockquote', 'center', 'dd', 'div', 'dl', 'dt',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4',
        'h5', 'h6', 'header', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section',
        'table', 'tbody', 'tfoot', 'thead', 'tr', 'ul',
    ];

    private const SKIPPED_TAGS = ['head', 'style', 'script', 'title', 'meta', 'link', 'noscript', 'template', 'svg'];

    /**
     * Characters used to pad preheaders or that are invisible anyway:
     * combining grapheme joiner, zero-width (non-)joiners/space, BOM, soft hyphen.
     */
    private const INVISIBLE = "/[\x{034F}\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u";

    /**
     * The text part to send with an e-mail: the given plain text when it has
     * any, else text generated from the HTML. Null when the text alternative
     * is switched off (config netsendo.email.plain_text_alternative) or there
     * is nothing to say.
     */
    public static function forEmail(?string $plainText, string $html): ?string
    {
        if (!config('netsendo.email.plain_text_alternative', true)) {
            return null;
        }

        $text = $plainText !== null && trim($plainText) !== ''
            ? trim(str_replace(["\r\n", "\r"], "\n", $plainText))
            : self::fromHtml($html);

        return $text === '' ? null : $text;
    }

    public static function fromHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('body')->item(0) ?? $document->documentElement;
        if (!$root) {
            return self::tidy(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return self::tidy(self::render($root));
    }

    private static function render(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            // Whitespace in HTML text collapses to a single space
            return preg_replace('/\s+/u', ' ', $node->textContent);
        }

        if (!$node instanceof DOMElement) {
            // Comments, processing instructions (the encoding hint), doctype
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::SKIPPED_TAGS, true) || self::isHidden($node)) {
            return '';
        }

        switch ($tag) {
            case 'br':
                return "\n";
            case 'hr':
                return "\n\n----------\n\n";
            case 'img':
                return self::renderImage($node);
            case 'pre':
                return "\n\n" . $node->textContent . "\n\n";
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= self::render($child);
        }

        switch ($tag) {
            case 'a':
                return self::renderLink($node, $inner);
            case 'li':
                return "\n- " . trim($inner);
            case 'td':
            case 'th':
                return ' ' . $inner . ' ';
            case 'h1':
            case 'h2':
                $heading = trim($inner);
                return $heading === '' ? '' : "\n\n" . mb_strtoupper($heading, 'UTF-8') . "\n\n";
        }

        if (in_array($tag, self::BLOCK_TAGS, true)) {
            return "\n\n" . $inner . "\n\n";
        }

        return $inner;
    }

    private static function renderLink(DOMElement $node, string $label): string
    {
        $href = trim($node->getAttribute('href'));
        $label = trim(preg_replace('/\s+/u', ' ', $label));

        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return $label;
        }

        $target = preg_replace('/^mailto:/i', '', $href);

        if ($label === '' || $label === $href || $label === $target) {
            return $target;
        }

        return $label . ' (' . $target . ')';
    }

    private static function renderImage(DOMElement $node): string
    {
        $width = $node->getAttribute('width');
        $height = $node->getAttribute('height');

        // Tracking pixels and spacers say nothing
        if (($width !== '' && (int) $width <= 1) || ($height !== '' && (int) $height <= 1)) {
            return '';
        }

        $alt = trim($node->getAttribute('alt'));

        return $alt === '' ? '' : ' ' . $alt . ' ';
    }

    private static function isHidden(DOMElement $node): bool
    {
        $style = strtolower(str_replace(' ', '', $node->getAttribute('style')));

        return str_contains($style, 'display:none')
            || str_contains($style, 'visibility:hidden')
            || $node->hasAttribute('hidden');
    }

    private static function tidy(string $text): string
    {
        $text = preg_replace(self::INVISIBLE, '', $text);
        $text = str_replace("\u{00A0}", ' ', $text);

        $lines = array_map(
            fn ($line) => trim(preg_replace('/[ \t]+/u', ' ', $line)),
            explode("\n", str_replace(["\r\n", "\r"], "\n", $text))
        );

        // At most one empty line between blocks
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        return trim($text);
    }
}
