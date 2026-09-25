# Guía operativa del super administrador

Todo lo de esta guía se hace desde el menú **Administración** de la plataforma. Necesitás el rol de
super administrador.

## 1. Publicar un dashboard nuevo

1. Conseguí el archivo `.html`. Si lo generás con un asistente de IA, pegá antes de tu pedido el
   bloque de **Administración → Docs** (también en `PLANTILLA-PROMPT.md`): describe sólo cómo se
   leen y guardan los valores; el diseño lo decidís vos en el pedido. `dashboard-referencia.html`
   muestra el resultado esperado.
2. Probalo suelto: abrí el archivo en el navegador. Debe dibujarse con sus valores por defecto.
3. **Administración → Dashboards → Cargar dashboard** y elegí el archivo.
4. La plataforma corre el validador sin guardar nada y muestra el resultado:
   - Si hay problemas, ves una tabla con la regla, la ubicación exacta (`params[2].default`,
     `línea 87`) y qué corregir. Corregí el archivo y volvé a elegirlo.
   - Si pasa, ves el id, título, versión y la lista de parámetros con sus defaults.
5. Elegí si publicarlo ya (visible para todos) o guardarlo como borrador (sólo lo ven los super
   administradores; sirve para probarlo con datos reales).
6. Confirmá. El dashboard aparece en el listado con su estado.

Si el `id` del manifiesto ya existe, la plataforma no crea otro: te avisa y te lleva a
**Actualizar** sobre el existente.

## 2. Actualizar un dashboard (versión nueva)

1. En el archivo nuevo, mantené el mismo `id` y subí la `version`.
2. **Administración → Dashboards → Actualizar** en la fila del dashboard.
3. Elegí el archivo. Además de la validación, ves el **resumen de cambios** respecto de la
   versión vigente:
   - **Agregados**: los usuarios los verán con su `default`.
   - **Eliminados**: los valores guardados quedan huérfanos. No se borran solos; ver §6.
   - **Cambian de tipo**: advertencia principal. Los valores guardados con el tipo viejo se
     ignoran y cada usuario verá el valor base o el default hasta que guarde uno nuevo.
   - **Modificados**: cambios de rango, opciones o etiqueta. Los valores fuera del nuevo rango se
     ignoran.
4. Confirmá. Los usuarios ven la versión nueva al recargar.

## 3. Publicar, despublicar y eliminar

- **Despublicar** oculta el dashboard a los usuarios sin borrar nada. Sirve para retirar una
  versión con problemas mientras se corrige.
- **Eliminar** quita el dashboard de la plataforma junto con **todos** los datos cargados por los
  usuarios y su historial. Antes de borrar, la plataforma archiva esos datos en el servidor
  (`storage/app/private/dashboard-archive/`); un técnico puede volver a cargarlos con
  `php artisan dashboards:restore` en un dashboard con el mismo id de manifiesto. Igual, preferí
  despublicar: es reversible con un clic.
- **Descargar HTML** (menú Más) baja el archivo vigente tal como se cargó. **Descargar HTML con
  datos** baja una copia que trae adentro todo lo cargado por los usuarios hasta ese momento; al
  abrirla suelta en un navegador se ve lo mismo que en la plataforma, con un aviso al pie de que
  los cambios no se guardan. Sirve como respaldo y para revisar sin conexión. Cualquiera de las dos
  se puede volver a subir con **Actualizar archivo** sin modificar los datos cargados.
- **Actualizar el archivo nunca reemplaza los datos.** Los datos iniciales que trae un archivo sólo
  se cargan cuando una colección está vacía; una colección con datos no se toca.
- **Reemplazar un dashboard por otro archivo con distinto id** crea un dashboard nuevo y vacío: los
  datos no se mueven solos. Para conservar lo cargado, actualizá el existente con el mismo `id`.
- La base de datos se copia todos los días a las 03:15 en el servidor (`/root/backups/ciabay_marketing`,
  14 días de retención).

## 4. Datos cargados por los usuarios

Los dashboards con colecciones (por ejemplo, solicitudes de traslado) guardan en la plataforma
lo que los usuarios cargan desde la propia interfaz del dashboard. Esos datos son **compartidos**:
lo que carga uno lo ven todos, con unos segundos de demora si están en pantalla al mismo tiempo.

**Administración → Dashboards → Datos** muestra, por colección, la cantidad de registros, el
último cambio, cada registro con su contenido y el historial de quién creó, modificó o eliminó
cada uno. Desde ahí se exporta cada colección como JSON.

- **Cualquier usuario puede crear, editar y eliminar** registros desde el dashboard. Lo eliminado
  queda en el historial con su contenido anterior.
