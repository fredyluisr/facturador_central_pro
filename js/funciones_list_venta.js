var tabla;
var ventasData = {}; // ven_id => row data
var notasVentaData = {}; // ven_id => notas emitidas
var conversionVentaCtx = null;

function EsNotaPedidoVenta(row) {
    return !row || Number(row.tde_id || 0) === 0 || !String(row.tde_codigo || "").trim();
}

$(document).ready(function () {
    $(".datetimepicker").datetimepicker({
        format: "DD-MM-YYYY",
        icons: { up: "fa fa-angle-up", down: "fa fa-angle-down", next: "fa fa-angle-right", previous: "fa fa-angle-left" }
    });

    var hoy   = moment();
    var desde = "01-" + hoy.format("MM-YYYY");
    var hasta = hoy.format("DD-MM-YYYY");
    $("#filtro_fecha_inicio").val(desde);
    $("#filtro_fecha_fin").val(hasta);

    CargarTabla(
        moment(desde, "DD-MM-YYYY").format("YYYY-MM-DD"),
        moment(hasta, "DD-MM-YYYY").format("YYYY-MM-DD")
    );
});

function CargarTabla(desde, hasta) {
    if (tabla) { tabla.destroy(); $("#tablaListado tbody").empty(); }

    tabla = $("#tablaListado").DataTable({
        destroy: true,
        pageLength: 25,
        language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json" },
        ajax: {
            url: "../controller/venta.php",
            data: { task: 8, desde: desde || "", hasta: hasta || "", rand: Math.random() },
            dataSrc: ""
        },
        columns: [
            { data: "ven_fecha" },
            {
                data: "ven_numero",
                render: function (d, type, row) {
                    if (row.ven_serie && row.ven_numero) {
                        var numFmt  = String(row.ven_numero).padStart(8, '0');
                        var codigo  = (row.tde_codigo || "").toUpperCase();
                        var cls     = codigo === "01" ? "comp-F" : (codigo === "03" ? "comp-B" : "comp-NV");
                        var esNotaPedido = EsNotaPedidoVenta(row);
                        var nombre  = esNotaPedido ? "NOTA DE VENTA" : (row.tde_nombre || "DOC");
                        var badge   = '<span class="comp-badge ' + cls + '">' + nombre + ' ' + row.ven_serie + '-' + numFmt + '</span>';
                        var emision = "";
                        if (row.ven_estado == 1) {
                            emision = !esNotaPedido && row.fac_id
                                ? '<br><span class="comp-emitido"><i class="fa fa-check-circle"></i> Emitido</span>'
                                : '<br><span class="comp-pendiente"><i class="fa fa-clock"></i> Pendiente</span>';
                        }
                        var notaCredito = row.nota_credito_documento
                            ? '<br><button type="button" class="venta-nc-vinculada" onclick="event.stopPropagation();VerComprobantePDF(\'../controller/facturacion.php?task=11&modo=inline&fac_id=' + Number(row.nota_credito_fac_id) + '\',\'Nota de crédito ' + TextoDetalleVenta(row.nota_credito_documento) + '\')"><i class="fa fa-file-invoice"></i> NC ' + TextoDetalleVenta(row.nota_credito_documento) + '</button>'
                            : '';
                        return badge + emision + notaCredito;
                    }
                    return "<span class='text-muted small'>Nota de venta sin numeración</span>";
                }
            },
            { data: "cliente", defaultContent: "<span class='text-muted'>General</span>" },
            {
                data: "total_items",
                className: "text-center",
                render: function (d) { return '<span class="badge-items">' + (parseInt(d, 10) || 0) + ' ítem(s)</span>'; }
            },
            {
                data: "ven_total",
                className: "text-end",
                render: function (d,type,row) { return BotonImportePago(d,type,row,false); }
            },
            {
                data: "ven_pagado", className: "text-end",
                render: function(d,type,row) { return BotonImportePago(d,type,row,false); }
            },
            {
                data: "ven_saldo", className: "text-end",
                render: function(d,type,row) { return BotonImportePago(d,type,row,true); }
            },
            {
                data: "ven_estado",
                className: "text-center",
                render: function (d, type, row) {
                    return d == 1 ? '<span class="badge-activo">Activo</span>' : '<span class="badge-anulado">' + (row.nota_credito_documento ? 'Anulado por NC' : 'Anulado') + '</span>';
                }
            },
            {
                data: "ven_id",
                className: "text-center text-nowrap",
                orderable: false,
                render: function (id, type, row) {
                    ventasData[id] = row;
                    var codigoDoc = String(row.tde_codigo || "").trim().padStart(2, "0");
                    var btns = '<button class="btn btn-sm btn-info me-1" onclick="AbrirOffcanvas(' + id + ')" title="Ver detalle"><i class="fa fa-list"></i></button>';
                    if (row.ven_estado == 1 && !row.fac_id && !EsNotaPedidoVenta(row) && Number(row.ven_saldo) <= 0) {
                        btns += '<button class="btn btn-sm btn-success me-1" onclick="EmitirCPE(' + id + ')" title="Emitir CPE"><i class="fa fa-paper-plane"></i></button>';
                    }
                    if (row.ven_estado == 1 && !row.fac_id && EsNotaPedidoVenta(row) && Number(row.ven_saldo) <= 0) {
                        btns += '<button class="btn btn-sm btn-success me-1" onclick="ConvertirNotaEnComprobante(' + id + ')" title="Convertir nota en boleta o factura"><i class="fa fa-file-invoice"></i></button>';
                    }
                    if (PuedeReenviarVenta(row)) {
                        btns += '<button class="btn btn-sm btn-success me-1" onclick="ReenviarCPEVenta(' + id + ')" title="Reenviar el mismo comprobante"><i class="fa fa-paper-plane"></i></button>';
                    }
                    if (row.ven_estado == 1 && row.fac_id && (codigoDoc === "01" || codigoDoc === "03")) {
                        btns += '<button class="btn btn-sm btn-outline-warning me-1" onclick="EmitirNotaCredito(' + id + ')" title="Nota de credito"><i class="fa fa-undo"></i></button>';
                        btns += '<button class="btn btn-sm btn-outline-secondary me-1" onclick="EmitirNotaDebito(' + id + ')" title="Nota de debito"><i class="fa fa-plus-circle"></i></button>';
                    }
                    if (row.ven_estado == 1 && !EsNotaPedidoVenta(row) && String(row.fac_estado_sunat) !== '0') {
                        btns += '<button class="btn btn-sm btn-warning me-1" onclick="VerificarSunat(' + id + ')" title="Verificación SUNAT"><i class="fa fa-sync-alt"></i></button>';
                    }
                    if (row.ven_estado == 1 && !row.fac_id) {
                        btns += '<button class="btn btn-sm btn-danger venta-accion-compacta" onclick="Anular(' + id + ')" title="Anular"><i class="fa fa-trash-alt"></i></button>';
                    }
                    return btns;
                }
            }
        ],
        order: [[0, "desc"]]
    });
}

