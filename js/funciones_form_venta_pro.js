var detalle       = [];
var insumos       = {};
var productos     = {};
var insumosOrden  = [];  // ins_id en el orden alfabético que devuelve el backend
var productosOrden = []; // pro_id en el orden alfabético que devuelve el backend
var tipo_venta = 2; // Venta de variantes del catálogo
var docs_data  = {};
var clientes   = [];  // todos los clientes con tipo_doc
var ventaMetodosPago = [];
var ventaCuentasPago = [];
var ventaFinalizada = false;
var ventaEnRegistro = false;
var ventaDocumentoEmisionId = 0;
var ventaSelectorClienteEmision = false;
var ventaRegistroClienteDesdeEmision = false;
var ventaRegistroClienteDesdeSelector = false;
var ventaClienteGuardadoEnModal = false;
var ventaRegistradaId = 0;
var ventaNotaRegistrada = '';
var ventaDecisionTomada = false;

function ReiniciarNuevaVentaPro() {
    // La venta ya quedó persistida. Recargar esta misma vista limpia carrito,
    // cliente, forma de pago y vuelve a consultar el stock actualizado.
    window.location.reload();
}

function EstablecerBotonesConfirmacionVenta(procesando) {
    var botones = $("#btn_confirmar_venta_superior, #btn_confirmar_venta_inferior");
    botones.prop("disabled", !!procesando);
    botones.find("i").attr("class", procesando ? "fa fa-spinner fa-spin me-1" : "fa fa-check me-1");
}

$(document).ready(function () {
    document.getElementById("offcanvasComprobante").addEventListener("hidden.bs.offcanvas", function () {
        if (ventaFinalizada) window.location.reload();
    });
    CargarProductos();
    CargarTiposPago();
    CargarCuentas();
    CargarClientes();
    CargarDocumentos();
    $("#buscar_cliente_venta").on("input", RenderSelectorClientesVenta);
});

// ===================== CARGAR INSUMOS =====================
function CargarInsumos() {
    $.get("../controller/venta.php", { task: 3, rand: Math.random() }, function (datos) {
        insumos = {};
        insumosOrden = [];
        $.each(datos, function (i, d) { insumos[d.ins_id] = d; insumosOrden.push(d.ins_id); });
        if (tipo_venta === 1) PoblarSelectItems();
    }, "json");
}

// ===================== CARGAR PRODUCTOS =====================
function CargarProductos() {
    catalogoEstado='cargando';
    RenderProductosPanel();
    $.get("../controller/venta.php", { task: 9, rand: Math.random() }, function (datos) {
        if(!Array.isArray(datos)){catalogoEstado='error';RenderProductosPanel();return;}
        catalogoEstado='listo';
        productos = {};
        productosOrden = [];
        $.each(datos, function (i, d) { productos[d.pva_id] = d; productosOrden.push(d.pva_id); });
        if (tipo_venta === 2) PoblarSelectItems();
    }, "json").fail(function(){catalogoEstado='error';RenderProductosPanel();});
}

// ===================== POBLAR SELECT SEGÚN TIPO =====================
function PoblarSelectItems() {
    $("#ven_ins_id").off("change.ventaItem").on("change.ventaItem", OnCambiarItem);
    RenderProductosPanel();
    return;
    var sel        = $("#ven_ins_id");
    var esProducto = (tipo_venta === 2);
    var fuente     = esProducto ? productos : insumos;
    // Los objetos "insumos"/"productos" están indexados por id numérico: en JS, las
    // llaves que parecen enteros siempre se recorren en orden numérico ascendente,
    // sin importar el orden de inserción — por eso NO se puede iterar "fuente"
    // directamente para pintar el combo (rompía el orden alfabético que ya manda
    // el backend). Se recorre en cambio el arreglo "orden", que sí respeta el
    // orden original de la respuesta del servidor.
    var orden      = esProducto ? productosOrden : insumosOrden;
    var etiqueta   = esProducto ? "producto" : "insumo";

    if ($.fn.select2 && sel.hasClass("select2-hidden-accessible")) sel.select2("destroy");

    sel.empty().append('<option value="0">Seleccione un ' + etiqueta + '...</option>');
    $.each(orden, function (_i, id) {
        var d = fuente[id];
        var nombre = esProducto ? d.pro_nombre : d.ins_nombre;
        var stock = Math.max(0, parseFloat(esProducto ? d.pro_stock : d.ins_stock) || 0);
        sel.append(
            $('<option>', {
                value: id,
                text: nombre + (esProducto ? ' · SKU: ' + d.pva_sku + (d.pva_codigo_barras ? ' · ' + d.pva_codigo_barras : '') : '') + ' (' + d.und_abrev + ')'
            }).attr('data-stock', stock.toFixed(3))
              .attr('data-unidad', d.und_abrev || '')
        );
    });

    if ($.fn.select2) {
        sel.select2({
            placeholder: "Buscar " + etiqueta + "...",
            templateResult: FormatearOpcionItem,
            templateSelection: function (opcion) { return opcion.text; }
        });
    }
    // Namespace propio: un .off("change") a secas borraría también el listener
    // interno de select2 que redibuja el texto seleccionado en la cajita.
    sel.off("change.ventaItem").on("change.ventaItem", OnCambiarItem);
}

// Muestra la disponibilidad dentro de cada resultado del buscador sin alterar
// el texto por el que Select2 filtra. Los agotados permanecen visibles para que
// el usuario sepa que el artículo existe, pero se distinguen inmediatamente.
function FormatearOpcionItem(opcion) {
    if (!opcion.id) return opcion.text;

    var stock = Math.max(0, parseFloat($(opcion.element).attr('data-stock')) || 0);
    var unidad = $(opcion.element).attr('data-unidad') || '';
    var agotado = stock <= 0;
    var estado = agotado ? 'Agotado' : stock.toFixed(3) + (unidad ? ' ' + unidad : '');

    var fila = $('<div>').addClass('venta-opcion-item');
    if (agotado) fila.addClass('sin-stock');
    fila.append($('<span>').addClass('venta-opcion-nombre').text(opcion.text));
    fila.append($('<span>').addClass('venta-opcion-stock').text(estado));
    return fila;
}

// ===================== SWITCH TIPO INSUMO / PRODUCTO =====================
function CambiarTipoSwitch() {
    if (detalle.length > 0) {
        $("#switch_tipo").prop("checked", tipo_venta === 2);
        toastr.warning("No se puede cambiar de tipo con ítems en la lista. Elimínelos primero.");
        return;
    }

    tipo_venta = $("#switch_tipo").is(":checked") ? 2 : 1;
    $("input[name='ven_tipo'][value='" + tipo_venta + "']").prop("checked", true);

    var esProducto = (tipo_venta === 2);
    $("#label_ins_prod").text(esProducto ? "Producto" : "Insumo");
    $("#label_ins_prod_titulo").text(esProducto ? "Productos" : "Insumos");
    $("#th_item").text(esProducto ? "Producto" : "Insumo");

    PoblarSelectItems();
    $("#ven_cantidad, #ven_precio_unit").val("");
    ActualizarTotalItem();
    $("#ven_stock").val("—").removeClass("input-ok input-warning input-danger");
}

// ===================== CARGAR CLIENTES =====================
function CargarClientes() {
    $.get("../controller/entidad_cliente.php", { task: 2, rand: Math.random() }, function (datos) {
        clientes = datos;
        RenderClientes(datos);
    }, "json");
}

function RenderClientes(lista, clienteSeleccionado) {
    var sel = $("#ven_cli_id");
    var seleccionPrevia = clienteSeleccionado !== undefined
        ? String(clienteSeleccionado || "0")
        : String(sel.val() || "0");
    sel.empty().append('<option value="0">Cliente general / sin registro</option>');
    $.each(lista, function (i, d) {
        var label = d.ent_nombres + (d.ent_nro_doc ? ' · ' + d.ent_nro_doc : '');
        sel.append('<option value="' + d.ent_id + '" data-tipo="' + d.ent_tipo_doc + '">' + label + '</option>');
    });
    if (sel.find('option[value="' + seleccionPrevia + '"]').length) {
        sel.val(seleccionPrevia);
    } else {
        sel.val("0");
    }
    ActualizarClienteSeleccionado();
    RenderSelectorClientesVenta();
}

function ActualizarClienteSeleccionado() {
    var opcion = $("#ven_cli_id option:selected");
    $("#cliente_seleccionado").text(opcion.length ? opcion.text() : "Cliente general / sin registro");
}

function AbrirSelectorClienteVenta() {
    $("#buscar_cliente_venta").val("");
    RenderSelectorClientesVenta();
    var modal = $("#selectorClienteVenta");
    modal.off("shown.bs.modal.ventaCliente").one("shown.bs.modal.ventaCliente", function () {
        var buscador = document.getElementById("buscar_cliente_venta");
        if (buscador) {
            buscador.focus({preventScroll:true});
            buscador.setSelectionRange(buscador.value.length,buscador.value.length);
        }
    });
    bootstrap.Modal.getOrCreateInstance(modal[0]).show();
}

