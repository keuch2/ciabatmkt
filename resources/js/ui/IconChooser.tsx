import { useRef, useState, type ChangeEvent } from 'react';
import { IconPicker } from './IconPicker';
import type { IconKey } from './icons';

export interface IconChoice {
    icon: IconKey | null;
    iconData: string | null;
}

const MAX_BYTES = 65536;

function shrinkPng(dataUrl: string, max: number): Promise<string> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const k = Math.min(1, max / Math.max(img.width, img.height));
            if (k === 1) return resolve(dataUrl);
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * k);
            c.height = Math.round(img.height * k);
            const ctx = c.getContext('2d');
            if (!ctx) return reject(new Error('canvas'));
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(img, 0, 0, c.width, c.height);
            resolve(c.toDataURL('image/png'));
        };
        img.onerror = () => reject(new Error('imagen'));
        img.src = dataUrl;
    });
}

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
        const reader = new FileReader();
        reader.onerror = () => setLocalError('No se pudo leer el archivo.');
        reader.onload = async () => {
            let data = String(reader.result);
            // Los PNG grandes se achican a 128 px: en el menú se ven a menos de 40 px.
            if (file.type === 'image/png') {
                try {
                    data = await shrinkPng(data, 128);
                } catch {
                    /* se usa el original */
                }
            }
            if (Math.ceil(((data.length - data.indexOf(',') - 1) * 3) / 4) > MAX_BYTES) {
                setLocalError('El ícono no puede superar 64 KB. Usá una imagen más simple o más chica.');
                return;
            }
            onChange({ icon: null, iconData: data });
        };
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
                <span className="text-xs text-slate-500">PNG o SVG. Los PNG grandes se achican solos. Se ve mejor cuadrado y con fondo transparente.</span>
            </div>
            <p className="text-xs text-slate-500">O elegí uno del catálogo:</p>
            <IconPicker value={value.iconData ? null : value.icon} onChange={(icon) => onChange({ icon, iconData: null })} />
            {(localError || error) && <p className="text-xs text-red-700">{localError ?? error}</p>}
        </div>
    );
}