function PuedeReenviarVenta(row) {
    if (!row || !row.fac_id || parseInt(row.fac_estado || 0, 10) === 2) return false;
    if (String(row.fac_estado_sunat || "") === "0" || parseInt(row.fac_plazo_vencido || 0, 10) === 1) return false;
    return ["rechazado", "anulado_sunat", "no_autorizado_sunat", "verificado_sunat_sin_cdr"].indexOf(row.fac_respuesta_estado) < 0;
}

function ReenviarCPEVenta(ven_id) {
    Swal.fire({title:"¿Reenviar comprobante?",text:"Se reprocesará el mismo documento y correlativo registrado en la central.",icon:"question",showCancelButton:true,confirmButtonText:"Sí, reenviar",confirmButtonColor:"#198754",cancelButtonText:"Cancelar"}).then(function(r){
        if(!r.isConfirmed)return;
        Swal.fire({title:"Reenviando comprobante...",allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
        $.ajax({type:"POST",url:"../controller/facturacion.php",dataType:"json",data:{task:6,ven_id:ven_id,reintentar:1,rand:Math.random()}})
            .done(function(res){if(tabla)tabla.ajax.reload(null,false);var api=res.api||{},mensaje=res.mensaje||api.descripcion||api.mensaje||api.error||"El comprobante continúa pendiente.";Swal.fire(res.estado==="ok"?"Comprobante confirmado":"No se pudo confirmar",mensaje,res.estado==="ok"?"success":"warning");})
            .fail(function(xhr){Swal.fire("Error de conexión",(xhr.responseJSON&&xhr.responseJSON.mensaje)||"No se pudo procesar el reenvío.","error");});
    });
}

function FiltrarFechas() {
    var rawDesde = $("#filtro_fecha_inicio").val();
    var rawHasta = $("#filtro_fecha_fin").val();
    CargarTabla(
        rawDesde ? moment(rawDesde, "DD-MM-YYYY").format("YYYY-MM-DD") : "",
        rawHasta ? moment(rawHasta, "DD-MM-YYYY").format("YYYY-MM-DD") : ""
    );
}

function AbrirOffcanvas(ven_id) {
    var row = ventasData[ven_id] || {};

    $("#oc_titulo").html('<i class="fa fa-receipt me-2"></i>Venta #' + ven_id);
    var total = Number(row.ven_total || 0);
    var subtotal = Number(row.ven_subtotal || (total / 1.18).toFixed(2));
    var igv = Number(row.ven_igv || (total - subtotal).toFixed(2));
    var metaHtml = CabeceraDetalleVenta(row) +
        '<div class="oc-section-title">Forma de pago</div><div id="oc_pagos"></div>' +
        '<div class="oc-section-title">Productos</div>' +
        '<div id="oc_items"><div class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin me-1"></i> Cargando...</div></div>';

    $("#oc_body").html(metaHtml);

    var oc = new bootstrap.Offcanvas(document.getElementById("offcanvasItems"));
    oc.show();
    CargarPagosDetalleVenta(ven_id,"#oc_pagos");

    $.get("../controller/venta.php", { task: 2, ven_id: ven_id, rand: Math.random() }, function (datos) {
        if (!datos || datos.length === 0) {
            $("#oc_items").html('<p class="text-muted text-center py-3">Sin ítems registrados.</p>');
            return;
        }

        var itemHtml = "";
        $.each(datos, function (i, d) {
            var sub = parseFloat(d.vde_subtotal);
            itemHtml +=
                '<div class="oc-item">' +
                    '<div class="d-flex justify-content-between align-items-start gap-2">' +
                        '<div>' +
                            '<div class="oc-item-nombre">' + TextoDetalleVenta(d.ins_nombre) + '</div>' +
                            '<div class="oc-item-detalle">' +
                                '<i class="fa fa-cubes me-1"></i>' +
                                parseFloat(d.vde_cantidad).toFixed(3) + ' ' + TextoDetalleVenta(d.unidad) +
                                ' &nbsp;·&nbsp; S/ ' + parseFloat(d.vde_precio_unit).toFixed(2) + ' c/u' +
                            '</div>' +
                        '</div>' +
                        '<span class="oc-item-subtotal">S/ ' + sub.toFixed(2) + '</span>' +
                    '</div>' +
                '</div>';
        });

        itemHtml +=
            '<div class="oc-breakdown">' +
                '<div class="oc-brow"><span class="oc-blabel">Subtotal (sin IGV)</span><span class="oc-bval">S/ ' + subtotal.toFixed(2) + '</span></div>' +
                '<div class="oc-brow"><span class="oc-blabel">IGV 18%</span><span class="oc-bval">S/ ' + igv.toFixed(2) + '</span></div>' +
                '<div class="oc-bdivider"></div>' +
                '<div class="oc-brow"><span class="oc-blabel-total">TOTAL</span><span class="oc-bval-total">S/ ' + total.toFixed(2) + '</span></div>' +
                '<div class="oc-brow"><span class="oc-blabel">Pagado</span><span class="oc-bval">S/ ' + Number(row.ven_pagado || 0).toFixed(2) + '</span></div>' +
                '<div class="oc-brow"><span class="oc-blabel">Saldo pendiente</span><span class="oc-bval">' + (row.ven_estado == 0 ? 'Venta anulada' : 'S/ ' + Number(row.ven_saldo || 0).toFixed(2)) + '</span></div>' +
            '</div>';

        var fac_id = (row && row.fac_id) ? row.fac_id : null;
        if (fac_id) {
            var urlTicket = "../controller/facturacion.php?task=11&modo=inline&fac_id=" + fac_id;
            var urlXml = "../controller/facturacion.php?task=11&tipo=xml&modo=attachment&fac_id=" + fac_id;
            var urlCdr = "../controller/facturacion.php?task=11&tipo=cdr&modo=attachment&fac_id=" + fac_id;
            itemHtml +=
                '<div class="mt-3 d-flex gap-2 flex-wrap">' +
                    '<button type="button" onclick="VerComprobantePDF(\'' + urlTicket + '\', \'Comprobante\')" class="btn btn-dark flex-grow-1" style="border-radius:10px;font-size:.86rem;">' +
                        '<i class="fa fa-print me-1"></i> Imprimir comprobante' +
                    '</button>' +
                    '<a href="' + urlXml + '" class="btn btn-outline-dark" style="border-radius:10px;font-size:.86rem;" title="Descargar XML"><i class="fa fa-code me-1"></i> XML</a>' +
                    '<a href="' + urlCdr + '" class="btn btn-outline-dark" style="border-radius:10px;font-size:.86rem;" title="Descargar CDR"><i class="fa fa-file-archive me-1"></i> CDR</a>' +
                '</div>' +
                '<div id="oc_notas" class="oc-notas mt-3">' +
                    '<div class="text-center text-muted small py-2"><i class="fa fa-spinner fa-spin me-1"></i> Cargando notas...</div>' +
                '</div>';
        }
        var serieNota = row.ven_nota_serie || (EsNotaPedidoVenta(row) ? row.ven_serie : '');
        var numeroNota = row.ven_nota_numero || (EsNotaPedidoVenta(row) ? row.ven_numero : '');
        var comprobanteAceptado = fac_id && parseInt(row.fac_estado, 10) === 2;
        if (serieNota && numeroNota && !comprobanteAceptado) {
            var urlNotaPedido = "venta_pdf_provisional.php?tipo=nota&ven_id=" + encodeURIComponent(ven_id);
            var numeroNotaPedido = serieNota + '-' + String(numeroNota).padStart(8, '0');
            itemHtml +=
                '<div class="mt-3">' +
                    '<button type="button" onclick="VerComprobantePDF(\'' + urlNotaPedido + '\', \'Nota de venta ' + numeroNotaPedido + '\')" class="btn btn-dark w-100" style="border-radius:10px;font-size:.86rem;">' +
                        '<i class="fa fa-print me-1"></i> Imprimir nota de venta' +
                    '</button>' +
                '</div>';
        }

        $("#oc_items").html(itemHtml);
        if (fac_id) CargarNotasVenta(ven_id);
    }, "json");
}

function CargarNotasVenta(ven_id) {
    $.get("../controller/facturacion.php", { task: 10, ven_id: ven_id, rand: Math.random() }, function (res) {
        if (!res || res.estado !== "ok" || !res.total) {
            $("#oc_notas").html("");
            notasVentaData[ven_id] = [];
            return;
        }

        notasVentaData[ven_id] = res.notas || [];
        var resumen = [];
        if (res.total_credito > 0) resumen.push(res.total_credito + " NC");
        if (res.total_debito > 0) resumen.push(res.total_debito + " ND");

        $("#oc_notas").html(
            '<div class="oc-notas-box">' +
                '<div>' +
                    '<div class="oc-notas-label">Notas emitidas</div>' +
                    '<div class="oc-notas-count">' + resumen.join(" | ") + '</div>' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-dark" onclick="VerNotasVenta(' + ven_id + ')">' +
                    '<i class="fa fa-eye me-1"></i> Ver' +
                '</button>' +
            '</div>'
        );
    }, "json");
}

function VerNotasVenta(ven_id) {
    var notas = notasVentaData[ven_id] || [];
    if (!notas.length) {
        Swal.fire("Sin notas", "Esta venta no tiene notas emitidas.", "info");
        return;
    }

    var html = '<div class="text-start">';
    $.each(notas, function (i, nota) {
        var urlTicket = "../controller/facturacion.php?task=11&modo=inline&fac_id=" + nota.fac_id;
        var badgeClass = nota.fac_tipo_doc === "07" ? "badge bg-warning text-dark" : "badge bg-secondary";
        var nombreNota = nota.fac_tipo_doc === "08" ? "Nota de débito" : "Nota de crédito";
        html +=
            '<div class="nota-row">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                    '<div>' +
                        '<div><span class="' + badgeClass + '">' + nota.tipo_nombre + '</span> <b>' + nota.documento + '</b></div>' +
                        '<div class="text-success small fw-bold mt-1"><i class="fa fa-check-circle me-1"></i>Aceptada por SUNAT</div>' +
                        '<div class="text-muted small mt-1">Afecta: ' + TextoDetalleVenta(nota.documento_referencia || '-') + '</div>' +
                        '<div class="text-muted small">Motivo: ' + TextoDetalleVenta(nota.fac_motivo_descripcion || '-') + '</div>' +
                        '<div class="small fw-bold mt-1">Total: S/ ' + parseFloat(nota.total || 0).toFixed(2) + '</div>' +
                    '</div>' +
                    '<div class="flex-shrink-0">' +
                        '<button type="button" onclick="VerComprobantePDF(\'' + urlTicket + '\', \'' + nombreNota + '\')" class="btn btn-dark px-3" title="Ver e imprimir ' + nombreNota.toLowerCase() + '"><i class="fa fa-print me-1"></i> Imprimir ' + (nota.fac_tipo_doc === "08" ? "ND" : "NC") + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
    });
    html += '</div>';

    Swal.fire({
        title: "Notas de la venta",
        html: html,
        width: 560,
        confirmButtonText: "Cerrar"
    });
}

function Anular(ven_id) {
    var venta = ventasData[ven_id] || {};
    if (venta.fac_id) {
        var aceptadoSunat = String(venta.fac_estado_sunat || "") === "0" || parseInt(venta.fac_estado || 0, 10) === 2;
        return Swal.fire({
            title: aceptadoSunat ? "Comprobante electrónico emitido" : "Verifique el comprobante",
            text: aceptadoSunat
                ? "Esta venta tiene un comprobante electrónico asociado. Para anular la operación debe emitir una nota de crédito."
                : "Esta venta tiene un comprobante electrónico asociado. Verifique primero su estado en SUNAT.",
            icon: "info",
            confirmButtonText: "Entendido"
        });
    }
    Swal.fire({
        title: "¿Anular esta venta?",
        text: "Se revertirá el stock descontado. Esta acción no se puede deshacer.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, anular",
        cancelButtonText: "Cancelar"
    }).then(function (result) {
        if (result.isConfirmed) {
            $.post("../controller/venta.php", { task: 7, ven_id: ven_id, rand: Math.random() }, function (res) {
                if (res.estado === "ok") {
                    AvisoIFact(res.mensaje);
                    tabla.ajax.reload();
                } else {
                    Swal.fire("Error", res.mensaje, "error");
                }
            }, "json");
        }
    });
}

function EmitirCPE(ven_id) {
    if (ventasData[ven_id] && Number(ventasData[ven_id].ven_saldo) > 0) {
        Swal.fire('Saldo pendiente', 'Complete el pago de la venta antes de emitir el comprobante.', 'warning'); return;
    }
    Swal.fire({
        title: "¿Emitir Comprobante Electrónico?",
        text: "Se registrará el comprobante en el sistema de facturación.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, emitir",
        cancelButtonText: "Cancelar"
    }).then(function (result) {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: "Procesando...",
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            type:     "POST",
            url:      "../controller/facturacion.php",
            dataType: "json",
            data:     { task: 6, ven_id: ven_id, rand: Math.random() },
            success: function (res) {
                Swal.close();
                if (res.estado === "ok") {
                    tabla.ajax.reload();

                    var emisionOk = res.api && res.api.success;
                    var confirmadoSunat = emisionOk && res.api.verificado_sunat;
                    var fac_id    = res.fac_id || null;

                    if (emisionOk && fac_id) {
                        var urlTicket = "../controller/facturacion.php?task=11&modo=inline&fac_id=" + fac_id;

                        Swal.fire({
                            title: confirmadoSunat
                                ? '<i class="fa fa-search text-info"></i> CPE confirmado en SUNAT'
                                : '<i class="fa fa-check-circle text-success"></i> CPE Emitido',
                            html:
                                '<div class="' + (confirmadoSunat ? 'alert alert-info' : 'text-muted') + ' mb-3" style="font-size:.9rem;">' +
                                    (res.api.descripcion || "Comprobante aceptado por SUNAT") +
                                '</div>' +
                                '<div>' +
                                    '<button type="button" onclick="VerComprobantePDF(\'' + urlTicket + '\', \'Comprobante\')" class="btn btn-dark px-5">' +
                                        '<i class="fa fa-print me-1"></i> Imprimir comprobante' +
                                    '</button>' +
                                '</div>',
                            showConfirmButton: false,
                            showCloseButton: true,
                            allowOutsideClick: true
                        });
                    } else {
                        var apiMsg = res.api && (res.api.mensaje || res.api.error) ? (res.api.mensaje || res.api.error) : (res.mensaje || "No se pudo confirmar el comprobante.");
                        var sunatMsg = res.api && res.api.sunat_verificacion && res.api.sunat_verificacion.descripcion
                            ? "<br><b>SUNAT:</b> " + res.api.sunat_verificacion.descripcion
                            : "";
                        Swal.fire({
                            title: "Comprobante no confirmado",
                            html: "<div class='text-start'><b>QPSE/API:</b> " + apiMsg + sunatMsg + "</div>",
                            icon: "warning",
                            confirmButtonText: "Entendido"
                        });
                    }
                } else {
                    Swal.fire("Error", res.mensaje, "error");
                }
            },
            error: function () {
                Swal.close();
                Swal.fire("Error de conexión", "No se pudo conectar con el servidor.", "error");
            }
        });
    });
}

