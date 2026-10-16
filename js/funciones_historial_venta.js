var tabla;
var ventasData = {};

$(document).ready(function () {
    // Inicializar datetimepicker (formato DD-MM-YYYY, solo fecha)
    $(".datetimepicker").datetimepicker({
        format: "DD-MM-YYYY",
        icons: {
            up:    "fa fa-angle-up",
            down:  "fa fa-angle-down",
            next:  "fa fa-angle-right",
            previous: "fa fa-angle-left"
        }
    });

    // Fechas por defecto: mes actual en formato DD-MM-YYYY para el picker
    var hoy   = moment();
    var desde = "01-" + hoy.format("MM-YYYY");
    var hasta = hoy.format("DD-MM-YYYY");
    $("#filtro_desde").val(desde);
    $("#filtro_hasta").val(hasta);

    CargarTabla(
        moment(desde, "DD-MM-YYYY").format("YYYY-MM-DD"),
        moment(hasta, "DD-MM-YYYY").format("YYYY-MM-DD")
    );
});

function CargarTabla(desde, hasta) {
    if (tabla) {
        tabla.destroy();
        $("#tablaHistorial tbody").empty();
    }

    tabla = $("#tablaHistorial").DataTable({
        destroy: true,
        pageLength: 25,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
        },
        ajax: {
            url: "../controller/venta.php",
            data: { task: 8, desde: desde || "", hasta: hasta || "", rand: Math.random() },
            dataSrc: function (json) {
                var total = 0;
                $.each(json, function (i, d) {
                    if (d.ven_estado == 1) total += parseFloat(d.ven_total);
                });
                $("#total_visible").text("S/ " + total.toFixed(2));
                return json;
            }
        },
        columns: [
            { data: "ven_fecha" },
            {
                data: "ven_numero",
                render: function(d, type, row) {
                    if (row.ven_serie && row.ven_numero) {
                        var numFormat = String(row.ven_numero).padStart(8, '0');
                        var esNotaPedido = !row.tde_codigo;
                        var docNombre = esNotaPedido ? "NOTA DE VENTA" : (row.tde_nombre ? row.tde_nombre.toUpperCase() : "DOC");
                        
                        var colorClass = "bg-light text-dark border";
                        if (docNombre.includes("FACTURA")) {
                            colorClass = "bg-primary text-white";
                        } else if (docNombre.includes("BOLETA")) {
                            colorClass = "bg-info text-white";
                        } else if (docNombre.includes("NOTA")) {
                            colorClass = "bg-dark text-white";
                        }
                        
                        var notaCredito = row.nota_credito_documento
                            ? '<br><button type="button" class="venta-nc-vinculada" onclick="VerComprobantePDF(\'../controller/facturacion.php?task=11&modo=inline&fac_id=' + Number(row.nota_credito_fac_id) + '\',\'Nota de crédito ' + row.nota_credito_documento + '\')"><i class="fa fa-file-invoice"></i> NC ' + row.nota_credito_documento + '</button>'
                            : '';
                        return '<span class="badge ' + colorClass + '" style="font-size:11px; font-weight:600; padding:4px 8px; border-radius:6px; letter-spacing:0.5px;">' + 
                               docNombre + ' ' + row.ven_serie + '-' + numFormat + '</span>' + notaCredito;
                    }
                    return "<span class='text-muted small'>Nota de venta sin numeración</span>";
                }
            },
            { data: "cliente", defaultContent: "<span class='text-muted'>General</span>" },
            {
                data: "ven_total",
                className: "text-right",
                render: function (d) { return "S/ " + parseFloat(d).toFixed(2); }
            },
            {
                data: "ven_estado",
                className: "text-center",
                render: function (d, type, row) {
                    return d == 1
                        ? '<span class="badge-activo">Activo</span>'
                        : '<span class="badge-anulado">' + (row.nota_credito_documento ? 'Anulado por NC' : 'Anulado') + '</span>';
                }
            },
            {
                data: "fac_estado",
                className: "text-center",
                render: function (estado, type, row) {
                    if (!row.tde_codigo) {
                        return '<span class="badge-sunat-na"><i class="fa fa-minus-circle"></i> No aplica</span>';
                    }
                    var aceptado = parseInt(estado, 10) === 2;
                    var etiqueta = EtiquetaEstadoSunat(row);
                    return '<button type="button" class="sunat-status-btn ' + (aceptado ? 'is-ok' : 'is-pending') + '" onclick="VerRespuestaSunat(' + row.ven_id + ')" title="Ver respuesta registrada"><i class="fa ' + (aceptado ? 'fa-check-circle' : 'fa-info-circle') + '"></i> ' + EscaparHtml(etiqueta) + '</button>';
                }
            },
            {
                data: "ven_id",
                className: "text-center",
                orderable: false,
                render: function (id, type, row) {
                    ventasData[id] = row;
                    var codigo = String(row.tde_codigo || "").padStart(2, "0");
                    var html = '<div class="opciones-venta">' +
                        '<button class="btn btn-sm btn-info" onclick="VerDetalle(' + id + ')" title="Ver detalle"><i class="fa fa-search"></i></button>';

                    if (PuedeReenviar(row)) {
                        html += '<button class="btn btn-sm btn-success" onclick="ReenviarCPE(' + id + ')" title="Reenviar el mismo comprobante"><i class="fa fa-paper-plane"></i></button>';
                    }
                    if (row.ven_estado == 1 && row.tde_codigo && parseInt(row.fac_estado || 0, 10) !== 2) {
                        html += '<button class="btn btn-sm btn-warning" onclick="VerificarSunat(' + id + ')" title="Verificación SUNAT"><i class="fa fa-sync-alt"></i></button>';
                    }
                    if (row.ven_estado == 1 && row.fac_id && parseInt(row.fac_estado, 10) === 2 && (codigo === "01" || codigo === "03")) {
                        html += '<button class="btn btn-sm btn-outline-warning" onclick="EmitirNotaCredito(' + id + ')" title="Emitir nota de crédito"><i class="fa fa-undo"></i></button>';
                        html += '<button class="btn btn-sm btn-outline-secondary" onclick="EmitirNotaDebito(' + id + ')" title="Emitir nota de débito"><i class="fa fa-plus-circle"></i></button>';
                    }
                    return html + '</div>';
                }
            }
        ],
        order: [[0, "desc"]]
    });
}

