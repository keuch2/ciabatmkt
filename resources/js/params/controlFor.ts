import type { ComponentType } from 'react';
import type { ParamType } from '@/api/dashboards';
import { BooleanControl } from './controls/BooleanControl';
import { ColorControl } from './controls/ColorControl';
import { DateControl } from './controls/DateControl';
import { NumberControl } from './controls/NumberControl';
import { RangeControl } from './controls/RangeControl';
import { SelectControl } from './controls/SelectControl';
import { TextControl } from './controls/TextControl';
import type { ControlProps } from './controls/types';

/** Un componente por tipo de parámetro. El generador de controles itera el manifiesto y usa este mapa. */
export const CONTROLS: Record<ParamType, ComponentType<ControlProps>> = {
    number: NumberControl,
    text: TextControl,
    boolean: BooleanControl,
    select: SelectControl,
    range: RangeControl,
    date: DateControl,
    color: ColorControl,
};
