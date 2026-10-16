$(document).ready(function () {

    var tabla;
    var cba_id_editar = 0;

    // ==================== TABLA ====================
    function inicializarTabla() {
        if ($.fn.DataTable.isDataTable("#tablaListado"))
            $("#tablaListado").DataTable().destroy();

        tabla = $("#tablaListado").DataTable({
            ajax: {
                url: "../controller/cuenta_bancaria.php",
                type: "POST",
                data: { action: 1 },
                dataSrc: function (response) {
                    return response.status === 1 ? response.data : [];
                }
            },
            columns: [
                {
                    data: "cba_banco",
                    render: function (data, type, row) {
                        return '<span style="cursor:pointer;" onclick="Editar(' + row.cba_id + ')" title="Clic para editar">' + data + '</span>';
                    }
                },
                {
                    data: "cba_moneda",
                    render: function (data) {
                        return '<span class="badge bg-info">' + data + '</span>';
                    }
                },
                { data: "cba_propietario" },
                {
                    data: "cba_numero",
                    render: function (data) {
                        return '<code>' + data + '</code>';
                    }
                },
                {
                    data: "estado_desc",
                    render: function (data) {
                        var cls = data === "Habilitado" ? "bg-success" : "bg-warning text-dark";
                        return '<span class="badge ' + cls + '">' + data + '</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function (data, type, row) {
                        return '<button class="btn btn-sm btn-danger" onclick="Eliminar(' + row.cba_id + ')" title="Eliminar">' +
                               '<i class="fas fa-trash-alt"></i></button>';
                    }
                }
            ],
            language: { url: "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" }
        });
    }

    // ==================== NUEVO ====================
    window.Nuevo = function () {
        cba_id_editar = 0;
        LimpiarFormulario();
        $("#nombre_opcion").text("Nueva Cuenta Bancaria");
        $("#reg_edi").modal("show");
    };

    // ==================== EDITAR ====================
    window.Editar = function (cba_id) {
        $.ajax({
            url: "../controller/cuenta_bancaria.php",
            type: "POST",
            data: { action: 2, cba_id: cba_id },
            dataType: "json",
            success: function (res) {
                if (res.status === 1) {
                    cba_id_editar = cba_id;
                    $("#cba_banco").val(res.data.cba_banco);
                    $("#cba_moneda").val(res.data.cba_moneda);
                    $("#cba_propietario").val(res.data.cba_propietario);
                    $("#cba_numero").val(res.data.cba_numero);
                    $("#cba_estado").val(res.data.cba_estado);
                    $("#nombre_opcion").text("Editar Cuenta Bancaria");
                    $("#reg_edi").modal("show");
                } else {
                    Swal.fire({icon:"error",title:"No se pudo completar",text:res.message});
                }
            }
        });
    };

    // ==================== GUARDAR ====================
    window.Guardar = function () {
        var banco       = $("#cba_banco").val().trim();
        var moneda      = $("#cba_moneda").val();
        var propietario = $("#cba_propietario").val().trim();
        var numero      = $("#cba_numero").val().trim();

        if (!banco)       { AvisoValidacionIFact("Ingrese el Banco.", "#cba_banco", 'warning'); return; }
        if (!propietario) { AvisoValidacionIFact("Ingrese el Propietario.", "#cba_propietario", 'warning'); return; }
        if (!numero)      { AvisoValidacionIFact("Ingrese el Número de Cuenta.", "#cba_numero", 'warning'); return; }

        var action = cba_id_editar > 0 ? 4 : 3;
        var data   = {
            action: action,
            cba_banco:       banco,
            cba_moneda:      moneda,
            cba_propietario: propietario,
            cba_numero:      numero,
            cba_estado:      $("#cba_estado").val()
        };
        if (cba_id_editar > 0) data.cba_id = cba_id_editar;

        Swal.fire({
            title: cba_id_editar > 0 ? "¿Actualizar cuenta?" : "¿Registrar cuenta?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Sí, guardar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#6c757d"
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: "../controller/cuenta_bancaria.php",
                type: "POST",
                data: data,
                dataType: "json",
                success: function (res) {
                    if (res.status === 1) {
                        Swal.fire({ icon: "success", title: "Listo", text: res.message, timer: 1500, showConfirmButton: true });
                        $("#reg_edi").modal("hide");
                        tabla.ajax.reload();
                    } else {
                        Swal.fire({icon:"error",title:"No se pudo completar",text:res.message});
                    }
                },
                error: function () { Swal.fire({icon:"error",title:"No se pudo completar",text:"Error de conexión"}); }
            });
        });
    };

    // ==================== ELIMINAR ====================
    window.Eliminar = function (cba_id) {
        Swal.fire({
            title: "¿Eliminar cuenta bancaria?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: "../controller/cuenta_bancaria.php",
                type: "POST",
                data: { action: 5, cba_id: cba_id },
                dataType: "json",
                success: function (res) {
                    if (res.status === 1) {
                        AvisoIFact(res.message);
                        tabla.ajax.reload();
                    } else {
                        Swal.fire({icon:"error",title:"No se pudo completar",text:res.message});
                    }
                },
                error: function () { Swal.fire({icon:"error",title:"No se pudo completar",text:"Error de conexión"}); }
            });
        });
    };

    // ==================== AUXILIARES ====================
    function LimpiarFormulario() {
        $("#cba_banco").val("");
        $("#cba_moneda").val("PEN");
        $("#cba_propietario").val("");
        $("#cba_numero").val("");
        $("#cba_estado").val("1");
    }

    // Mayúsculas automáticas en banco y propietario
    $("#cba_banco, #cba_propietario").on("input", function () {
        $(this).val($(this).val().toUpperCase());
    });

    $("#reg_edi").on("hidden.bs.modal", function () {
        LimpiarFormulario();
        cba_id_editar = 0;
    });

    inicializarTabla();
});