function FiltrarFechas() {
    var rawDesde = $("#filtro_desde").val();
    var rawHasta = $("#filtro_hasta").val();

    var desde = rawDesde ? moment(rawDesde, "DD-MM-YYYY").format("YYYY-MM-DD") : "";
    var hasta = rawHasta ? moment(rawHasta, "DD-MM-YYYY").format("YYYY-MM-DD") : "";

    CargarTabla(desde, hasta);
}

function LimpiarFiltro() {
    $("#filtro_desde, #filtro_hasta").val("");
    CargarTabla("", "");
}

function VerDetalle(ven_id) {
    var row = ventasData[ven_id] || {};
    $("#detalle_titulo").html('<i class="fa fa-receipt me-2"></i>Venta #' + ven_id);
    $("#detalle_contenido").html(
        CabeceraDetalleVenta(row) +
        '<div class="oc-section-title">Forma de pago</div><div id="detalle_pagos"></div>' +
        ((row.ven_num_operacion || row.ven_comentario)
            ? '<div class="detalle-observacion">' +
                (row.ven_num_operacion ? '<div><b>N.º operación:</b> ' + EscaparHtml(row.ven_num_operacion) + '</div>' : '') +
                (row.ven_comentario ? '<div><b>Comentario:</b> ' + EscaparHtml(row.ven_comentario) + '</div>' : '') +
              '</div>'
            : '') +
        '<div class="oc-section-title">' + (parseInt(row.ven_tipo, 10) === 2 ? 'Productos' : 'Insumos') + '</div>' +
        '<div id="detalle_items"><div class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin me-1"></i> Cargando detalle...</div></div>'
    );

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("offcanvasDetalleVenta")).show();
    CargarPagosDetalleVenta(ven_id,"#detalle_pagos");

    $.get("../controller/venta.php", { task: 2, ven_id: ven_id, rand: Math.random() }, function (datos) {
        var html = "";
        $.each(datos, function (i, d) {
            html += '<div class="oc-item"><div class="d-flex justify-content-between align-items-start gap-2">' +
                '<div><div class="oc-item-nombre">' + EscaparHtml(d.ins_nombre) + '</div>' +
                '<div class="oc-item-detalle"><i class="fa fa-cubes me-1"></i>' + parseFloat(d.vde_cantidad).toFixed(3) + ' ' + EscaparHtml(d.unidad) +
                ' · S/ ' + parseFloat(d.vde_precio_unit).toFixed(2) + ' c/u</div></div>' +
                '<span class="oc-item-subtotal">S/ ' + parseFloat(d.vde_subtotal).toFixed(2) + '</span></div></div>';
        });
        if (!html) html = '<div class="text-center py-4 text-muted">Sin ítems registrados</div>';

        var subtotal = parseFloat(row.ven_subtotal || 0);
        var igv = parseFloat(row.ven_igv || 0);
        var total = parseFloat(row.ven_total || 0);
        html += '<div class="oc-breakdown">' +
            '<div class="oc-brow"><span class="oc-blabel">Subtotal</span><span class="oc-bval">S/ ' + subtotal.toFixed(2) + '</span></div>' +
            '<div class="oc-brow"><span class="oc-blabel">IGV</span><span class="oc-bval">S/ ' + igv.toFixed(2) + '</span></div>' +
            '<div class="oc-bdivider"></div>' +
            '<div class="oc-brow"><span class="oc-blabel-total">Total</span><span class="oc-bval-total">S/ ' + total.toFixed(2) + '</span></div>' +
            '<div class="oc-brow"><span class="oc-blabel">Pagado</span><span class="oc-bval">S/ ' + Number(row.ven_pagado || 0).toFixed(2) + '</span></div>' +
            '<div class="oc-brow"><span class="oc-blabel">Saldo pendiente</span><span class="oc-bval">' + (row.ven_estado == 0 ? 'Venta anulada' : 'S/ ' + Number(row.ven_saldo || 0).toFixed(2)) + '</span></div>' +
        '</div>';

        if (!row.tde_codigo && row.ven_serie && row.ven_numero) {
            var urlNotaPedido = 'venta_pdf_provisional.php?ven_id=' + encodeURIComponent(ven_id);
            var numeroNotaPedido = row.ven_serie + '-' + String(row.ven_numero).padStart(8, '0');
            html += '<div class="oc-section-title mt-3">Volver a imprimir</div>' +
                '<div class="detalle-impresion">' +
                    '<button class="btn btn-dark w-100" onclick="VerComprobantePDF(\'' + urlNotaPedido + '\', \'Nota de venta ' + numeroNotaPedido + '\')"><i class="fa fa-print me-1"></i> Imprimir nota de venta</button>' +
                '</div>';
        } else if (row.fac_id && parseInt(row.fac_estado, 10) === 2) {
            var url = '../controller/facturacion.php?task=11&modo=inline&fac_id=' + row.fac_id;
            html += '<div class="oc-section-title mt-3">Volver a imprimir</div>' +
                '<div class="detalle-impresion">' +
                    '<button class="btn btn-dark w-100" onclick="VerComprobantePDF(\'' + url + '\', \'Comprobante\')"><i class="fa fa-print me-1"></i> Imprimir comprobante</button>' +
                '</div>';
        }
        $("#detalle_items").html(html);
    }, "json");
}

