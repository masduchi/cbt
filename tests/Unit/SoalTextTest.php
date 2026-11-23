<?php

namespace Tests\Unit;

use App\Support\SoalText;
use PHPUnit\Framework\TestCase;

class SoalTextTest extends TestCase
{
    public function test_hash_ignores_html_formatting_and_spacing(): void
    {
        $a = SoalText::hash('<p>Hitung <strong>2 + 3</strong></p>');
        $b = SoalText::hash('hitung 2+3');

        $this->assertSame($a, $b);
    }

    public function test_hash_differs_for_different_questions(): void
    {
        $this->assertNotSame(SoalText::hash('Berapa 2 + 3?'), SoalText::hash('Berapa 2 + 4?'));
    }

    public function test_empty_question_has_empty_hash(): void
    {
        $this->assertSame(sha1(''), SoalText::hash('<p> </p>'));
    }

    public function test_plain_converts_breaks_and_decodes_entities(): void
    {
        $text = SoalText::plain('<p>Baris satu<br>Baris &amp; dua</p>');

        $this->assertSame("Baris satu\nBaris & dua", $text);
    }
}
