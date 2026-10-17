# Locales por empresa

## Objetivo

Una empresa puede administrar uno o más locales. Cada local es propietario de sus series y correlativos. Todo comprobante conserva `em_id` y `loc_id` para mantener trazabilidad y compatibilidad con la autenticación actual por empresa.

## Modelo propuesto

- `empresa 1 ─ N local_empresa`
- `local_empresa 1 ─ N serie_documento`
- `local_empresa 1 ─ N comprobante`
- `serie_documento` conserva `em_id` durante la transición para no romper consultas, permisos ni integraciones.
- Cada empresa recibe automáticamente un local `PRINCIPAL`; sus series y comprobantes históricos se asignan inicialmente allí.

## Despliegue seguro en producción

1. Generar y comprobar un respaldo de la base central.
2. Ejecutar `bd/2026_10_16_01_locales_empresa_expand.sql`.
3. Desplegar la API con resolución de local y reserva de correlativos por `loc_id`.
4. Desplegar el panel central para administrar locales y sus series.
5. Desplegar iFact para seleccionar y enviar el local activo.
6. Verificar emisiones, reintentos, notas, guías y documentos históricos.
7. Ejecutar `bd/2026_10_16_02_locales_empresa_contract.sql` únicamente cuando no existan filas sin local.

## Cambios por proyecto

### Intelsoft Empresas API Pro

- Autenticar la empresa como actualmente y validar que el local solicitado le pertenezca.
- Aceptar `local_codigo` o `loc_id` en el contrato de emisión.
- Resolver y bloquear la serie usando `loc_id + tipo_doc + serie`.
- Guardar `loc_id` en cada comprobante y usarlo en reintentos, notas, guías y consultas.
- Durante la transición, si no llega local, resolver el local principal.

### Facturador Central Pro

- Incorporar una pantalla de locales dentro de cada empresa.
- Crear, editar, activar y desactivar locales sin eliminar historial.
- Administrar series y correlativos dentro del local, no directamente dentro de la empresa.
- Impedir dejar una empresa sin local principal activo.
- Auditar cambios de local, series y correlativos.

### iFact Pro

- Sincronizar los locales autorizados para la empresa.
- Conservar el local activo en sesión y mostrarlo en la cabecera.
- Enviar el local en ventas, notas, guías y reintentos.
- Evitar que un usuario utilice series de otro local.

## Reglas de integridad

- Solo un local principal activo por empresa, validado transaccionalmente.
- No reutilizar la misma serie tributaria entre locales de una empresa.
- Reservar correlativos con bloqueo de fila y transacción en la API central.
- Desactivar locales en lugar de eliminarlos cuando tengan historial.
- No cambiar el local de un comprobante ya emitido.

## Estado

Los scripts de expansión y cierre están preparados, pero no se ejecutan automáticamente. El formulario de empresa conserva temporalmente la edición de series hasta conectar el flujo de locales de extremo a extremo.
