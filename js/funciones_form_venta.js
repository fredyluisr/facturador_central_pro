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
    $("#selectorPagoVenta").on("shown.bs.offcanvas", function () {
        $(".offcanvas-backdrop").last().addClass("venta-pago-backdrop");
    });
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
        if (!permitidos.has(String(c.ent_id))) return false;
        return !buscar || [c.ent_nombres,c.ent_nro_doc].join(" ").toLocaleLowerCase("es").includes(buscar);
    });
    var general = !buscar || "cliente general sin registro".includes(buscar)
        ? '<button type="button" class="venta-selector-opcion'+(actual==='0'?' activo':'')+'" onclick="SeleccionarClienteVenta(0)"><strong>Cliente general / sin registro</strong><span>Venta sin cliente identificado</span></button>' : '';
    $("#clientes_venta_lista").html(general + lista.map(function(c){
        return '<button type="button" class="venta-selector-opcion'+(actual===String(c.ent_id)?' activo':'')+'" onclick="SeleccionarClienteVenta('+Number(c.ent_id)+')"><strong>'+EscaparVenta(c.ent_nombres)+'</strong><span>'+EscaparVenta(c.ent_nro_doc||'Sin documento')+'</span></button>';
    }).join('') || '<div class="venta-selector-vacio">No se encontraron clientes.</div>');
}

function SeleccionarClienteVenta(id) {
    $("#ven_cli_id").val(String(id));
    ActualizarClienteSeleccionado();
    bootstrap.Modal.getOrCreateInstance(document.getElementById("selectorClienteVenta")).hide();
}

function EscaparVenta(valor) { return $("<div>").text(valor == null ? "" : String(valor)).html(); }

function AbrirModalClienteVenta() {
    $("#venta_cli_tipo_doc").val("1");
    $("#venta_cli_nro_doc, #venta_cli_nombres, #venta_cli_telefono, #venta_cli_direccion, #venta_cli_email, #venta_ent_marca").val("");
    $("#modalClienteVenta").modal("show");
    setTimeout(function () { $("#venta_cli_nro_doc").trigger("focus"); }, 250);
}

function ConsultarDocumentoClienteVenta() {
    var tipo = parseInt($("#venta_cli_tipo_doc").val(), 10);
    var numero = $.trim($("#venta_cli_nro_doc").val());
    var esDni = tipo === 1;
    var esRuc = tipo === 6;

    if (!esDni && !esRuc) return toastr.info("La consulta solo está disponible para DNI y RUC");
    if (!(esDni ? /^[0-9]{8}$/.test(numero) : /^[0-9]{11}$/.test(numero))) {
        return toastr.warning(esDni ? "El DNI debe tener 8 dígitos" : "El RUC debe tener 11 dígitos");
    }

    Swal.fire({ title: esDni ? "Consultando DNI..." : "Consultando RUC...", text: "Por favor espere", icon: "info", allowOutsideClick: false, showConfirmButton: false });
    $.ajax({
        url: "../controller/consultas_api.php",
        type: "GET",
        dataType: "json",
        data: esDni ? { task: 7, dni: numero } : { task: 8, ruc: numero },
        success: function (respuesta) {
            Swal.close();
            var datos = Array.isArray(respuesta) ? respuesta[0] : respuesta;
            if (!(datos && (datos.success == 1 || datos.success === true))) {
                return Swal.fire("No encontrado", (datos && (datos.message || datos.error)) || "No se encontraron datos", "warning");
            }
            if (esDni) {
                $("#venta_cli_nombres").val([datos.nombres, datos.ape_paterno, datos.ape_materno].filter(Boolean).join(" ").trim());
            } else {
                $("#venta_cli_nombres").val($.trim(datos.razon_social || ""));
                if (datos.direccion) $("#venta_cli_direccion").val($.trim(datos.direccion));
            }
            AvisoIFact("Datos encontrados");
        },
        error: function () {
            Swal.close();
            Swal.fire("Error", "No se pudo realizar la consulta. Intente nuevamente.", "error");
        }
    });
}

