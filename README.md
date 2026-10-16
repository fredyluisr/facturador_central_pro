# iFact Pro: estado actual y estructura de trabajo

Revisión inicial: 16 de septiembre de 2026. Implementación del catálogo: 17 de septiembre de 2026. Las secciones 1–7 documentan etapas anteriores; el estado vigente del POS, inventario y costos está en la sección 8. La decisión sobre empresa, tienda y futuro soporte multisede está en la sección 9. Las secciones 10–14 describen la implementación vigente de locales, caja y movimientos financieros y prevalecen ante cualquier descripción histórica incompatible.

## 1. Objetivo y alcance inicial

Construir un punto de venta con facturación electrónica que pueda utilizarse en distintos rubros comerciales: zapatos, zapatillas, carteras, accesorios y otros productos. El rubro debe configurar el catálogo, sin obligar a desarrollar un sistema distinto por negocio.

La primera versión no manejará inventario. Registrar cantidades vendidas no significa controlar existencias: no habrá stock inicial, descuentos de stock, reservas, kardex, transferencias ni bloqueo de venta por falta de existencias. Compras, producción, planillas y logística quedan fuera de este alcance. Caja y cobros sí pertenecen al punto de venta.

## 2. Lo que ya se ha avanzado

El proyecto utiliza PHP, controladores, modelos, vistas y JavaScript, con conexión MySQL configurada mediante `.env`. Se revisaron estos componentes, los respaldos de `bd/`, los scripts de limpieza y los cambios recientes de Git.

| Área | Base existente | Cómo continuar |
| --- | --- | --- |
| Acceso y administración | Usuarios, sesiones, roles, permisos, menús y páginas | Aprovechar esta base y revisar permisos por operación y tienda |
| Personal | Empleados y cargos; la base activa ya no tiene los campos laborales retirados | Mantener datos mínimos para identificar vendedores y cajeros |
| Clientes y entidades | Mantenimiento de entidades y clientes del POS | Unificar la experiencia y las validaciones |
| Cobros | Formas de pago, cuentas bancarias y tipos de ingreso/egreso | Reutilizar catálogos para pagos de ventas y caja |
| Configuración | Empresa emisora vinculada a la central y formato A4/ticket | Definir configuración comercial, locales y cajas |
| Ventas | Formulario, listado, historial, cabecera y detalles | Adaptar a variantes y funcionamiento sin inventario |
| Facturación | Adaptador central, emisión desde venta, notas de crédito/débito, consultas de estado, archivos y lógica de reintentos | Consolidar y verificar el flujo completo |

“Existente” significa que se encontró estructura o implementación; no certifica que todos los flujos funcionen. No se realizaron ventas, emisiones, cambios de empresa ni pruebas contra el servicio central en esta revisión.

## 3. Revisión de la base de datos y pendientes reales

Se consultó la base local activa `ifact` con una transacción configurada en modo de solo lectura. Contiene 35 tablas, 1.092 productos, 13 colores, 16 ventas y 5 registros de facturación. Estas cifras describen los datos actuales; no acreditan aceptación tributaria ni que el catálogo sea definitivo.

Hallazgos que deben resolverse en una etapa posterior:

- **Catálogo incompleto para varios rubros:** `producto` tiene nombre, descripción, unidad, imagen y precio, pero no tiene categoría, marca, SKU, código de barras ni estructura de variantes. Existe `colores`, pero no una relación con productos; tampoco existen tablas de tallas o categorías.
- **Ventas todavía dependen de inventario:** `model/venta.php` consulta y valida stock, registra salidas y revierte movimientos al anular. Consulta `movimientos_stock_producto` e `insumo`, que no existen en la base activa. `model/stock_pos.php` conserva esas dependencias. El POS necesita desacoplarse de ellas antes de utilizarse sin inventario.
- **Restos de módulos retirados:** existen archivos de pedidos y asistencia aunque sus tablas fueron eliminadas. El módulo de pedidos también consulta producción. La navegación conserva rutas de mantenimientos y reportes cuyos archivos no están en `view/`; registrar una página no implica tener el módulo implementado.
- **Relaciones heredadas incompletas:** `compra_detalle` y `movimiento_stock` conservan claves foráneas hacia la tabla ausente `insumo`. Aún hay 1 compra y 357 movimientos heredados. Documentar su origen y decidir conservación o archivo antes de cualquier limpieza.
- **Transacciones de ventas:** el detalle vigente se almacena exclusivamente en `venta_detalle_producto`; la tabla heredada `venta_detalle` fue retirada. Antes de confiar en `beginTransaction`/`rollBack`, confirme que las tablas transaccionales involucradas usan InnoDB.
- **Precisión y cantidades:** hay importes de facturación en `FLOAT`; el detalle facturado usa cantidad entera, mientras ventas permite decimales. Unificar precisión monetaria, cantidades por unidad y redondeo en el diseño posterior.
- **Historia de ventas:** el detalle comercial obtiene el nombre desde el producto actual. Debe conservar la descripción, variante, unidad, precio, descuento e impuestos de la operación, aunque después se modifique el catálogo.
- **Impuestos inconsistentes:** ventas calcula con un factor fijo de 1,18 y el adaptador consulta la configuración tributaria de la central. Definir una única regla de cálculo y permitir afectación por artículo cuando corresponda. La implementación deberá validarse con los requisitos tributarios vigentes, sin asumir que todos los negocios tienen el mismo tratamiento.
- **Usuarios heredados:** 35 usuarios no tienen empleado asociado; ninguno de esos usuarios está activo. Revisar su conservación como datos históricos, sin tratarlos como cuentas operativas válidas.
- **Empresa emisora:** el sistema permite cambiar las credenciales de empresa, pero las ventas no tienen un identificador explícito de empresa. Cambiar el RUC de conexión no constituye separación de datos entre empresas.
- **Respaldos y codificación:** conviven nombres de respaldos `ifact`/`ifact_pro`, estructuras anteriores con campos laborales y tablas con `latin1`/`utf8mb4`. La base activa debe ser la referencia del próximo diseño; no restaurar un respaldo antiguo suponiendo que representa el estado actual.