function RenderSelectorClientesVenta() {
    var buscar = String($("#buscar_cliente_venta").val() || "").trim().toLocaleLowerCase("es");
    var permitidos = new Set($("#ven_cli_id option").map(function(){ return String(this.value); }).get());
    var actual = String($("#ven_cli_id").val() || "0");
    var lista = clientes.filter(function(c){
        if (!ventaSelectorClienteEmision && !permitidos.has(String(c.ent_id))) return false;
        return !buscar || [c.ent_nombres,c.ent_nro_doc].join(" ").toLocaleLowerCase("es").includes(buscar);
    });
    var general = !buscar || "cliente general sin registro".includes(buscar)
        ? '<button type="button" class="venta-selector-opcion'+(actual==='0'?' activo':'')+'" onclick="SeleccionarClienteVenta(0)"><strong>Cliente general / sin registro</strong><span>Venta sin cliente identificado</span></button>' : '';
    $("#clientes_venta_lista").html(general + lista.map(function(c){
        return '<button type="button" class="venta-selector-opcion'+(actual===String(c.ent_id)?' activo':'')+'" onclick="SeleccionarClienteVenta('+Number(c.ent_id)+')"><strong>'+EscaparVenta(c.ent_nombres)+'</strong><span>'+EscaparVenta(c.ent_nro_doc||'Sin documento')+'</span></button>';
    }).join('') || '<div class="venta-selector-vacio">No se encontraron clientes.</div>');
}

function SeleccionarClienteVenta(id) {
    if (ventaSelectorClienteEmision) RenderClientes(clientes, id);
    else $("#ven_cli_id").val(String(id));
    ActualizarClienteSeleccionado();
    bootstrap.Modal.getOrCreateInstance(document.getElementById("selectorClienteVenta")).hide();
    if (ventaSelectorClienteEmision) {
        ActualizarClienteEmisionPro();
        ventaSelectorClienteEmision = false;
    }
}

function EscaparVenta(valor) { return $("<div>").text(valor == null ? "" : String(valor)).html(); }

function AbrirModalClienteVenta(desdeEmision) {
    ventaRegistroClienteDesdeEmision = desdeEmision === true || !!document.querySelector('.venta-emision-modal');
    ventaRegistroClienteDesdeSelector = $('#selectorClienteVenta').hasClass('show');
    ventaClienteGuardadoEnModal = false;
    $("#venta_cli_tipo_doc").val("1");
    $("#venta_cli_nro_doc, #venta_cli_nombres, #venta_cli_direccion, #venta_ent_marca").val("");
    $('#venta_cli_consulta_estado').removeClass('error exito').text('');
    var abrir = function () {
        var modal = $('#modalClienteVenta');
        modal.off('shown.bs.modal.ventaNuevoCliente').one('shown.bs.modal.ventaNuevoCliente', function () {
            $('.modal-backdrop').last().addClass('venta-nuevo-cliente-backdrop');
            $('#venta_cli_nro_doc').trigger('focus');
        }).off('hidden.bs.modal.ventaNuevoCliente').one('hidden.bs.modal.ventaNuevoCliente', function () {
            if (!ventaClienteGuardadoEnModal && ventaRegistroClienteDesdeSelector) AbrirSelectorClienteEmisionPro();
            ventaRegistroClienteDesdeSelector = false;
        });
        modal.modal('show');
    };
    if (ventaRegistroClienteDesdeSelector) {
        $('#selectorClienteVenta').one('hidden.bs.modal.ventaAbrirRegistro', abrir);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('selectorClienteVenta')).hide();
    } else abrir();
}

function AbrirNuevoClienteEmisionPro() { AbrirModalClienteVenta(true); }

function MostrarValidacionClienteVenta(mensaje, selector) {
    $('#venta_validacion_cliente_mensaje').text(mensaje);
    var modal = $('#modalValidacionClienteVenta');
    modal.off('shown.bs.modal.ventaValidacion').one('shown.bs.modal.ventaValidacion', function () {
        $('.modal-backdrop').last().addClass('venta-validacion-cliente-backdrop');
    }).off('hidden.bs.modal.ventaValidacion').one('hidden.bs.modal.ventaValidacion', function () {
        if ($('#modalClienteVenta').hasClass('show')) document.body.classList.add('modal-open');
        if (selector) setTimeout(function () { $(selector).trigger('focus'); }, 80);
    });
    bootstrap.Modal.getOrCreateInstance(modal[0]).show();
}

function ConsultarDocumentoClienteVenta() {
    var tipo = parseInt($("#venta_cli_tipo_doc").val(), 10);
    var numero = $.trim($("#venta_cli_nro_doc").val());
    var esDni = tipo === 1;
    var esRuc = tipo === 6;

    if (!esDni && !esRuc) return MostrarValidacionClienteVenta('Seleccione DNI o RUC.', '#venta_cli_tipo_doc');
    if (!(esDni ? /^[0-9]{8}$/.test(numero) : /^[0-9]{11}$/.test(numero))) {
        return MostrarValidacionClienteVenta(esDni ? 'El DNI debe tener 8 dígitos.' : 'El RUC debe tener 11 dígitos.', '#venta_cli_nro_doc');
    }

    var boton = $('#btnConsultarDocumentoClienteVenta');
    var estado = $('#venta_cli_consulta_estado');
    boton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
    estado.removeClass('error exito').text('Consultando ' + (esDni ? 'RENIEC' : 'SUNAT') + '…');
    $.ajax({
        url: "../controller/consultas_api.php",
        type: "GET",
        dataType: "json",
        data: esDni ? { task: 7, dni: numero } : { task: 8, ruc: numero },
        success: function (respuesta) {
            var datos = Array.isArray(respuesta) ? respuesta[0] : respuesta;
            if (!(datos && (datos.success == 1 || datos.success === true))) {
                var mensaje = (datos && (datos.message || datos.error)) || 'No se encontraron datos';
                estado.addClass('error').text(mensaje);
                return MostrarValidacionClienteVenta(mensaje, '#venta_cli_nro_doc');
            }
            if (esDni) {
                $("#venta_cli_nombres").val([datos.nombres, datos.ape_paterno, datos.ape_materno].filter(Boolean).join(" ").trim());
            } else {
                $("#venta_cli_nombres").val($.trim(datos.razon_social || ""));
                if (datos.direccion) $("#venta_cli_direccion").val($.trim(datos.direccion));
            }
            estado.addClass('exito').text('Datos encontrados correctamente.');
        },
        error: function () {
            estado.addClass('error').text('No se pudo realizar la consulta. Intente nuevamente.');
            MostrarValidacionClienteVenta('No se pudo realizar la consulta. Intente nuevamente.', '#venta_cli_nro_doc');
        },
        complete: function () {
            boton.prop('disabled', false).html('<i class="fa fa-search"></i>');
        }
    });
}

function GuardarClienteVenta() {
    var tipo = parseInt($('#venta_cli_tipo_doc').val(), 10);
    var documento = $.trim($('#venta_cli_nro_doc').val());
    var nombres = $.trim($("#venta_cli_nombres").val());
    var direccion = $.trim($('#venta_cli_direccion').val());
    var marca = $.trim($("#venta_ent_marca").val());
    if (![1, 6].includes(tipo)) return MostrarValidacionClienteVenta('Seleccione DNI o RUC.', '#venta_cli_tipo_doc');
    if (!(tipo === 1 ? /^\d{8}$/.test(documento) : /^\d{11}$/.test(documento))) {
        return MostrarValidacionClienteVenta(tipo === 1 ? 'El DNI debe tener 8 dígitos.' : 'El RUC debe tener 11 dígitos.', '#venta_cli_nro_doc');
    }
    if (!nombres) {
        return MostrarValidacionClienteVenta('Los nombres o la razón social son obligatorios.', '#venta_cli_nombres');
    }
    if (!direccion) {
        return MostrarValidacionClienteVenta('La dirección es obligatoria.', '#venta_cli_direccion');
    }
    if (marca.length < 3) {
        return MostrarValidacionClienteVenta('La marca es obligatoria y debe tener al menos 3 caracteres.', '#venta_ent_marca');
    }

    var boton = $("#btnGuardarClienteVenta").prop("disabled", true);
    $.ajax({
        url: "../controller/entidad_cliente.php",
        type: "POST",
        dataType: "json",
        data: {
            task: 4,
            registro_rapido: 1,
            ent_tipo_doc: $("#venta_cli_tipo_doc").val(),
            ent_nro_doc: documento,
            ent_nombres: nombres,
            ent_contacto: '',
            ent_direccion: direccion,
            ent_email: '',
            ent_marca: marca,
            rand: Math.random()
        },
        success: function (res) {
            if (res.estado !== "ok" || !parseInt(res.ent_id, 10)) {
                $('#venta_cli_consulta_estado').addClass('error').text(res.mensaje || 'No se pudo registrar el cliente');
                return MostrarValidacionClienteVenta(res.mensaje || 'No se pudo registrar el cliente.', '#venta_cli_nro_doc');
            }
            var clienteId = parseInt(res.ent_id, 10);
            ventaClienteGuardadoEnModal = true;
            $("#modalClienteVenta").modal("hide");
            $.get("../controller/entidad_cliente.php", { task: 2, rand: Math.random() }, function (datos) {
                clientes = datos;
                RenderClientes(datos, clienteId);
                if (ventaRegistroClienteDesdeEmision) {
                    ventaSelectorClienteEmision = false;
                    ActualizarClienteEmisionPro();
                }
                ventaRegistroClienteDesdeEmision = false;
                ventaRegistroClienteDesdeSelector = false;
            }, "json");
        },
        error: function () {
            $('#venta_cli_consulta_estado').addClass('error').text('No se pudo conectar con el servidor.');
            MostrarValidacionClienteVenta('No se pudo conectar con el servidor. Intente nuevamente.', null);
        },
        complete: function () { boton.prop("disabled", false); }
    });
}

