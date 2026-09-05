<?php

namespace Tests\Unit\Manifest;

use App\Services\Manifest\ManifestExtractor;
use PHPUnit\Framework\TestCase;

class ManifestExtractorTest extends TestCase
{
    private ManifestExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ManifestExtractor;
    }

    public function test_rule_1_missing_block(): void
    {
        $result = $this->extractor->extract('<html><body><script>1</script></body></html>');

        $this->assertNull($result['manifest']);
        $this->assertSame(1, $result['problems'][0]->rule);
        $this->assertStringContainsString('dashboard-manifest', $result['problems'][0]->message);
    }

    public function test_rule_1_duplicate_blocks(): void
    {
        $block = '<script type="application/json" id="dashboard-manifest">{"id":"a"}</script>';
        $result = $this->extractor->extract("<html>{$block}{$block}</html>");

        $this->assertSame(1, $result['problems'][0]->rule);
        $this->assertStringContainsString('2 bloques', $result['problems'][0]->message);
    }

    public function test_rule_2_invalid_json(): void
    {
        $result = $this->extractor->extract('<script id="dashboard-manifest" type="application/json">{"id": }</script>');

        $this->assertSame(2, $result['problems'][0]->rule);
        $this->assertStringContainsString('no es JSON válido', $result['problems'][0]->message);
    }

    public function test_rule_2_json_must_be_an_object(): void
    {
        $result = $this->extractor->extract('<script type="application/json" id="dashboard-manifest">[1,2]</script>');

        $this->assertSame(2, $result['problems'][0]->rule);
    }

    public function test_extracts_manifest_regardless_of_attribute_order_and_quotes(): void
    {
        $result = $this->extractor->extract("<script id='dashboard-manifest' type='application/json'>\n {\"id\":\"x\",\"params\":[]} \n</script>");

        $this->assertSame([], $result['problems']);
        $this->assertSame('x', $result['manifest']['id']);
    }

    public function test_manifest_tag_inside_an_html_comment_is_ignored(): void
    {
        $html = "<!-- usa <script type=\"application/json\" id=\"dashboard-manifest\"> -->\n<script type=\"application/json\" id=\"dashboard-manifest\">{\"id\":\"ok\"}</script>";

        $result = $this->extractor->extract($html);

        $this->assertSame([], $result['problems']);
        $this->assertSame('ok', $result['manifest']['id']);
    }

    public function test_strip_preserves_line_numbers(): void
    {
        $html = "<!-- a\nb -->\n<script type=\"application/json\" id=\"dashboard-manifest\">\n{}\n</script>\n<script>x</script>";

        $this->assertSame("\n\n\n\n\n<script>x</script>", $this->extractor->strip($html));
    }

    public function test_strip_removes_the_block_for_scanning(): void
    {
        $html = '<a></a><script type="application/json" id="dashboard-manifest">{"note":"localStorage"}</script><b></b>';

        $this->assertSame('<a></a><b></b>', $this->extractor->strip($html));
    }
}