function ConvertirNotaEnComprobante(ven_id) {
    var venta = ventasData[ven_id] || {};
    if (Number(venta.ven_saldo || 0) > 0) {
        Swal.fire('Saldo pendiente', 'Complete el pago antes de emitir el comprobante.', 'warning');
        return;
    }
    $.when(
        $.getJSON('../controller/tipo_doc_elect.php', { task: 8, rand: Math.random() }),
        $.getJSON('../controller/entidad_cliente.php', { task: 2, rand: Math.random() })
    ).done(function (docsResp, clientesResp) {
        var docs = docsResp[0] || [];
        var clientes = clientesResp[0] || [];
        var boleta = docs.find(function (d) { return String(d.tde_codigo) === '03'; });
        conversionVentaCtx = {
            ven_id: ven_id,
            docs: docs.filter(function (d) { return ['01','03'].indexOf(String(d.tde_codigo)) >= 0; }),
            clientes: clientes,
            tde_id: Number(boleta ? boleta.tde_id : (docs[0] && docs[0].tde_id)) || 0,
            ent_id: 0
        };
        MostrarModalConversionVenta();
    }).fail(function () { Swal.fire('Error de conexión', 'No se pudieron cargar clientes y comprobantes.', 'error'); });
}

function MostrarModalConversionVenta() {
    if (!conversionVentaCtx) return;
    var ctx = conversionVentaCtx;
    var cliente = ctx.clientes.find(function (c) { return Number(c.ent_id) === Number(ctx.ent_id); });
    var docsHtml = ctx.docs.map(function (d) {
        return '<button type="button" class="venta-emision-documento' + (Number(d.tde_id) === Number(ctx.tde_id) ? ' activo' : '') + '" onclick="SeleccionarDocumentoConversionVenta(' + Number(d.tde_id) + ')"><span><i class="fa fa-file-invoice"></i></span><strong>' + TextoDetalleVenta(d.tde_nombre || 'Comprobante') + '</strong><i class="fa fa-check"></i></button>';
    }).join('');
    var nombre = cliente ? cliente.ent_nombres : 'Cliente general / sin registro';
    var numero = cliente ? (cliente.ent_nro_doc || 'Sin documento') : 'Disponible para boleta';
    var tipo = cliente ? (Number(cliente.ent_tipo_doc) === 6 ? 'RUC' : 'DNI') : 'GENERAL';
    Swal.fire({
        title: 'Emitir comprobante', width: 760, showConfirmButton: false, showCloseButton: true,
        customClass: { popup: 'venta-emision-popup', closeButton: 'venta-comprobante-cerrar' },
        html: '<div class="venta-emision-modal">' +
            '<section class="venta-emision-cliente-seccion"><label>Cliente</label><button type="button" class="venta-emision-cliente" onclick="AbrirSelectorClienteConversionVenta()"><span class="venta-emision-cliente-icono"><i class="fa fa-user"></i></span><span class="venta-emision-cliente-datos"><strong>' + TextoDetalleVenta(nombre) + '</strong><small>' + TextoDetalleVenta(numero) + '</small></span><em>' + tipo + '</em><i class="fa fa-search"></i></button><small id="conversion_venta_error" class="venta-emision-error"></small></section>' +
            '<section><label>Tipo de comprobante</label><div class="venta-emision-documentos">' + docsHtml + '</div><small class="venta-emision-ayuda">La factura requiere un cliente con RUC.</small></section>' +
            '<section class="venta-emision-resumen"><div><span>Venta</span><strong>#' + Number(ctx.ven_id) + '</strong></div><div><span>Estado</span><strong>Nota registrada</strong></div><div class="total"><span>Total</span><strong>S/ ' + Number((ventasData[ctx.ven_id] || {}).ven_total || 0).toFixed(2) + '</strong></div></section>' +
            '<button type="button" class="venta-emision-confirmar" onclick="ConfirmarConversionVenta()"><i class="fa fa-file-invoice me-2"></i> Reservar y emitir</button>' +
            '</div>'
    });
}

