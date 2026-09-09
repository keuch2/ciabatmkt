import { useState } from 'react';
import { getDocs, REFERENCE_DASHBOARD_PATH } from '@/api/admin';
import { withBase } from '@/app/basePath';
import { useRequest } from '@/app/useRequest';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

type Tab = 'prompt' | 'spec' | 'guide';

const TABS: { id: Tab; label: string }[] = [
    { id: 'prompt', label: 'Prompt de integración' },
    { id: 'spec', label: 'Especificación técnica' },
    { id: 'guide', label: 'Guía operativa' },
];

/**
 * Documentación del super administrador. El contenido sale de los archivos del kit en el
 * servidor, así el prompt que se copia acá es siempre el vigente.
 */
export function DocsPage() {
    const { data, error, loading } = useRequest(getDocs, []);
    const [tab, setTab] = useState<Tab>('prompt');

    return (
        <div className="max-w-5xl">
            <PageHeader
                title="Docs"
                description="Qué pegar antes de pedir un dashboard a una IA para que los datos que carguen los usuarios se guarden en la plataforma, y cómo operarla."
                actions={
                    <a href={withBase(REFERENCE_DASHBOARD_PATH)} download className="text-sm text-slate-600 underline-offset-2 hover:underline">
                        Descargar dashboard de referencia
                    </a>
                }
            />

            <div className="mb-4 flex gap-1 border-b border-slate-200">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`-mb-px border-b-2 px-3 py-2 text-sm ${
                            tab === t.id ? 'border-slate-800 font-medium text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {loading && <Spinner />}
            {error && <Alert tone="error">{error}</Alert>}

            {data && tab === 'prompt' && <PromptTab intro={data.prompt.intro_html} text={data.prompt.text} example={data.prompt.example_html} />}
            {data && tab === 'spec' && <article className="doc rounded border border-slate-200 bg-white p-6" dangerouslySetInnerHTML={{ __html: data.specification_html }} />}
            {data && tab === 'guide' && <article className="doc rounded border border-slate-200 bg-white p-6" dangerouslySetInnerHTML={{ __html: data.guide_html }} />}
        </div>
    );
}

function PromptTab({ intro, text, example }: { intro: string; text: string; example: string }) {
    const [copied, setCopied] = useState<'ok' | 'fail' | null>(null);

    async function copy() {
        try {
            await navigator.clipboard.writeText(text);
            setCopied('ok');
        } catch {
            setCopied('fail');
        }
        window.setTimeout(() => setCopied(null), 2500);
    }

    return (
        <div className="space-y-4">
            <div className="doc rounded border border-slate-200 bg-white p-4" dangerouslySetInnerHTML={{ __html: intro }} />

            <div className="rounded border border-slate-300 bg-white">
                <div className="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-2">
                    <div>
                        <p className="text-sm font-semibold text-slate-900">Bloque para pegar al inicio del prompt</p>
                        <p className="text-xs text-slate-500">Copialo completo. Después del bloque, describí tu dashboard como quieras: el diseño es tuyo.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {copied === 'ok' && <span className="text-xs text-green-700">Copiado</span>}
                        {copied === 'fail' && <span className="text-xs text-red-700">No se pudo copiar: seleccioná el texto y copialo a mano.</span>}
                        <Button onClick={copy}>Copiar prompt</Button>
                    </div>
                </div>
                <textarea
                    readOnly
                    value={text}
                    onFocus={(e) => e.currentTarget.select()}
                    className="block h-[28rem] w-full resize-y bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-800 focus:outline-none"
                    aria-label="Prompt de integración"
                />
            </div>

            <div className="doc rounded border border-slate-200 bg-white p-4" dangerouslySetInnerHTML={{ __html: example }} />
        </div>
    );
}
