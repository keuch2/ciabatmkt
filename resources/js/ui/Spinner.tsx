export function Spinner({ className = '' }: { className?: string }) {
    return (
        <span
            role="status"
            aria-label="Cargando"
            className={`inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700 ${className}`}
        />
    );
}

export function FullPageSpinner() {
    return (
        <div className="flex min-h-screen items-center justify-center">
            <Spinner className="h-6 w-6" />
        </div>
    );
}
