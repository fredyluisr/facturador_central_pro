
var tabla;
var opcion_actual = 0;
function AvisoEmpleado(tipo, mensaje, campo) {
    return Swal.fire({
        icon: tipo === 'warning' ? 'info' : tipo,
        title: tipo === 'warning' ? 'Falta completar un dato' : (tipo === 'success' ? 'Listo' : 'No se pudo completar'),
        text: mensaje,
        background: '#ffffff',
        color: '#24324a',
        customClass: { popup: 'ifact-dni-alert', confirmButton: 'ifact-dni-confirm' },
        buttonsStyling: false,
        confirmButtonText: 'Entendido',
        showConfirmButton: true,
        timer: tipo === 'success' ? 1500 : undefined,
        returnFocus: false
    }).then(function () {
        if (campo) $(campo).prop('disabled', false).trigger('focus');
    });
} 

function ActualizarBusquedaDocumento() {
    var esDni = $('#emp_tipo_doc').val() === '1';
    $('#btn_reniec').prop('hidden', !esDni);
    $('.employee-document-field').toggleClass('has-document-search', esDni);
}

// Fredy Luis
$(document).ready(function() {
    $('#emp_tipo_doc').on('change', ActualizarBusquedaDocumento);
    $('#reg_edi').on('show.bs.modal', ActualizarBusquedaDocumento);
    ActualizarBusquedaDocumento();
    Listar();
    Cargar_Combo("../controller/cargo.php", "car_id", 7, 0);

  
    $('.datetimepicker').datetimepicker({
        format: 'DD-MM-YYYY',
        useCurrent: false
    });

   
    $('#reg_edi').on('click', '.campo-editable', function() {
        if (opcion_actual !== 2) return; // Solo en modo Editar
        var campo = $(this);
        if (campo.prop('disabled')) {
            campo.prop('disabled', false).focus().addClass('border-warning');
        }
    });

    $('#reg_edi').on('blur', '.campo-editable', function() {
        $(this).removeClass('border-warning');
    });

   
    $('#tablaListado').on('click', '.editable-cell', function() {
        var empId = $(this).attr('data-emp-id');
        if (empId) {
            Editar(empId);
        }
    });

    $('#emp_nombres').on('blur', function() {
        if ($('#emp_alias').val().trim() === '') {
            $('#emp_alias').val(obtenerPrimerNombre($(this).val()));
        }
    });
});

// Fredy Luis
function Listar() {
    tabla = $('#tablaListado').DataTable({
        "destroy": true,
        "ajax": {
            "url": "../controller/empleado.php",
            "type": "POST",
            "data": { task: '1' },
            "dataSrc": function(json) {
                if (json == '' || json == null) return [];
                return json;
            }
        },
        "columns": [
            {
                "data": null,
                "render": function(data) {
                    return data.emp_alias || (data.emp_nombres || '').trim().split(/\s+/)[0] || '—';
                }
            },
            {
                "data": null,
                "render": function(data) {
                    return data.emp_nro_doc;
                }
            },
            { "data": "car_nombre" },
            {
                "data": "emp_estado",
                "render": function(data) {
                    if (data == 1)
                        return '<span class="badge bg-success">Habilitado</span>';
                    else
                        return '<span class="badge bg-danger">Deshabilitado</span>';
                }
            },
            {
                "data": null,
                "render": function(data) {
                    return '<div class="d-flex align-items-center gap-1">' +
                        '<button class="btn btn-sm btn-danger"  onclick="Anular('     + data.emp_id + ')" title="Anular"><i class="fa fa-trash-alt"></i></button>' +
                        '</div>';
                }
            }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json"
        },
        "order": [[0, "asc"]],
        "pageLength": 25,
        "createdRow": function(row, data, dataIndex) {
            $(row).find('td').each(function(index) {
                if (index < 4) {
                    $(this).addClass('editable-cell').css('cursor', 'pointer').attr('data-emp-id', data.emp_id);
                }
            });
        }
    });
}

// Fredy Luis
function LimpiarCampos() {
    $('#emp_tipo_doc').val('1');
    ActualizarBusquedaDocumento();
    $('#emp_nro_doc').val('');
    $('#emp_ape_paterno').val('');
    $('#emp_ape_materno').val('');
    $('#emp_nombres').val('');
    $('#emp_alias').val('');
    $('#emp_estado').val('1');
    $('#car_id').val('0');

    $('#emp_direccion').val('');
    $('#emp_email').val('');
    $('#emp_fec_nac').val('');
    $('#emp_movil').val('');
    $('#emp_est_civil_opcional').val('1');
    $('#emp_sexo_opcional').val('1');


    $('#empleado_foto').attr('src', '../img/trabajadores/usuario-default.svg');
    $('#empleado_foto_nombre').text('Empleado');
    $('#btn_eliminar_foto').prop('disabled', true).hide();

    $('#datos-personales-tab').tab('show');
}