function FilaDetalle(icono, etiqueta, valor) {
    return FilaDetalleHtml(icono, etiqueta, '<span>' + EscaparHtml(valor) + '</span>');
}

function FilaDetalleHtml(icono, etiqueta, html) {
    return '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa ' + icono + ' me-1"></i>' + etiqueta + '</span><span class="oc-meta-val">' + html + '</span></div>';
}

function EscaparHtml(valor) {
    return $("<div>").text(valor == null ? "" : String(valor)).html();
}

function EtiquetaEstadoSunat(row) {
    if (parseInt(row.fac_estado, 10) === 2) return row.fac_respuesta_estado === "verificado_sunat_sin_cdr" ? "Confirmado en SUNAT" : "Aceptado";
    var etiquetas = {error_historico:"Error histórico",error:"Error técnico",error_consulta:"Error de consulta",no_existe_sunat:"No existe en SUNAT",rechazado:"Rechazado",anulado_sunat:"Anulado en SUNAT",no_autorizado_sunat:"No autorizado",enviado:"Enviado · sin CDR"};
    return etiquetas[row.fac_respuesta_estado] || "Pendiente";
}

function TextoPlazo(doc) {
    if (String(doc.fac_estado_sunat) === "0") return "Confirmado";
    if (parseInt(doc.plazo_vencido || 0, 10) === 1) return "Plazo vencido";
    if (!doc.plazo_limite) return "Plazo no disponible";
    var limite = moment(doc.plazo_limite);
    var minutos = Math.max(0, limite.diff(moment(), "minutes"));
    return Math.floor(minutos / 60) + " h " + (minutos % 60) + " min restantes";
}

