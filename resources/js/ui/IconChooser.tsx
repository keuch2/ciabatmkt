import { useRef, useState, type ChangeEvent } from 'react';
import { IconPicker } from './IconPicker';
import type { IconKey } from './icons';

export interface IconChoice {
    icon: IconKey | null;
    iconData: string | null;
}

const MAX_BYTES = 65536;

/** Elegí un ícono del catálogo o subí uno propio (PNG o SVG, hasta 64 KB). */
export function IconChooser({ value, onChange, error }: { value: IconChoice; onChange: (next: IconChoice) => void; error?: string }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [localError, setLocalError] = useState<string | null>(null);

    function handleFile(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setLocalError(null);
        if (!/^image\/(png|svg\+xml)$/.test(file.type)) {
            setLocalError('El ícono debe ser un archivo PNG o SVG.');
            return;
        }
        if (file.size > MAX_BYTES) {
            setLocalError('El ícono no puede superar 64 KB. Alcanza con 64×64 px.');
            return;
        }
        const reader = new FileReader();
        reader.onload = () => onChange({ icon: null, iconData: String(reader.result) });
        reader.onerror = () => setLocalError('No se pudo leer el archivo.');
        reader.readAsDataURL(file);
    }

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    onClick={() => fileRef.current?.click()}
                    className={`flex h-9 items-center gap-2 rounded border px-3 text-sm ${value.iconData ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-500'}`}
                >
                    {value.iconData ? (
                        <>
                            <img src={value.iconData} alt="" className="h-5 w-5 object-contain" />
                            Ícono propio
                        </>
                    ) : (
                        'Subir PNG o SVG…'
                    )}
                </button>
                {value.iconData && (
                    <button type="button" onClick={() => onChange({ icon: null, iconData: null })} className="text-xs text-slate-600 underline-offset-2 hover:underline">
                        Quitar el ícono propio
                    </button>
                )}
                <input ref={fileRef} type="file" accept="image/png,image/svg+xml,.png,.svg" onChange={handleFile} className="hidden" />
                <span className="text-xs text-slate-500">PNG o SVG, hasta 64 KB. Se ve mejor cuadrado y con fondo transparente.</span>
            </div>
            <p className="text-xs text-slate-500">O elegí uno del catálogo:</p>
            <IconPicker value={value.iconData ? null : value.icon} onChange={(icon) => onChange({ icon, iconData: null })} />
            {(localError || error) && <p className="text-xs text-red-700">{localError ?? error}</p>}
        </div>
    );
}