// ==================== NUEVO ====================
function Nuevo() {
    $('#btn_reniec').prop('disabled', false);
    opcion_actual = 1;
    LimpiarCampos();
    // Todos los campos habilitados y editables libremente
    $('#reg_edi .campo-editable').prop('disabled', false).removeClass('border-warning');
    $('#btn_guardar').show();
    $('#nombre_opcion').text('Nuevo Empleado');
    $('#reg_edi').modal('show');
}

// ==================== VER DETALLE ====================
function VerDetalle(emp_id) {
    $('#btn_reniec').prop('disabled', true);
    opcion_actual = 0;
    LimpiarCampos();
    $('#nombre_opcion').text('Detalle Empleado');

    $.ajax({
        url: '../controller/empleado.php',
        type: 'POST',
        data: { task: '2', emp_id: emp_id },
        dataType: 'json',
        success: function(data) {
            if (data && data.length > 0) {
                var d = data[0];
                _cargarDatos(d);

                // Bloquear todo — solo lectura
                $('#reg_edi .campo-editable').prop('disabled', true).css('cursor', 'default');
                $('#btn_guardar').hide();
                $('#reg_edi').modal('show');
            }
        },
        error: function() {
            AvisoEmpleado('error', 'Error al cargar los datos del empleado.');
        }
    });
}

// ==================== EDITAR ====================
function Editar(emp_id) {
    $('#btn_reniec').prop('disabled', false);
    opcion_actual = 2;
    LimpiarCampos();
    $('#nombre_opcion').text('Editar Empleado');

    $.ajax({
        url: '../controller/empleado.php',
        type: 'POST',
        data: { task: '2', emp_id: emp_id },
        dataType: 'json',
        success: function(data) {
            if (data && data.length > 0) {
                var d = data[0];
                _cargarDatos(d);

                // Bloquear campos — se habilitan al hacer click (campo-editable)
                $('#reg_edi .campo-editable').prop('disabled', true).css('cursor', 'pointer');
                $('#btn_guardar').show();

                // Mostrar tooltip visual de que son editables
                $('#reg_edi .campo-editable').attr('title', 'Haz click para editar');

                $('#reg_edi').modal('show');
            }
        },
        error: function() {
            AvisoEmpleado('error', 'Error al cargar los datos del empleado.');
        }
    });
}

// ==================== HELPER: CARGAR DATOS EN FORMULARIO ====================
function _cargarDatos(d) {
    $('#emp_id').val(d.emp_id);
    $('#emp_tipo_doc').val(d.emp_tipo_doc);
    ActualizarBusquedaDocumento();
    $('#emp_nro_doc').val(d.emp_nro_doc);
    $('#emp_ape_paterno').val(d.emp_ape_paterno);
    $('#emp_ape_materno').val(d.emp_ape_materno);
    $('#emp_nombres').val(d.emp_nombres);
    $('#emp_alias').val(d.emp_alias);
    $('#emp_estado').val(d.emp_estado).trigger('change');
    $('#car_id').val(d.car_id);

    $('#emp_direccion').val(d.emp_direccion);
    $('#emp_email').val(d.emp_email);
    var nacimiento = moment(d.emp_fec_nac, 'YYYY-MM-DD', true);
    $('#emp_fec_nac').val(nacimiento.isValid() ? nacimiento.format('YYYY-MM-DD') : '');
    $('#emp_movil').val(d.emp_movil);
    $('#emp_est_civil_opcional').val(d.emp_est_civil);
    $('#emp_sexo_opcional').val(d.emp_sexo);


    CargarFotoEmpleado(d.emp_id);
}

function CargarFotoEmpleado(emp_id) {
    $.ajax({
        url: '../controller/foto_perfil.php',
        type: 'GET',
        dataType: 'json',
        data: { task: 3, emp_id: emp_id },
        success: function(respuesta) {
            if (!respuesta || respuesta.estado !== 1) return;

            $('#empleado_foto').attr('src', respuesta.foto + '?v=' + Date.now());
            $('#empleado_foto_nombre').text(respuesta.nombre || 'Empleado');
            $('#empleado_foto_estado').text(respuesta.tiene_foto ? 'Fotografía registrada del empleado.' : 'Sin fotografía disponible. Se muestra la imagen predeterminada.');
            $('#btn_eliminar_foto')
                .prop('disabled', !respuesta.tiene_foto)
                .toggle(!!respuesta.tiene_foto);
        },
        error: function() {
            $('#empleado_foto').attr('src', '../img/trabajadores/usuario-default.svg');
            $('#btn_eliminar_foto').prop('disabled', true).hide();
        }
    });
}

