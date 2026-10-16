
//Variables Globales
var Opcion = 1; // 1: Registrar; 2: Editar
var Rol_Id = 0;

var tabla

// Fredy Luis
$(function(){
	tabla = $('#tablaListado').DataTable();
	Buscar()
});

// Fredy Luis
function Buscar()
{
	tabla.destroy();

	tabla = $('#tablaListado').DataTable({

				"pageLength": 25,

				ajax:{
					url: "../controller/rol.php?task=1",
					dataSrc:""
				},

				columns:[
					{data: "id", visible: false},
					{
						data: "nombre",
						render: function(data, type, row) {
                                    return `<span style="cursor:pointer;" onclick="Editar(${row.id})">${data}</span>`;
                                }
					},
					{
						data: "descripcion",
						className: "d-none d-md-table-cell",
						render: function(data, type, row) {
                                    return `<span style="cursor:pointer;" onclick="Editar(${row.id})">${data}</span>`;
                                }
					},
					{
						"data": "estado",
						"render": function(data) {
							if (data == 1)
								return '<span class="badge bg-success">Habilitado</span>';
							else
								return '<span class="badge bg-danger">Deshabilitado</span>';
						}
					},
					{
						defaultContent: '<button type="button" class="btnEditar btn btn-success btn-sm" title="Editar"><i class="fa fa-edit"></i></button>',
						className: "d-none d-md-table-cell"
					}
				]
			});
			
			// Ocultar "Mostrar registros" en móvil
			if (window.innerWidth <= 768) {
				$('.dataTables_length').hide();
			} else {
				$('.dataTables_length').show();
			}
}

$('#tablaListado tbody').on('click', '.btnEditar', function () {
    
    let table = $('#tablaListado').DataTable();
    let data = table.row($(this).parents('tr')).data();

    Editar(data.id);
});

// Fredy Luis
function Nuevo()
{
	Opcion = 1;
	Limpiar();

	var div = document.getElementById("nombre_opcion");
	div.textContent = "Registro de Rol";

	$('#reg_edi').modal('show'); 
}

// Fredy Luis
function Limpiar(){
	$("#rol_nombre").val("")
	$("#rol_descripcion").val("")
}

// Fredy Luis
async function Guardar()
{
    if (!Validaciones()) return;
    if (!await ConfirmarGuardadoIFact(Opcion == 1 ? 'registrar' : 'actualizar', 'rol')) return;
    if (Opcion == 1) Registrar();
    else if (Rol_Id > 0) Actualizar();
    else Swal.fire({icon: 'error', title: 'No se pudo guardar', text: 'Selecciona un registro válido.'});
}

// Fredy Luis
function Registrar()
{
	$.ajax({
		type: "get",
		dataType: "json",
		url: "../controller/rol.php?task=4",
		data: 	"rol_nombre="+$("#rol_nombre").val()+
				"&rol_descripcion="+$("#rol_descripcion").val()+
				"&rol_estado="+$("#rol_estado").val()+
				"&rand="+parseInt(Math.random()*99999999),

		success: function(datos){

					if(datos == 1)
					{
						AvisoIFact("Datos registrados Correctamente", "Registro", {
							closeButton: true,
							tapToDismiss: false
						});

						Cerrar()
					}else{

						toastr.error("Error al registrar Rol", "Error", {
							closeButton: true,
							tapToDismiss: false
						});

						Cerrar()
					}

					Rol_Id = 0;
				}
	});
}

// Fredy Luis
function Actualizar()
{
	$.ajax({
		type: "get",
		dataType: "json",
		url: "../controller/rol.php?task=3",
		data: 	"rol_id="+Rol_Id+
				"&rol_nombre="+$("#rol_nombre").val()+
				"&rol_descripcion="+$("#rol_descripcion").val()+
				"&rol_estado="+$("#rol_estado").val()+
				"&rand="+parseInt(Math.random()*99999999),

		success: function(datos){

					if(datos == 1)
					{
						AvisoIFact("Datos actualizados Correctamente", "Actualización", {
							closeButton: true,
							tapToDismiss: false
						});

						Cerrar()
					}else{

						toastr.error("No se puede Actualizar Rol", "Error", {
							closeButton: true,
							tapToDismiss: false
						});
					}
		
					Rol_Id = 0;
				}
	});
}

// Fredy Luis
function Cerrar()
{
	$('#reg_edi').modal('hide');
	
	Buscar()
}

// Fredy Luis
function Editar(rol_id)
{
	Opcion = 2;
	Rol_Id = rol_id;
	
	var div = document.getElementById("nombre_opcion");
	div.textContent = "Edición de Rol";

	$('#reg_edi').modal('show'); 

	$.ajax({
		type: "get",
		dataType: "json",
		url: "../controller/rol.php?task=2",
		data: "rol_id="+rol_id+
			  "&rand="+parseInt(Math.random()*99999999),
		success: function(datos){

					if(datos[0].rol_id != '0')
					{
						$("#rol_nombre").val(datos[0].rol_nombre)
						$("#rol_descripcion").val(datos[0].rol_descripcion)
						$("#rol_estado").val(datos[0].rol_estado).trigger("change")
					}
		}
	});
}

// Fredy Luis
function Validaciones()
{
	if($("#rol_nombre").val().length < 4)
	{
		Swal.fire({
					title: "¡Alerta!",
					text: "Debe ingresar nombre del rol (Min 3 caracteres)",
					icon: "warning",
					confirmButtonClass: "btn btn-warning",
					buttonsStyling: false,
		});

		return false
	}


	if($("#rol_descripcion").val().length < 4)
	{
		Swal.fire({
					title: "¡Alerta!",
					text: "Debe ingresar Descripción del Rol (Min 3 caracteres)",
					icon: "warning",
					confirmButtonClass: "btn btn-warning",
					buttonsStyling: false,
		});

		return false
	}

	return true
}
