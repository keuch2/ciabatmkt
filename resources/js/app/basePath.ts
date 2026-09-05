/**
 * Prefijo de ruta cuando la app vive en una subcarpeta (ej. http://localhost/ciabaymkt).
 * Lo define Laravel en el atributo data-base de #root a partir de APP_URL. Vacío en la raíz.
 */
export const BASE_PATH: string = document.getElementById('root')?.dataset.base ?? '';

/** Convierte una ruta absoluta de la app ("/api/auth/me") en la URL real a pedir. */
export function withBase(path: string): string {
    return `${BASE_PATH}${path}`;
}