function AccionRecomendada(doc) {
    var estado = doc.fac_respuesta_estado;
    if (estado === "verificado_sunat_sin_cdr") return "No reenviar. El comprobante ya existe en SUNAT; la verificación quedó guardada como sustento.";
    if (["rechazado","anulado_sunat","no_autorizado_sunat"].indexOf(estado) >= 0) return "No reenviar el mismo documento. Revise el estado tributario y corrija la operación.";
    if (estado === "no_existe_sunat") return parseInt(doc.plazo_vencido,10)===1 ? "SUNAT no lo localizó y el plazo venció. Emita un nuevo comprobante con fecha vigente." : "SUNAT no lo localizó. Revise el documento antes de realizar una nueva emisión.";
    if (estado === "error_consulta") return "La consulta falló técnicamente; esto no significa rechazo. Intente consultar nuevamente.";
    if (parseInt(doc.plazo_vencido,10)===1) return "Reenvío bloqueado por vencimiento. Consulte primero en SUNAT; si no existe, emita uno nuevo.";
    if (estado === "error") return "Puede consultar si el documento existe en SUNAT o reenviar el mismo comprobante y correlativo.";
    return "Consulte primero en SUNAT; si no existe, puede reenviar el mismo comprobante dentro del plazo.";
}

function PuedeReenviar(doc) {
    if (!doc || !doc.fac_id || parseInt(doc.fac_estado || 0, 10) === 2) return false;
    if (String(doc.fac_estado_sunat || "") === "0" || parseInt(doc.fac_plazo_vencido || 0, 10) === 1) return false;
    return ["rechazado", "anulado_sunat", "no_autorizado_sunat", "verificado_sunat_sin_cdr"].indexOf(doc.fac_respuesta_estado) < 0;
}

function VerRespuestaSunat(ven_id) {
    $.get("../controller/facturacion.php", {task:12,ven_id:ven_id,rand:Math.random()}, function(res){
        if (!res || res.estado !== "ok") { Swal.fire("Sin trazabilidad", res && res.mensaje ? res.mensaje : "No hay respuesta registrada", "info"); return; }
        var d=res.documento, puedeConsultar=String(d.fac_estado_sunat)!=="0" && d.fac_respuesta_estado!=="verificado_sunat_sin_cdr", puedeReenviar=PuedeReenviar(d);
        var html='<div class="sunat-trace"><div class="trace-document"><span>Documento</span><strong>'+EscaparHtml(d.documento)+'</strong></div><div class="trace-grid"><div><span>Estado registrado</span><strong>'+EscaparHtml(d.fac_respuesta_estado||"Sin confirmar")+'</strong></div><div><span>Código SUNAT</span><strong>'+EscaparHtml(d.fac_estado_sunat||"No registrado")+'</strong></div></div><div class="trace-message"><span>Detalle de la respuesta</span><p>'+EscaparHtml(d.fac_respuesta_mensaje||"Este registro no conserva detalle de respuesta.")+'</p></div><div class="trace-grid"><div><span>Última respuesta</span><strong>'+EscaparHtml(d.fac_respuesta_fecha||"No registrada")+'</strong></div><div><span>Plazo operativo</span><strong>'+EscaparHtml(TextoPlazo(d))+'</strong></div></div><div class="trace-action"><span>Acción recomendada</span><p>'+EscaparHtml(AccionRecomendada(d))+'</p></div></div>';
        Swal.fire({title:"Respuesta de SUNAT",html:html,width:560,showCancelButton:puedeConsultar,showDenyButton:puedeReenviar,showConfirmButton:true,confirmButtonText:"Cerrar",cancelButtonText:"Consultar en SUNAT",denyButtonText:"Reenviar comprobante",denyButtonColor:"#198754",reverseButtons:true,customClass:{popup:"sunat-trace-popup"}}).then(function(r){if(r.isDenied)ReenviarCPE(ven_id);else if(r.dismiss===Swal.DismissReason.cancel)VerificarSunat(ven_id);});
    },"json").fail(function(){Swal.fire("Error","No se pudo cargar la trazabilidad.","error");});
}

