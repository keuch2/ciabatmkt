import { PageHeader } from '@/ui/PageHeader';

/** Semana 4: panel de super administración. */
export function AdminPlaceholderPage({ title }: { title: string }) {
    return (
        <div>
            <PageHeader title={title} />
            <div className="rounded border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                Sección en construcción.
            </div>
        </div>
    );
}