function EliminarFotoEmpleado() {
    var emp_id = parseInt($('#emp_id').val(), 10) || 0;
    if (!emp_id) return;

    Swal.fire({
        title: '¿Eliminar fotografía?',
        text: 'El archivo se eliminará definitivamente del servidor.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: '../controller/foto_perfil.php',
            type: 'POST',
            dataType: 'json',
            data: { task: 4, emp_id: emp_id },
            success: function(respuesta) {
                if (respuesta.estado === 1) {
                    $('#empleado_foto').attr('src', respuesta.foto + '?v=' + Date.now());
                    $('#btn_eliminar_foto').prop('disabled', true).hide();
                    AvisoEmpleado('success', respuesta.mensaje);
                } else {
                    AvisoEmpleado('error', respuesta.mensaje || 'No se pudo eliminar la fotografía.');
                }
            },
            error: function(xhr) {
                var respuesta = xhr.responseJSON || {};
                AvisoEmpleado('error', respuesta.mensaje || 'No se pudo eliminar la fotografía.');
            }
        });
    });
}

// ==================== VALIDAR ====================
function Validar() {
    if ($('#emp_nro_doc').val().trim() == '') {
        AvisoEmpleado('warning', 'Ingrese el Nro de Documento.', '#emp_nro_doc');
        return false;
    }
    if ($('#emp_ape_paterno').val().trim() == '') {
        AvisoEmpleado('warning', 'Ingrese el Apellido Paterno.', '#emp_ape_paterno');
        return false;
    }
    if ($('#emp_ape_materno').val().trim() == '') {
        AvisoEmpleado('warning', 'Ingrese el Apellido Materno.', '#emp_ape_materno');
        return false;
    }
    if ($('#emp_nombres').val().trim() == '') {
        AvisoEmpleado('warning', 'Ingrese los Nombres.', '#emp_nombres');
        return false;
    }
    if ($('#emp_alias').val().trim() == '') {
        AvisoEmpleado('warning', 'Ingrese el Alias.', '#emp_alias');
        return false;
    }
    if ($('#car_id').val() == '0' || $('#car_id').val() == '') {
        AvisoEmpleado('warning', 'Seleccione un Cargo.', '#car_id');
        return false;
    }
    return true;
}


// ==================== GUARDAR ====================
async function Guardar() {
    if (!Validar()) return;
    if (!await ConfirmarGuardadoIFact(opcion_actual === 1 ? 'registrar' : 'actualizar', 'empleado')) return;

    // Asegurarse de leer valores aunque estén disabled
    var getVal = function(id) {
        return $('#' + id).val() || '';
    };

    var task   = opcion_actual == 1 ? '4' : '3';
    var emp_id = opcion_actual == 2 ? getVal('emp_id') : 0;

    $.ajax({
        url: '../controller/empleado.php',
        type: 'POST',
        data: {
            task:                    task,
            emp_id:                  emp_id,
            emp_tipo_doc:            getVal('emp_tipo_doc'),
            emp_nro_doc:             getVal('emp_nro_doc'),
            emp_ape_paterno:         getVal('emp_ape_paterno'),
            emp_ape_materno:         getVal('emp_ape_materno'),
            emp_nombres:             getVal('emp_nombres'),
            emp_alias:               getVal('emp_alias'),
            emp_fec_nac:             getVal('emp_fec_nac'),
            emp_sexo:                getVal('emp_sexo_opcional'),
            emp_est_civil:           getVal('emp_est_civil_opcional'),
            emp_direccion:           getVal('emp_direccion'),
            emp_email:               getVal('emp_email'),
            emp_movil:               getVal('emp_movil'),
            car_id:                  getVal('car_id'),
            emp_estado:              getVal('emp_estado')
        },
        success: function(res) {
            if (res == 1) {
                AvisoEmpleado('success', opcion_actual == 1 ? 'Empleado registrado correctamente.' : 'Empleado actualizado correctamente.');
                $('#reg_edi').modal('hide');
                tabla.destroy();
                Listar();
            } else {
                AvisoEmpleado('error', 'Ocurrió un error al guardar. Intente nuevamente.');
            }
        },
        error: function() {
            AvisoEmpleado('error', 'Error de conexión con el servidor.');
        }
    });
}

