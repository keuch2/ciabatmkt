# Guía operativa del super administrador

Todo lo de esta guía se hace desde el menú **Administración** de la plataforma. Necesitás el rol de
super administrador.

## 1. Publicar un dashboard nuevo

1. Conseguí el archivo `.html` (ver `PLANTILLA-PROMPT.md` para generarlo con IA, o
   `dashboard-referencia.html` como base).
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
- **Eliminar** borra el dashboard, **todos** los valores guardados por los usuarios y su historial.
  No se puede deshacer. Preferí despublicar.

## 4. Valores base

El valor base es lo que ven todos los usuarios que no definieron un valor propio. Si no hay valor
base, ven el `default` del manifiesto.

1. **Administración → Dashboards → Valores base**.
2. Es la misma pantalla que ven los usuarios, pero cada cambio se guarda como valor base.
   El indicador de guardado confirma cada escritura.
3. **reset** en un parámetro quita el valor base y vuelve al `default` del manifiesto.
4. Los usuarios que ya definieron su propio valor **no** son afectados: su valor sigue mandando.
   Los que nunca lo tocaron ven el base nuevo al instante.

Tu propio valor como usuario y el valor base son cosas distintas: en la pantalla normal del
dashboard editás el tuyo; en "Valores base" editás el de todos.

## 5. Escenarios por usuario

**Administración → Dashboards → Escenarios** muestra una matriz usuarios × parámetros con el
valor efectivo de cada celda:

- celda azul: valor propio del usuario;
- celda normal: hereda el valor base;
- celda gris: hereda el `default` del manifiesto;
- `!`: el usuario tiene un valor guardado que ya no es válido para la versión vigente.

La primera fila es el valor base. Los usuarios con más valores propios aparecen primero.

## 6. Historial

**Administración → Historial** lista cada cambio de valor, de cualquier usuario y del nivel base,
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

## 7. Usuarios

**Administración → Usuarios**.

- **Nuevo usuario**: nombre, correo, contraseña inicial y rol. Pasale la contraseña por un canal
  seguro; el usuario puede cambiarla con "Olvidé mi contraseña" desde la pantalla de acceso (llega
  un correo con el enlace).
- **Editar**: cambiar nombre, correo, rol, contraseña o estado.
- Los usuarios **no se eliminan**: se desactivan. Un usuario inactivo no puede iniciar sesión y, si
  estaba conectado, pierde la sesión en su próxima acción. Sus valores y su historial se conservan.
- No podés desactivarte ni quitarte el rol de super administrador a vos mismo.

## 8. Ampliar la lista de CDN

Los hosts permitidos para scripts, estilos y `fetch` de los dashboards se configuran en el
servidor, en la variable `DASHBOARD_CDN_ALLOWLIST` del archivo `.env` (separados por coma).
Después de cambiarla: `php artisan config:clear`. Los dashboards ya publicados toman la lista
nueva al abrirse.

## 9. Problemas frecuentes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| "El archivo no contiene el bloque dashboard-manifest" | Falta el `<script type="application/json" id="dashboard-manifest">` o tiene otro `id`/`type`. | Revisar el `<head>`. |
| "no es JSON válido" | Coma final, comillas simples, comentarios dentro del JSON. | Validar el bloque en un validador JSON. |
| "Tipo «x» no soportado" | El manifiesto usa un tipo fuera de los siete. | Cambiar a `number`, `text`, `boolean`, `select`, `range`, `date` o `color`. |
| "línea N: <script src=…> no apunta a un CDN autorizado" | Script relativo o desde otro host. | Embeber el código o usar un CDN de la lista. |
| El iframe queda con un aviso de que no llamó `ready()` | El dashboard no llama `Dashboard.ready()` o tiene un error antes. | Ver los errores mostrados sobre el iframe; agregar la llamada. |
| El iframe queda muy alto o muy bajo | El dashboard mide con `documentElement.scrollHeight` o fija `100vh`. | Usar `Dashboard.setHeight()` sin argumento y quitar alturas de viewport. |
| Un usuario ve valores "obsoletos" | Cambió el tipo o el rango en una versión nueva. | Esperado: al guardar un valor nuevo se reemplaza. |
