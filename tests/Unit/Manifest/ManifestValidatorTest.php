<?php

namespace Tests\Unit\Manifest;

use App\Services\Manifest\ManifestValidator;
use App\Services\Params\ParamValueValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDashboardHtml;

class ManifestValidatorTest extends TestCase
{
    use BuildsDashboardHtml;

    private ManifestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ManifestValidator(new ParamValueValidator);
    }

    /** @return list<array{rule:int, path:string, message:string}> */
    private function problems(array $manifest): array
    {
        return array_map(fn ($p) => $p->toArray(), $this->validator->validate($manifest));
    }

    private function withParam(array $param): array
    {
        return $this->fullManifest(['params' => [$param]]);
    }

    public function test_happy_path_with_all_seven_types(): void
    {
        $this->assertSame([], $this->problems($this->fullManifest()));
    }

    public function test_rule_3_root_fields_are_required(): void
    {
        $problems = $this->problems(['params' => 'no']);
        $paths = array_column($problems, 'path');

        $this->assertContains('id', $paths);
        $this->assertContains('version', $paths);
        $this->assertContains('title', $paths);
        $this->assertContains('params', $paths);
        $this->assertSame([3], array_unique(array_column($problems, 'rule')));
    }

    public function test_rule_3_param_needs_id_and_label(): void
    {
        $problems = $this->problems($this->withParam(['type' => 'boolean', 'default' => true]));

        $this->assertSame('params[0].id', $problems[0]['path']);
        $this->assertSame('params[0].label', $problems[1]['path']);
    }

    public function test_rule_4_duplicate_ids(): void
    {
        $manifest = $this->fullManifest();
        $manifest['params'][] = ['id' => 'meta', 'label' => 'Otra meta', 'type' => 'boolean', 'default' => false];

        $problems = $this->problems($manifest);

        $this->assertCount(1, $problems);
        $this->assertSame(4, $problems[0]['rule']);
        $this->assertStringContainsString('«meta» está repetido', $problems[0]['message']);
        $this->assertStringContainsString('params[7]', $problems[0]['path']);
    }

    public function test_rule_5_unknown_type(): void
    {
        $problems = $this->problems($this->withParam(['id' => 'x', 'label' => 'X', 'type' => 'slider', 'default' => 1]));

        $this->assertSame(5, $problems[0]['rule']);
        $this->assertStringContainsString('«slider» no soportado', $problems[0]['message']);
        $this->assertStringContainsString('number, text, boolean, select, range, date, color', $problems[0]['message']);
    }

    public function test_rule_6_required_fields_per_type(): void
    {
        $problems = $this->problems($this->withParam(['id' => 'r', 'label' => 'R', 'type' => 'range', 'default' => 5]));

        $this->assertSame(6, $problems[0]['rule']);
        $this->assertStringContainsString('requiere: min, max', $problems[0]['message']);
    }

    public function test_rule_6_min_must_be_below_max(): void
    {
        $problems = $this->problems($this->withParam(['id' => 'r', 'label' => 'R', 'type' => 'range', 'default' => 5, 'min' => 10, 'max' => 1]));

        $this->assertSame('params[0] (r).min', $problems[0]['path']);
    }

    public function test_rule_7_default_out_of_range(): void
    {
        $problems = $this->problems($this->withParam(['id' => 'n', 'label' => 'N', 'type' => 'number', 'default' => 5000, 'min' => 0, 'max' => 100]));

        $this->assertSame(7, $problems[0]['rule']);
        $this->assertSame('params[0] (n).default', $problems[0]['path']);
        $this->assertStringContainsString('entre 0 y 100', $problems[0]['message']);
    }

    public function test_rule_7_default_of_wrong_type(): void
    {
        $problems = $this->problems($this->withParam(['id' => 'b', 'label' => 'B', 'type' => 'boolean', 'default' => 'si']));

        $this->assertSame(7, $problems[0]['rule']);
        $this->assertStringContainsString('verdadero o falso', $problems[0]['message']);
    }

    public function test_rule_7_bad_date_and_color_defaults(): void
    {
        $date = $this->problems($this->withParam(['id' => 'd', 'label' => 'D', 'type' => 'date', 'default' => '2026-02-30']));
        $color = $this->problems($this->withParam(['id' => 'c', 'label' => 'C', 'type' => 'color', 'default' => 'red']));

        $this->assertStringContainsString('AAAA-MM-DD', $date[0]['message']);
        $this->assertStringContainsString('#RRGGBB', $color[0]['message']);
    }

    public function test_rule_8_select_needs_non_empty_options(): void
    {
        $problems = $this->problems($this->withParam(['id' => 's', 'label' => 'S', 'type' => 'select', 'default' => 'a', 'options' => []]));

        $this->assertSame(8, $problems[0]['rule']);
        $this->assertStringContainsString('al menos una opción', $problems[0]['message']);
    }

    public function test_rule_8_select_default_must_be_an_option(): void
    {
        $problems = $this->problems($this->withParam(['id' => 's', 'label' => 'S', 'type' => 'select', 'default' => 'z', 'options' => [['value' => 'a', 'label' => 'A']]]));

        $this->assertSame(8, $problems[0]['rule']);
        $this->assertSame('params[0] (s).default', $problems[0]['path']);
    }

    public function test_rule_8_duplicate_option_values(): void
    {
        $problems = $this->problems($this->withParam(['id' => 's', 'label' => 'S', 'type' => 'select', 'default' => 'a', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'A2']]]));

        $this->assertStringContainsString('repetido', $problems[0]['message']);
    }

    public function test_reports_every_problem_at_once(): void
    {
        $manifest = $this->fullManifest();
        $manifest['params'][0]['default'] = -1;
        $manifest['params'][2]['type'] = 'toggle';
        $manifest['params'][6]['default'] = '#zzz';

        $rules = array_column($this->problems($manifest), 'rule');

        $this->assertSame([7, 5, 7], $rules);
    }
}
