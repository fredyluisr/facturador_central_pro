// Visor de PDF embebido (ticket / A4) — evita abrir el comprobante en otra pestaña
// y deja el documento listo para imprimir desde la misma pantalla.

var pdfVisorSolicitudActual = 0;

function PrepararCargaPdfVisor(url) {
    var marco = document.getElementById("pdfVisorMarco");
    if (!marco) return null;

    var solicitud = ++pdfVisorSolicitudActual;
    marco.replaceChildren();
    $("#pdfVisorEstado").removeClass("d-none");
    $("#btnDescargarPdfVisor, #btnImprimirPdfVisor").prop("disabled", true);

    var iframe = document.createElement("iframe");
    iframe.id = "iframePdfVisor";
    iframe.className = "pdf-visor-frame";
    iframe.title = "Vista previa del documento PDF";
    iframe.dataset.pdfUrl = url;
    iframe.dataset.solicitud = String(solicitud);
    marco.appendChild(iframe);

    $(iframe).one("load.pdfVisor", function () {
        if (solicitud !== pdfVisorSolicitudActual || iframe !== document.getElementById("iframePdfVisor")) return;
        $("#pdfVisorEstado").addClass("d-none");
        iframe.classList.add("is-ready");
        $("#btnDescargarPdfVisor, #btnImprimirPdfVisor").prop("disabled", false);
    });
    iframe.src = url;
    return iframe;
}

function LimpiarPdfVisor() {
    pdfVisorSolicitudActual++;
    var marco = document.getElementById("pdfVisorMarco");
    if (marco) marco.replaceChildren();
    $("#pdfVisorEstado").removeClass("d-none");
    $("#btnDescargarPdfVisor, #btnImprimirPdfVisor").prop("disabled", true);
}

function VerComprobantePDF(url, titulo) {
    // Varios de los botones que llaman a esto viven dentro de un Swal.fire();
    // cerrarlo evita que su overlay quede por encima del modal del visor.
    if (typeof Swal !== "undefined" && Swal.isVisible && Swal.isVisible()) {
        Swal.close();
    }

    if ($("#modalPdfViewer").length === 0) {
        $("body").append(
            '<div class="modal fade" id="modalPdfViewer" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-lg modal-dialog-centered" style="max-width:720px;">' +
                    '<div class="modal-content" style="height:88vh;">' +
                        '<div class="modal-header py-2">' +
                            '<h6 class="modal-title mb-0" id="modalPdfViewerTitulo">Comprobante</h6>' +
                            '<div class="pdf-viewer-actions d-flex align-items-center gap-2">' +
                                '<button type="button" id="btnDescargarPdfVisor" class="btn btn-sm btn-outline-light pdf-viewer-btn" onclick="DescargarPdfVisor()" title="Guardar PDF" aria-label="Guardar PDF" disabled>' +
                                    '<i class="fa fa-download"></i><span class="pdf-download-label">Guardar PDF</span>' +
                                '</button>' +
                                '<button type="button" id="btnImprimirPdfVisor" class="btn btn-sm btn-dark pdf-viewer-btn" onclick="ImprimirPdfVisor()" title="Imprimir" aria-label="Imprimir" disabled>' +
                                    '<i class="fa fa-print"></i><span class="pdf-print-label">Imprimir</span>' +
                                '</button>' +
                                '<button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="modal-body p-0 pdf-visor-body">' +
                            '<div id="pdfVisorEstado" class="pdf-visor-estado"><span class="spinner-border" role="status" aria-hidden="true"></span><strong>Cargando documento…</strong></div>' +
                            '<div id="pdfVisorMarco" class="pdf-visor-marco"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
        $("#modalPdfViewer").on("hidden.bs.modal.pdfVisor", LimpiarPdfVisor);
    }

    $("#modalPdfViewerTitulo").text(titulo || "Comprobante");

    PrepararCargaPdfVisor(url);

    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalPdfViewer"));
    modal.show();
}

function CargarPdfEnVisor(url) {
    return PrepararCargaPdfVisor(url);
}

function DescargarPdfVisor() {
    var iframe = document.getElementById("iframePdfVisor");
    if (!iframe) return;

    var url = iframe.dataset.pdfUrl || iframe.src;
    try {
        var descarga = new URL(url, window.location.href);
        descarga.searchParams.set("modo", "attachment");
        window.location.href = descarga.toString();
    } catch (e) {
        window.location.href = url;
    }
}

function ImprimirPdfVisor() {
    var iframe = document.getElementById("iframePdfVisor");
    if (!iframe) return;
    try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    } catch (e) {
        if (typeof toastr !== "undefined") {
            toastr.info("Use el ícono de impresión del visor de PDF.");
        }
    }
}