function SeleccionarDocumentoConversionVenta(tdeId) {
    if (!conversionVentaCtx) return;
    conversionVentaCtx.tde_id = Number(tdeId) || 0;
    $('.venta-emision-documento').removeClass('activo');
    $('.venta-emision-documento[onclick*="(' + conversionVentaCtx.tde_id + ')"]').addClass('activo');
    $('#conversion_venta_error').text('');
}

function AbrirSelectorClienteConversionVenta() {
    if (!conversionVentaCtx) return;
    MostrarSelectorClienteConversionVenta('');
}

function MostrarSelectorClienteConversionVenta(filtro) {
    var ctx = conversionVentaCtx;
    if (!ctx) return;
    var termino = String(filtro || '').toLowerCase();
    var lista = ctx.clientes.filter(function (c) {
        return !termino || String(c.ent_nombres || '').toLowerCase().indexOf(termino) >= 0 || String(c.ent_nro_doc || '').indexOf(termino) >= 0;
    });
    var filas = '<button type="button" class="venta-selector-opcion' + (Number(ctx.ent_id) === 0 ? ' activo' : '') + '" onclick="SeleccionarClienteConversionVenta(0)"><strong>Cliente general / sin registro</strong><span>Disponible para boleta</span></button>' + lista.map(function (c) {
        return '<button type="button" class="venta-selector-opcion' + (Number(ctx.ent_id) === Number(c.ent_id) ? ' activo' : '') + '" onclick="SeleccionarClienteConversionVenta(' + Number(c.ent_id) + ')"><strong>' + TextoDetalleVenta(c.ent_nombres) + '</strong><span>' + TextoDetalleVenta(c.ent_nro_doc || 'Sin documento') + '</span></button>';
    }).join('');
    Swal.fire({
        title: 'Seleccionar cliente', width: 620, showConfirmButton: false, showCloseButton: true,
        customClass: { popup: 'venta-selector-popup', closeButton: 'venta-comprobante-cerrar' },
        html: '<div class="venta-selector-busqueda"><i class="fa fa-search"></i><input id="conversion_buscar_cliente" type="text" placeholder="Buscar por nombre o documento..." value="' + TextoDetalleVenta(filtro || '') + '"></div><div id="conversion_clientes_lista" class="venta-selector-lista">' + filas + '</div>',
        didOpen: function () {
            $('#conversion_buscar_cliente').trigger('focus').on('input', function () {
                var valor = this.value;
                var filtrados = ctx.clientes.filter(function (c) { return !valor || String(c.ent_nombres || '').toLowerCase().indexOf(valor.toLowerCase()) >= 0 || String(c.ent_nro_doc || '').indexOf(valor) >= 0; });
                $('#conversion_clientes_lista').html('<button type="button" class="venta-selector-opcion" onclick="SeleccionarClienteConversionVenta(0)"><strong>Cliente general / sin registro</strong><span>Disponible para boleta</span></button>' + filtrados.map(function (c) { return '<button type="button" class="venta-selector-opcion" onclick="SeleccionarClienteConversionVenta(' + Number(c.ent_id) + ')"><strong>' + TextoDetalleVenta(c.ent_nombres) + '</strong><span>' + TextoDetalleVenta(c.ent_nro_doc || 'Sin documento') + '</span></button>'; }).join(''));
            });
        }
    });
}