function ReenviarCPE(ven_id) {
    Swal.fire({title:"¿Reenviar comprobante?",text:"Se reprocesará el mismo documento y correlativo registrado en la central.",icon:"question",showCancelButton:true,confirmButtonText:"Sí, reenviar",confirmButtonColor:"#198754",cancelButtonText:"Cancelar"}).then(function(r){
        if(!r.isConfirmed)return;
        Swal.fire({title:"Reenviando comprobante...",allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
        $.ajax({type:"POST",url:"../controller/facturacion.php",dataType:"json",data:{task:6,ven_id:ven_id,reintentar:1,rand:Math.random()}})
            .done(function(res){if(tabla)tabla.ajax.reload(null,false);var api=res.api||{},mensaje=res.mensaje||api.descripcion||api.mensaje||api.error||"El comprobante continúa pendiente.";Swal.fire(res.estado==="ok"?"Comprobante confirmado":"No se pudo confirmar",mensaje,res.estado==="ok"?"success":"warning");})
            .fail(function(xhr){Swal.fire("Error de conexión",(xhr.responseJSON&&xhr.responseJSON.mensaje)||"No se pudo procesar el reenvío.","error");});
    });
}

function VerificarSunat(ven_id) {
    Swal.fire({title:"Verificación SUNAT",text:"Se verificará el comprobante directamente en SUNAT y, si existe, la central recuperará y guardará su CDR.",icon:"question",showCancelButton:true,confirmButtonText:"Verificar y recuperar",cancelButtonText:"Cancelar"}).then(function(r){
        if(!r.isConfirmed)return; Swal.fire({title:"Consultando SUNAT...",allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
        $.post("../controller/facturacion.php",{task:7,ven_id:ven_id,rand:Math.random()},function(res){
            if(tabla)tabla.ajax.reload(null,false);
            var v=res.verificacion||{}, mensaje=res.mensaje||(v.descripcion||"Sin respuesta"), icono=res.actualizado?"success":"warning";
            if(res.actualizado&&res.cdr_recuperado)mensaje+=". Se recuperó y guardó el CDR"+(res.cdr_nombre?" como "+res.cdr_nombre:"")+".";
            else if(res.actualizado&&!res.cdr_recuperado){mensaje+=". SUNAT confirmó el comprobante, pero no se recuperó el CDR: "+(res.advertencia_cdr||"el servicio central no lo devolvió")+".";icono="warning";}
            if(res.actualizado&&res.pdf_generado)mensaje+=" También se actualizó el PDF en la central.";
            Swal.fire(res.actualizado?"Comprobante localizado":"Resultado de la consulta",mensaje,icono);
        },"json").fail(function(xhr){Swal.fire("No se pudo verificar",(xhr.responseJSON&&xhr.responseJSON.mensaje)||"No se pudo consultar SUNAT.","error");});
    });
}

var reintentoEnCurso=false;
function ProcesarReintentoAutomatico(){if(reintentoEnCurso)return;reintentoEnCurso=true;$.post("../controller/facturacion.php",{task:13,rand:Math.random()},function(res){if(res&&res.procesado&&tabla)tabla.ajax.reload(null,false);},"json").always(function(){reintentoEnCurso=false;});}

function MostrarResultadoNota(res, tituloOk) {
    if (!res || res.estado !== "ok") {
        Swal.fire("Error", res && res.mensaje ? res.mensaje : "No se pudo registrar la nota.", "error");
        return;
    }
    if (tabla) tabla.ajax.reload(null, false);
    var api = res.api || {};
    var detalle = api.success ? (api.descripcion || "Nota aceptada correctamente") : (api.mensaje || api.error || "La nota quedó pendiente de confirmación.");
    var enlace = res.fac_id
        ? '<div class="mt-3"><button type="button" onclick="VerComprobantePDF(\'../controller/facturacion.php?task=11&modo=inline&fac_id=' + res.fac_id + '\', \'Comprobante\')" class="btn btn-dark"><i class="fa fa-file-pdf me-1"></i> Ver PDF</button></div>'
        : "";
    Swal.fire({ icon: api.success ? "success" : "warning", title: api.success ? tituloOk : "Nota no confirmada", html: '<b>' + (api.documento || res.comprobante || "Nota") + '</b><br>' + detalle + enlace, confirmButtonText: "Aceptar" });
}

function EmitirNotaCredito(ven_id) {
    var row = ventasData[ven_id] || {};
    var comprobante = ComprobanteVenta(row, ven_id);
    Swal.fire({
        title: "Emitir nota de crédito",
        html: '<div class="text-start"><div class="mb-2"><b>Referencia:</b> ' + comprobante + '</div><label class="form-label">Motivo</label><input id="nc_motivo" class="form-control" value="Anulación de la operación"></div>',
        icon: "question", showCancelButton: true, confirmButtonText: "Emitir NC", cancelButtonText: "Cancelar",
        preConfirm: function () { var motivo = ($("#nc_motivo").val() || "").trim(); if (!motivo) { Swal.showValidationMessage("Ingrese el motivo"); return false; } return motivo; }
    }).then(function (result) {
        if (!result.isConfirmed) return;
        EnviarNota({ task: 8, ven_id: ven_id, motivo_codigo: "01", motivo_descripcion: result.value }, "Nota de crédito emitida");
    });
}

function EmitirNotaDebito(ven_id) {
    var row = ventasData[ven_id] || {};
    var comprobante = ComprobanteVenta(row, ven_id);
    Swal.fire({
        title: "Emitir nota de débito",
        html: '<div class="text-start"><div class="mb-2"><b>Referencia:</b> ' + comprobante + '</div><label class="form-label">Monto total</label><input id="nd_monto" type="number" step="0.01" min="0.01" class="form-control mb-2"><label class="form-label">Motivo</label><input id="nd_motivo" class="form-control mb-2" value="Intereses por mora"><label class="form-label">Descripción</label><input id="nd_descripcion" class="form-control" value="Intereses por mora - ' + comprobante + '"></div>',
        icon: "question", showCancelButton: true, confirmButtonText: "Emitir ND", cancelButtonText: "Cancelar",
        preConfirm: function () {
            var monto = parseFloat($("#nd_monto").val());
            var motivo = ($("#nd_motivo").val() || "").trim();
            var descripcion = ($("#nd_descripcion").val() || "").trim();
            if (!monto || monto <= 0) { Swal.showValidationMessage("Ingrese un monto mayor a cero"); return false; }
            if (!motivo || !descripcion) { Swal.showValidationMessage("Complete el motivo y la descripción"); return false; }
            return { monto: monto, motivo: motivo, descripcion: descripcion };
        }
    }).then(function (result) {
        if (!result.isConfirmed) return;
        EnviarNota({ task: 9, ven_id: ven_id, monto: result.value.monto, motivo_codigo: "01", motivo_descripcion: result.value.motivo, descripcion: result.value.descripcion }, "Nota de débito emitida");
    });
}

function EnviarNota(datos, tituloOk) {
    datos.rand = Math.random();
    Swal.fire({ title: "Procesando nota...", allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
    $.ajax({
        type: "POST", url: "../controller/facturacion.php", dataType: "json", data: datos,
        success: function (res) { Swal.close(); MostrarResultadoNota(res, tituloOk); },
        error: function () { Swal.fire("Error de conexión", "No se pudo conectar con el servidor.", "error"); }
    });
}

function ComprobanteVenta(row, ven_id) {
    return row.ven_serie && row.ven_numero
        ? row.ven_serie + "-" + String(row.ven_numero).padStart(8, "0")
        : "Venta #" + ven_id;
}