function AbrirImpresionVentaHTML(venId, documento, alFinalizar) {
    venId = Number(venId);
    if (!Number.isSafeInteger(venId) || venId <= 0) return null;
    documento = documento === "cpe" ? "cpe" : "nota";

    var terminada = false;
    var monitor = null;
    var marcoImpresion = null;
    var finalizar = function () {
        if (terminada) return;
        terminada = true;
        if (monitor) window.clearInterval(monitor);
        if (marcoImpresion && marcoImpresion.parentNode) marcoImpresion.parentNode.removeChild(marcoImpresion);
        window.removeEventListener("message", recibirFin);
        if (typeof alFinalizar === "function") alFinalizar();
    };
    var recibirFin = function (evento) {
        if (evento.origin !== window.location.origin || !evento.data || evento.data.type !== "ifact-impresion-finalizada" || Number(evento.data.ven_id) !== venId || evento.data.documento !== documento) return;
        finalizar();
    };
    window.addEventListener("message", recibirFin);

    var tipoQuery = documento === "cpe" ? "&tipo=cpe" : "";
    var urlImpresion = "venta_nota_impresion.php?ven_id=" + encodeURIComponent(venId) + tipoQuery;

    // La emisión electrónica llega aquí después de una llamada asíncrona para
    // reservar el correlativo. Chrome bloquea un window.open tardío; un iframe
    // temporal del mismo origen permite imprimir sin pedir permiso para pop-ups.
    if (documento === "cpe") {
        marcoImpresion = document.createElement("iframe");
        marcoImpresion.title = "Impresión del comprobante";
        marcoImpresion.style.position = "fixed";
        marcoImpresion.style.left = "-10000px";
        marcoImpresion.style.top = "0";
        marcoImpresion.style.width = "80mm";
        marcoImpresion.style.height = "297mm";
        marcoImpresion.style.border = "0";
        document.body.appendChild(marcoImpresion);
        marcoImpresion.src = urlImpresion;
    } else {
        var ventana = window.open(urlImpresion, "ifact_impresion_" + documento + "_" + venId, "width=470,height=780,scrollbars=yes,resizable=yes");
        if (!ventana) {
            window.removeEventListener("message", recibirFin);
            if (typeof Swal !== "undefined") Swal.fire("Ventana bloqueada", "Permite las ventanas emergentes para imprimir el documento.", "warning");
            return null;
        }
        monitor = window.setInterval(function () { if (ventana.closed) finalizar(); }, 500);
    }

    // Conservar también el PDF, pero generarlo fuera del camino inmediato de
    // impresión. El navegador ya está mostrando el HTML cuando inicia esta tarea.
    window.setTimeout(function () {
        var tipoPdf = documento === "nota" ? "&tipo=nota" : "";
        fetch("venta_pdf_provisional.php?ven_id=" + encodeURIComponent(venId) + tipoPdf + "&actualizado=" + Date.now(), {
            credentials: "same-origin", cache: "no-store", keepalive: true
        }).catch(function () {});
    }, 800);
    return marcoImpresion || ventana;
}

function ImprimirNotaVentaHTML(venId, alFinalizar) {
    return AbrirImpresionVentaHTML(venId, "nota", alFinalizar);
}

function ImprimirComprobanteVentaHTML(venId, alFinalizar) {
    return AbrirImpresionVentaHTML(venId, "cpe", alFinalizar);
}