- **Datos iniciales.** La primera vez que se abre un dashboard, los datos que trae el archivo se
  cargan en la plataforma. Después mandan los datos del servidor: republicar el archivo no los pisa.
- **Restaurar un respaldo** (reemplazar toda una colección) es sólo para super administradores. Si
  el dashboard ofrece esa acción, sólo la ve un super administrador.
- Si dos personas editan **el mismo registro** al mismo tiempo, la segunda ve un aviso y el
  registro recargado con los cambios de la otra; registros distintos no chocan.
- Límites: 5000 registros por colección y 256 KB por registro, salvo que el manifiesto pida menos.

## 5. Valores base (sólo dashboards con parámetros)

Algunos dashboards declaran además parámetros escalares (una meta, un color). El valor base es lo
que ven todos los usuarios que no definieron un valor propio. Si no hay valor base, ven el
`default` del manifiesto.

1. **Administración → Dashboards → Valores base**.
2. Es la misma pantalla que ven los usuarios, pero cada cambio se guarda como valor base.
   El indicador de guardado confirma cada escritura.
3. **reset** en un parámetro quita el valor base y vuelve al `default` del manifiesto.
4. Los usuarios que ya definieron su propio valor **no** son afectados: su valor sigue mandando.
   Los que nunca lo tocaron ven el base nuevo al instante.

Tu propio valor como usuario y el valor base son cosas distintas: en la pantalla normal del
dashboard editás el tuyo; en "Valores base" editás el de todos.

## 6. Escenarios por usuario (sólo dashboards con parámetros)

**Administración → Dashboards → Escenarios** muestra una matriz usuarios × parámetros con el
valor efectivo de cada celda:

- celda azul: valor propio del usuario;
- celda normal: hereda el valor base;
- celda gris: hereda el `default` del manifiesto;
- `!`: el usuario tiene un valor guardado que ya no es válido para la versión vigente.

La primera fila es el valor base. Los usuarios con más valores propios aparecen primero.

## 7. Historial de parámetros

**Administración → Historial** lista cada cambio de parámetro, de cualquier usuario y del nivel base,
filtrable por dashboard, parámetro, usuario, nivel y fechas. Lo escribe la base de datos por
trigger: nada de lo que hacés en la aplicación puede editarlo ni borrarlo.

Cada usuario ve su propio historial en el dashboard, con el enlace **Mis cambios**.

Un parámetro marcado **huérfano** ya no existe en el manifiesto vigente. Para limpiar los valores
huérfanos (no el historial) se usa la línea de comandos en el servidor:

```bash
php artisan dashboards:prune-orphans --dry-run     # sólo lista
php artisan dashboards:prune-orphans               # borra
php artisan dashboards:prune-orphans --dashboard=ventas-sucursal
```

## 8. Divisiones y grupos

**Administración → Divisiones**. Una división es un sector de negocio (Comercial, Finanzas,
Operaciones…); dentro de cada división se crean grupos (Sucursales, Casa central…). Sirven para
dos cosas: decir a qué pertenece cada usuario y decidir quién ve cada dashboard.

- Crear una división: nombre y listo. Con «↑ ↓» se ordena el menú.
- Crear un grupo: desde la fila de su división. El nombre es único dentro de la división.
- Renombrar: clic sobre el nombre.
- Eliminar: sólo si ningún dashboard está asignado a la división ni a sus grupos; si lo hay, la
  plataforma avisa cuál reasignar. Los usuarios pierden la pertenencia, nada más.

**Quién ve un dashboard.** En **Administración → Dashboards → Editar** (o al cargarlo) se
elige:

- **Toda la empresa**: cualquier usuario activo.
- **Divisiones enteras**: todos los miembros de esa división.
- **Grupos puntuales**: sólo los miembros de ese grupo.
- **Sin nada marcado**: sólo los super administradores. Un dashboard nuevo arranca así: hay que
  asignarlo para que alguien lo vea.

Ahí mismo se elige el **ícono** con el que aparece en el menú; sin ícono se muestran las
iniciales del título.

**El menú del usuario** muestra sus dashboards agrupados por división y grupo, más la sección
"Toda la empresa". Con el botón «‹» del pie se contrae a una columna de íconos para dar espacio
al dashboard; «›» lo vuelve a expandir. La elección se recuerda en ese navegador.

## 9. Usuarios

**Administración → Usuarios**.

- **Nuevo usuario**: nombre, correo, contraseña inicial, rol y **divisiones y grupos**. Un
  usuario puede estar en varias divisiones y en grupos de cualquiera de ellas; un grupo sólo se
  puede marcar si su división está marcada. Pasale la contraseña por un canal seguro; el usuario
  puede cambiarla con "Olvidé mi contraseña" desde la pantalla de acceso (llega un correo con el
  enlace).