Las comprobaciones puntuales no encontraron detalles de producto sin producto o sin venta, ni comprobantes con `ven_id` informado que apunten a una venta inexistente. No se realizó una auditoría exhaustiva de todos los datos ni una prueba funcional completa.

## 4. Estructura general propuesta

Separar conceptualmente cinco bloques: administración y configuración; catálogo; clientes; ventas, cobros y caja; facturación electrónica y reportes. Inventario podrá añadirse después como módulo independiente.

### Producto y variante

El **producto** representa el modelo comercial: “Zapatilla Urbana”. La **variante** representa lo que se vende: “Zapatilla Urbana / negro / talla 38”. Esa variante tendrá identificación propia y podrá tener precio distinto. No duplicar toda la ficha del producto por cada talla o color.

Los atributos serán configurables. Talla y color son los primeros, pero se debe poder incorporar material, capacidad, presentación o medida sin rediseñar ventas. Diferenciar atributos que generan variantes de características informativas: la combinación vendible debe quedar identificada y no repetirse dentro de un mismo producto.

| Ejemplo | Configuración de variantes |
| --- | --- |
| Zapato o zapatilla | Color + talla |
| Cartera | Color; tamaño o material solo si diferencian lo vendido |
| Prenda | Color + talla de ropa |
| Accesorio | Sin atributos, o color/presentación cuando corresponda |

No todos los productos necesitan talla ni color. Para mantener un único flujo de venta, un producto simple puede tener una variante predeterminada que el usuario no tenga que seleccionar. Crear solo combinaciones reales, sin generar automáticamente todas las combinaciones posibles.

Las tallas tendrán grupos o sistemas: calzado numérico, ropa XS/S/M/L y talla única. Evitar mezclar tallas de diferentes sistemas. La categoría clasifica el producto; no reemplaza su talla ni su color.

### Relaciones conceptuales para el futuro diseño

- Empresa → locales/puntos de venta → cajas → sesiones de caja.
- Categoría → productos; marca opcional → productos; producto → variantes.
- Atributo → valores permitidos; variante → combinación de valores.
- Cliente → ventas; venta → detalles, pagos y comprobantes relacionados.
- Detalle de venta → variante vendida y copia de los datos comerciales de ese momento.
- Comprobante → detalle fiscal, documento de referencia cuando corresponda e historial de comunicación con la central.

Estas son relaciones de negocio, no tablas definitivas ni instrucciones SQL. Si cada negocio tendrá su propia instalación/base, documentar ese límite. Si varias empresas compartirán una base, diseñar aislamiento de catálogos, usuarios, ventas, caja y comprobantes desde el inicio.

## 5. Orden de trabajo, uno por uno

### Paso 1. Cerrar las reglas de la primera versión

Definir si cada empresa tendrá instalación propia, cuántos locales/cajas podrá usar y si la venta inicial será solo al contado. Propuesta inicial: catálogo configurable, productos simples y con variantes, venta al contado, pagos combinados y caja básica. Crédito, cuotas, listas de precios mayoristas y promociones complejas pueden esperar.

**Resultado esperado:** alcance y reglas escritos, con ejemplos de un negocio de calzado y otro de accesorios.

### Paso 2. Alinear lo heredado con el alcance

Preparar la adaptación de ventas sin consultas, validaciones o movimientos de stock. Revisar accesos a módulos retirados, relaciones hacia tablas ausentes, motores de ventas y conservación de datos históricos. No borrar información para ocultar una dependencia sin haber definido cómo se sustituye.

**Resultado esperado:** lista de componentes a conservar, adaptar o retirar, y plan de transición que preserve ventas y comprobantes.

### Paso 3. Definir empresa, locales y cajas

Separar punto de venta de almacén: una tienda y una caja siguen siendo necesarias aunque no exista inventario. Definir datos comerciales, moneda, formato de impresión, vendedor/cajero y permisos para cada local. Mantener en la central la autoridad sobre series y correlativos de comprobantes, como establece el adaptador actual.

**Resultado esperado:** estructura operativa clara y responsabilidad definida entre POS y central.

### Paso 4. Preparar los catálogos maestros

Trabajar categorías y subcategorías opcionales, marcas opcionales, unidades de venta, colores, grupos de tallas, tallas y otros atributos con sus valores. Cada negocio utilizará únicamente lo que necesite. Las unidades deben distinguir unidad, par, kilogramo, metro u otras presentaciones cuando corresponda.

**Resultado esperado:** catálogos que permitan describir distintos rubros sin imponer campos innecesarios.

### Paso 5. Definir productos y variantes

La ficha del producto incluirá nombre, descripción, categoría, marca opcional, unidad, imagen y estado. La variante incluirá combinación de atributos, SKU, código de barras opcional, precio y estado. Definir cuándo hereda el precio del producto y cuándo lo reemplaza; establecer identificación única dentro de la empresa.

**Resultado esperado:** ejemplos completos de zapatilla con talla/color, cartera con color y accesorio simple. Una variante inactiva no se ofrece para ventas nuevas, pero sigue disponible en el historial.

### Paso 6. Consolidar clientes

Reutilizar entidades y clientes actuales. Definir venta a consumidor final, búsqueda por documento, alta rápida y datos requeridos para cada comprobante. Evitar duplicados y conservar en el documento los datos del cliente usados al emitirlo.