// ==================== ANULAR ====================
function Anular(emp_id) {
    Swal.fire({
        title: '¿Está seguro?',
        text: 'Esta acción anulará al empleado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor:  '#6c757d',
        confirmButtonText:  'Sí, anular',
        cancelButtonText:   'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '../controller/empleado.php',
                type: 'POST',
                data: { task: '5', emp_id: emp_id },
                success: function(res) {
                    if (res == 1) {
                        AvisoEmpleado('success', 'Empleado anulado correctamente.');
                        tabla.destroy();
                        Listar();
                    } else {
                        AvisoEmpleado('error', 'Error al anular el empleado.');
                    }
                },
                error: function() {
                    AvisoEmpleado('error', 'Error de conexión con el servidor.');
                }
            });
        }
    });
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////// CONSULTA EXTERNA ///////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////

// Fredy Luis
function ConsultaDNI()
{
    if ($('#emp_tipo_doc').val() !== '1') return;
    if ($('#btn_reniec').attr('aria-busy') === 'true') return;
    const dniAlert = Swal.mixin({ customClass: { popup: 'ifact-dni-alert', confirmButton: 'ifact-dni-confirm' }, buttonsStyling: false, confirmButtonText: 'Entendido' });
    let dni = $('#emp_nro_doc').val().trim();

    if (!/^[0-9]{8}$/.test(dni)) {
        AvisoEmpleado('warning', 'Para consultar el DNI, ingrese exactamente 8 números, sin espacios ni letras.', '#emp_nro_doc');
        return;
    }



    $.ajax({
        url: "../controller/consultas_api.php?task=7",
        type: "GET",
        data: { dni: dni },
        dataType: "json",

        beforeSend: function () {
            $('#btn_reniec').prop('disabled', true).attr('aria-busy', 'true');
            dniAlert.fire({ title: 'Consultando DNI', text: 'Estamos buscando los datos del documento.', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        },

        success: function (resp) 
        {
            if (!resp || !Array.isArray(resp) || resp.length === 0) {

                dniAlert.fire({
                    icon: "warning",
                    title: "Alerta",
                    text: "Respuesta inválida del servidor"
                });

                limpiarCampos();
                return;
            }

            let r = resp[0];

            if (r.success == 1)
            {
                $('#emp_ape_paterno').val(r.ape_paterno);
                $('#emp_ape_materno').val(r.ape_materno);
                $('#emp_nombres').val(r.nombres);
                $('#emp_alias').val(obtenerPrimerNombre(r.nombres));

                dniAlert.fire({
                    icon: "success",
                    title: "DNI consultado",
                    text: "Los nombres y apellidos ya están completos.",
                    timer: 2000,
                    showConfirmButton: false
                });
            }
            else if (r.success == 2)
            {
                limpiarCampos();

                dniAlert.fire({
                    icon: "warning",
                    title: "Atención",
                    text: "El DNI existe, pero no hay datos completos"
                });
            }
            else
            {
                limpiarCampos();

                dniAlert.fire({
                    icon: "warning",
                    title: "Alerta",
                    text: "No se encontraron datos para este DNI"
                });
            }
        },

        error: function () {
            dniAlert.fire({
                icon: "error",
                title: "Error",
                text: "No pudimos consultar el DNI. Revise su conexión e intente nuevamente. También puede completar los nombres y apellidos manualmente."
            });
        },

        complete: function () {
            $('#btn_reniec').prop('disabled', false).removeAttr('aria-busy');
        }
    });
}

// Fredy Luis
function limpiarCampos()
{
    $('#emp_ape_paterno').val("");
    $('#emp_ape_materno').val("");
    $('#emp_nombres').val("");
    $('#emp_alias').val("");
}

function obtenerPrimerNombre(nombres)
{
    return String(nombres || '').trim().split(/\s+/)[0].toUpperCase();
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////// FIN CONSULTA EXTERNA ////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////


// Fredy Luis
function Solo_Numeros(e) {
      var key = e.keyCode || e.which;
      var tecla = String.fromCharCode(key).toLowerCase();
      var numeros = "0123456789";
      if (key == 8 || key == 9 || key == 13 || key == 0) {
          return true;
      }
      if (numeros.indexOf(tecla) == -1) {
          return false;
      } else {
          return true;
      }
  }
