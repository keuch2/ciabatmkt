<?php

namespace Tests\Unit\Params;

use App\Services\Params\ParamValueValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParamValueValidatorTest extends TestCase
{
    public static function cases(): array
    {
        $number = ['id' => 'n', 'type' => 'number', 'default' => 1, 'min' => 0, 'max' => 100];
        $range = ['id' => 'r', 'type' => 'range', 'default' => 1, 'min' => 0, 'max' => 10];
        $text = ['id' => 't', 'type' => 'text', 'default' => '', 'maxLength' => 5];
        $bool = ['id' => 'b', 'type' => 'boolean', 'default' => true];
        $select = ['id' => 's', 'type' => 'select', 'default' => 'a', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 2, 'label' => 'Dos']]];
        $date = ['id' => 'd', 'type' => 'date', 'default' => '2026-01-01', 'min' => '2026-01-01', 'max' => '2026-12-31'];
        $color = ['id' => 'c', 'type' => 'color', 'default' => '#000000'];

        return [
            'number ok int' => [$number, 50, null],
            'number ok float' => [$number, 50.5, null],
            'number string rejected' => [$number, '50', 'debe ser un número'],
            'number bool rejected' => [$number, true, 'debe ser un número'],
            'number below min' => [$number, -1, 'entre 0 y 100'],
            'number above max' => [$number, 101, 'entre 0 y 100'],
            'number without bounds' => [['id' => 'n', 'type' => 'number', 'default' => 0], -999999, null],
            'range ok' => [$range, 10, null],
            'range out' => [$range, 11, 'entre 0 y 10'],
            'text ok' => [$text, 'hola', null],
            'text too long' => [$text, 'demasiado', 'no puede superar 5 caracteres'],
            'text number rejected' => [$text, 5, 'debe ser un texto'],
            'boolean ok' => [$bool, false, null],
            'boolean int rejected' => [$bool, 1, 'verdadero o falso'],
            'select ok string' => [$select, 'a', null],
            'select ok int' => [$select, 2, null],
            'select strict type' => [$select, '2', 'una de las opciones'],
            'select unknown' => [$select, 'z', 'una de las opciones: "a", 2'],
            'date ok' => [$date, '2026-06-30', null],
            'date invalid day' => [$date, '2026-02-30', 'AAAA-MM-DD'],
            'date wrong format' => [$date, '30/06/2026', 'AAAA-MM-DD'],
            'date before min' => [$date, '2025-12-31', 'anterior a 2026-01-01'],
            'date after max' => [$date, '2027-01-01', 'posterior a 2026-12-31'],
            'color ok' => [$color, '#A1b2C3', null],
            'color short' => [$color, '#fff', '#RRGGBB'],
            'color name' => [$color, 'red', '#RRGGBB'],
            'unknown type' => [['id' => 'x', 'type' => 'other'], 1, 'tipo no soportado'],
        ];
    }

    #[DataProvider('cases')]
    public function test_validation(array $param, mixed $value, ?string $expectedFragment): void
    {
        $error = (new ParamValueValidator)->validate($param, $value);

        if ($expectedFragment === null) {
            $this->assertNull($error);
        } else {
            $this->assertNotNull($error);
            $this->assertStringContainsString($expectedFragment, $error);
        }
    }
}