**Resultado esperado:** un flujo de clientes consistente entre administración y POS.

### Paso 7. Diseñar la venta del POS

Definir búsqueda por nombre/SKU/código de barras, selección de variante, carrito, cantidades, precios, descuento autorizado, cliente y tipo de comprobante. Mostrar claramente talla y color para evitar vender otra variante. El servidor deberá validar y calcular la operación con las mismas reglas utilizadas para facturar.

**Resultado esperado:** recorrido completo desde seleccionar un artículo hasta confirmar la venta, sin depender de existencias.

### Paso 8. Definir cobros y caja

Reutilizar formas de pago y cuentas bancarias. Una venta podrá tener uno o varios pagos, por ejemplo efectivo y transferencia. Registrar monto, medio, referencia cuando aplique, recibido y vuelto de efectivo. Separar el importe entregado por el cliente del importe efectivamente aplicado a la venta.

La caja básica incluirá apertura, monto inicial, cobros, ingresos/egresos autorizados, devoluciones y cierre con comparación entre efectivo esperado y contado. Transferencias y tarjetas se reportan por separado del efectivo físico.

**Resultado esperado:** trazabilidad del dinero por venta, cajero, caja y turno. Una venta al contado se confirma cuando sus pagos cubren el total.

### Paso 9. Consolidar la facturación electrónica

Aprovechar el adaptador central. Definir el vínculo de venta y comprobante, estados comerciales y fiscales separados, prevención de duplicados, recuperación cuando se pierde la respuesta, reintentos, consulta de estado y acceso a PDF/XML/CDR. Una falla de comunicación no debe provocar otra venta ni otro comprobante al repetir la operación.

Antes de producción, verificar casos reales en el entorno de pruebas de la central y confirmar requisitos tributarios vigentes. No dar por probadas las funciones solo porque ya existen métodos o registros.

**Resultado esperado:** una venta con comprobante trazable, incluyendo operaciones pendientes o rechazadas y su resolución.

### Paso 10. Definir anulaciones, devoluciones y cambios

Distinguir anular una operación comercial, corregir un comprobante mediante el procedimiento que corresponda y devolver dinero. Registrar motivo, usuario, cantidades e importes; contemplar devolución parcial y cambio de talla/color con referencia a la operación original. En esta versión no habrá movimientos de stock por devolución.

**Resultado esperado:** flujo documentado que mantenga consistencia entre venta, documento y caja, sin modificar la historia original.

### Paso 11. Completar reportes y validación

Priorizar ventas por fecha/local/cajero, ventas por producto/variante/categoría, cobros por medio de pago, cierre de caja y comprobantes pendientes o rechazados. No ofrecer stock ni utilidad real si no existen los datos que los sustentan.

Validar productos simples, talla/color, precios diferentes por variante, cantidades decimales donde apliquen, pagos combinados, descuentos, doble clic al confirmar, pérdida de respuesta de la central, devolución parcial y permisos por local/empresa.

**Resultado esperado:** primera versión de POS y facturación utilizable en los rubros de ejemplo, con flujos y límites comprobados.

## 6. Secuencia acordada inicialmente

Comenzar por las reglas del paso 1 y el mapa de dependencias del paso 2. Después definir categorías, atributos, tallas, productos y variantes. Ese catálogo será la base de ventas, comprobantes y reportes; conviene acordarlo antes de diseñar tablas o pantallas definitivas.

La implementación del catálogo fue autorizada posteriormente y se describe a continuación. Los pasos de ventas, caja, facturación e inventario completo siguen siendo trabajo posterior.

## 7. Catálogo implementado — 17/09/2026

El menú Inventario ahora se llama **Catálogo**. Insumos deja de mostrarse y el acceso anterior de Colores se sustituye por el mantenimiento de valores del atributo Color. Los datos anteriores no se borraron.

| Pantalla | Funciones |
| --- | --- |
| `view/list_categorias.php` | Crear, editar, habilitar/deshabilitar, retirar categorías sin productos relacionados |
| `view/list_producto.php` | Crear y editar productos por categoría/unidad, generar combinaciones y mantener SKU, código de barras, precio y estado por variante |
| `view/list_variantes.php` | Crear atributos configurables y sus valores; incluye Color, Talla de calzado y Talla de ropa como atributos iniciales |
| `view/list_unidad_medida.php` | Mantener nombre, abreviatura, código de unidad opcional para facturación y precisión de cantidades de 0 a 3 decimales |

Todas las pantallas utilizan los estilos comunes de iFact, modal de registro/edición, preguntas antes de guardar/eliminar, mensajes de validación y resultado, y apertura del formulario al hacer clic en el nombre o estado, sin botones de información o edición. Los campos de unidades se adaptaron del módulo de `hristovlaser_dev_pro`, sin copiar sus dependencias antiguas de avisos.

### Cómo comenzar a registrar

1. Crear las categorías que realmente utiliza el negocio: por ejemplo Calzado, Carteras y Accesorios.
2. Revisar o crear unidades: por ejemplo Unidad o Par. La precisión pertenece a la unidad; el POS deberá utilizarla cuando se adapte a variantes.
3. En Variantes, abrir **Valores** para registrar colores, tallas y otras opciones. Se importaron los 13 colores anteriores; las tallas se registran según el negocio, sin asumir un sistema universal.
4. En Productos, completar nombre, categoría, unidad y precio base.
5. Para un producto simple, completar la fila sin atributos. Para uno configurable, agregar hasta tres atributos, marcar sus valores y generar las combinaciones. Se pueden quitar combinaciones nuevas que no se comercializan.
6. Revisar los códigos y precios de cada fila antes de guardar. Los SKU sugeridos son editables; cada SKU y cada código de barras informado deben ser únicos en esta base.

