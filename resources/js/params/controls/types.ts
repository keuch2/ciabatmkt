import type { ParamDefinition, ParamScalar } from '@/api/dashboards';

export interface ControlProps<T extends ParamScalar = ParamScalar> {
    id: string;
    definition: ParamDefinition;
    value: T;
    invalid: boolean;
    onChange: (value: ParamScalar) => void;
}
