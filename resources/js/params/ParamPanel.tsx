import type { ParamDefinition } from '@/api/dashboards';
import type { ParamScope } from '@/api/params';
import { Button } from '@/ui/Button';
import { CONTROLS } from './controlFor';
import { SaveIndicator } from './SaveIndicator';
import type { useParamState } from './useParamState';

type ParamState = ReturnType<typeof useParamState>;

interface Props {
    definitions: ParamDefinition[];
    state: ParamState;
    scope: ParamScope;
}

const SOURCE_LABEL = { user: 'tu valor', base: 'base', default: 'default' } as const;

/**
 * Panel lateral generado a partir del manifiesto, en el orden en que aparecen los parámetros.
 * Cada control muestra su label, su unit si tiene, y un botón de reset visible sólo cuando
 * ese parámetro tiene override en el nivel que se está editando.
 */
export function ParamPanel({ definitions, state, scope }: Props) {
    const { entries, overall, overrideCount, setValue, reset, resetAll } = state;
    const resetLabel = scope === 'base' ? 'Quitar todos los valores base' : 'Restablecer todo';

    return (
        <aside className="flex flex-col rounded border border-slate-200 bg-white">
            <div className="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2">
                <div>
                    <p className="text-sm font-semibold text-slate-900">{scope === 'base' ? 'Valores base' : 'Parámetros'}</p>
                    <p className="text-xs text-slate-500">
                        {scope === 'base' ? 'Lo que ven todos los usuarios que no definieron su propio valor.' : 'Tus valores. Nadie más los ve.'}
                    </p>
                </div>
                <SaveIndicator status={overall} />
            </div>

            <div className="divide-y divide-slate-100">
                {definitions.map((def) => {
                    const entry = entries[def.id];
                    if (!entry) return null;
                    const Control = CONTROLS[def.type];
                    const inputId = `param-${def.id}`;

                    return (
                        <div key={def.id} className="relative px-3 py-2.5">
                            <div className="mb-1 flex items-center justify-between gap-2">
                                <label htmlFor={inputId} className="text-xs font-medium text-slate-700">
                                    {def.label}
                                    {def.unit && def.type !== 'number' && def.type !== 'range' && <span className="text-slate-400"> ({def.unit})</span>}
                                </label>
                                <div className="flex items-center gap-1.5">
                                    {entry.stale && (
                                        <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-amber-800" title="Tu valor guardado ya no es válido para esta versión">
                                            obsoleto
                                        </span>
                                    )}
                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-slate-500">
                                        {SOURCE_LABEL[entry.source]}
                                    </span>
                                    {entry.has_override && (
                                        <button
                                            type="button"
                                            onClick={() => void reset(def.id)}
                                            className="text-[11px] text-slate-500 underline-offset-2 hover:text-slate-900 hover:underline"
                                            title={scope === 'base' ? 'Quitar el valor base y volver al default' : 'Volver al valor base'}
                                        >
                                            reset
                                        </button>
                                    )}
                                </div>
                            </div>

                            {Control ? (
                                <Control id={inputId} definition={def} value={entry.value} invalid={entry.status === 'error'} onChange={(v) => setValue(def.id, v)} />
                            ) : (
                                <p className="text-xs text-red-700">Tipo «{def.type}» no soportado.</p>
                            )}

                            {entry.error && (
                                <p className="mt-1 text-[11px] text-red-700" role="alert">
                                    {entry.error}
                                </p>
                            )}
                        </div>
                    );
                })}
                {definitions.length === 0 && <p className="px-3 py-6 text-center text-xs text-slate-500">Este dashboard no declara parámetros.</p>}
            </div>

            <div className="mt-auto border-t border-slate-200 px-3 py-2">
                <Button variant="secondary" className="w-full" disabled={overrideCount === 0} onClick={() => void resetAll()}>
                    {resetLabel}
                    {overrideCount > 0 ? ` (${overrideCount})` : ''}
                </Button>
            </div>
        </aside>
    );
}
