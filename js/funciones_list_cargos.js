


$(document).ready(function() {
    
    var tabla;
    var car_id_editar = 0;
    var guardando = false;
    
    // Inicializar tabla
    function inicializarTabla() {
    if ($.fn.DataTable.isDataTable('#tablaListado')) {
        $('#tablaListado').DataTable().destroy();
    }
    
    tabla = $('#tablaListado').DataTable({
        "ajax": {
            "url": "../controller/cargo.php",
            "type": "GET",
            "data": { task: 1 },
            "dataSrc": function(response) {
                if (response && response.length > 0) {
                    return response;
                } else {
                    console.error('Error al cargar datos:', response);
                    return [];
                }
            },
            "error": function(xhr, status, error) {
                console.error('Error AJAX:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron cargar los datos',
                    confirmButtonColor: '#d33'
                });
            }
        },
        "columns": [
            { 
                "data": "car_nombre",
                "render": $.fn.dataTable.render.text()
            },
            { 
                "data": "car_estado",
                "render": function(data, type, row) {
                    var clase = '';
                    var texto = '';
                    if (data == 1) {
                        clase = 'bg-success';
                        texto = 'HABILITADO';
                    } else {
                        clase = 'bg-danger';
                        texto = 'DESHABILITADO';
                    }
                    return '<span class="badge ' + clase + '">' + texto + '</span>';
                }
            },
            { 
                "data": null,
                "orderable": false,
                "render": function(data, type, row) {
                    var botones = '';
                    
                  
                    
                  
                    if (row.car_estado != 2) {
                        botones += '<button class="btn btn-sm btn-danger" onclick="Anular(' + row.car_id + ')" title="Anular">';
                        botones += '<i class="fas fa-trash-alt"></i>';
                        botones += '</button>';
                    }
                    
                    return botones;
                }
            }
        ],
        "createdRow": function(row) {
            $(row).find('td').not(':last-child').attr({tabindex: 0, title: 'Clic para editar cargo'}).css('cursor', 'pointer');
        },
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
        }
    });
}
$('#tablaListado').on('click keydown', 'tbody td', function(event) {
    if ($(event.target).closest('button, a').length) return;
    if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
    var cargo = tabla.row($(this).closest('tr')).data();
    if (!cargo) return;
    event.preventDefault();
    Editar(cargo.car_id);
});
 

window.Nuevo = function() {
    car_id_editar = 0;
    Limpiar();
    
    var div = document.getElementById("nombre_opcion");
    div.textContent = "Registro de Cargo";
    
    $('#reg_edi').modal('show');
};
 

function Limpiar() {
    $("#car_nombre").val("");
    $("#car_estado").val("1").trigger("change");
}
 

window.Editar = function(car_id) {
    car_id_editar = car_id;
    
    var div = document.getElementById("nombre_opcion");
    div.textContent = "Edición de Cargo";
    
    $('#reg_edi').modal('show');
    
    $.ajax({
        type: "GET",
        dataType: "json",
        url: "../controller/cargo.php",
        data: {
            task: 2,
            car_id: car_id
        },
        success: function(response) {
            if (response && response.length > 0) {
                $("#car_nombre").val(response[0].car_nombre);
                $("#car_estado").val(response[0].car_estado).trigger("change");
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar datos:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudieron cargar los datos del cargo',
                confirmButtonColor: '#d33'
            });
        }
    });
};
 

window.Guardar = async function() {
    if (guardando || !Validaciones()) return;
    if (!await ConfirmarGuardadoIFact(car_id_editar > 0 ? 'actualizar' : 'registrar', 'cargo')) return;
    ejecutarGuardar();
};
function ejecutarGuardar() {
    if (guardando) return;
    guardando = true;
    var car_nombre = $('#car_nombre').val().trim();
    var car_estado = $('#car_estado').val();
    
    var action = car_id_editar > 0 ? 3 : 4; 
    var data = {
        task: action,
        car_nombre: car_nombre,
        car_estado: car_estado
    };
    
    
    if (car_id_editar > 0) {
        data.car_id = car_id_editar;
    }
    
    
    Swal.fire({
        title: 'Procesando...',
        text: 'Por favor espere',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: "../controller/cargo.php",
        type: "GET",
        data: data,
        dataType: "json",
        success: function(response) {
            Swal.close();
            
            if (response == 1) {
                var mensaje = car_id_editar > 0 ? 'actualizado' : 'registrado';
                AvisoIFact('Cargo ' + mensaje + ' correctamente.');
                Cerrar();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo ' + (car_id_editar > 0 ? 'actualizar' : 'registrar') + ' el cargo',
                    confirmButtonColor: '#d33'
                });
            }
        },
        complete: function() { guardando = false; },
        error: function(xhr, status, error) {
            Swal.close();
            console.error('Error AJAX:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error en la comunicación con el servidor',
                confirmButtonColor: '#d33'
            });
        }
    });
}
 
// Función Cerrar
function Cerrar() {
    $('#reg_edi').modal('hide');
    inicializarTabla();
}
 
window.Anular = function(car_id)
{
    Swal.fire({
            title: 'Anular cargo',
            text: '¿Estás seguro de anular este cargo? Dejará de aparecer en el listado.',
            icon: 'warning',
            iconHtml: '<i class="fas fa-trash-alt" aria-hidden="true"></i>',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Anular',
            cancelButtonText: 'Cancelar'
    }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "../controller/cargo.php",
                    type: "GET",
                    data: {
                        task: 5,
                        car_id: car_id
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response == 1) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Anulado',
                                text: 'Cargo anulado correctamente',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                inicializarTabla();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se pudo anular el cargo',
                                confirmButtonColor: '#d33'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ocurrió un error en la comunicación con el servidor',
                            confirmButtonColor: '#d33'
                        });
                    }
                });
            }
    });
};
    
function Validaciones() {
    var car_nombre = $('#car_nombre').val().trim();
    
    if (car_nombre.length < 3) {
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Debe ingresar nombre del cargo (Mínimo 3 caracteres)',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    return true;
}
 

    inicializarTabla();
});