El límite actual es de 300 variantes por producto, contando las deshabilitadas. Generar combinaciones copia el precio base a las filas nuevas; modificar el precio base no altera precios de variantes ya guardadas. En filas nuevas, el precio sigue al base hasta que se modifica individualmente.

### Datos e identidad preparados para stock

Se añadieron `categoria`, `atributo`, `atributo_valor`, `producto_variante` y `producto_variante_valor`. `producto` conserva su identidad y ahora admite categoría; se ampliaron nombre y descripción. Se mantienen `unidad_medida` y `colores` originales, añadiendo a unidades los campos de código y precisión.

Los 1.092 productos anteriores recibieron una variante simple con SKU `PRO-{id}`, conservando el precio y el estado operativo. Sus categorías quedan pendientes de clasificación. Los atributos Color y tallas son una base configurable, no una restricción de rubro.

Cada variante tiene `pva_id` estable y una combinación única de valores. No se cambia la combinación de una variante existente ni se elimina una fila existente desde el formulario: se deshabilita y se crea otra. Convertir un producto simple a configurable conserva la variante anterior deshabilitada. Estas reglas permiten relacionar posteriormente movimientos de stock y detalle de ventas con la variante exacta.

**Preparado para stock no significa inventario implementado.** Todavía no hay existencias por variante/ubicación, entradas, salidas, ajustes, reservas ni kardex nuevos. Tampoco se adaptó el POS para vender variantes: conserva las dependencias heredadas de stock identificadas en la revisión. El próximo módulo deberá vincular el detalle de venta a `pva_id`, guardar su descripción histórica, aplicar unidad/precisión y usar el código de unidad configurado en el envío fiscal. El adaptador actual todavía utiliza su mapeo de abreviaturas.

La implementación conserva el alcance actual de una empresa por base. No incorpora aislamiento de varias empresas dentro de una misma base.

### Consistencia, acceso y aplicación

El guardado de producto y variantes es transaccional en InnoDB. Se validan precios, estados, valores habilitados, pertenencia de variantes, SKU, códigos de barras y combinaciones repetidas. La edición comprueba una versión del registro para evitar sobrescribir cambios de otro usuario. No se retiran categorías/unidades/valores con relaciones ni productos con ventas registradas; se utiliza deshabilitación para conservar el historial.

El endpoint `controller/catalogo.php` exige sesión, permiso de página y token del formulario. Las nuevas páginas heredaron los roles del acceso anterior de Insumos, además de administración; los permisos siguen administrándose en el mantenimiento existente.

La migración aplicada en la base `ifact` está en `bd/catalogo_20260917.sql`. El ejecutor es exclusivamente CLI y toma un respaldo de las tablas afectadas antes de ejecutar cambios. Los cambios DDL de MySQL no se deshacen con un rollback; conservar el respaldo antes de cualquier aplicación posterior.

```powershell
& C:\wamp64\bin\php\php8.3.28\php.exe bd\apply_catalogo_20260917.php
```

Respaldo previo de esta aplicación: `bd/catalogo_before_20260917_000934.sql`. No contiene sesiones ni credenciales del entorno. No ejecutarlo como restauración parcial sin revisar sus relaciones con las demás tablas.

### Validación realizada

- Sintaxis PHP de los nuevos archivos y sintaxis JavaScript.
- 23 comprobaciones funcionales en una base de pruebas aislada: altas, edición, combinaciones, SKU/códigos duplicados, valores deshabilitados, precios inválidos, versión anterior, protección de relaciones, retiro lógico y conservación de variantes al convertir un producto simple.
- Peticiones HTTP: operación autorizada, rechazo de token inválido y rechazo sin acceso.
- Navegador sobre el entorno aislado: alta de categoría y talla, producto con cuatro combinaciones de talla/color, edición de precio por variante, recarga de listado, confirmaciones y modal de información. También se revisaron la pantalla y los campos de unidades de medida.

Las pruebas se ejecutan con `tests/catalogo_test.php`; crea su propia base `ifact_catalogo_test_*` y la elimina al terminar. Requiere que el usuario MySQL tenga permiso de crear/eliminar bases de prueba. No emite comprobantes ni registra ventas reales.

Los títulos de pestaña se unificaron como «Nombre de página · iFact», incluyendo Roles, mantenedores, configuración, navegación e inicio de sesión.
### Ajustes de productos — 17/09/2026

Revisión posterior contrastada con HRisto Blaser y Setlistone: `last_activity` se actualiza en cada petición que usa la sesión, por lo que las tres horas se cuentan desde la última actividad. Se alineó `session.gc_maxlifetime=10800` con el manejador MySQL. La prueba HTTP de recuerdo ahora valida también renovación cerca del límite con y sin recuerdo, y vencimiento por inactividad sin recuerdo: once comprobaciones aprobadas. Mantener la página abierta sin peticiones no renueva la sesión; dentro de los 30 días, Recuérdame restaura el acceso en la siguiente petición.

Login: se añadió «Recuérdame — Mantener mi acceso por 30 días» y se envía por POST. Se revisó el código de HRisto Blaser como referencia; su implementación heredada exigía cookie Secure en HTTP y consultaba campos de usuario que no existen aquí. iFact usa `ifact_remember_me` limitada a `/ifact_pro/`, HttpOnly, SameSite=Lax y Secure cuando hay HTTPS. `remember_tokens.alm_id` conserva la tienda; se restauran únicamente usuario activo y el perfil/rol y tienda guardados y habilitados. La sesión normal conserva el TTL de tres horas; el token restaura el acceso hasta 30 días desde el login sin interrumpir la página ni responder con redirecciones HTML a la API. Cerrar sesión revoca el token y las sesiones anuladas no se vuelven a leer. Migración aplicada: `bd/remember_me_20260917.sql`. Prueba HTTP local `tests/remember_me_test.php`: siete comprobaciones aprobadas (login, cookie HTTP, vigencia/tienda, restauración tras vencimiento, identidad, revocación y bloqueo tras salir); sintaxis PHP/JS validada.