- **Editar**: cambiar nombre, correo, rol, contraseña o estado.
- Los usuarios **no se eliminan**: se desactivan. Un usuario inactivo no puede iniciar sesión y, si
  estaba conectado, pierde la sesión en su próxima acción. Sus valores y su historial se conservan.
- No podés desactivarte ni quitarte el rol de super administrador a vos mismo.

## 10. Cuando un dashboard "no guarda"

**Administración → Dashboards → Más → Diagnóstico** analiza el dashboard sin tocar código:

- corre las reglas del validador sobre el archivo vigente;
- compara las colecciones declaradas, las usadas en el código y las que tienen datos;
- marca registros cerca del tope de tamaño;
- lista las escrituras que la plataforma rechazó en los últimos 7 días, con usuario y motivo, los
  conflictos entre usuarios y los guardados repetidos que no cambian nada (señal de que la pantalla
  muestra algo que el archivo no llega a enviar).

Cada hallazgo dice qué hacer. Si hace falta corregir el archivo: **Copiar informe**, **Descargar
HTML** y usar el **Prompt de corrección** de Docs con un asistente de IA; después, **Actualizar
archivo** y volver a analizar. En la tabla de dashboards, una etiqueta roja "N rechazadas" avisa
cuando hubo escrituras rechazadas en la semana.

Además, cuando una escritura falla, el usuario ve el motivo arriba del dashboard aunque el archivo
no lo muestre.

## 11. Ampliar la lista de CDN

Los hosts permitidos para scripts, estilos y `fetch` de los dashboards se configuran en el
servidor, en la variable `DASHBOARD_CDN_ALLOWLIST` del archivo `.env` (separados por coma).
Después de cambiarla: `php artisan config:clear`. Los dashboards ya publicados toman la lista
nueva al abrirse.

## 12. Problemas frecuentes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| "El archivo no contiene el bloque dashboard-manifest" | Falta el `<script type="application/json" id="dashboard-manifest">` o tiene otro `id`/`type`. | Revisar el `<head>`. |
| "no es JSON válido" | Coma final, comillas simples, comentarios dentro del JSON. | Validar el bloque en un validador JSON. |
| "Tipo «x» no soportado" | El manifiesto usa un tipo fuera de los siete. | Cambiar a `number`, `text`, `boolean`, `select`, `range`, `date` o `color`. |
| "línea N: <script src=…> no apunta a un CDN autorizado" | Script relativo o desde otro host. | Embeber el código o usar un CDN de la lista. |
| El iframe queda con un aviso de que no llamó `ready()` | El dashboard no llama `Dashboard.ready()` o tiene un error antes. | Ver los errores mostrados sobre el iframe; agregar la llamada. |
| El iframe queda muy alto o muy bajo | El dashboard mide con `documentElement.scrollHeight` o fija `100vh`. | Usar `Dashboard.setHeight()` sin argumento y quitar alturas de viewport. |
| Un usuario ve valores "obsoletos" | Cambió el tipo o el rango en una versión nueva. | Esperado: al guardar un valor nuevo se reemplaza. |
| Las casillas o valores se ven marcados pero al recargar desaparecen | El archivo guardaba en una estructura que JSON no serializa, o la plataforma (hasta el 25/9) convertía objetos vacíos en listas. | Diagnóstico → si aparece "guardados sin cambio real", corregir con el prompt de corrección. La plataforma ya conserva los objetos vacíos. |
| Una sección del dashboard "no guarda" y el resto sí | El código usa una colección que no está en `collections` del manifiesto (típico al agregar una función nueva). | Desde ahora el validador lo rechaza al publicar (regla 11) y, si ocurre, el motivo aparece arriba del dashboard. Agregar la colección al manifiesto y actualizar. |
| "La colección «x» no está declarada en el manifiesto" | El dashboard escribe en una colección que no figura en `collections`. | Agregarla al manifiesto y actualizar el dashboard. |
| Los usuarios no ven los datos de otros | El dashboard guarda en memoria o con `localStorage` en lugar de `Dashboard.data`. | Adaptar el archivo con el prompt de Docs. |
| "Exportar PDF" o "Copiar imagen" fallaban | El dashboard usa html2canvas, que no funciona en el iframe aislado. | Resuelto por la plataforma: reemplaza html2canvas por una captura compatible. Si persiste, recargar la página. |
| Un usuario no ve un dashboard publicado | No está asignado a su división ni a sus grupos, o el dashboard no tiene asignación. | Revisar Administración → Dashboards → Editar y las divisiones del usuario. |
