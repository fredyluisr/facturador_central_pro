# Panel central de Intelsoft Perú

## Fuente de verdad

La lógica de negocio, el modelo de datos y los flujos tributarios se tomarán de `C:\\wamp64\\www\\intelsoft_empresas_api_pro` (Intelsoft Empresas API Pro). `facturador_central_pro` será el ambiente administrativo renovado y se construirá por etapas sobre esa base, sin duplicar ni reinterpretar la lógica fiscal.

## Principios

- La conexión central usa únicamente `FACTURACION_CENTRAL_DB_*` desde `.env`.
- Credenciales, claves SOL, secretos y tokens no se envían a vistas ni JavaScript.
- Las consultas usan PDO y la misma base central de empresas, usuarios, series y comprobantes.
- Toda escritura incorporará CSRF, validación de servidor, transacciones y auditoría antes de habilitarse.
- `intelsoft_empresas_api_pro` es la referencia para campos, validaciones, series, correlativos, estados SUNAT y contratos de API.

## Etapas

1. **Base visual y lectura conectada (actual):** sesión, layout responsivo, identidad Intelsoft Perú, resumen, empresas, usuarios y comprobantes.
2. **Mantenimientos seguros:** crear/editar empresas, rotación de tokens, usuarios y contraseñas.
3. **Operación tributaria:** detalle, estados, descargas autorizadas, reintentos y recuperación de CDR.
4. **Observabilidad:** filtros, indicadores, trazabilidad, alertas y auditoría.
5. **Endurecimiento:** permisos por rol, límites, registro de acciones, pruebas y despliegue.

## Evolución: locales por empresa

El diseño y la secuencia de despliegue están en `docs/LOCALES_POR_EMPRESA.md`. La migración se divide en expansión compatible y cierre posterior para no interrumpir el servicio que ya está en producción.

## Estado de esta entrega

La autenticación existente se conserva. Al iniciar sesión se abre el panel conectado, ya no la pantalla provisional. Las acciones de alta y edición se mantienen deshabilitadas hasta trasladar su flujo completo de seguridad; las vistas actuales son deliberadamente de consulta.