async function GuardarClienteVenta() {
    var nombres = $.trim($("#venta_cli_nombres").val());
    var marca = $.trim($("#venta_ent_marca").val());
    if (!nombres) {
        toastr.warning("El nombre es obligatorio");
        return $("#venta_cli_nombres").trigger("focus");
    }
    if (marca.length < 3) {
        toastr.warning("La marca es obligatoria y debe tener al menos 3 caracteres");
        return $("#venta_ent_marca").trigger("focus");
    }

    if (!await ConfirmarGuardadoIFact('registrar', 'cliente')) return;
    var boton = $("#btnGuardarClienteVenta").prop("disabled", true);
    $.ajax({
        url: "../controller/entidad_cliente.php",
        type: "POST",
        dataType: "json",
        data: {
            task: 4,
            ent_tipo_doc: $("#venta_cli_tipo_doc").val(),
            ent_nro_doc: $("#venta_cli_nro_doc").val(),
            ent_nombres: nombres,
            ent_contacto: $("#venta_cli_telefono").val(),
            ent_direccion: $("#venta_cli_direccion").val(),
            ent_email: $("#venta_cli_email").val(),
            ent_marca: marca,
            rand: Math.random()
        },
        success: function (res) {
            if (res.estado !== "ok" || !parseInt(res.ent_id, 10)) return Swal.fire("Error", res.mensaje || "No se pudo registrar el cliente", "error");
            var clienteId = parseInt(res.ent_id, 10);
            $("#modalClienteVenta").modal("hide");
            AvisoIFact(res.mensaje || "Cliente registrado");
            $.get("../controller/entidad_cliente.php", { task: 2, rand: Math.random() }, function (datos) {
                clientes = datos;
                RenderClientes(datos, clienteId);
            }, "json");
        },
        error: function () { Swal.fire("Error", "No se pudo conectar con el servidor.", "error"); },
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
        ActualizarPagoVenta();
    }, "json");
}

// ===================== CARGAR CUENTAS =====================
function CargarCuentas() {
    $.get("../controller/cuenta_bancaria.php", { action: 1, rand: Math.random() }, function (res) {
        var sel = $("#ven_cba_id");
        sel.empty().append('<option value="0">Seleccione cuenta</option>');

        ventaCuentasPago = res && res.status === 1 && Array.isArray(res.data) ? res.data.filter(function(d){return parseInt(d.cba_estado)===1;}) : [];
        if (!ventaCuentasPago.length) { ActualizarPagoVenta(); return; }

        $.each(ventaCuentasPago, function (i, d) {
            var label = d.cba_banco + " - " + d.cba_moneda + " - " + d.cba_numero;
            sel.append('<option value="' + d.cba_id + '">' + label + '</option>');
        });
        ActualizarPagoVenta();
    }, "json");
}

function ActualizarPagoVenta() {
    $("#metodo_pago_seleccionado").text($("#ven_tpa_id option:selected").text() || "Seleccione método de pago");
    $("#cuenta_pago_seleccionada").text($("#ven_cba_id option:selected").text() || "Seleccione cuenta");
}

function AbrirSelectorPagoVenta(tipo) {
    var metodo = tipo === "metodo";
    var datos = metodo ? ventaMetodosPago : CuentasMetodoVenta();
    var actual = String($(metodo ? "#ven_tpa_id" : "#ven_cba_id").val() || "0");
    $("#selectorPagoVentaTitulo").text(metodo ? "Seleccionar método de pago" : "Seleccionar cuenta");
    $("#opcionesPagoVenta").html(datos.map(function(d){
        var id = String(metodo ? d.tpa_id : d.cba_id);
        var titulo = metodo ? d.tpa_nombre : d.cba_banco;
        var detalle = metodo ? "Medio de pago" : [d.cba_moneda,d.cba_propietario,d.cba_numero].filter(Boolean).join(" · ");
        return '<button type="button" class="venta-pago-opcion'+(actual===id?' activo':'')+'" onclick="SeleccionarPagoVenta(\''+tipo+'\','+Number(id)+')"><span class="venta-pago-icono"><i class="fa '+(metodo?'fa-credit-card':'fa-university')+'"></i></span><span><strong>'+EscaparVenta(titulo)+'</strong><small>'+EscaparVenta(detalle)+'</small></span><i class="fa fa-chevron-right"></i></button>';
    }).join('') || '<div class="venta-selector-vacio">No hay opciones disponibles.</div>');
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("selectorPagoVenta")).show();
}

