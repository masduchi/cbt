<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_strips_scripts_and_event_handlers(): void
    {
        $html = '<p onclick="alert(1)">Halo</p><script>alert(2)</script><img src="x" onerror="alert(3)">';

        $clean = HtmlSanitizer::clean($html);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('Halo', $clean);
    }

    public function test_it_blocks_javascript_urls(): void
    {
        $clean = HtmlSanitizer::clean('<a href="javascript:alert(1)">Klik</a>');

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('Klik', $clean);
    }

    public function test_it_keeps_safe_formatting_and_tables(): void
    {
        $html = '<table><tr><td><strong>Teks</strong></td></tr></table>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_plain_text_is_escaped_and_line_breaks_preserved(): void
    {
        $clean = HtmlSanitizer::clean("Baris < satu\nBaris dua");

        $this->assertStringContainsString('&lt;', $clean);
        $this->assertStringContainsString('<br', $clean);
    }

    public function test_it_allows_data_image_sources(): void
    {
        $clean = HtmlSanitizer::clean('<img src="data:image/png;base64,AAAA" alt="grafik">');

        $this->assertStringContainsString('data:image/png', $clean);
    }
}
