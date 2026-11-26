<?php
/**
 * Checks the small PDF writer the receipts use.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Pdf\PdfDocument;
use PHPUnit\Framework\TestCase;

/**
 * A reader opens a PDF by its byte offsets, so a wrong one makes the whole file unreadable. These pin them down.
 */
final class PdfDocumentTest extends TestCase
{

    public function test_every_xref_offset_points_at_its_object(): void
    {
        $doc = new PdfDocument();
        $doc->add_page();
        $doc->text(48, 56, 'Café (São João) \\ ok', 14, true);
        $doc->add_page();
        $doc->rect(0, 0, 100, 20, array(0, 96, 156));

        $pdf = $doc->output('Receipt X');

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertSame(1, preg_match('/startxref\n(\d+)/', $pdf, $start));
        $this->assertSame('xref', substr($pdf, (int) $start[1], 4));

        $lines = explode("\n", substr($pdf, (int) $start[1]));
        $count = (int) explode(' ', $lines[1])[1];

        for ($id = 1; $id < $count; $id++) {
            $offset = (int) substr($lines[2 + $id], 0, 10);
            $this->assertStringStartsWith($id . ' 0 obj', substr($pdf, $offset, 12), 'object ' . $id);
        }
    }

    public function test_each_page_is_in_the_page_tree(): void
    {
        $doc = new PdfDocument();
        $doc->add_page();
        $doc->add_page();
        $doc->add_page();

        $this->assertSame(3, $doc->page_count());
        $this->assertStringContainsString('/Count 3', $doc->output());
    }

    public function test_the_stream_length_matches_what_is_written(): void
    {
        $doc = new PdfDocument();
        $doc->add_page();
        $doc->text(10, 10, 'Olá', 12);
        $pdf = $doc->output();

        $this->assertSame(1, preg_match('/<< \/Length (\d+) >>\nstream\n/', $pdf, $found, PREG_OFFSET_CAPTURE));
        $from = $found[0][1] + strlen($found[0][0]);

        $this->assertSame("\nendstream", substr($pdf, $from + (int) $found[1][0], 10));
    }

    public function test_brackets_and_backslashes_cannot_end_the_string(): void
    {
        $doc = new PdfDocument();
        $doc->add_page();
        $doc->text(10, 10, 'a) Tj (b \\ c', 12);

        $this->assertStringContainsString('(a\\) Tj \\(b \\\\ c)', $doc->output());
    }

    public function test_accented_letters_are_written_as_windows_1252(): void
    {
        $doc = new PdfDocument();
        $doc->add_page();
        $doc->text(10, 10, 'ção €', 12);

        // ç is 0xE7, ã 0xE3 and the euro sign 0x80 in Windows-1252.
        $this->assertStringContainsString("(\xE7\xE3o \x80)", $doc->output());
    }

    public function test_wrap_keeps_every_line_inside_the_width(): void
    {
        $doc   = new PdfDocument();
        $lines = $doc->wrap('Uma linha muito longa que precisa de ser partida em várias linhas Supercalifragilisticexpialidocious_Supercalifragilisticexpialidocious', 11, 120);

        $this->assertGreaterThan(3, count($lines));

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(120.0, $doc->width($line, 11));
        }

        $this->assertSame('uma linha', strtolower(implode(' ', array_slice(explode(' ', $lines[0]), 0, 2))));
    }

    public function test_wrap_keeps_a_line_break_in_the_text(): void
    {
        $this->assertSame(array('one', 'two'), (new PdfDocument())->wrap("one\ntwo", 11, 300));
    }

    public function test_right_aligned_text_ends_at_the_edge(): void
    {
        $doc = new PdfDocument();
        $doc->add_page();
        $doc->text(500, 20, 'Receipt', 12, false, array(0, 0, 0), true);

        $this->assertSame(1, preg_match('/Tf ([\d.]+) /', $doc->output(), $found));
        $this->assertEqualsWithDelta(500 - $doc->width('Receipt', 12), (float) $found[1], 0.01);
    }
}