// ===================== CARGAR TIPOS DE PAGO =====================
function CargarTiposPago() {
    $.get("../controller/venta.php", { task: 5, rand: Math.random() }, function (datos) {
        ventaMetodosPago = Array.isArray(datos) ? datos : [];
        var sel = $("#ven_tpa_id");
        sel.empty().append('<option value="0">Seleccione método de pago</option>');
        $.each(datos, function (i, d) {
            sel.append('<option value="' + d.tpa_id + '">' + d.tpa_nombre + '</option>');
        });
        RenderMetodosPagoVenta();
        ActualizarPagoVenta();
    }, "json");
}

// ===================== CARGAR CUENTAS =====================
function CargarCuentas() {
    $.get("../controller/cuenta_bancaria.php", { action: 1, rand: Math.random() }, function (res) {
        ventaCuentasPago = res && res.status === 1 && Array.isArray(res.data) ? res.data.filter(function(d){return parseInt(d.cba_estado)===1;}) : [];
        ActualizarPagoVenta();
    }, "json");
}

function NombreMetodoPagoVenta(nombre) {
    return String(nombre || '').trim().toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function IconoMetodoPagoVenta(nombre) {
    var normalizado = NombreMetodoPagoVenta(nombre);
    if (normalizado.indexOf('YAPE') !== -1) return 'fa-mobile-alt';
    if (normalizado.indexOf('PLIN') !== -1) return 'fa-qrcode';
    if (normalizado.indexOf('TRANSF') !== -1) return 'fa-exchange-alt';
    if (normalizado.indexOf('EFECTIVO') !== -1) return 'fa-money-bill-wave';
    return 'fa-credit-card';
}

function OrdenMetodoPagoVenta(nombre) {
    var normalizado = NombreMetodoPagoVenta(nombre);
    if (normalizado.indexOf('YAPE') !== -1) return 1;
    if (normalizado.indexOf('PLIN') !== -1) return 2;
    if (normalizado.indexOf('TRANSF') !== -1) return 3;
    if (normalizado.indexOf('EFECTIVO') !== -1) return 4;
    return 99;
}

function RenderMetodosPagoVenta() {
    var actual = Number($('#ven_tpa_id').val()) || 0;
    var metodos = ventaMetodosPago.slice().sort(function (a, b) {
        return OrdenMetodoPagoVenta(a.tpa_nombre) - OrdenMetodoPagoVenta(b.tpa_nombre);
    });
    $('#venta_metodos_botones').html(metodos.map(function (metodo) {
        var activo = Number(metodo.tpa_id) === actual;
        return '<button type="button" class="venta-metodo-boton' + (activo ? ' activo' : '') + '" onclick="SeleccionarMetodoPagoVenta(' + Number(metodo.tpa_id) + ')" aria-pressed="' + (activo ? 'true' : 'false') + '"><span><i class="fa ' + IconoMetodoPagoVenta(metodo.tpa_nombre) + '"></i></span><strong>' + EscaparVenta(metodo.tpa_nombre) + '</strong></button>';
    }).join('') || '<div class="venta-metodos-vacio">No hay formas de pago habilitadas.</div>');
}

function EsTransferenciaVenta(tpaId) {
    var metodo = ventaMetodosPago.find(function (item) { return Number(item.tpa_id) === Number(tpaId); });
    return metodo && NombreMetodoPagoVenta(metodo.tpa_nombre).indexOf('TRANSF') !== -1;
}

function EtiquetaCuentaPagoVenta(cuenta) {
    // En venta importa identificar el destino y su propietario; el número de
    // cuenta se conserva en la configuración, pero no se expone en esta vista.
    return [cuenta.cba_banco, cuenta.cba_moneda, cuenta.cba_propietario]
        .filter(Boolean)
        .join(' - ');
}

function ActualizarPagoVenta() {
    var metodoId = Number($('#ven_tpa_id').val()) || 0;
    var cuentaActual = Number($('#ven_cba_id').val()) || 0;
    var cuentas = CuentasMetodoVentaPro(metodoId);
    var transferencia = EsTransferenciaVenta(metodoId);
    var selector = $('#ven_cba_id');

    RenderMetodosPagoVenta();
    selector.empty();
    $('#venta_cuenta_wrap').removeClass('cuenta-seleccionada');
    if (!metodoId) {
        selector.append('<option value="0">Seleccione primero el método</option>').prop('disabled', true);
        $('#venta_cuenta_ayuda').text('Elige una forma de pago para ver su cuenta asociada.');
        return;
    }
    if (!cuentas.length) {
        selector.append('<option value="0">Sin cuenta asociada</option>').prop('disabled', true);
        $('#venta_cuenta_ayuda').text('Este método todavía no tiene una cuenta habilitada.');
        return;
    }

    if (transferencia) selector.append('<option value="0">Seleccione cuenta</option>');
    cuentas.forEach(function (cuenta) {
        var etiqueta = EtiquetaCuentaPagoVenta(cuenta);
        selector.append('<option value="' + Number(cuenta.cba_id) + '">' + EscaparVenta(etiqueta) + '</option>');
    });

    if (transferencia) {
        var conservaCuenta = cuentas.some(function (cuenta) { return Number(cuenta.cba_id) === cuentaActual; });
        selector.prop('disabled', false).val(conservaCuenta ? String(cuentaActual) : '0');
        $('#venta_cuenta_ayuda').text('Selecciona la cuenta donde se recibirá la transferencia.');
    } else {
        selector.val(String(cuentas[0].cba_id)).prop('disabled', true);
        $('#venta_cuenta_ayuda').text('Cuenta asociada seleccionada automáticamente.');
        $('#venta_cuenta_wrap').addClass('cuenta-seleccionada');
    }
}

function SeleccionarMetodoPagoVenta(id) {
    $('#ven_tpa_id').val(String(id));
    $('#ven_cba_id').val('0');
    ActualizarPagoVenta();
}

function ActualizarCuentaPagoVenta() {
    $('#venta_cuenta_wrap').toggleClass('cuenta-seleccionada', Number($('#ven_cba_id').val()) > 0);
}

function CuentasMetodoVentaPro(tpaId) {
    var metodoId = Number(tpaId || $('#ven_tpa_id').val() || 0);
    return ventaCuentasPago.filter(function (cuenta) {
        return Number(cuenta.cba_estado) === 1 && Number(cuenta.tpa_id) === metodoId;
    });
}

// ===================== CARGAR DOCUMENTOS ELECTRONICOS =====================
function CargarDocumentos() {
    $.get("../controller/tipo_doc_elect.php", { task: 8, rand: Math.random() }, function (datos) {
        var sel = $("#ven_tde_id");
        sel.empty().append('<option value="0">Sin comprobante</option>');
        if (!datos || datos.length === 0) return;
        $.each(datos, function (i, d) {
            docs_data[d.tde_id] = d;
            sel.append('<option value="' + d.tde_id + '">' + d.tde_nombre + '</option>');
        });
        sel.on("change", OnCambiarDocumento);
        sel.trigger("change");
    }, "json");
}

// ===================== AL CAMBIAR DOCUMENTO =====================
function OnCambiarDocumento() {
    var tde_id = parseInt($("#ven_tde_id").val()) || 0;
    var clienteSeleccionado = $("#ven_cli_id").val() || "0";
    $("#aviso_cliente_ruc").remove();

    if (tde_id <= 0) {
        $("#comp_serie").text("—").addClass("comp-auto").removeClass("comp-field-text");
        $("#comp_numero").text("Se asigna al confirmar").addClass("comp-auto").removeClass("comp-field-text");
        RenderClientes(clientes, clienteSeleccionado);
        return;
    }

    var doc = docs_data[tde_id];
    if (doc) {
        $("#comp_serie").text("Central").removeClass("comp-auto").addClass("comp-field-text");
        $("#comp_numero").text("Se asigna al emitir").addClass("comp-auto").removeClass("comp-field-text");

        // Factura → solo clientes con RUC (tipo_doc = 6)
        if (doc.tde_codigo === "01") {
            var soloRuc = clientes.filter(function (c) { return parseInt(c.ent_tipo_doc, 10) === 6; });
            RenderClientes(soloRuc, clienteSeleccionado);

            if (soloRuc.length === 0) {
                $("#ven_cli_id").closest(".col-12").append(
                    '<div id="aviso_cliente_ruc" class="text-danger mt-1" style="font-size:.82rem;">' +
                    '<i class="fa fa-exclamation-triangle me-1"></i>' +
                    'No hay clientes con RUC registrados. Regístrelo primero en "Clientes" para poder emitir una Factura.' +
                    '</div>'
                );
            }
        } else {
            RenderClientes(clientes, clienteSeleccionado);
        }
    }
}

// ===================== AL CAMBIAR ÍTEM — actualizar stock y precio =====================
function OnCambiarItem() {
    var id         = parseInt($(this).val());
    var stockEl    = $("#ven_stock");
    var esProducto = (tipo_venta === 2);
    stockEl.val("—").removeClass("input-ok input-warning input-danger");
    $("#ven_precio_unit").val("");
    if (!Number.isInteger(id) || id <= 0) return;

    var item   = esProducto ? productos[id] : insumos[id];
    if (!item) return;
    var precio = item ? parseFloat(esProducto ? item.pro_prec_venta : item.ins_prec_venta) : NaN;
    $("#ven_precio_unit").val(isNaN(precio) ? "" : precio.toFixed(2));
    $("#producto_seleccionado").text(item.pro_nombre);
    $("#venta_unidad").text("Unidad de medida: " + item.und_abrev + " · SKU: " + item.pva_sku);

    var params = { task: esProducto ? 10 : 4, rand: Math.random() };
    if (esProducto) {
        params.pva_id = id;
        $("#ven_cantidad").attr("step", Math.pow(10, -(parseInt(item.und_decimales, 10) || 0))).val(1);
    } else params.ins_id = id;

    $.get("../controller/venta.php", params, function (d) {
        if (String($("#ven_ins_id").val()) !== String(id)) return;
        if (!d) { stockEl.val("0.000"); return; }
        var stock = parseFloat(esProducto ? d.pro_stock : d.ins_stock) || 0;
        stockEl.val(stock.toFixed(3));
        if (stock <= 0)       stockEl.addClass("input-danger");
        else if (stock < 5)   stockEl.addClass("input-warning");
        else                  stockEl.addClass("input-ok");
    }, "json");
}

// ===================== AGREGAR ÍTEM AL CARRITO =====================
function AgregarItem() {
    var id         = parseInt($("#ven_ins_id").val());
    var cantidad   = parseFloat($("#ven_cantidad").val());
    var precio     = parseFloat($("#ven_precio_unit").val());
    var stock      = parseFloat($("#ven_stock").val()) || 0;
    var esProducto = (tipo_venta === 2);
    var etiqueta   = esProducto ? "producto" : "insumo";
    var key        = esProducto ? "pva_id" : "ins_id";

    if (id <= 0) { toastr.warning("Seleccione un " + etiqueta); return; }
    if (isNaN(cantidad) || cantidad <= 0) { toastr.warning("Ingrese una cantidad válida mayor a cero"); $("#ven_cantidad").focus(); return; }
    if (isNaN(precio) || precio < 0)     { toastr.warning("Ingrese un precio válido"); $("#ven_precio_unit").focus(); return; }
    if (!window.ventaSinStock && stock <= 0) { MostrarRestriccionStockVenta(0, cantidad); return; }
    if (!window.ventaSinStock && cantidad > stock) { MostrarRestriccionStockVenta(stock, cantidad); return; }

    if (detalle.findIndex(function (x) { return x[key] === id; }) >= 0) {
        MostrarProductoDuplicadoVenta(etiqueta);
        return;
    }

    var item     = esProducto ? productos[id] : insumos[id];
    if (!item) return;
    if (esProducto && Math.abs(cantidad - Number(cantidad.toFixed(parseInt(item.und_decimales, 10) || 0))) > 0.0000001) {
        toastr.warning("La cantidad debe respetar la unidad " + item.und_abrev); return;
    }
    var nombre   = esProducto ? item.pro_nombre + ' · SKU: ' + item.pva_sku : item.ins_nombre;
    var subtotal = parseFloat((cantidad * precio).toFixed(2));

    var registro = {
        und_med:     item.und_id,
        nombre:      nombre,
        producto:    esProducto ? item.producto_nombre : item.ins_nombre,
        variante:    esProducto && Array.isArray(item.atributos) && item.atributos.length
            ? item.atributos.map(function (atributo) { return atributo.atr_nombre + ': ' + atributo.ava_nombre; }).join(' · ')
            : 'Variante única',
        imagen:      esProducto ? (item.pro_imagen || '') : '',
        sku:         esProducto ? item.pva_sku : '',
        unidad:      item.und_abrev,
        cantidad:    cantidad,
        precio_unit: precio,
        subtotal:    subtotal,
        stock:       stock
    };
    registro[key] = id;
    if (esProducto) registro.pro_id = item.pro_id;
    detalle.push(registro);

    RenderTabla();
    $("#switch_tipo").prop("disabled", true);

    if ($.fn.select2) $("#ven_ins_id").val(null).trigger("change");
    else              $("#ven_ins_id").val(0).trigger("change");
    $("#ven_cantidad, #ven_precio_unit").val("");
    ActualizarTotalItem();
    $("#ven_stock").val("—").removeClass("input-ok input-warning input-danger");
    $("#producto_seleccionado").text("Seleccionar producto y atributos");
    $("#venta_unidad").text("Unidad de medida: —");
}

function MostrarProductoDuplicadoVenta(etiqueta) {
    Swal.fire({
        icon: 'info',
        title: 'Producto ya agregado',
        html: '<div class="venta-alerta-duplicado">Este ' + etiqueta + ' ya se encuentra en el carrito.<span>Modifica la cantidad con los botones <strong>−</strong> y <strong>+</strong>.</span></div>',
        confirmButtonText: 'Entendido',
        buttonsStyling: false,
        customClass: {
            popup: 'venta-duplicado-popup',
            confirmButton: 'venta-duplicado-confirmar'
        }
    });
}

function MostrarRestriccionStockVenta(disponible, solicitado) {
    var sinExistencia = disponible <= 0;
    var detalleStock = sinExistencia
        ? 'La variante seleccionada no tiene existencias disponibles en este local.'
        : 'Solicitaste <strong>' + Number(solicitado).toFixed(3) + '</strong> y solo hay <strong>' + Number(disponible).toFixed(3) + '</strong> disponibles.';
    Swal.fire({
        icon: 'warning',
        title: sinExistencia ? 'Producto sin stock' : 'Stock insuficiente',
        html: '<div class="venta-alerta-stock"><p>' + detalleStock + '</p><div><i class="fa fa-shield-alt"></i><span>La configuración actual exige contar con stock antes de registrar una venta.</span></div><small>Si deseas permitir ventas sin existencias, cambia la opción <strong>Vender sin stock</strong> en Configuración.</small></div>',
        confirmButtonText: 'Entendido',
        denyButtonText: '<i class="fa fa-cog me-1"></i> Ir a configuración',
        showDenyButton: true,
        buttonsStyling: false,
        customClass: {
            popup: 'venta-stock-popup',
            confirmButton: 'btn btn-primary px-4',
            denyButton: 'btn btn-light px-4'
        }
    }).then(function(resultado) {
        if (resultado.isDenied) window.location.href = 'configuracion.php';
    });
}

// ===================== ELIMINAR ÍTEM =====================
function EliminarItem(idx) {
    var item = detalle[idx];
    if (!item) return;
    Swal.fire({
        icon: 'question',
        title: '¿Eliminar producto?',
        text: '¿Estás seguro de eliminar ' + (item.producto || 'este producto') + ' del carrito?',
        showCancelButton: true,
        confirmButtonText: '<i class="fa fa-trash-alt me-1"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: { confirmButton: 'btn btn-danger px-4', cancelButton: 'btn btn-light px-4 me-2' }
    }).then(function (resultado) {
        if (!resultado.isConfirmed) return;
        detalle.splice(idx, 1);
        RenderTabla();
        if (detalle.length === 0) $("#switch_tipo").prop("disabled", false);
    });
}

function CambiarCantidadItemPro(idx, delta) {
    var item = detalle[idx];
    if (!item) return;
    var variante = productos[item.pva_id] || {};
    var decimales = parseInt(variante.und_decimales, 10) || 0;
    var paso = Math.pow(10, -decimales);
    if (delta < 0 && Number(item.cantidad) <= 1) return;
    var nueva = Math.max(1, Number((Number(item.cantidad) + (delta * paso)).toFixed(decimales)));
    if (!window.ventaSinStock && nueva > Number(item.stock || 0)) {
        MostrarRestriccionStockVenta(Number(item.stock || 0), nueva);
        return;
    }
    item.cantidad = nueva;
    item.subtotal = Number((nueva * Number(item.precio_unit)).toFixed(2));
    var fila = $('.venta-pro-carrito-item[data-item-index="' + idx + '"]');
    fila.find('.venta-pro-cantidad b').text(nueva.toFixed(decimales));
    fila.find('.venta-pro-cantidad button[data-delta="-1"]').prop('disabled', nueva <= 1);
    fila.find('.venta-pro-linea-subtotal strong').text('S/ ' + item.subtotal.toFixed(2));
    RecalcularTotalesVentaPro();
}

function EscaparVentaPro(valor) {
    return $('<span>').text(String(valor == null ? '' : valor)).html();
}

function RecalcularTotalesVentaPro() {
    var total = Math.round(detalle.reduce(function (suma, item) { return suma + Number(item.subtotal || 0); }, 0) * 100) / 100;
    var subtotalBase = Number((total / 1.18).toFixed(2));
    var igv = Number((total - subtotalBase).toFixed(2));
    $('#total_items').text(detalle.length);
    $('#total_subtotal').text(subtotalBase.toFixed(2));
    $('#total_igv').text(igv.toFixed(2));
    $('#total_monto, #venta_pro_total_carrito').text(total.toFixed(2));
    $('#ven_monto_pago').val(total.toFixed(2));
    ActualizarSaldoVenta();
}

// ===================== RENDERIZAR TABLA =====================
function RenderTabla() {
    ActualizarSaldoVenta();
    var tbody = $("#tbody_detalle");
    $("#venta_pro_badge").text(detalle.length);

    if (detalle.length === 0) {
        tbody.html('<div class="venta-pro-carrito-vacio"><i class="fa fa-inbox"></i><span>Sin ítems agregados</span></div>');
        $("#total_items").text("0");
        $("#total_subtotal").text("0.00");
        $("#total_igv").text("0.00");
        $("#total_monto").text("0.00");
        $("#venta_pro_total_carrito").text("0.00");
        $("#ven_monto_pago").val("0.00");
        ActualizarSaldoVenta();
        return;
    }

    var html  = "";
    var total = 0;
    $.each(detalle, function (i, d) {
        total += d.subtotal;
        var rutaImagen = typeof RutaImagenVentaPro === 'function' ? RutaImagenVentaPro(d.imagen) : '../assets/img/ifact-symbol.png';
        var cantidad = Number(d.cantidad).toFixed(parseInt((productos[d.pva_id] || {}).und_decimales, 10) || 0);
        html += "<article class='venta-pro-carrito-item fade-in' data-item-index='" + i + "'>" +
            "<img class='venta-pro-carrito-img' src='" + EscaparVentaPro(rutaImagen) + "' alt='' onerror=\"this.onerror=null;this.src='../assets/img/ifact-symbol.png'\">" +
            "<div class='venta-pro-carrito-producto'><strong>" + EscaparVentaPro(d.producto || d.nombre) + "</strong><span>" + EscaparVentaPro(d.variante) + "</span><small>" + EscaparVentaPro(d.sku) + " · S/ " + Number(d.precio_unit).toFixed(2) + "</small></div>" +
            "<div class='venta-pro-cantidad'><button type='button' data-delta='-1' onclick='CambiarCantidadItemPro(" + i + ",-1)' aria-label='Reducir cantidad' " + (Number(d.cantidad) <= 1 ? "disabled" : "") + "><i class='fa fa-minus'></i></button><b>" + cantidad + "</b><button type='button' data-delta='1' onclick='CambiarCantidadItemPro(" + i + ",1)' aria-label='Aumentar cantidad'><i class='fa fa-plus'></i></button></div>" +
            "<div class='venta-pro-linea-subtotal'><small>Subtotal</small><strong>S/ " + Number(d.subtotal).toFixed(2) + "</strong></div>" +
            "<button type='button' class='venta-pro-eliminar' onclick='EliminarItem(" + i + ")' title='Eliminar'><i class='fa fa-trash-alt'></i></button>" +
            "</article>";
    });

    var subtotal_base = parseFloat((total / 1.18).toFixed(2));
    var igv           = parseFloat((total - subtotal_base).toFixed(2));

    tbody.html(html);
    $("#total_items").text(detalle.length);
    $("#total_subtotal").text(subtotal_base.toFixed(2));
    $("#total_igv").text(igv.toFixed(2));
    $("#total_monto").text(total.toFixed(2));
    $("#venta_pro_total_carrito").text(total.toFixed(2));
    $("#ven_monto_pago").val(total.toFixed(2));
    ActualizarSaldoVenta();
}

// ===================== GUARDAR VENTA =====================
function Guardar() {
    if (ventaFinalizada || ventaEnRegistro) return;
    if (detalle.length === 0) {
        Swal.fire({ title: "Sin ítems", text: "Agregue al menos un producto.", icon: "info" });
        return;
    }
    if (!ValidarCobroVentaPro()) return;

    // La venta se registra primero como nota interna. La emisión electrónica es
    // una segunda operación sobre la misma venta y nunca vuelve a descontar stock.
    $('#ven_tde_id').val('0').trigger('change');
    GuardarVentaSeleccionada(true);
}

function ConfirmarVentaConComprobantePro() {
    ventaDecisionTomada = true;
    Swal.close();
    MostrarEmisionComprobanteVentaPro();
}

function ConfirmarVentaSinComprobantePro() {
    ventaDecisionTomada = true;
    Swal.close();
    AbrirNotaVentaRegistradaPro();
}

function ConfirmarVentaSinImpresionPro() {
    ventaDecisionTomada = true;
    Swal.close();
    ReiniciarNuevaVentaPro();
}

function MostrarDestinoVentaRegistradaPro() {
    ventaDecisionTomada = false;
    var facturacionActiva = typeof facturacion_electronica_habilitada === 'undefined' || facturacion_electronica_habilitada;
    var mensaje = facturacionActiva
        ? 'La nota de venta ya quedó guardada. Elige si deseas emitir ahora un comprobante electrónico.'
        : 'La venta ya quedó guardada. Elige si deseas imprimir la nota de venta.';
    var acciones = facturacionActiva
        ? '<button type="button" class="venta-comprobante-opcion venta-comprobante-opcion--con" onclick="ConfirmarVentaConComprobantePro()"><span class="venta-comprobante-opcion-icono"><i class="fa fa-file-invoice"></i></span><span>Emitir comprobante</span></button>' +
          '<button type="button" class="venta-comprobante-opcion venta-comprobante-opcion--sin" onclick="ConfirmarVentaSinComprobantePro()"><span class="venta-comprobante-opcion-icono"><i class="fa fa-print"></i></span><span>Imprimir nota de venta</span></button>'
        : '<button type="button" class="venta-comprobante-opcion venta-comprobante-opcion--con" onclick="ConfirmarVentaSinComprobantePro()"><span class="venta-comprobante-opcion-icono"><i class="fa fa-print"></i></span><span>Imprimir nota de venta</span></button>' +
          '<button type="button" class="venta-comprobante-opcion venta-comprobante-opcion--sin" onclick="ConfirmarVentaSinImpresionPro()"><span class="venta-comprobante-opcion-icono"><i class="fa fa-check"></i></span><span>Finalizar sin imprimir</span></button>';
    Swal.fire({
        title: 'Venta registrada',
        html: '<div class="venta-eleccion-comprobante venta-eleccion-comprobante--simple">' + mensaje + '</div>' +
            '<div class="venta-comprobante-botones">' + acciones + '</div>',
        icon: 'success', width: 640, showConfirmButton: false, allowOutsideClick: false, allowEscapeKey: false,
        customClass: { popup: 'venta-comprobante-popup' },
        didClose: function () {
            if (ventaRegistradaId && !ventaDecisionTomada) {
                window.setTimeout(MostrarDestinoVentaRegistradaPro, 0);
            }
        }
    });
}

function AbrirNotaVentaRegistradaPro() {
    if (!ventaRegistradaId) return;
    Swal.close();
    ImprimirNotaVentaHTML(ventaRegistradaId, ReiniciarNuevaVentaPro);
}

function ValidarCobroVentaPro() {
    var tpaId = parseInt($('#ven_tpa_id').val(), 10) || 0;
    if (tpaId <= 0) {
        Swal.fire({ title: 'Forma de pago requerida', text: 'Debe seleccionar el método de pago.', icon: 'info', confirmButtonText: 'Entendido' });
        return false;
    }

    var cbaId = parseInt($('#ven_cba_id').val(), 10) || 0;
    if (cbaId <= 0) {
        Swal.fire({ title: 'Cuenta requerida', text: 'Debe seleccionar la cuenta.', icon: 'info', confirmButtonText: 'Entendido' });
        return false;
    }

    var total = Math.round(detalle.reduce(function (suma, item) { return suma + Number(item.subtotal || 0); }, 0) * 100) / 100;
    var textoPago = $('#ven_monto_pago').val();
    var montoPago = Number(textoPago);
    var montoValido = textoPago !== '' && Number.isFinite(montoPago) && montoPago >= 0 && montoPago <= total && Math.abs(montoPago - Math.round(montoPago * 100) / 100) < 0.000001;
    if (!montoValido) {
        Swal.fire({ title: 'Monto de pago no válido', text: 'Ingrese un monto entre S/ 0.00 y S/ ' + total.toFixed(2) + '.', icon: 'info', confirmButtonText: 'Entendido' });
        return false;
    }
    return true;
}

function MostrarEmisionComprobanteVentaPro() {
    ventaDecisionTomada = false;
    var clienteActual = String($('#ven_cli_id').val() || '0');
    var cliente = clientes.find(function (item) { return String(item.ent_id) === clienteActual; });
    var numeroCliente = cliente ? String(cliente.ent_nro_doc || '').replace(/\D/g, '') : '';
    var clienteRuc = !!cliente && (parseInt(cliente.ent_tipo_doc, 10) === 6 || /^\d{11}$/.test(numeroCliente));
    var documentos = Object.keys(docs_data).filter(function (id) {
        return clienteRuc || String(docs_data[id].tde_codigo) !== '01';
    });
    var documentoPreferido = documentos.find(function (id) { return String(docs_data[id].tde_codigo) === (clienteRuc ? '01' : '03'); });
    ventaDocumentoEmisionId = Number(documentoPreferido || documentos[0]) || 0;
    var opcionesDocumento = documentos.map(function (id) {
        var documento = docs_data[id];
        return '<button type="button" class="venta-emision-documento' + (Number(id) === ventaDocumentoEmisionId ? ' activo' : '') + '" data-tde-id="' + Number(id) + '" onclick="SeleccionarDocumentoEmisionPro(' + Number(id) + ')"><span><i class="fa fa-file-invoice"></i></span><strong>' + EscaparVentaPro(documento.tde_nombre || 'Comprobante') + '</strong><i class="fa fa-check"></i></button>';
    }).join('');
    var nombreCliente = cliente ? cliente.ent_nombres : 'Cliente general / sin registro';
    var detalleCliente = cliente ? (cliente.ent_nro_doc || 'Sin documento') : 'Solo disponible para boleta';
    var etiquetaCliente = clienteRuc ? 'RUC' : (cliente ? 'DNI' : 'General');
    var total = Math.round(detalle.reduce(function (suma, item) { return suma + Number(item.subtotal || 0); }, 0) * 100) / 100;
    var subtotal = Number((total / 1.18).toFixed(2));
    var igv = Number((total - subtotal).toFixed(2));

    Swal.fire({
        title: 'Emitir comprobante',
        width: 760,
        html: '<div class="venta-emision-modal">' +
                '<section class="venta-emision-cliente-seccion"><div class="venta-emision-label-row"><label>Cliente</label><button type="button" onclick="AbrirNuevoClienteEmisionPro()"><i class="fa fa-user-plus"></i> Agregar nuevo</button></div><button type="button" class="venta-emision-cliente" onclick="AbrirSelectorClienteEmisionPro()"><span class="venta-emision-cliente-icono"><i class="fa fa-user"></i></span><span class="venta-emision-cliente-datos"><strong>' + EscaparVentaPro(nombreCliente) + '</strong><small>' + EscaparVentaPro(detalleCliente) + '</small></span><em>' + etiquetaCliente + '</em><i class="fa fa-search"></i></button><small id="venta_emision_error" class="venta-emision-error"></small></section>' +
                '<section><label>Tipo de comprobante</label><div class="venta-emision-documentos">' + (opcionesDocumento || '<p>No hay comprobantes configurados.</p>') + '</div><small class="venta-emision-ayuda">' + (clienteRuc ? 'Cliente con RUC: Factura seleccionada automáticamente; también puede emitir Boleta.' : 'Este cliente solo puede emitir Boleta.') + '</small></section>' +
                '<section class="venta-emision-resumen"><div><span>Subtotal</span><strong>S/ ' + subtotal.toFixed(2) + '</strong></div><div><span>IGV 18%</span><strong>S/ ' + igv.toFixed(2) + '</strong></div><div class="total"><span>Total</span><strong>S/ ' + total.toFixed(2) + '</strong></div></section>' +
                '<button type="button" class="venta-emision-confirmar" onclick="EmitirComprobanteVentaPro()"><i class="fa fa-file-invoice me-2"></i> Emitir comprobante</button>' +
            '</div>',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: { popup: 'venta-emision-popup', closeButton: 'venta-comprobante-cerrar' },
        didOpen: function () {
            var popup = Swal.getPopup();
            if (popup) popup.style.setProperty('width', Math.min(760, window.innerWidth - 28) + 'px', 'important');
        },
        didClose: function () {
            if (ventaRegistradaId && !ventaDecisionTomada) {
                window.setTimeout(MostrarDestinoVentaRegistradaPro, 0);
            }
        }
    });
}

function AbrirSelectorClienteEmisionPro() {
    ventaSelectorClienteEmision = true;
    $('#buscar_cliente_venta').val('');
    RenderSelectorClientesVenta();
    $('#selectorClienteVenta').off('shown.bs.modal.ventaEmisionCapa').one('shown.bs.modal.ventaEmisionCapa', function () {
        $('.modal-backdrop').last().addClass('venta-cliente-emision-backdrop');
    }).off('hidden.bs.modal.ventaEmisionCapa').one('hidden.bs.modal.ventaEmisionCapa', function () {
        ventaSelectorClienteEmision = false;
    });
    AbrirSelectorClienteVenta();
}

function ActualizarClienteEmisionPro() {
    var clienteId = String($('#ven_cli_id').val() || '0');
    var cliente = clientes.find(function (item) { return String(item.ent_id) === clienteId; });
    var numero = cliente ? String(cliente.ent_nro_doc || '').replace(/\D/g, '') : '';
    var tieneRuc = !!cliente && (parseInt(cliente.ent_tipo_doc, 10) === 6 || /^\d{11}$/.test(numero));
    var documentos = Object.keys(docs_data).filter(function (id) {
        return tieneRuc || String(docs_data[id].tde_codigo) !== '01';
    });
    var preferido = documentos.find(function (id) { return String(docs_data[id].tde_codigo) === (tieneRuc ? '01' : '03'); });
    ventaDocumentoEmisionId = Number(preferido || documentos[0]) || 0;

    $('.venta-emision-cliente-datos strong').text(cliente ? cliente.ent_nombres : 'Cliente general / sin registro');
    $('.venta-emision-cliente-datos small').text(cliente ? (cliente.ent_nro_doc || 'Sin documento') : 'Solo disponible para boleta');
    $('.venta-emision-cliente em').text(tieneRuc ? 'RUC' : (cliente ? 'DNI' : 'General'));
    $('.venta-emision-documentos').html(documentos.map(function (id) {
        var documento = docs_data[id];
        return '<button type="button" class="venta-emision-documento' + (Number(id) === ventaDocumentoEmisionId ? ' activo' : '') + '" data-tde-id="' + Number(id) + '" onclick="SeleccionarDocumentoEmisionPro(' + Number(id) + ')"><span><i class="fa fa-file-invoice"></i></span><strong>' + EscaparVentaPro(documento.tde_nombre || 'Comprobante') + '</strong><i class="fa fa-check"></i></button>';
    }).join(''));
    $('.venta-emision-ayuda').text(tieneRuc ? 'Cliente con RUC: Factura seleccionada automáticamente; también puede emitir Boleta.' : 'Este cliente solo puede emitir Boleta.');
    $('#venta_emision_error').text('');
}

function SeleccionarDocumentoEmisionPro(tdeId) {
    ventaDocumentoEmisionId = Number(tdeId) || 0;
    $('.venta-emision-documento').removeClass('activo');
    $('.venta-emision-documento[data-tde-id="' + ventaDocumentoEmisionId + '"]').addClass('activo');
    $('#venta_emision_error').text('');
}

function EmitirComprobanteVentaPro() {
    var documento = docs_data[ventaDocumentoEmisionId];
    var clienteId = parseInt($('#ven_cli_id').val(), 10) || 0;
    var cliente = clientes.find(function (item) { return Number(item.ent_id) === clienteId; });
    var numeroCliente = cliente ? String(cliente.ent_nro_doc || '').replace(/\D/g, '') : '';
    var clienteRuc = !!cliente && (parseInt(cliente.ent_tipo_doc, 10) === 6 || /^\d{11}$/.test(numeroCliente));
    if (!documento) {
        $('#venta_emision_error').text('Selecciona un tipo de comprobante.');
        return;
    }
    if (documento.tde_codigo === '01' && !clienteRuc) {
        $('#venta_emision_error').text('Para emitir una factura debes seleccionar un cliente con RUC.');
        return;
    }
    if (!ventaRegistradaId) {
        $('#venta_emision_error').text('Primero debe registrarse la venta.');
        return;
    }
    ventaDecisionTomada = true;
    Swal.close();
    PrepararComprobanteVentaRegistradaPro(clienteId, ventaDocumentoEmisionId, documento.tde_nombre || 'Comprobante');
}

function PrepararComprobanteVentaRegistradaPro(clienteId, tdeId, documentoTexto) {
    Swal.fire({ title: 'Preparando comprobante...', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
    $.ajax({
        type: 'POST', url: '../controller/venta.php', dataType: 'json',
        data: { task: 13, ven_id: ventaRegistradaId, ent_id: clienteId || '', tde_id: tdeId, rand: Math.random() },
        success: function (res) {
            if (!res || res.estado !== 'ok') {
                MostrarFalloEmisionConNotaPro(res && res.mensaje ? res.mensaje : 'No se pudo preparar el comprobante.');
                return;
            }
            ReservarYEmitirVentaRegistradaPro(ventaRegistradaId, documentoTexto);
        },
        error: function () { MostrarFalloEmisionConNotaPro('No se pudo conectar para preparar el comprobante.'); }
    });
}

function ReservarYEmitirVentaRegistradaPro(venId, documentoTexto) {
    $.ajax({
        type: 'POST', url: '../controller/facturacion.php', dataType: 'json', timeout: 15000,
        data: { task: 14, ven_id: venId, rand: Math.random() },
        success: function (fac) {
            if (!(fac && fac.fac_id && fac.serie && fac.correlativo)) {
                MostrarFalloEmisionConNotaPro((fac && fac.mensaje) || 'La central no asignó la numeración.');
                return;
            }
            var numero = fac.serie + '-' + String(fac.correlativo).padStart(8, '0');
            Swal.close();
            ImprimirComprobanteVentaHTML(venId, ReiniciarNuevaVentaPro);
            $.ajax({
                type: 'POST', url: '../controller/facturacion.php', dataType: 'json',
                data: { task: 6, ven_id: venId, reintentar: 1, rand: Math.random() },
                success: function (emitida) {
                    if (emitida && emitida.fac_id && emitida.api && emitida.api.success) {
                        if (typeof toastr !== 'undefined') toastr.success('SUNAT confirmó el comprobante. La representación impresa permanece lista para imprimir.');
                    } else if (typeof toastr !== 'undefined') toastr.warning('La nota sigue disponible. El comprobante quedó pendiente para reintento automático.');
                },
                error: function () { if (typeof toastr !== 'undefined') toastr.warning('La nota sigue disponible. La emisión se reintentará automáticamente.'); }
            });
        },
        error: function () { MostrarFalloEmisionConNotaPro('No se pudo obtener numeración de la central.'); }
    });
}

function MostrarFalloEmisionConNotaPro(mensaje) {
    Swal.fire({
        title: 'Comprobante pendiente',
        html: EscaparVentaPro(mensaje) + '<br><small>La venta y su nota interna quedaron guardadas correctamente.</small>',
        icon: 'warning', showCancelButton: true, confirmButtonText: '<i class="fa fa-print me-1"></i> Imprimir nota', cancelButtonText: 'Ir a ventas'
    }).then(function (r) { if (r.isConfirmed) AbrirNotaVentaRegistradaPro(); else window.location.href = 'list_venta.php'; });
}

function GuardarVentaSeleccionada(omitirConfirmacion) {
    if (ventaFinalizada || ventaEnRegistro) return;
    if (detalle.length === 0) {
        Swal.fire({ title: "Sin ítems", text: "Agregue al menos un " + (tipo_venta === 2 ? "producto" : "insumo") + ".", icon: "warning" });
        return;
    }

    var tpa_id = parseInt($("#ven_tpa_id").val()) || 0;
    if (tpa_id <= 0) {
        Swal.fire({ title: "Forma de pago requerida", text: "Debe seleccionar el método de pago.", icon: "warning" });
        return;
    }

    var cba_id = parseInt($("#ven_cba_id").val()) || 0;
    if (cba_id <= 0) {
        Swal.fire({ title: "Cuenta requerida", text: "Debe seleccionar la cuenta.", icon: "warning" });
        return;
    }

    var ent_id        = parseInt($("#ven_cli_id").val()) || 0;
    var tde_id        = parseInt($("#ven_tde_id").val()) || 0;

    // Validar: factura requiere cliente con RUC
    if (tde_id > 0 && docs_data[tde_id] && docs_data[tde_id].tde_codigo === "01") {
        var tipoDocCli = parseInt($("#ven_cli_id option:selected").data("tipo")) || 0;
        if (ent_id <= 0 || tipoDocCli !== 6) {
            Swal.fire({
                title: "Cliente con RUC requerido",
                html: "La <b>Factura</b> solo puede emitirse a clientes con <b>RUC</b>.<br>Seleccione o registre un cliente con RUC.",
                icon: "warning"
            });
            return;
        }
    }

    var num_operacion = $.trim($("#ven_num_operacion").val());
    var comentario    = $.trim($("#ven_comentario").val());
    var total         = detalle.reduce(function (s, d) { return s + d.subtotal; }, 0);
    var pagoTexto = $("#ven_monto_pago").val();
    var pago = Number(pagoTexto);
    if (pagoTexto === "" || !Number.isFinite(pago) || pago < 0 || Math.abs(pago - Math.round(pago * 100) / 100) > 0.000001 || pago > Math.round(total * 100) / 100) {
        Swal.fire("Pago no válido", "Ingrese cuánto pagará ahora, entre 0 y el total, con hasta dos decimales.", "warning"); return;
    }
    var pagoCompleto = Math.round(pago * 100) === Math.round(total * 100);
    var docTexto      = tde_id > 0 ? $("#ven_tde_id option:selected").text() : "Sin comprobante";

    var confirmacion = omitirConfirmacion ? Promise.resolve({ isConfirmed: true }) : Swal.fire({
        title: "Confirmar Venta",
        html:
            "<div class='text-muted mb-2'>" + detalle.length + " ítem(s) · " + docTexto + "</div>" +
            "<h3 class='fw-bold'>S/ " + total.toFixed(2) + "</h3>" +
            "<p>Pago ahora: S/ " + pago.toFixed(2) + " · Saldo: S/ " + (total-pago).toFixed(2) + "</p>" +
            (pagoCompleto ? "" : "<small>El comprobante se podrá emitir cuando se complete el pago.</small>"),
        icon: "question",
        showCancelButton: true,
        confirmButtonText: pagoCompleto && tde_id > 0 ? "<i class='fa fa-print me-1'></i> Registrar y emitir" : "Registrar venta",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#198754"
    });
    confirmacion.then(function (result) {
        if (result.isDismissed) return;
        if (ventaFinalizada || ventaEnRegistro) return;
        ventaEnRegistro = true;
        EstablecerBotonesConfirmacionVenta(true);

        var emitir = result.isConfirmed && pagoCompleto && tde_id > 0;

        Swal.fire({
            title: "Procesando...",
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });

        var esProducto = (tipo_venta === 2);
        var payload = detalle.map(function (d) {
            var row = {
                und_med:     d.und_med,
                cantidad:    d.cantidad,
                precio_unit: d.precio_unit,
                subtotal:    d.subtotal
            };
            if (esProducto) { row.pro_id = d.pro_id; row.pva_id = d.pva_id; } else row.ins_id = d.ins_id;
            return row;
        });

        $.ajax({
            type:     "POST",
            url:      "../controller/venta.php",
            dataType: "json",
            data: {
                task:          6,
                ent_id:        ent_id || "",
                tde_id:        tde_id,
                tpa_id:        tpa_id,
                cba_id:        cba_id,
                monto_pago:    pago,
                num_operacion: num_operacion,
                comentario:    comentario,
                ven_tipo:      tipo_venta,
                detalle:       JSON.stringify(payload),
                rand:          Math.random()
            },
            success: function (res) {
                if (res.estado === "ok") {
                    ventaFinalizada = true;
                    $('button[onclick="Guardar()"]').prop("disabled", true);
                    if (emitir) {
                        $.ajax({
                            type:     "POST",
                            url:      "../controller/facturacion.php",
                            dataType: "json",
                            timeout: 15000,
                            data:     { task: 14, ven_id: res.ven_id, rand: Math.random() },
                            success: function (fac) {
                                // task 14 solo reserva la serie y el correlativo. Una respuesta
                                // satisfactoria aquí todavía no significa que SUNAT haya emitido
                                // el comprobante; la representación inicial debe ser la copia local.
                                var reservaOk = fac && fac.fac_id && fac.serie && fac.correlativo;
                                var meta = {
                                    cliente:    $("#ven_cli_id option:selected").text(),
                                    forma_pago: $("#ven_tpa_id option:selected").text(),
                                    documento:  docTexto,
                                    serie:      fac && fac.serie ? fac.serie : "—",
                                    numero:     fac && fac.correlativo ? String(fac.correlativo).padStart(8, '0') : "—",
                                    fac_ok:     false,
                                    fac_sunat:  false,
                                    fac_id:     fac && fac.fac_id ? fac.fac_id : null,
                                    archivos:   fac && fac.archivos ? fac.archivos : {},
                                    fac_msg:    fac ? (
                                                    fac.api && fac.api.descripcion ? fac.api.descripcion :
                                                    fac.api && fac.api.mensaje_error ? fac.api.mensaje_error :
                                                    fac.mensaje
                                                ) : "Sin respuesta"
                                };
                                if (reservaOk) {
                                    Swal.close();
                                    ImprimirComprobanteVentaHTML(res.ven_id, function () {
                                        window.location.reload();
                                    });
                                    if (typeof toastr !== "undefined") toastr.info("Número " + fac.comprobante + " reservado. La emisión electrónica continúa en segundo plano.");

                                    // Reservar es la operación que bloquea la interfaz. Una vez abierto
                                    // el PDF local numerado, firma, envío y respuesta SUNAT continúan
                                    // de forma independiente usando el mismo id_externo.
                                    $.ajax({
                                        type: "POST",
                                        url: "../controller/facturacion.php",
                                        dataType: "json",
                                        data: { task: 6, ven_id: res.ven_id, reintentar: 1, rand: Math.random() },
                                        success: function (emitida) {
                                            var confirmada = emitida && emitida.fac_id && emitida.api && emitida.api.success;
                                            if (!confirmada) {
                                                if (typeof toastr !== "undefined") toastr.warning("La representación sigue disponible. La emisión quedó pendiente para reintento automático.");
                                                return;
                                            }
                                            if (typeof toastr !== "undefined") toastr.success("SUNAT confirmó el comprobante. La representación impresa permanece lista para imprimir.");
                                        },
                                        error: function () {
                                            if (typeof toastr !== "undefined") toastr.warning("La representación sigue disponible. SUNAT no respondió y la emisión se reintentará automáticamente.");
                                        }
                                    });
                                } else {
                                    Swal.fire({
                                        title: "Venta registrada",
                                        text: ((fac && fac.mensaje) ? fac.mensaje + ". " : "La central todavía no asignó la numeración. ") + "La venta quedó en cola y se reintentará sin duplicarla.",
                                        icon: "warning",
                                        confirmButtonText: "Aceptar"
                                    }).then(function () { window.location.href = "list_venta.php"; });
                                }
                            },
                            error: function () {
                                Swal.fire({
                                    title: "Venta registrada",
                                    html: "No se pudo obtener numeración de la central en este momento.<br><small>La venta quedó en cola para reintento; no se generará una representación sin número.</small>",
                                    icon: "warning",
                                    confirmButtonText: "Aceptar"
                                }).then(function () { window.location.href = "list_venta.php"; });
                            }
                        });
                    } else if (tde_id <= 0) {
                        ventaRegistradaId = Number(res.ven_id) || 0;
                        ventaNotaRegistrada = res.ven_serie && res.ven_numero
                            ? res.ven_serie + '-' + String(res.ven_numero).padStart(8, '0')
                            : '';
                        // Swal.fire reemplaza directamente el aviso "Procesando".
                        // No llamar Swal.close() antes: su animación asíncrona podía
                        // cerrar también este nuevo modal apenas se mostraba.
                        window.setTimeout(MostrarDestinoVentaRegistradaPro, 0);
                    } else {
                        Swal.close();
                        Swal.fire({ title: "Venta registrada", text: "El comprobante quedó registrado para continuar con su emisión.", icon: "success", confirmButtonText: "Aceptar" })
                            .then(function () { window.location.href = "list_venta.php"; });
                    }
                } else {
                    ventaEnRegistro = false;
                    EstablecerBotonesConfirmacionVenta(false);
                    Swal.fire("Error", res.mensaje, "error");
                }
            },
            error: function () {
                ventaEnRegistro = false;
                EstablecerBotonesConfirmacionVenta(false);
                Swal.fire("Error de conexión", "No se pudo conectar con el servidor.", "error");
            }
        });
    });
}

// ===================== OFF-CANVAS COMPROBANTE =====================
function AbrirOffcanvasComprobante(ven_id, meta) {
    ventaFinalizada = true;
    var compNum = (meta.serie !== "—" && meta.numero !== "—")
        ? (meta.documento + " " + meta.serie + "-" + meta.numero)
        : meta.documento;

    $("#oc_comp_titulo").html('<i class="fa fa-file-invoice me-2 text-success"></i> ' + compNum);

    var fecha = new Date().toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });

    var estadoCPE = "";
    if (typeof meta.fac_ok !== "undefined") {
        if (meta.fac_ok && meta.fac_sunat) {
            estadoCPE = '<div class="alert alert-info py-2 px-3 mb-2" style="font-size:.82rem;border-radius:10px;">' +
                  '<i class="fa fa-search me-1"></i> CPE confirmado en SUNAT. QPSE report� error, pero el comprobante existe: ' + meta.fac_msg +
              '</div>';
        } else if (meta.fac_ok) {
            estadoCPE = '<div class="alert alert-success py-2 px-3 mb-2" style="font-size:.82rem;border-radius:10px;">' +
                  '<i class="fa fa-check-circle me-1"></i> CPE emitido correctamente - ' + meta.fac_msg +
              '</div>';
        } else {
            estadoCPE = '<div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.82rem;border-radius:10px;">' +
                  '<i class="fa fa-exclamation-triangle me-1"></i> Venta registrada pero CPE no emitido: ' + meta.fac_msg +
              '</div>';
        }
    }

    var bodyHtml =
        estadoCPE +
        '<div class="oc-meta">' +
            '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa fa-hashtag me-1"></i>Venta</span><span class="oc-meta-val">#' + ven_id + '</span></div>' +
            '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa fa-user me-1"></i>Cliente</span><span class="oc-meta-val">' + meta.cliente + '</span></div>' +
            '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa fa-credit-card me-1"></i>Pago</span><span class="oc-meta-val">' + meta.forma_pago + '</span></div>' +
            '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa fa-calendar me-1"></i>Fecha</span><span class="oc-meta-val">' + fecha + '</span></div>' +
        '</div>' +
        '<div class="oc-section-title">' + (tipo_venta === 2 ? "Productos" : "Insumos") + '</div>' +
        '<div id="oc_comp_items"><div class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin me-1"></i> Cargando...</div></div>';

    $("#oc_comp_body").html(bodyHtml);

    var oc = new bootstrap.Offcanvas(document.getElementById("offcanvasComprobante"));
    oc.show();

    $.get("../controller/venta.php", { task: 2, ven_id: ven_id, rand: Math.random() }, function (datos) {
        if (!datos || datos.length === 0) {
            $("#oc_comp_items").html('<p class="text-muted text-center py-3">Sin ítems registrados.</p>');
            return;
        }

        var totalCalc = 0;
        var itemHtml  = "";
        $.each(datos, function (i, d) {
            var sub = parseFloat(d.vde_subtotal);
            totalCalc += sub;
            itemHtml +=
                '<div class="oc-item">' +
                    '<div class="d-flex justify-content-between align-items-start gap-2">' +
                        '<div>' +
                            '<div class="oc-item-nombre">' + d.ins_nombre + '</div>' +
                            '<div class="oc-item-detalle">' +
                                '<i class="fa fa-cubes me-1"></i>' +
                                parseFloat(d.vde_cantidad).toFixed(3) + ' ' + d.unidad +
                                ' &nbsp;·&nbsp; S/ ' + parseFloat(d.vde_precio_unit).toFixed(2) + ' c/u' +
                            '</div>' +
                        '</div>' +
                        '<span class="oc-item-subtotal">S/ ' + sub.toFixed(2) + '</span>' +
                    '</div>' +
                '</div>';
        });

        var subtotal = parseFloat((totalCalc / 1.18).toFixed(2));
        var igv      = parseFloat((totalCalc - subtotal).toFixed(2));

        var btnsPDF = "";
        if (meta.fac_ok && meta.fac_id) {
            var urlTicket = "../controller/facturacion.php?task=11&modo=inline&fac_id=" + meta.fac_id;
            btnsPDF =
                '<div class="mt-3">' +
                    '<button type="button" onclick="VerComprobantePDF(\'' + urlTicket + '\', \'Comprobante\')" class="btn btn-dark w-100" style="border-radius:10px; font-size:.86rem;">' +
                        '<i class="fa fa-print me-1"></i> Imprimir comprobante' +
                    '</button>' +
                '</div>';
        }

        itemHtml +=
            '<div class="oc-breakdown">' +
                '<div class="oc-brow"><span class="oc-blabel">Subtotal (sin IGV)</span><span class="oc-bval">S/ ' + subtotal.toFixed(2) + '</span></div>' +
                '<div class="oc-brow"><span class="oc-blabel">IGV 18%</span><span class="oc-bval">S/ ' + igv.toFixed(2) + '</span></div>' +
                '<div class="oc-bdivider"></div>' +
                '<div class="oc-brow"><span class="oc-blabel-total">TOTAL</span><span class="oc-bval-total">S/ ' + totalCalc.toFixed(2) + '</span></div>' +
            '</div>' +
            btnsPDF +
            '<div class="mt-2">' +
                '<a href="list_venta.php" class="btn btn-secondary w-100" style="border-radius:10px; font-size:.82rem;"><i class="fa fa-list me-1"></i> Ver listado de ventas</a>' +
            '</div>';

        $("#oc_comp_items").html(itemHtml);
    }, "json");
}