La sesión del sistema vence tras tres horas sin actividad. El catálogo ahora distingue sesión ausente/vencida de falta de permisos: las páginas redirigen a login y la API devuelve HTTP 401 con un mensaje específico; los permisos insuficientes mantienen HTTP 403. Se revisó que Variantes y su menú están habilitados para los roles con acceso.

Ajuste posterior del selector de color: se quitaron las muestras rápidas y el texto inferior; quedan el input de paleta y «Quitar». En Talla de ropa se registraron/habilitaron S, M, L, XL y XXL, sin duplicados.

«Nuevo valor» usa el botón con degradado azul/violeta del sistema. El campo opcional de color tiene una paleta nativa, muestras rápidas y «Quitar»; el código se guarda automáticamente sin tener que escribirlo. Un valor nuevo permanece sin color hasta seleccionar uno; editar conserva el color existente. Sintaxis PHP y JavaScript verificada.

Unidades básicas registradas a solicitud del usuario: Unidad/UND/NIU (se conservó la existente), Par/PAR/PR, Docena/DOC/DZN, Juego/JGO/SET, Caja/CAJ/BX y Paquete/PQT/PK. Todas habilitadas y con cantidades enteras. Códigos contrastados con la tabla oficial publicada por SUNAT: https://www.sunat.gob.pe/orientacion/amazon/ple/RS-169-2015-SUNAT-PLEver5.pdf. Se corrigió la validación para admitir códigos de dos o tres caracteres (PR, BX y PK tienen dos). Estas presentaciones no crean conversiones de stock automáticamente.

Corrección posterior: el usuario solicitó tablas físicamente vacías. Se eliminaron las 15 unidades (incluidos los registros retirados) y el producto de prueba con sus variantes. Conteo confirmado: `unidad_medida=0`, `producto=0`. Respaldo: `bd/unidades_productos_before_20260917_011001.sql`. Antes de eliminar unidades se guardó `unidad_abrev_historica` en los detalles de ventas y movimientos; sus referencias a unidades se hicieron nulas. Las consultas de ventas/facturación usan la abreviatura histórica con LEFT JOIN. Las ventas y los movimientos se conservan. Pasaron las 23 comprobaciones funcionales aisladas.

Se retiraron las 15 unidades de medida anteriores del catálogo mediante `und_estado=2`, conservando sus registros para relaciones históricas. Respaldo: `bd/unidades_before_20260917_010557.sql`. El listado distingue «Abreviatura», «Código SUNAT» y «Precisión de cantidad». La precisión no representa stock; el adaptador fiscal actual todavía convierte la abreviatura mediante un mapa y no utiliza `und_codigo_sunat`.

Los listados de Categorías, Productos y Variantes tienen un espaciado vertical ligeramente menor en encabezados, tarjetas y filas, conservando la tipografía. Variantes muestra una tarjeta de orientación cuyo contenido cambia al abrir los valores de un atributo. El botón «Volver a atributos» tiene mayor contraste y el título del atributo queda destacado. Sintaxis PHP y JavaScript verificada.

El estado de las variantes vendibles usa un switch accesible y las filas tienen menor espaciado vertical, conservando el tamaño de letra. El formulario enlaza al mantenedor Catálogo → Variantes, donde «Valores» permite registrar tallas, colores y otras opciones. «Actualizar atributos» consulta nuevamente las opciones sin cerrar el formulario ni perder lo ingresado. Un atributo sin valores habilitados muestra instrucciones específicas.

Se vació el catálogo activo por solicitud del usuario: 1 092 productos y sus variantes. Se conservan las 31 líneas de ventas, sus cantidades y montos; `venta_detalle_producto.vdp_nombre` guarda el nombre histórico y `pro_id` queda nulo para los productos retirados. Las consultas de ventas y facturación usan ese nombre aunque el producto no exista. Respaldo previo: `bd/productos_before_20260917_005556.sql`. La limpieza puntual se ejecutó con `bd/limpiar_productos_20260917.php`; no volver a ejecutarla como parte del arranque.

Se retiró `producto.pro_descripcion` de la base de datos, del formulario y de la API. Nombre, categoría, unidad, precio base y estado componen la ficha principal. El mensaje del listado se presenta como una tarjeta de ayuda. «Sin atributos (variante simple)» representa una sola opción vendible sin talla, color u otros atributos. Generar combinaciones crea una opción por cada combinación de valores seleccionados; no asigna existencias.

El SKU se propone automáticamente y es editable. El código de barras es opcional y se ingresa manualmente por variante; todavía no existe generación ni impresión de etiquetas. Validación posterior: 23 comprobaciones funcionales en una base aislada, sintaxis PHP y JavaScript correctas.

## 8. Estado vigente del POS, stock y costos — 21/09/2026

Las secciones 1–7 registran decisiones y hallazgos anteriores; varias frases sobre «sin inventario» ya no describen el sistema actual. Este es el estado que debe utilizarse al continuar el trabajo desde otra PC.

