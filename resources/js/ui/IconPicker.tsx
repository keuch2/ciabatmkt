import { ICON_KEYS, ICON_LABELS, Icon, type IconKey } from './icons';

/** Selector del ícono del menú: los 24 del catálogo más "sin ícono" (iniciales). */
export function IconPicker({ value, onChange }: { value: IconKey | null; onChange: (value: IconKey | null) => void }) {
    const base = 'flex h-9 w-9 items-center justify-center rounded border text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500';
    return (
        <div role="radiogroup" aria-label="Ícono del dashboard" className="flex flex-wrap gap-1.5">
            <button
                type="button"
                role="radio"
                aria-checked={value === null}
                title="Sin ícono (iniciales del título)"
                onClick={() => onChange(null)}
                className={`${base} text-[10px] font-semibold ${value === null ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-white hover:border-slate-500'}`}
            >
                AB
            </button>
            {ICON_KEYS.map((key) => (
                <button
                    key={key}
                    type="button"
                    role="radio"
                    aria-checked={value === key}
                    aria-label={ICON_LABELS[key]}
                    title={ICON_LABELS[key]}
                    onClick={() => onChange(key)}
                    className={`${base} ${value === key ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-white hover:border-slate-500'}`}
                >
                    <Icon name={key} className="h-5 w-5" />
                </button>
            ))}
        </div>
    );
}
