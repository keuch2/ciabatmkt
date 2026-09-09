import type { ParamScalar } from '@/api/dashboards';
import { buildPreamble, type Viewer } from './preamble';

function escapeAttribute(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

/**
 * Arma el documento que va en el srcdoc del iframe: la CSP y el preámbulo se insertan
 * como primeros hijos de <head>, así corren antes que cualquier script del dashboard.
 */
export function buildSrcdoc(html: string, params: Record<string, ParamScalar>, csp: string, viewer: Viewer | null = null): string {
    const injection =
        `<meta http-equiv="Content-Security-Policy" content="${escapeAttribute(csp)}">\n` +
        `<script>${buildPreamble(params, viewer)}</script>\n`;

    const head = /<head\b[^>]*>/i.exec(html);
    if (head) {
        const at = head.index + head[0].length;
        return html.slice(0, at) + '\n' + injection + html.slice(at);
    }

    const root = /<html\b[^>]*>/i.exec(html);
    if (root) {
        const at = root.index + root[0].length;
        return html.slice(0, at) + '\n<head>\n' + injection + '</head>\n' + html.slice(at);
    }

    return '<!doctype html>\n<html>\n<head>\n' + injection + '</head>\n<body>\n' + html + '\n</body>\n</html>';
}