- **Catálogo y ventas:** productos con variantes, atributos, unidades y precio de venta por variante. Nueva venta permite elegir la combinación exacta, cantidad y precio, registrar un pago inicial y completar abonos posteriores. El comprobante se emite al terminar de pagar.
- **Stock:** `view/list_ingreso_stock.php` registra entradas por variante, cantidad y **compra unitaria**; `view/list_ajuste_inventario.php` registra conteos y ajustes. El modo «vender con stock / vender sin stock» se configura en `view/configuracion.php`. Una venta descuenta existencias; una anulación las repone.
- **Costo y utilidad:** `model/costos_fifo.php` conserva lotes por ingreso o ajuste y consume primero la fecha/lote más antiguo. El costo queda congelado por línea de venta en `venta_detalle_producto`; `view/list_utilidad.php` presenta ganancia bruta (venta menos costo FIFO) y excluye de los KPI las líneas sin costo verificable. No incluye gastos, impuestos recuperables, descuentos de proveedor ni utilidad neta.
- **Reportes:** el reporte de stock valorizado sigue mostrando valor **comercial**, calculado con precio de venta, no costo de adquisición. No confundirlo con utilidad.

### Carga estimada de costos existentes

El 21/09/2026 se completaron los **71 renglones** de los dos ingresos existentes. A falta de facturas de proveedor, cada costo se estimó como `redondear(precio de lista de la variante × 0,70; 2 decimales)`. Eso representa aproximadamente **30 % de margen bruto sobre el precio de lista**: una venta a S/ 90 implica costo estimado de S/ 63 y ganancia bruta de S/ 27. Si se buscara 30 % de recargo sobre el costo, la fórmula sería diferente. Estos valores son supuestos de prueba, no compras verificadas; deben reemplazarse con los comprobantes reales cuando estén disponibles.

Los movimientos históricos de prueba tenían fechas de venta anteriores a la «carga inicial» del 21/09/2026, aunque sus cantidades concilian con ella. Se reconstruyeron lotes FIFO de esa carga y se asignaron costos retrospectivos por variante y orden de venta. **99 líneas / 149 pares** encontraron ingreso y costo; **2 líneas / 3 pares** no tienen ingreso asociado y permanecen pendientes, sin inventar ganancia. El stock final de **1.501 pares** concilió exactamente con las unidades restantes de los lotes. En el rango de septiembre, el reporte calculó venta S/ 11.330,10, costo S/ 7.931,07 y utilidad bruta estimada S/ 3.399,03 para las 99 líneas; otras ventas por S/ 117,00 quedan pendientes de costear. El 30 % agregado resulta de los precios actuales de esta semilla de pruebas y no constituye una garantía de margen en ventas futuras, donde el precio se puede modificar.

La reconstrucción se hizo una sola vez con `bd/backfill_costos_fifo_20260921.php`, que comprueba el estado esperado antes de escribir y crea `bd/respaldo_fifo_20260921_184158.json`. **No volver a ejecutar el script sobre la base ya actualizada.** Para trabajar desde otra PC se deben trasladar código y base de datos juntos; el respaldo JSON de FIFO no sustituye un volcado completo de MySQL. La migración estructural de costos está en `bd/costos_fifo.sql` y ya estaba aplicada a esta base. Nuevos ingresos exigen costo unitario al registrarse. Mantener las dos líneas sin costo como pendientes hasta documentar su procedencia.

### Ajuste de inventario: costo de referencia

Al elegir una variante, el ajuste muestra el costo del **siguiente lote disponible en orden FIFO**. Es una referencia, no un promedio ni una modificación automática del costo de todos los pares. Si hay varios lotes con costos distintos, la pantalla lo advierte. Por ejemplo, para la sandalia plataforma pedrería negra talla 38, el stock consultado era 17 pares y el siguiente costo FIFO registrado era S/ 59,43 (estimado en la carga de pruebas). Un conteo físico de 20 genera **+3 pares**; el costo nuevo, por ejemplo S/ 50, se aplicará solo a esos 3 pares y los 17 existentes conservarán sus costos. «Usar» copia el costo de referencia al campo nuevo para evitar teclearlo, pero puede corregirse. Si el conteo baja, el sistema consume los lotes más antiguos y no pide costo nuevo. Con el mismo conteo, un costo opcional solo valora lotes legados aún sin costo.

### Revisión gerencial y situación para puesta en uso — 21/09/2026

El dashboard anterior consultaba `movimientos_stock_producto`, una tabla heredada que ya no existe. Se sustituyó por un panel de la tienda activa basado en `venta`, pagos, `venta_detalle_producto`, `stock_variante`, lotes FIFO y el reporte de utilidad. El periodo gobierna ventas, cobros, ganancia, gráficos y comprobantes pendientes; las tarjetas de stock muestran el saldo actual. El dashboard es un resumen; el reporte de utilidad conserva el detalle por línea y los filtros de análisis.

Las dos líneas antiguas de la venta #19 (calzado talla 37 negro y nude, 3 pares) no tenían ingreso de compra ni variante vigente. Por autorización del usuario se asignó un costo **estimado** de S/ 27,30 por par, equivalente al 70 % del precio histórico de venta de S/ 39. Se guardaron lotes históricos consumidos para conservar la trazabilidad de esa asignación y un respaldo puntual en `bd/respaldo_costos_venta_19_20260921_200133.json`. El script de una sola aplicación es `bd/costear_venta_19_20260921.php`; no volver a ejecutarlo. La estimación tampoco prueba el costo real de proveedor de esas tres unidades.

Comprobaciones realizadas: 36 verificaciones del flujo de ventas en base aislada (variante, FIFO, descuento y restitución de stock, modos con/sin stock, pagos parciales y completos, reintentos y bloqueo de emisión con deuda). En la base activa, 51 ventas vigentes suman S/ 11.447,10, completamente pagados; 101 líneas tienen costo asignado y cero quedan sin costear. Los pagos coinciden con la cabecera, no hay stock negativo y lotes FIFO concilian con `stock_variante`. La ganancia bruta estimada del periodo es S/ 3.434,13, con costo S/ 8.012,97 y margen 30 %.

