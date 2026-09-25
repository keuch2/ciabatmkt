# Plantilla de prompt: corregir un dashboard que no guarda bien

Este bloque sirve para **arreglar la integración** de un dashboard ya publicado que no guarda o
no muestra datos como debería, **sin cambiar su diseño ni su interfaz**. Antes de usarlo:

1. En **Administración → Dashboards → Más → Diagnóstico**, revisá los hallazgos y apretá
   **Copiar informe**.
2. En el mismo menú, **Descargar HTML** para tener el archivo vigente.
3. Pegá en el asistente de IA, en este orden: el bloque de abajo, el informe copiado, y el
   archivo HTML completo. Si el usuario describió el problema ("marco las noches y al recargar
   desaparecen"), agregalo al final.
4. Publicá el archivo corregido con **Actualizar archivo** (mismo id, versión nueva) y volvé a
   correr el diagnóstico.

---INICIO---

Te voy a pegar (1) el informe de diagnóstico de la plataforma y (2) el HTML completo de un
dashboard que ya está publicado y en uso. El dashboard tiene un problema de **integración con la
plataforma**: no guarda, no muestra o pierde datos. Tu tarea es corregir sólo eso.

## Reglas

- **El diseño y la interfaz no se tocan.** Mismo HTML, CSS, textos, controles y comportamiento
  visible. Cambiá el mínimo de líneas necesario y explicá cada cambio.
- **Los datos ya guardados se conservan.** No renombres colecciones ni campos que ya tienen
  registros. Si un campo cambia de forma, agregá una normalización al cargar (por ejemplo, un
  campo que debe ser objeto y llega como lista `[]` pasa a `{}`) para que los registros viejos
  sigan funcionando.
- Mantené el mismo `id` del manifiesto y subí la `version`.

## Cómo funciona la plataforma (resumen)

`window.Dashboard` existe antes de cualquier script del archivo. Los datos compartidos viven en
colecciones declaradas en `collections` del manifiesto y se manejan con:

- `Dashboard.data.list(coleccion)` → `[{ id, data, version, updated_at, updated_by }]`
- `Dashboard.data.put(coleccion, id, objeto)` → guarda el registro completo (promesa con el
  registro). Falla con `error.code === 'invalid'` (mensaje en `error.message`) o `'conflict'`
  (otro usuario cambió el registro: `error.record` trae la versión actual).
- `Dashboard.data.remove(coleccion, id)`, `Dashboard.data.seed(coleccion, [{id, data}])` (sólo si
  la colección está vacía), `Dashboard.data.replace(coleccion, [...])` (sólo super administrador).
- `Dashboard.data.onChange(cb)` → cambios de otros usuarios cada ~10 s y conflictos.
- `Dashboard.setHeight()`, `Dashboard.ready()`, `Dashboard.user`, `Dashboard.clipboard.write`,
  `Dashboard.capture` (reemplaza a html2canvas, que no funciona en el iframe).

Ids de registro: letras, números, `-`, `_`, `.`, `:`; hasta 100. Registros: objetos JSON de hasta
256 KB (o el `maxBytes` declarado). Sin `localStorage`, `sessionStorage`, cookies ni `fetch`
fuera de los CDN autorizados.

## Qué revisar, en este orden

1. **Colecciones.** Toda colección usada con `Dashboard.data` debe estar en `collections` del
   manifiesto, con el mismo nombre. El informe lista las declaradas y las que tienen datos.
2. **Errores tapados.** Todo `catch` de un `put`/`remove`/`seed` debe mostrar `error.message`
   al usuario. "No se pudo guardar" a secas esconde el motivo real.
3. **Estructuras que JSON pierde.** Propiedades con nombre sobre una lista
   (`lista['2026-11-07'] = true`), `Set`, `Map`, `Date`, `undefined`, funciones. Un mapa por
   clave debe ser un objeto `{}`. Si el informe dice "guardados repetidos sin cambio real", es
   casi seguro esto: la pantalla cambia pero el JSON enviado no.
4. **Forma de los registros viejos.** Al cargar (`list`, `seed`, `onChange`, conflicto),
   normalizá cada registro antes de usarlo.
5. **Tamaño.** Si el informe marca registros cerca del tope, reducí lo que se embebe (fotos más
   chicas o menos fotos) o subí `maxBytes` en el manifiesto hasta 262144.
6. **Conflictos.** Si hay conflictos frecuentes, guardá en registros más chicos (uno por fila o
   por entidad) en lugar de un registro grande que varios usuarios editan a la vez, y al recibir
   `conflict` mostrá la versión actual y avisá.
7. **Verificación tras guardar.** Después de cada `put`, compará el registro devuelto con el
   estado en pantalla y avisá si difieren.
8. **Carga inicial.** `await` de `list`/`seed` de todas las colecciones antes del primer dibujo y
   de `Dashboard.ready()`.

## Entrega

1. El archivo completo corregido, en un solo bloque de código.
2. La lista de cambios: línea, qué se cambió y qué hallazgo del informe resuelve.
3. Qué debería probar el usuario para confirmar que quedó resuelto (pasos concretos).

---FIN---

Después del bloque pegá el informe de diagnóstico y luego el HTML. Si el usuario describió el
problema, agregalo al final, por ejemplo:

> El cliente marca las noches de cada huésped en la tabla y, al recargar la página, las casillas
> vuelven a aparecer vacías. Pasa sólo en las reservas nuevas.
