import { useEffect, useState, type ChangeEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '@/api/client';
import { createDashboard, previewDashboard, updateDashboard, type PreviewResult } from '@/api/dashboards';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';
import { ManifestDiffSummary } from './ManifestDiffSummary';

/**
 * Carga o actualización de un dashboard: se elige el archivo, se valida en el servidor sin
 * guardar (preview) y recién con el resultado a la vista se confirma la publicación.
 */
export function DashboardUploadPage() {
    const { id } = useParams();
    const isUpdate = Boolean(id);
    const navigate = useNavigate();

    const [fileName, setFileName] = useState<string | null>(null);
    const [html, setHtml] = useState<string | null>(null);
    const [preview, setPreview] = useState<PreviewResult | null>(null);
    const [publish, setPublish] = useState(true);
    const [checking, setChecking] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleFile(event: ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];
        if (!file) return;
        setFileName(file.name);
        setPreview(null);
        setError(null);
        setHtml(await file.text());
    }

    useEffect(() => {
        if (html === null) return;
        let cancelled = false;
        setChecking(true);
        previewDashboard(html)
            .then((result) => {
                if (!cancelled) setPreview(result);
            })
            .catch((e: unknown) => {
                if (!cancelled) setError(e instanceof ApiError ? e.message : 'No se pudo conectar con el servidor.');
            })
            .finally(() => {
                if (!cancelled) setChecking(false);
            });
        return () => {
            cancelled = true;
        };
    }, [html]);

    // Al actualizar, el archivo debe corresponder al dashboard elegido; al crear, no debe existir.
    const targetMismatch = isUpdate && preview?.existing && preview.existing.id !== id;
    const alreadyExists = !isUpdate && Boolean(preview?.existing);
    const canConfirm = Boolean(html) && preview?.valid === true && !targetMismatch && !alreadyExists && !checking;

    async function confirm() {
        if (!html) return;
        setSaving(true);
        setError(null);
        try {
            if (isUpdate && id) {
                const result = await updateDashboard(id, { html });
                navigate('/admin/dashboards', { state: { notice: `«${result.data.title}» actualizado a la versión ${result.data.version}.` } });
            } else {
                const created = await createDashboard(html, publish);
                navigate('/admin/dashboards', { state: { notice: `«${created.title}» ${publish ? 'publicado' : 'guardado como borrador'}.` } });
            }
        } catch (e) {
            if (e instanceof ApiError && e.problems.length) {
                setPreview((p) => (p ? { ...p, valid: false, problems: e.problems } : p));
            }
            setError(e instanceof ApiError ? e.message : 'No se pudo conectar con el servidor.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="max-w-4xl">
            <PageHeader
                title={isUpdate ? 'Actualizar dashboard' : 'Cargar dashboard'}
                description="El archivo se valida antes de guardar. Si hay problemas, se listan con la ubicación exacta."
                actions={
                    <Link to="/admin/dashboards" className="text-sm text-slate-600 underline-offset-2 hover:underline">
                        Volver
                    </Link>
                }
            />

            <div className="space-y-4 rounded border border-slate-200 bg-white p-4">
                <div>
                    <label htmlFor="file" className="block text-xs font-medium text-slate-700">
                        Archivo HTML del dashboard
                    </label>
                    <input
                        id="file"
                        type="file"
                        accept=".html,text/html"
                        onChange={handleFile}
                        className="mt-1 block text-sm text-slate-700 file:mr-3 file:rounded file:border file:border-slate-300 file:bg-white file:px-3 file:py-1 file:text-sm file:text-slate-800 hover:file:bg-slate-50"
                    />
                    {fileName && <p className="mt-1 text-xs text-slate-500">{fileName}</p>}
                </div>

                {checking && <p className="text-sm text-slate-500">Validando…</p>}
                {error && <Alert tone="error">{error}</Alert>}

                {preview && !preview.valid && (
                    <div className="space-y-2">
                        <Alert tone="error">
                            {preview.problems.length === 1
                                ? 'Se encontró 1 problema. Corregilo y volvé a cargar el archivo.'
                                : `Se encontraron ${preview.problems.length} problemas. Corregilos y volvé a cargar el archivo.`}
                        </Alert>
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="py-1 pr-3">Regla</th>
                                    <th className="py-1 pr-3">Ubicación</th>
                                    <th className="py-1">Problema</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {preview.problems.map((p, i) => (
                                    <tr key={i} className="align-top">
                                        <td className="py-1.5 pr-3 text-slate-500">{p.rule}</td>
                                        <td className="py-1.5 pr-3 font-mono text-xs text-slate-700">{p.path}</td>
                                        <td className="py-1.5 text-slate-900">{p.message}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {preview?.valid && preview.manifest && (
                    <div className="space-y-3">
                        <Alert tone="success">El archivo cumple las diez reglas del validador.</Alert>

                        <dl className="grid grid-cols-[120px_1fr] gap-y-1 text-sm">
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Id</dt>
                            <dd className="font-mono text-xs">{preview.manifest.id}</dd>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Título</dt>
                            <dd>{preview.manifest.title}</dd>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Versión</dt>
                            <dd>{preview.manifest.version}</dd>
                        </dl>

                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="py-1 pr-3">Parámetro</th>
                                    <th className="py-1 pr-3">Etiqueta</th>
                                    <th className="py-1 pr-3">Tipo</th>
                                    <th className="py-1">Default</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {preview.manifest.params.map((p) => (
                                    <tr key={p.id ?? ''}>
                                        <td className="py-1 pr-3 font-mono text-xs">{p.id}</td>
                                        <td className="py-1 pr-3">{p.label}</td>
                                        <td className="py-1 pr-3">{p.type}</td>
                                        <td className="py-1 font-mono text-xs">{JSON.stringify(p.default)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {alreadyExists && preview.existing && (
                            <Alert tone="error">
                                Ya existe un dashboard con id «{preview.existing.slug}» ({preview.existing.title}, versión{' '}
                                {preview.existing.version}). Para reemplazarlo usá{' '}
                                <Link to={`/admin/dashboards/${preview.existing.id}/update`} className="underline">
                                    Actualizar
                                </Link>{' '}
                                sobre ese dashboard.
                            </Alert>
                        )}
                        {targetMismatch && preview.existing && (
                            <Alert tone="error">
                                Este archivo corresponde al dashboard «{preview.existing.slug}», no al que estás actualizando.
                            </Alert>
                        )}
                        {isUpdate && preview.existing === null && (
                            <Alert tone="error">
                                El id del manifiesto no coincide con este dashboard. Para reemplazarlo, el id debe ser el mismo.
                            </Alert>
                        )}

                        {isUpdate && preview.diff && !targetMismatch && (
                            <div>
                                <p className="mb-1 text-sm font-medium text-slate-900">Cambios respecto de la versión vigente</p>
                                <ManifestDiffSummary diff={preview.diff} />
                            </div>
                        )}

                        {!isUpdate && (
                            <label className="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" checked={publish} onChange={(e) => setPublish(e.target.checked)} />
                                Publicar inmediatamente (visible para los usuarios)
                            </label>
                        )}
                    </div>
                )}

                <div className="flex justify-end gap-2 border-t border-slate-200 pt-3">
                    <Button variant="secondary" onClick={() => navigate('/admin/dashboards')}>
                        Cancelar
                    </Button>
                    <Button onClick={confirm} disabled={!canConfirm} loading={saving}>
                        {isUpdate ? 'Confirmar actualización' : publish ? 'Publicar' : 'Guardar borrador'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