**No considerar todavía el sistema apto para operación fiscal plena.** Hay 30 ventas pagadas con comprobante seleccionado y sin emisión registrada. No se ejecutó una prueba real de emisión, aceptación, PDF/XML/CDR ni recuperación ante errores de la central. Varias ventas y costos son datos de prueba; antes de operar con cifras reales, confirmar precios de compra, revisar esas 30 ventas y ejecutar una venta de punta a punta en el entorno de prueba de facturación. Las ventas históricas con variantes eliminadas también requieren una política explícita para anulación/devolución, porque no se puede reponer stock de una variante que ya no existe.

### Movimientos en el panel gerencial

`Reportes → Movimientos` consulta la tabla `movimiento` del local activo y permite filtrar por periodo, tipo (`INGRESO` o `EGRESO`) y estado de pago (`pagado`, `parcial` o `pendiente`). El reporte separa los importes de ingresos y egresos, muestra pagos aplicados, saldos pendientes, evolución diaria, distribución por tipo, conceptos principales y el detalle consultable. Cada fila abre el movimiento correspondiente en Finanzas → Movimientos.

Los pagos parciales se obtienen de `movimiento_pago`; los conceptos y sus importes proceden de `movimiento_detalle` y `tipo_movimiento`. El reporte usa exclusivamente el local seleccionado en sesión. Los importes representan operaciones y pagos registrados: no equivalen por sí solos a utilidad contable ni a saldo bancario.

### Identidad comercial e imágenes en cotizaciones — 21/09/2026

Configuración incorpora «Identidad comercial» con un nombre editable, inicializado como `INTELSOFT`, separado de la razón social y RUC de la empresa emisora. La migración `bd/cotizacion_identidad_comercial_20260921.sql` añade el campo comercial y una copia local de la identidad fiscal, para generar cotizaciones aun si la central está desconectada. El PDF muestra iFact, nombre comercial, razón social y RUC en lugar del nombre del punto de venta. Las cotizaciones nuevas guardan la ruta de imagen de la variante si existe, o la del producto; el PDF también consulta esas rutas actuales como respaldo para cotizaciones anteriores y convierte WebP a PNG para Dompdf. Si no hay imagen válida, conserva «Sin imagen».

## 9. Alcance vigente de empresa, tienda y sedes — 24/09/2026

### Decisión operativa actual

La versión actual debe tratarse como **una empresa, un RUC, una instalación/base de datos y una sola tienda operativa**. El sistema no está terminado ni validado para administrar varias sedes en producción. Que algunas tablas y consultas reconozcan `alm_id` no convierte al conjunto en una solución multisede.

Los textos del dashboard «Compras · toda la empresa» y «el balance abarca todas las tiendas de la empresa» describen el alcance técnico de esas consultas: compras y pagos de compras se leen sin filtro de tienda, y el total de cobros suma pagos de ventas de todos los `alm_id`. **No significan que exista administración completa de locales.** Mientras solo haya un punto de venta activo, «toda la empresa» coincide en la práctica con ese único local. Si se habilita otro punto de venta sin completar el diseño, el panel mezclará datos globales con ventas, stock y utilidad de la tienda en sesión.

### Evidencia en la base activa y el código

Auditoría de solo lectura realizada el 24/09/2026 sobre la base activa `ifact` y contrastada con `bd/ifact_pro_21_09_26_3.sql`:

| Área | Situación actual | Consecuencia |
| --- | --- | --- |
| Empresa emisora | `configuracion_sistema` usa una sola fila (`csi_id=1`) para nombre comercial, razón social, RUC, formato y modo de stock. Las credenciales de facturación también son globales en `.env`. | Existe una empresa/RUC por instalación; no hay aislamiento multiempresa. |
| Locales/almacenes | `almacen` tiene tres filas, pero solo `PV 01` está marcado como punto de venta. La tabla solo guarda nombre, tipo, estado y si es punto de venta. | No guarda dirección, ubigeo, código de establecimiento, datos comerciales, configuración fiscal ni series por sede. |
| Inicio de sesión | El login toma automáticamente el primer almacén activo marcado como punto de venta y lo envía en un campo oculto. `usuario` no tiene `alm_id` ni existe una tabla usuario–local. | No se puede seleccionar sede ni restringir formalmente a un usuario a una o varias sedes. |
| Ventas y cotizaciones | `venta` y `cotizacion` guardan `alm_id`; modelos y reportes principales filtran por la tienda de la sesión. | Esta parte sí tiene separación básica por tienda. En la base activa las 51 ventas y las 2 cotizaciones pertenecen a `PV 01`. |
| Inventario y costo FIFO | Stock, lotes, movimientos, ingresos y ajustes guardan `alm_id` y los flujos activos lo filtran. | Hay una base útil para existencias por ubicación. Las 69 variantes y 1.501 unidades activas están en `PV 01`; aún no hay transferencias entre almacenes. |
| Compras y cuentas por pagar | `compra`, `compra_pago` y `compra_documento` no tienen `alm_id`. | Las compras son globales por diseño actual; no se puede atribuir una compra, gasto o pago a una sede. |
| Caja y tesorería | No existen entidades de caja, terminal, turno/apertura/cierre o sesión de caja. `cuenta_bancaria`, `venta_pago` e `ingreso_egreso` tampoco identifican local; el local de un cobro solo se infiere mediante su venta. | No se puede obtener arqueo, saldo ni responsabilidad de caja por sede o terminal. |
| Clientes, proveedores y catálogo | `entidad`, `producto`, variantes, categorías, cuentas bancarias, empleados, roles y permisos son globales. | Esto puede ser correcto para una sola empresa con maestros compartidos, pero faltan reglas de visibilidad/asignación por local donde correspondan. |
| Comprobantes y series | La venta conserva la serie recibida y `facturacion` guarda RUC, serie y correlativo. La emisión consulta una configuración central por RUC; no envía ni configura un local del POS. | No hay asignación de serie por sede/caja ni protección local contra usar la serie equivocada. La central sigue siendo autoridad de correlativos, pero hoy la selección no está contextualizada por local. |
| Integridad de relaciones de tienda | Varias tablas contienen `alm_id`, pero la base activa no declara claves foráneas hacia `almacen`; `almacen` además continúa en MyISAM. | La pertenencia se valida principalmente en el código y no queda garantizada completamente por la base de datos. |

