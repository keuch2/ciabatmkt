import type { ReactNode } from 'react';

interface Props {
    title: string;
    description?: string;
    actions?: ReactNode;
}

export function PageHeader({ title, description, actions }: Props) {
    return (
        <div className="mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 className="text-lg font-semibold text-slate-900">{title}</h1>
                {description && <p className="text-sm text-slate-500">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}
