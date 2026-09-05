<?php

namespace Tests\Unit\Manifest;

use App\Services\Manifest\ManifestDiff;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDashboardHtml;

class ManifestDiffTest extends TestCase
{
    use BuildsDashboardHtml;

    public function test_detects_added_removed_type_changed_and_modified(): void
    {
        $old = $this->fullManifest();
        $new = $this->fullManifest();
        unset($new['params'][1]);                       // titulo eliminado
        $new['params'][2]['type'] = 'select';            // activo cambia de tipo
        $new['params'][2]['options'] = [['value' => true, 'label' => 'Sí']];
        $new['params'][0]['max'] = 5000;                 // meta cambia rango
        $new['params'][] = ['id' => 'nuevo', 'label' => 'Nuevo', 'type' => 'text', 'default' => ''];
        $new['params'] = array_values($new['params']);

        $diff = (new ManifestDiff)->compare($old, $new);

        $this->assertSame(['nuevo'], array_column($diff['added'], 'id'));
        $this->assertSame(['titulo'], array_column($diff['removed'], 'id'));
        $this->assertSame([['id' => 'activo', 'from' => 'boolean', 'to' => 'select']], $diff['type_changed']);
        $this->assertSame([['id' => 'meta', 'fields' => ['max']]], $diff['modified']);
        $this->assertSame(4, $diff['unchanged']);
        $this->assertCount(3, $diff['warnings']);
        $this->assertStringContainsString('«activo» cambia de tipo boolean a select', $diff['warnings'][0]);
    }

    public function test_identical_manifests_have_no_changes(): void
    {
        $diff = (new ManifestDiff)->compare($this->fullManifest(), $this->fullManifest());

        $this->assertSame([], $diff['added']);
        $this->assertSame([], $diff['removed']);
        $this->assertSame([], $diff['type_changed']);
        $this->assertSame(7, $diff['unchanged']);
        $this->assertSame([], $diff['warnings']);
    }
}