function SeleccionarClienteConversionVenta(entId) {
    if (!conversionVentaCtx) return;
    conversionVentaCtx.ent_id = Number(entId) || 0;
    MostrarModalConversionVenta();
}

function ConfirmarConversionVenta() {
    var ctx = conversionVentaCtx;
    if (!ctx) return;
    var doc = ctx.docs.find(function (d) { return Number(d.tde_id) === Number(ctx.tde_id); });
    var cliente = ctx.clientes.find(function (c) { return Number(c.ent_id) === Number(ctx.ent_id); });
    if (!doc) { $('#conversion_venta_error').text('Seleccione un tipo de comprobante.'); return; }
    if (String(doc.tde_codigo) === '01' && (!cliente || Number(cliente.ent_tipo_doc) !== 6)) { $('#conversion_venta_error').text('La factura requiere un cliente con RUC.'); return; }
    Swal.fire({title:'Preparando comprobante...',allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
    $.post('../controller/venta.php', { task:13, ven_id:ctx.ven_id, ent_id:ctx.ent_id, tde_id:ctx.tde_id, rand:Math.random() }, null, 'json')
                .done(function (preparada) {
                    if (!preparada || preparada.estado !== 'ok') { Swal.fire('No se pudo preparar', (preparada && preparada.mensaje) || 'La nota permanece disponible.', 'warning'); return; }
                    $.post('../controller/facturacion.php', { task:14, ven_id:ctx.ven_id, rand:Math.random() }, null, 'json')
                        .done(function (reserva) {
                            if (!(reserva && reserva.fac_id && reserva.serie && reserva.correlativo)) { Swal.fire('Numeración pendiente', (reserva && reserva.mensaje) || 'La nota permanece disponible y la emisión puede reintentarse.', 'warning'); tabla.ajax.reload(null,false); return; }
                            $.post('../controller/facturacion.php', { task:6, ven_id:ctx.ven_id, reintentar:1, rand:Math.random() }, null, 'json')
                                .always(function () { tabla.ajax.reload(null,false); });
                            var numero = reserva.serie + '-' + String(reserva.correlativo).padStart(8,'0');
                            Swal.close();
                            VerComprobantePDF('venta_pdf_provisional.php?ven_id=' + encodeURIComponent(ctx.ven_id) + '&actualizado=' + Date.now(), 'Comprobante ' + numero + ' · pendiente de confirmación');
                        }).fail(function () { Swal.fire('Numeración pendiente', 'La nota permanece disponible y la emisión puede reintentarse.', 'warning'); tabla.ajax.reload(null,false); });
                }).fail(function () { Swal.fire('Error de conexión', 'No se pudo preparar el comprobante. La nota permanece disponible.', 'error'); });
}

function MostrarResultadoNota(res, tituloOk, ven_id) {
    if (!res || res.estado !== "ok") {
        var esDebitoError = String(tituloOk || '').toLowerCase().indexOf('debito') >= 0 || String(tituloOk || '').toLowerCase().indexOf('débito') >= 0;
        MostrarFalloNotaElectronica(esDebitoError ? 'débito' : 'crédito', res && res.mensaje ? res.mensaje : 'La central no respondió.');
        return;
    }

    if (tabla) tabla.ajax.reload(null, false);
    if (ven_id) CargarNotasVenta(ven_id);

    var api = res.api || {};
    var okApi = !!api.success;
    var esDebito = String(res.tipo_nota || '') === '08' || String(tituloOk || '').toLowerCase().indexOf('debito') >= 0 || String(tituloOk || '').toLowerCase().indexOf('débito') >= 0;
    var nombreNota = esDebito ? 'nota de débito' : 'nota de crédito';
    var nombreNotaTitulo = esDebito ? 'Nota de débito' : 'Nota de crédito';
    var detalle = okApi
        ? "Aceptada por SUNAT"
        : (api.mensaje || api.error || "La nota se guardo localmente, pero la API no confirmo la emision.");
    var pdfLinks = "";
    if (res.fac_id) {
        var urlTicket = "../controller/facturacion.php?task=11&modo=inline&fac_id=" + res.fac_id;
        pdfLinks =
            '<div class="mt-4">' +
                '<button type="button" onclick="VerComprobantePDF(\'' + urlTicket + '\', \'' + nombreNotaTitulo + '\')" class="nota-resultado-imprimir">' +
                    '<i class="fa fa-print"></i><span><strong>Ver e imprimir ' + nombreNota + '</strong><small>Abre el comprobante electrónico emitido</small></span>' +
                '</button>' +
            '</div>';
    }

    Swal.fire({
        icon: okApi ? "success" : "warning",
        title: okApi ? (res.ya_existia ? "Nota ya emitida" : tituloOk) : "Nota no confirmada",
        html:
            '<div class="nota-resultado">' +
                (res.ya_existia ? '<div class="nota-resultado-aviso"><i class="fa fa-info-circle"></i> ' + (res.stock_devuelto ? 'Esta nota ya existía. No se emitió otra; ahora se aplicó la devolución de stock que estaba pendiente.' : 'Esta nota ya existía. No se realizó una nueva emisión ni una segunda devolución de stock.') + '</div>' : '') +
                '<div class="nota-resultado-sunat"><i class="fa fa-check-circle"></i><span><strong>' + TextoDetalleVenta(detalle) + '</strong><small>Código SUNAT: ' + TextoDetalleVenta(api.codigo_sunat === undefined || api.codigo_sunat === '' ? '0' : api.codigo_sunat) + '</small></span></div>' +
                '<div class="nota-resultado-datos">' +
                    '<div><span>' + nombreNotaTitulo + '</span><strong>' + TextoDetalleVenta(api.documento || res.comprobante || "-") + '</strong></div>' +
                    '<div><span>Factura o boleta afectada</span><strong>' + TextoDetalleVenta(res.referencia || "-") + '</strong></div>' +
                    '<div><span>Motivo</span><strong>' + TextoDetalleVenta(res.motivo || "Anulación de la operación") + '</strong></div>' +
                    '<div><span>Total</span><strong>S/ ' + Number(res.total || 0).toFixed(2) + '</strong></div>' +
                '</div>' +
                (res.venta_anulada ? '<div class="nota-resultado-stock"><i class="fa fa-box-open"></i> Venta anulada y stock devuelto al inventario.</div>' : '') +
            '</div>' +
            pdfLinks,
        confirmButtonText: "Cerrar",
        timer: 0,
        allowOutsideClick: false,
        customClass: { popup: 'nota-resultado-popup' }
    });
}

function MostrarFalloNotaElectronica(tipo, mensaje) {
    var esCredito = tipo === 'crédito';
    Swal.fire({
        icon: 'warning',
        title: 'Nota de ' + tipo + ' no emitida',
        html:
            '<div class="text-start">' +
                '<p>' + TextoDetalleVenta(mensaje || 'No fue posible comunicarse con la central.') + '</p>' +
                '<div class="alert alert-light border mb-0">' +
                    '<strong>No se generó una nueva nota electrónica.</strong><br>' +
                    'La factura o boleta original permanece sin cambios' +
                    (esCredito ? ' y el stock no fue devuelto' : '') + '. Puedes volver a intentarlo cuando se restablezca la conexión.' +
                '</div>' +
            '</div>',
        confirmButtonText: 'Entendido',
        timer: 0,
        allowOutsideClick: false
    });
}

function EmitirNotaCredito(ven_id) {
    var row = ventasData[ven_id] || {};
    var comprobante = row.ven_serie && row.ven_numero
        ? row.ven_serie + '-' + String(row.ven_numero).padStart(8, '0')
        : 'Venta #' + ven_id;

    Swal.fire({
        title: "Emitir nota de credito",
        html:
            '<div class="text-start" style="font-size:.92rem;">' +
                '<div class="mb-2">Se emitirá una nota de crédito por anulación total de la operación.</div>' +
                '<div class="mb-2"><b>Referencia:</b> ' + comprobante + '</div>' +
                '<div class="mb-2"><b>Total:</b> S/ ' + Number(row.ven_total || 0).toFixed(2) + '</div>' +
                '<div class="alert alert-warning py-2 small"><i class="fa fa-box-open me-1"></i> Cuando SUNAT la acepte, la venta quedará anulada y el stock será devuelto automáticamente.</div>' +
                '<label class="form-label mt-2">Motivo</label>' +
                '<input id="nc_motivo" class="form-control" value="Anulación de la operación">' +
            '</div>',
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Emitir NC",
        cancelButtonText: "Cancelar",
        preConfirm: function () {
            var motivo = ($("#nc_motivo").val() || "").trim();
            if (!motivo) {
                Swal.showValidationMessage("Ingrese el motivo");
                return false;
            }
            return { motivo: motivo };
        }
    }).then(function (result) {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: "Procesando nota...",
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            type: "POST",
            url: "../controller/facturacion.php",
            dataType: "json",
            data: {
                task: 8,
                ven_id: ven_id,
                motivo_codigo: "01",
                motivo_descripcion: result.value.motivo,
                rand: Math.random()
            },
            success: function (res) {
                Swal.close();
                MostrarResultadoNota(res, "Nota de crédito emitida", ven_id);
            },
            error: function (xhr) {
                Swal.close();
                MostrarFalloNotaElectronica('crédito', xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : 'No se pudo conectar con la central.');
            }
        });
    });
}

