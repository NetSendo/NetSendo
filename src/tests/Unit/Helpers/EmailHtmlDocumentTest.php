<?php

namespace Tests\Unit\Helpers;

use App\Helpers\EmailHtmlDocument;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EmailHtmlDocumentTest extends TestCase
{
    public function test_fragment_content_is_wrapped_in_a_full_document(): void
    {
        $fragment = '<div style="color:red">Hello</div>';

        $result = EmailHtmlDocument::wrap($fragment, 'My subject');

        $this->assertStringStartsWith('<!DOCTYPE html>', $result);
        $this->assertMatchesRegularExpression('/<html[\s>]/i', $result);
        $this->assertStringContainsString('<head>', $result);
        $this->assertStringContainsString('<body>', $result);
        $this->assertStringContainsString('</body>', $result);
        $this->assertStringContainsString('</html>', $result);
        // Original content is preserved inside the body.
        $this->assertStringContainsString($fragment, $result);
    }

    public function test_preheader_fragment_is_wrapped(): void
    {
        // Reproduces issue #22: content that begins with a preheader div and
        // has no <html>/<body> wrapper.
        $fragment = "<!-- Preheader text -->\n<div style=\"display:none;max-height:0;overflow:hidden;\">Hi</div>\n<div>Body</div>";

        $result = EmailHtmlDocument::wrap($fragment, 'Subject');

        $this->assertMatchesRegularExpression('/<html[\s>]/i', $result);
        $this->assertStringContainsString('<body>', $result);
    }

    public function test_content_with_html_tag_is_returned_unchanged(): void
    {
        $full = "<!DOCTYPE html>\n<html><head></head><body><p>Hi</p></body></html>";

        $this->assertSame($full, EmailHtmlDocument::wrap($full, 'Subject'));
    }

    public function test_content_with_body_tag_only_is_treated_as_full_document(): void
    {
        $withBody = '<body><p>Hi</p></body>';

        $this->assertTrue(EmailHtmlDocument::isFullDocument($withBody));
        $this->assertSame($withBody, EmailHtmlDocument::wrap($withBody, 'Subject'));
    }

    public function test_is_full_document_detection(): void
    {
        $this->assertTrue(EmailHtmlDocument::isFullDocument('<HTML lang="en"></HTML>'));
        $this->assertTrue(EmailHtmlDocument::isFullDocument('<html>x</html>'));
        $this->assertTrue(EmailHtmlDocument::isFullDocument('<BODY>x</BODY>'));
        $this->assertFalse(EmailHtmlDocument::isFullDocument('<div>x</div>'));
        // Substrings such as "htmlContent" must not be mistaken for a real tag.
        $this->assertFalse(EmailHtmlDocument::isFullDocument('<p>htmlContent and body text</p>'));
    }

    public function test_subject_is_html_escaped_in_the_title(): void
    {
        $result = EmailHtmlDocument::wrap('<p>x</p>', 'A & B <script>"x"');

        $this->assertStringContainsString('<title>A &amp; B &lt;script&gt;&quot;x&quot;</title>', $result);
        // The raw subject must not leak into the document as markup.
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function test_language_attribute_uses_provided_value_and_is_sanitised(): void
    {
        $this->assertStringContainsString('<html lang="pl"', EmailHtmlDocument::wrap('<p>x</p>', 'S', 'pl'));
        $this->assertStringContainsString('<html lang="en-us"', EmailHtmlDocument::wrap('<p>x</p>', 'S', 'en-US'));
        // Attribute-injection attempt is stripped down to safe characters.
        $result = EmailHtmlDocument::wrap('<p>x</p>', 'S', 'en"><script>');
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function test_wrapping_can_be_disabled_via_config(): void
    {
        Config::set('netsendo.email.wrap_html_document', false);

        $fragment = '<div>Hello</div>';
        $this->assertSame($fragment, EmailHtmlDocument::wrap($fragment, 'Subject'));
    }
}
