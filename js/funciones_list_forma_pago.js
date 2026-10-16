$(function () {
    var tabla, tpaId = 0, cargandoForma = false, bancosCargados;

    function texto(valor) { return $('<div>').text(valor == null ? '' : String(valor)).html(); }
    function error(mensaje) { Swal.fire({ icon: 'error', title: 'No se pudo completar', text: mensaje || 'Ocurrió un error' }); }

    function cargarBancos() {
        bancosCargados = $.getJSON('../controller/cuenta_bancaria.php', { action: 6 }, function (r) {
            var opciones = '<option value="">Seleccione banco</option>';
            if (r.status === 1) opciones += r.data.map(function (b) { return '<option value="' + Number(b.ban_id) + '">' + texto(b.ban_nombre) + '</option>'; }).join('');
            $('#ban_id').html(opciones);
        });
    }

    function inicializarTabla() {
        if ($.fn.DataTable.isDataTable('#tablaListado')) $('#tablaListado').DataTable().destroy();
        tabla = $('#tablaListado').DataTable({
            ajax: { url: '../controller/tipo_pago.php', type: 'POST', data: { action: 1 }, dataSrc: function (r) { return r.status === 1 ? r.data : []; } },
            columns: [
                { data: 'tpa_nombre', render: function (v) { return '<span class="forma-pago-nombre">' + texto(v) + '</span>'; } },
                { data: 'cuentas_total', render: function (v) { var n = Number(v || 0); return '<span class="forma-cuentas-badge"><i class="fa fa-wallet"></i> ' + n + ' cuenta' + (n === 1 ? '' : 's') + '</span>'; } },
                { data: 'estado_desc', render: function (v) { return '<span class="badge ' + (v === 'Habilitado' ? 'bg-success' : 'bg-warning text-dark') + '">' + texto(v) + '</span>'; } },
                { data: null, orderable: false, render: function (_v, _t, r) { return '<button type="button" class="btn btn-sm btn-danger" onclick="event.stopPropagation(); Eliminar(' + r.tpa_id + ')"><i class="fas fa-trash-alt"></i></button>'; } }
            ],
            createdRow: function (fila, datos) {
                $(fila).addClass('forma-pago-fila').attr({ tabindex: '0', role: 'button', 'aria-label': 'Editar ' + datos.tpa_nombre })
                    .on('click', function (e) { if (!$(e.target).closest('button,a,input,select').length) Editar(datos.tpa_id); })
                    .on('keydown', function (e) { if (!$(e.target).closest('button,a,input,select').length && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); Editar(datos.tpa_id); } });
            },
            language: { url: '//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
        });
    }

    window.Nuevo = function () {
        tpaId = 0;
        limpiarForma();
        $('#nombre_opcion').text('Nueva Forma de Pago');
        $('#btn_guardar').prop('hidden', false).html('<i class="fa fa-check me-1"></i> Crear forma de pago');
        prepararCuentas();
        $('#reg_edi').modal('show');
    };

    window.Editar = function (id) {
        $.post('../controller/tipo_pago.php', { action: 2, tpa_id: id }, function (r) {
            if (r.status !== 1) return error(r.message);
            cargandoForma = true;
            tpaId = Number(id);
            $('#tpa_nombre').val(r.data.tpa_nombre);
            $('#tpa_estado').val(r.data.tpa_estado).trigger('change');
            cargandoForma = false;
            $('#nombre_opcion').text('Editar Forma de Pago');
            $('#btn_guardar').prop('hidden', true);
            prepararCuentas();
            cargarCuentas();
            $('#reg_edi').modal('show');
        }, 'json');
    };

    window.Guardar = function () {
        var nombre = $.trim($('#tpa_nombre').val());
        if (!nombre) return AvisoValidacionIFact('Ingrese el nombre.', '#tpa_nombre');
        if (tpaId) return guardarForma(true);
        $.post('../controller/tipo_pago.php', { action: 3, tpa_nombre: nombre, tpa_estado: $('#tpa_estado').val() }, function (r) {
            if (r.status !== 1) return error(r.message);
            tpaId = Number(r.tpa_id);
            if (!tpaId) return error('La forma de pago se creó, pero no fue posible abrir sus cuentas. Cierra y vuelve a ingresar.');
            $('#nombre_opcion').text('Editar Forma de Pago');
            $('#btn_guardar').prop('hidden', true);
            prepararCuentas();
            cargarCuentas();
            tabla.ajax.reload(null, false);
            AvisoIFact(r.message);
        }, 'json').fail(function () { error('Error de conexión'); });
    };

    function guardarForma(mostrarAviso) {
        if (!tpaId || cargandoForma) return;
        var nombre = $.trim($('#tpa_nombre').val());
        if (!nombre) return AvisoValidacionIFact('Ingrese el nombre.', '#tpa_nombre');
        $.post('../controller/tipo_pago.php', { action: 4, tpa_id: tpaId, tpa_nombre: nombre, tpa_estado: $('#tpa_estado').val() }, function (r) {
            if (r.status !== 1) return error(r.message);
            tabla.ajax.reload(null, false);
            if (mostrarAviso) AvisoIFact(r.message);
        }, 'json').fail(function () { error('Error de conexión'); });
    }

    window.Eliminar = function (id) {
        Swal.fire({ title: '¿Eliminar forma de pago?', text: 'Sus cuentas dejarán de estar disponibles para nuevas operaciones.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }).then(function (resultado) {
            if (!resultado.isConfirmed) return;
            $.post('../controller/tipo_pago.php', { action: 5, tpa_id: id }, function (r) {
                if (r.status === 1) { AvisoIFact(r.message); tabla.ajax.reload(null, false); } else error(r.message);
            }, 'json');
        });
    };

    function prepararCuentas() {
        $('#btn_nueva_cuenta').prop('disabled', !tpaId).prop('hidden', false);
        $('#cuentas_forma_titulo').text(tpaId ? 'Destinos disponibles para este método' : 'Guarda primero la forma de pago');
        $('#cuentas_forma_lista').prop('hidden', false).html(tpaId ? '<div class="forma-cuentas-vacio">Cargando cuentas…</div>' : '<div class="forma-cuentas-vacio">Después de guardar podrás agregar una o más cuentas.</div>');
        cancelarEditor();
    }

    function cargarCuentas() {
        if (!tpaId) return;
        $.getJSON('../controller/cuenta_bancaria.php', { action: 1, tpa_id: tpaId }, function (r) {
            var lista = r.status === 1 ? r.data : [];
            $('#cuentas_forma_lista').html(lista.map(function (c) {
                return '<article class="forma-cuenta-item"><span class="forma-cuenta-icono"><i class="fa fa-university"></i></span><span class="forma-cuenta-datos"><strong>' + texto(c.cba_banco) + ' · ' + texto(c.cba_moneda) + '</strong><span>' + texto(c.cba_propietario) + ' · ' + texto(c.cba_numero) + ' · <b class="forma-cuenta-estado ' + (Number(c.cba_estado) === 1 ? '' : 'inactivo') + '">' + texto(c.estado_desc) + '</b></span></span><span class="forma-cuenta-acciones"><button type="button" onclick="EditarCuentaForma(' + c.cba_id + ')" aria-label="Editar cuenta"><i class="fa fa-pen"></i></button><button type="button" class="eliminar" onclick="EliminarCuentaForma(' + c.cba_id + ')" aria-label="Eliminar cuenta"><i class="fa fa-trash"></i></button></span></article>';
            }).join('') || '<div class="forma-cuentas-vacio">Aún no hay cuentas asociadas. Agrega la primera.</div>');
            tabla.ajax.reload(null, false);
        });
    }

    function mostrarEditorCuenta() {
        $('#cuentas_forma_lista, #btn_nueva_cuenta').prop('hidden', true);
        $('#cuenta_forma_editor').prop('hidden', false);
    }

    window.NuevaCuentaForma = function () { if (tpaId) { limpiarCuenta(); mostrarEditorCuenta(); $('#ban_id').trigger('focus'); } };
    window.CancelarCuentaForma = cancelarEditor;
    window.EditarCuentaForma = function (id) {
        $.post('../controller/cuenta_bancaria.php', { action: 2, cba_id: id }, function (r) {
            if (r.status !== 1) return error(r.message);
            $.when(bancosCargados).always(function () {
                $('#cba_id').val(r.data.cba_id); $('#ban_id').val(r.data.ban_id || ''); $('#cba_moneda').val(r.data.cba_moneda);
                $('#cba_propietario').val(r.data.cba_propietario); $('#cba_numero').val(r.data.cba_numero); $('#cba_estado').val(r.data.cba_estado);
                mostrarEditorCuenta();
            });
        }, 'json');
    };

    window.GuardarCuentaForma = function () {
        var banId = Number($('#ban_id').val()) || 0, propietario = $.trim($('#cba_propietario').val()), numero = $.trim($('#cba_numero').val()), id = Number($('#cba_id').val()) || 0;
        if (!banId) return AvisoValidacionIFact('Seleccione el banco.', '#ban_id');
        if (!propietario) return AvisoValidacionIFact('Ingrese el propietario.', '#cba_propietario');
        if (!numero) return AvisoValidacionIFact('Ingrese el número.', '#cba_numero');
        $.post('../controller/cuenta_bancaria.php', { action: id ? 4 : 3, cba_id: id, tpa_id: tpaId, ban_id: banId, cba_moneda: $('#cba_moneda').val(), cba_propietario: propietario, cba_numero: numero, cba_estado: $('#cba_estado').val() }, function (r) {
            if (r.status !== 1) return error(r.message);
            AvisoIFact(r.message); cancelarEditor(); cargarCuentas();
        }, 'json');
    };

    window.EliminarCuentaForma = function (id) {
        Swal.fire({ title: '¿Eliminar cuenta?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }).then(function (resultado) {
            if (!resultado.isConfirmed) return;
            $.post('../controller/cuenta_bancaria.php', { action: 5, cba_id: id }, function (r) {
                if (r.status === 1) { AvisoIFact(r.message); cargarCuentas(); } else error(r.message);
            }, 'json');
        });
    };

    function limpiarForma() { cargandoForma = true; $('#tpa_nombre').val(''); $('#tpa_estado').val('1').trigger('change'); cargandoForma = false; }
    function limpiarCuenta() { $('#cba_id').val(''); $('#ban_id').val(''); $('#cba_propietario, #cba_numero').val(''); $('#cba_moneda').val('PEN'); $('#cba_estado').val('1'); }
    function cancelarEditor() { limpiarCuenta(); $('#cuenta_forma_editor').prop('hidden', true); $('#cuentas_forma_lista').prop('hidden', false); $('#btn_nueva_cuenta').prop('hidden', false); }

    $('#tpa_nombre, #cba_propietario').on('input', function () { this.value = this.value.toUpperCase(); });
    $('#tpa_nombre').on('blur', function () { guardarForma(false); });
    $('#tpa_estado').on('change', function () { guardarForma(false); });
    $('#reg_edi').on('hidden.bs.modal', function () { limpiarForma(); cancelarEditor(); tpaId = 0; });
    cargarBancos();
    inicializarTabla();
});