function EmitirNotaDebito(ven_id) {
    var row = ventasData[ven_id] || {};
    var comprobante = row.ven_serie && row.ven_numero
        ? row.ven_serie + '-' + String(row.ven_numero).padStart(8, '0')
        : 'Venta #' + ven_id;

    Swal.fire({
        title: "Emitir nota de debito",
        html:
            '<div class="text-start" style="font-size:.92rem;">' +
                '<div class="mb-2"><b>Referencia:</b> ' + comprobante + '</div>' +
                '<label class="form-label">Monto total</label>' +
                '<input id="nd_monto" type="number" step="0.01" min="0.01" class="form-control mb-2" placeholder="0.00">' +
                '<label class="form-label">Motivo</label>' +
                '<input id="nd_motivo" class="form-control mb-2" value="Intereses por mora">' +
                '<label class="form-label">Descripcion</label>' +
                '<input id="nd_descripcion" class="form-control" value="Intereses por mora - ' + comprobante + '">' +
            '</div>',
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Emitir ND",
        cancelButtonText: "Cancelar",
        preConfirm: function () {
            var monto = parseFloat($("#nd_monto").val());
            var motivo = ($("#nd_motivo").val() || "").trim();
            var descripcion = ($("#nd_descripcion").val() || "").trim();
            if (!monto || monto <= 0) {
                Swal.showValidationMessage("Ingrese un monto mayor a cero");
                return false;
            }
            if (!motivo) {
                Swal.showValidationMessage("Ingrese el motivo");
                return false;
            }
            if (!descripcion) {
                Swal.showValidationMessage("Ingrese la descripcion");
                return false;
            }
            return { monto: monto, motivo: motivo, descripcion: descripcion };
        }
    }).then(function (result) {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: "Procesando nota...",
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            type: "POST",
            url: "../controller/facturacion.php",
            dataType: "json",
            data: {
                task: 9,
                ven_id: ven_id,
                monto: result.value.monto,
                motivo_codigo: "01",
                motivo_descripcion: result.value.motivo,
                descripcion: result.value.descripcion,
                rand: Math.random()
            },
            success: function (res) {
                Swal.close();
                MostrarResultadoNota(res, "Nota de debito emitida");
            },
            error: function (xhr) {
                Swal.close();
                MostrarFalloNotaElectronica('débito', xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : 'No se pudo conectar con la central.');
            }
        });
    });
}

