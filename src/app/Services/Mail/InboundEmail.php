<?php

namespace App\Services\Mail;

use Webklex\PHPIMAP\Message;

/**
 * An email read from a mailbox's reply inbox, reduced to what reply detection needs.
 */
class InboundEmail
{
    /**
     * Subject prefixes mail clients put on a reply (Re:, Odp:, AW:, SV:, ...).
     */
    private const REPLY_SUBJECT_PATTERN = '/^\s*(re|odp|aw|sv|antw|rif|res)\s*(\[\d+\])?\s*:/iu';

    /**
     * Subjects of out-of-office and other automatic replies.
     */
    private const AUTOMATIC_SUBJECT_PATTERN = '/^\s*((re|odp|aw|sv)\s*:\s*)*(auto(matic)?[\s-]?(reply|response)|autoreply|autoresponder|out of (the )?office|odpowiedź automatyczna|automatyczna odpowiedź|poza biurem|automatische antwort|abwesenheitsnotiz|respuesta automática|fuera de la oficina)/iu';

    /**
     * Lines that start the quoted conversation below a reply.
     */
    private const QUOTE_MARKERS = [
        // "On Mon, 14 Sep 2026, Anna <anna@example.com> wrote:", possibly wrapped onto a second line
        '/^[ \t]*(On|Am|El|Le|W dniu|Dnia)\s[^\n]*(\n[^\n]*)?(wrote|schrieb|escribió|a écrit|napisał\(a\)|napisała|napisał|pisze)[ \t]*:[ \t]*$/imu',
        '/^[ \t]*-{2,}[ \t]*(Original Message|Wiadomość oryginalna|Oryginalna wiadomość|Ursprüngliche Nachricht|Mensaje original)[ \t]*-{2,}/imu',
        // Outlook: a rule line, or the From:/Sent: block of the quoted message
        '/^[ \t]*_{10,}[ \t]*$/mu',
        '/^[ \t]*(From|Od|Von|De):[ \t][^\n]*\n[ \t]*(Sent|Wysłano|Wysłane|Gesendet|Enviado|Date|Data):/imu',
        // Signature delimiter
        '/^-- $/mu',
    ];

    /**
     * @param array<string, string> $headers lower-case header name => decoded value of its first occurrence
     */
    public function __construct(
        public readonly string $fromEmail,
        public readonly string $subject,
        public readonly array $headers = [],
        public readonly string $textBody = '',
        public readonly string $htmlBody = '',
    ) {}

    /**
     * Build from a raw RFC 5322 message, or from its header block alone.
     */
    public static function fromRaw(string $raw): self
    {
        $normalized = str_replace("\r\n", "\n", $raw);
        [$rawHeaders, $body] = array_pad(explode("\n\n", $normalized, 2), 2, '');
        $headers = self::parseHeaders($rawHeaders);

        $textBody = $htmlBody = '';
        if (trim($body) !== '') {
            try {
                $message = Message::fromString($raw);
                $textBody = $message->getTextBody();
                $htmlBody = $message->getHTMLBody();
            } catch (\Throwable) {
                // An unparsable body (e.g. an invalid Date header) still leaves a reply without an excerpt
            }
        }

        return new self(
            fromEmail: self::parseAddress($headers['from'] ?? ''),
            subject: mb_scrub($headers['subject'] ?? '', 'UTF-8'),
            headers: $headers,
            textBody: mb_scrub($textBody, 'UTF-8'),
            htmlBody: mb_scrub($htmlBody, 'UTF-8'),
        );
    }

    /**
     * The Message-ID without angle brackets, or a hash of the sender, date and
     * subject when the header is missing. Identifies the email across scans.
     */
    public function messageId(): string
    {
        $id = trim($this->headers['message-id'] ?? '', " \t<>");

        if ($id !== '') {
            return $id;
        }

        return 'sha1:' . sha1($this->fromEmail . '|' . ($this->headers['date'] ?? '') . '|' . $this->subject);
    }

    /**
     * Whether the sender wrote this by replying to an earlier email.
     */
    public function isReply(): bool
    {
        return trim($this->headers['in-reply-to'] ?? '') !== ''
            || trim($this->headers['references'] ?? '') !== ''
            || preg_match(self::REPLY_SUBJECT_PATTERN, $this->subject) === 1;
    }

    /**
     * Whether a machine sent this: an out-of-office reply, autoresponder,
     * newsletter, delivery or read report.
     */
    public function isAutomatic(): bool
    {
        $header = fn (string $name): string => strtolower(trim($this->headers[$name] ?? ''));

        // RFC 3834: anything but "no" is an automatic message
        if ($header('auto-submitted') !== '' && !str_starts_with($header('auto-submitted'), 'no')) {
            return true;
        }

        foreach (['x-autoreply', 'x-autorespond', 'x-autoresponder', 'x-failed-recipients', 'list-id', 'list-unsubscribe'] as $name) {
            if ($header($name) !== '') {
                return true;
            }
        }

        if (in_array($header('precedence'), ['bulk', 'junk', 'list', 'auto_reply'], true)
            || $header('return-path') === '<>'
            || str_contains($header('content-type'), 'multipart/report')) {
            return true;
        }

        return preg_match(self::AUTOMATIC_SUBJECT_PATTERN, $this->subject) === 1;
    }

    /**
     * The start of what the sender wrote, without the quoted conversation.
     */
    public function excerpt(int $limit = 300): string
    {
        $text = $this->textBody !== '' ? $this->textBody : self::htmlToText($this->htmlBody);
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);

        $quoteStart = null;
        foreach (self::QUOTE_MARKERS as $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
                $quoteStart = min($quoteStart ?? PHP_INT_MAX, $match[0][1]);
            }
        }
        if ($quoteStart !== null) {
            $text = substr($text, 0, $quoteStart);
        }

        $lines = array_filter(
            array_map('trim', explode("\n", $text)),
            fn (string $line) => !str_starts_with($line, '>'),
        );
        $text = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace !== false && $lastSpace > $limit * 0.6 ? mb_substr($cut, 0, $lastSpace) : $cut) . '…';
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeaders(string $rawHeaders): array
    {
        // Unfold header values continued on indented lines
        $unfolded = preg_replace('/\n[ \t]+/', ' ', $rawHeaders);

        $headers = [];
        foreach (explode("\n", $unfolded) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));

            if ($name !== '' && !isset($headers[$name])) {
                $value = trim($value);
                $headers[$name] = str_contains($value, '=?') ? mb_decode_mimeheader($value) : $value;
            }
        }

        return $headers;
    }

    private static function parseAddress(string $from): string
    {
        $address = preg_match('/<([^<>]+)>/', $from, $match)
            ? $match[1]
            : (preg_match('/[^\s<>"\'(),;]+@[^\s<>"\'(),;]+/', $from, $match) ? $match[0] : '');

        $address = strtolower(trim($address));

        return filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : '';
    }

    private static function htmlToText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<(head|style|script)\b[^>]*>.*?<\/\1>/is', '', $html);
        // Gmail and Apple Mail put the quoted conversation in a blockquote or a gmail_quote block
        $html = preg_replace('/<div[^>]*class="[^"]*gmail_quote[^"]*".*$/is', '', $html);
        $html = preg_replace('/<blockquote\b.*<\/blockquote>/is', '', $html);
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/<(br|\/p|\/div|\/li|\/tr|\/h[1-6])\b[^>]*>/i', "\n", $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
