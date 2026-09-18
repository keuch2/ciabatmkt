/* eslint-disable @typescript-eslint/no-explicit-any */

/**
 * Captura de un elemento a <canvas> que funciona dentro del iframe aislado.
 *
 * html2canvas clona el documento en un iframe hijo y lee su `document`; en un sandbox sin
 * allow-same-origin ese hijo tiene otro origen opaco y el navegador lo bloquea. Esta función
 * hace lo mismo sin iframes: clona el nodo con sus estilos computados, lo serializa dentro de un
 * <foreignObject> SVG y dibuja ese SVG en un canvas.
 *
 * Se inyecta en el preámbulo con installCapture.toString(): debe ser AUTOCONTENIDA (sin imports
 * ni referencias externas). Reemplaza a window.html2canvas con la misma firma
 * html2canvas(elemento, { scale, backgroundColor, width, height }) → Promise<HTMLCanvasElement>,
 * así los dashboards que ya lo usan funcionan sin cambios, y se expone como Dashboard.capture.
 */
export function installCapture(): void {
    const w = window as any;
    const dataUrlCache: Record<string, Promise<string>> = {};
    let fontCss: Promise<string> | null = null;
    let uid = 0;

    function toDataUrl(url: string): Promise<string> {
        if (!dataUrlCache[url]) {
            dataUrlCache[url] = fetch(url)
                .then((r) => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.blob();
                })
                .then(
                    (blob) =>
                        new Promise<string>((resolve, reject) => {
                            const reader = new FileReader();
                            reader.onload = () => resolve(String(reader.result));
                            reader.onerror = () => reject(reader.error);
                            reader.readAsDataURL(blob);
                        }),
                );
        }
        return dataUrlCache[url];
    }

    /** @font-face de las hojas enlazadas y en línea, con los archivos de fuente embebidos como data: URI. */
    function embeddedFontCss(): Promise<string> {
        if (fontCss) return fontCss;
        const sources: Promise<{ css: string; base: string }>[] = [];
        document.querySelectorAll('link[rel~="stylesheet"][href]').forEach((link: any) => {
            sources.push(
                fetch(link.href)
                    .then((r) => (r.ok ? r.text() : ''))
                    .then((css) => ({ css, base: link.href }))
                    .catch(() => ({ css: '', base: link.href })),
            );
        });
        document.querySelectorAll('style').forEach((style) => {
            const css = style.textContent || '';
            if (css.indexOf('@font-face') !== -1) sources.push(Promise.resolve({ css, base: document.baseURI }));
        });

        fontCss = Promise.all(sources)
            .then((sheets) => {
                const jobs: Promise<string>[] = [];
                sheets.forEach((sheet) => {
                    const faces = sheet.css.match(/@font-face\s*\{[^}]*\}/g) || [];
                    faces.forEach((face) => {
                        // Sólo subconjuntos latinos: alcanza para español y evita embeber megas de fuentes.
                        const range = /unicode-range\s*:\s*([^;}]+)/i.exec(face);
                        if (range && range[1].indexOf('U+0000-00FF') === -1 && range[1].indexOf('U+0100') === -1) return;
                        const urls: string[] = [];
                        face.replace(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g, (_m: string, _q: string, u: string) => {
                            if (u.indexOf('data:') !== 0) urls.push(u);
                            return '';
                        });
                        jobs.push(
                            Promise.all(
                                urls.map((u) => {
                                    let absolute = u;
                                    try {
                                        absolute = new URL(u, sheet.base).href;
                                    } catch (e) {
                                        /* se deja como está */
                                    }
                                    return toDataUrl(absolute)
                                        .then((data) => ({ u, data }))
                                        .catch(() => ({ u, data: '' }));
                                }),
                            ).then((pairs) => {
                                let out = face;
                                pairs.forEach((p) => {
                                    if (p.data) out = out.split(p.u).join(p.data);
                                });
                                return out;
                            }),
                        );
                    });
                });
                return Promise.all(jobs);
            })
            .then((faces) => faces.join('\n'))
            .catch(() => '');
        return fontCss;
    }

    function cssTextOf(style: CSSStyleDeclaration): string {
        let text = '';
        for (let i = 0; i < style.length; i++) {
            const prop = style[i];
            const value = style.getPropertyValue(prop);
            if (value) text += prop + ':' + value + ';';
        }
        return text;
    }

    /** XML no admite caracteres de control salvo tab, salto de línea y retorno. */
    function xmlSafe(text: string): string {
        let out = '';
        for (let i = 0; i < text.length; i++) {
            const code = text.charCodeAt(i);
            if (code >= 32 || code === 9 || code === 10 || code === 13) out += text[i];
        }
        return out;
    }

    function capture(element: any, options?: any): Promise<HTMLCanvasElement> {
        const opts = options || {};
        if (!element || element.nodeType !== 1) return Promise.reject(new Error('capture: se esperaba un elemento del DOM.'));

        const rect = element.getBoundingClientRect();
        const width = Math.ceil(opts.width || Math.max(rect.width, element.scrollWidth || 0));
        const height = Math.ceil(opts.height || Math.max(rect.height, element.scrollHeight || 0));
        if (!width || !height) return Promise.reject(new Error('capture: el elemento no tiene tamaño (¿está oculto?).'));
        const scale = Number(opts.scale) > 0 ? Number(opts.scale) : window.devicePixelRatio || 1;

        const rules: string[] = [];
        const pending: Promise<unknown>[] = [];
        const clone = element.cloneNode(true);

        function sync(src: any, dst: any, isRoot: boolean) {
            const tag = String(src.tagName || '').toLowerCase();
            if (tag === 'script' || tag === 'noscript' || tag === 'style' || tag === 'link') {
                dst.setAttribute('data-capture-skip', '1');
                return;
            }

            let css = cssTextOf(getComputedStyle(src));
            if (isRoot) css += 'position:relative;left:auto;top:auto;right:auto;bottom:auto;margin:0;transform:none;width:' + width + 'px;';
            dst.setAttribute('style', css);

            ['::before', '::after'].forEach((pseudo) => {
                const ps = getComputedStyle(src, pseudo);
                const content = ps.getPropertyValue('content');
                if (!content || content === 'none' || content === 'normal') return;
                const cls = 'cap-' + ++uid;
                dst.setAttribute('class', ((dst.getAttribute('class') || '') + ' ' + cls).trim());
                rules.push('.' + cls + pseudo + '{' + cssTextOf(ps) + '}');
            });

            if (tag === 'input') {
                if (src.type === 'checkbox' || src.type === 'radio') {
                    if (src.checked) dst.setAttribute('checked', '');
                    else dst.removeAttribute('checked');
                } else {
                    dst.setAttribute('value', src.value);
                }
            } else if (tag === 'textarea') {
                dst.textContent = src.value;
            } else if (tag === 'select') {
                Array.prototype.forEach.call(dst.options || [], (o: any, i: number) => {
                    if (i === src.selectedIndex) o.setAttribute('selected', '');
                    else o.removeAttribute('selected');
                });
            } else if (tag === 'canvas') {
                try {
                    const img = document.createElement('img');
                    img.src = src.toDataURL();
                    img.setAttribute('style', css);
                    if (dst.parentNode) dst.parentNode.replaceChild(img, dst);
                } catch (e) {
                    /* canvas contaminado: se deja vacío */
                }
                return;
            } else if (tag === 'img' && src.src && src.src.indexOf('data:') !== 0) {
                pending.push(
                    toDataUrl(src.src)
                        .then((data) => dst.setAttribute('src', data))
                        .catch(() => undefined),
                );
                dst.removeAttribute('srcset');
            }

            // Instantánea de los pares: reemplazar un canvas por img altera la colección viva.
            const a = src.children;
            const b = dst.children;
            const pairs: any[] = [];
            for (let i = 0; i < a.length && i < b.length; i++) pairs.push([a[i], b[i]]);
            pairs.forEach((p) => sync(p[0], p[1], false));
        }

        sync(element, clone, true);
        clone.querySelectorAll('[data-capture-skip]').forEach((n: any) => n.parentNode && n.parentNode.removeChild(n));

        return Promise.all([embeddedFontCss(), Promise.all(pending)]).then((results) => {
            const holder = document.createElement('div');
            holder.setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
            const style = document.createElement('style');
            style.textContent = results[0] + '\n' + rules.join('\n');
            holder.appendChild(style);
            holder.appendChild(clone);

            const xhtml = xmlSafe(new XMLSerializer().serializeToString(holder));
            const svg =
                '<svg xmlns="http://www.w3.org/2000/svg" width="' + width * scale + '" height="' + height * scale + '" viewBox="0 0 ' + width + ' ' + height + '">' +
                '<foreignObject x="0" y="0" width="' + width + '" height="' + height + '">' + xhtml + '</foreignObject></svg>';

            return new Promise<HTMLCanvasElement>((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const draw = () => {
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.round(width * scale);
                        canvas.height = Math.round(height * scale);
                        const ctx = canvas.getContext('2d');
                        if (!ctx) return reject(new Error('capture: no se pudo crear el canvas.'));
                        if (opts.backgroundColor) {
                            ctx.fillStyle = opts.backgroundColor;
                            ctx.fillRect(0, 0, canvas.width, canvas.height);
                        }
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        resolve(canvas);
                    };
                    // decode() asegura que las fuentes embebidas ya estén aplicadas antes de dibujar.
                    if (img.decode) img.decode().then(draw, draw);
                    else draw();
                };
                img.onerror = () => reject(new Error('capture: el navegador no pudo renderizar el elemento.'));
                img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
            });
        });
    }

    w.__ciabayCapture = capture;
    // html2canvas real no puede funcionar en el sandbox: se fija esta versión y se ignora cualquier
    // asignación posterior (el script del CDN hace window.html2canvas = ...).
    try {
        Object.defineProperty(w, 'html2canvas', { configurable: false, get: () => capture, set: () => undefined });
    } catch (e) {
        /* ya definido */
    }
}