La pantalla actual de Configuración no debe mostrar «la o las tiendas» mientras el producto mantenga el alcance de una sola tienda operativa. Su nombre comercial identifica al negocio/empresa en los documentos; no representa una sede. Cuando se implemente multisede, Configuración necesitará dos niveles claramente separados: **Empresa** (RUC, razón social, nombre comercial y reglas comunes) y **Locales** (identidad operativa y fiscal de cada establecimiento).

### Qué falta para declarar soporte multisede

Antes de habilitar un segundo punto de venta se debe diseñar, migrar y probar como mínimo:

1. Un mantenedor de locales con empresa/RUC propietario, nombre, dirección, ubigeo, código de establecimiento, zona horaria/moneda si aplican, estado y almacén asociado.
2. Asignación de usuarios y permisos a uno o varios locales, selección segura de local al iniciar/cambiar sesión y validación del local en cada operación.
3. Cajas o terminales por local, aperturas/cierres, turnos, responsables, movimientos y conciliación por caja.
4. Series de boleta, factura y notas asignadas por local o caja, manteniendo a la central como autoridad del correlativo y evitando duplicidades o cruces de sede.
5. `alm_id` o un `local_id` bien definido en compras, pagos de compra, ingresos/egresos y demás operaciones que deban analizarse por sede; definir también cuáles registros seguirán siendo corporativos.
6. Transferencias de inventario entre ubicaciones con origen, destino, estados, recepción, kardex y tratamiento del costo FIFO.
7. Reportes con selector y permisos para «local actual», «local específico» o «toda la empresa», sin mezclar silenciosamente indicadores locales y globales.
8. Claves foráneas e índices consistentes para la ubicación, migrando `almacen` a InnoDB y definiendo una estrategia para los datos históricos antes de imponer restricciones.

### Regla para continuar el desarrollo

Hasta que exista una decisión expresa y se complete la migración anterior:

- entregar una instalación/base independiente por empresa o cliente;
- mantener un solo almacén marcado como punto de venta;
- interpretar empresa, negocio y tienda como el mismo alcance operativo en esta versión;
- no crear una segunda sede editando directamente `almacen` ni reutilizar la misma instalación para locales reales;
- conservar la arquitectura central de facturación para un RUC, pero no afirmar que las series ya están resueltas por local;
- tratar la futura versión multisede como una ampliación planificada, no como una capacidad oculta del sistema actual.


## 10. Arquitectura operativa vigente — 25/09/2026

La aplicación trabaja con **local** como única dimensión operativa. Cada local representa también su ubicación de existencias; por ello, ventas, cotizaciones, ingresos de stock, ajustes, FIFO, reportes, sesiones y tokens persistentes utilizan exclusivamente `loc_id`. La tabla `almacen`, las columnas `alm_id` y las variables de sesión asociadas fueron retiradas.

No existe apertura, cierre ni turno de caja. Los pagos no dependen de una caja abierta: se registran por fecha, medio de pago, cuenta, monto y número de operación. Esto permite operar con efectivo, bancos y billeteras sin imponer un arqueo físico. Si en el futuro un negocio necesita control de efectivo por cajero, deberá incorporarse como un módulo opcional y explícito, sin condicionar los movimientos financieros actuales.

## 11. Movimientos financieros unificados

`view/list_movimientos.php` es el único listado de ingresos y egresos. La base usa:

- `movimiento`: cabecera común, tipo `INGRESO` o `EGRESO`, local, fecha, entidad, comprobante, importes y auditoría.
- `movimiento_detalle`: conceptos detallados de los egresos.
- `movimiento_pago`: aplicaciones de dinero separadas de la cabecera, permitiendo pagos parciales o combinados.
- `movimiento_documento`: comprobantes y sustentos adjuntos.

Las estructuras heredadas `compra*` y `movimiento_ingreso` fueron migradas y eliminadas. Los 31 egresos, 31 detalles, 22 pagos y 4 documentos existentes se conservaron en las nuevas tablas.

El ingreso solicita fecha, monto, tipo, medio de pago y cuenta de destino. El egreso conserva proveedor, comprobante, conceptos, documentos y pagos parciales. Ambos quedan asociados al `loc_id` de la sesión y el controlador impide abrir o modificar movimientos de otro local.

## 12. Migración y respaldo

La migración aplicada es `database/migrations/20260925_unificar_locales_y_movimientos.sql`. Antes de ejecutarla se generó el respaldo `database/backups/ifact_before_local_movimientos_20260925.sql`.

La migración:

- consolida las tablas financieras bajo `movimiento*`;
- convierte todas las relaciones operativas de `alm_id` a `loc_id`;
- elimina `almacen`, `caja_turno` y `caja_movimiento`;
- elimina `venta_pago.ctu_id`;
- retira la página y permisos de apertura/cierre;
- conserva los permisos de `list_movimientos.php`.

La verificación posterior confirma que no quedan tablas heredadas ni columnas `alm_id`, y que ventas, stock, inventario, dashboard y movimientos consultan correctamente por local.