function SeleccionarPagoVenta(tipo,id) {
    $(tipo === "metodo" ? "#ven_tpa_id" : "#ven_cba_id").val(String(id));
    if (tipo === 'metodo') {
        var cuentas = CuentasMetodoVenta(id);
        $('#ven_cba_id').val(cuentas.length === 1 ? String(cuentas[0].cba_id) : '0');
    }
    ActualizarPagoVenta();
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("selectorPagoVenta")).hide();
}

function CuentasMetodoVenta(tpaId) {
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
        toastr.warning("Este " + etiqueta + " ya está en la lista. Elimínelo para modificar la cantidad.");
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
    detalle.splice(idx, 1);
    RenderTabla();
    if (detalle.length === 0) $("#switch_tipo").prop("disabled", false);
}

// ===================== RENDERIZAR TABLA =====================
function RenderTabla() {
    ActualizarSaldoVenta();
    var tbody = $("#tbody_detalle");

    if (detalle.length === 0) {
        tbody.html('<tr><td colspan="6" class="text-center text-muted" style="padding:22px; font-size:.85rem;"><i class="fa fa-inbox me-1"></i> Sin ítems agregados</td></tr>');
        $("#total_items").text("0");
        $("#total_subtotal").text("0.00");
        $("#total_igv").text("0.00");
        $("#total_monto").text("0.00");
        return;
    }

    var html  = "";
    var total = 0;
    $.each(detalle, function (i, d) {
        total += d.subtotal;
        html += "<tr class='fade-in'>" +
            "<td>" + $("<span>").text(d.nombre).html() + "</td>" +
            "<td class='text-center'>" + d.cantidad.toFixed(3) + "</td>" +
            "<td class='text-center'>" + d.unidad + "</td>" +
            "<td class='text-center'>S/ " + d.precio_unit.toFixed(2) + "</td>" +
            "<td class='text-center'>S/ " + d.subtotal.toFixed(2) + "</td>" +
            "<td class='text-center'>" +
                "<button class='btn btn-sm btn-outline-danger' onclick='EliminarItem(" + i + ")' title='Eliminar'>" +
                    "<i class='fa fa-trash-alt'></i>" +
                "</button>" +
            "</td>" +
            "</tr>";
    });

    var subtotal_base = parseFloat((total / 1.18).toFixed(2));
    var igv           = parseFloat((total - subtotal_base).toFixed(2));

    tbody.html(html);
    $("#total_items").text(detalle.length);
    $("#total_subtotal").text(subtotal_base.toFixed(2));
    $("#total_igv").text(igv.toFixed(2));
    $("#total_monto").text(total.toFixed(2));
}

// ===================== GUARDAR VENTA =====================
function Guardar() {
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

    Swal.fire({
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
    }).then(function (result) {
        if (result.isDismissed) return;
        if (ventaFinalizada || ventaEnRegistro) return;
        ventaEnRegistro = true;

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
                                // task 14 confirma la reserva del correlativo, no que el PDF
                                // oficial ya haya sido generado por la central.
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
                    } else {
                        Swal.close();
                        var msg = "Venta #" + res.ven_id + " registrada.";
                        msg += "<br>Pagado: S/ " + Number(res.ven_pagado).toFixed(2) + " · Saldo: S/ " + Number(res.ven_saldo).toFixed(2);
                        if (res.ven_serie && res.ven_numero) {
                            var numFmt = String(res.ven_numero).padStart(8, '0');
                            msg = "Comprobante: <b>" + res.ven_serie + "-" + numFmt + "</b><br>" + msg;
                        }
                        Swal.fire({ title: "Venta Registrada", html: msg, icon: "success", confirmButtonText: "Aceptar" })
                            .then(function () { window.location.href = "list_venta.php"; });
                    }
                } else {
                    ventaEnRegistro = false;
                    Swal.fire("Error", res.mensaje, "error");
                }
            },
            error: function () {
                ventaEnRegistro = false;
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