function VerificarSunat(ven_id) {
    var row = ventasData[ven_id] || {};
    var comprobante = row.ven_serie && row.ven_numero
        ? row.ven_serie + '-' + String(row.ven_numero).padStart(8, '0')
        : 'Venta #' + ven_id;

    Swal.fire({
        title: "Consultando SUNAT...",
        html: "<div class='text-muted'>Consultando " + comprobante + "</div>",
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: function () { Swal.showLoading(); }
    });

    $.ajax({
        type: "POST",
        url: "../controller/facturacion.php",
        dataType: "json",
        data: { task: 7, ven_id: ven_id, rand: Math.random() },
        success: function (res) {
            if (!res || res.estado !== "ok") {
                Swal.fire("Error", res && res.mensaje ? res.mensaje : "No se pudo verificar el comprobante.", "error");
                return;
            }

            var v = res.verificacion || {};
            var existe = !!v.existe_sunat;
            var icon = existe ? "success" : "warning";
            var titulo = existe ? "Comprobante en SUNAT" : "No confirmado en SUNAT";
            var descripcion = v.descripcion || res.mensaje || "Sin descripcion";
            var estadoCp = v.estado_cp || "-";
            var estadoRuc = v.estado_ruc || "-";
            var condDomi = v.cond_domi || "-";
            var actualizado = res.actualizado
                ? '<div class="alert alert-success py-2 mt-3 mb-0">Estado actualizado en el sistema.</div>'
                : '';
            var cdr = res.actualizado
                ? (res.cdr_recuperado
                    ? '<div class="alert alert-success py-2 mt-2 mb-0">CDR recuperado y guardado' + (res.cdr_nombre ? ': ' + res.cdr_nombre : '.') + '</div>'
                    : '<div class="alert alert-warning py-2 mt-2 mb-0">SUNAT confirmó el comprobante, pero no se recuperó el CDR: ' + (res.advertencia_cdr || 'el servicio central no lo devolvió') + '.</div>')
                : '';

            Swal.fire({
                icon: icon,
                title: titulo,
                html:
                    '<div class="text-start" style="font-size:.92rem;">' +
                        '<div class="mb-2"><b>Comprobante:</b> ' + (v.documento || res.comprobante || comprobante) + '</div>' +
                        '<div class="mb-2"><b>Resultado:</b> ' + descripcion + '</div>' +
                        '<div class="d-flex justify-content-between border-top pt-2 mt-2"><span>Estado CP</span><b>' + estadoCp + '</b></div>' +
                        '<div class="d-flex justify-content-between"><span>Estado RUC</span><b>' + estadoRuc + '</b></div>' +
                        '<div class="d-flex justify-content-between"><span>Cond. domicilio</span><b>' + condDomi + '</b></div>' +
                        actualizado + cdr +
                    '</div>',
                confirmButtonText: "Aceptar"
            });

            if (res.actualizado && tabla) tabla.ajax.reload(null, false);
        },
        error: function () {
            Swal.fire("Error de conexion", "No se pudo conectar con el servidor.", "error");
        }
    });
}
