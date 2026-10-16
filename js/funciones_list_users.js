
//Variables Globales
var Opcion = 1; // 1: Registrar; 2: Editar
var Usuario_Id = 0;
var cantidad_dni_registrados = 0
var Id_Elemento = 0

var tabla

// Fredy Luis
$(function(){

	$('#reg_edi .select2').select2({
		dropdownParent: $('#reg_edi')
	});

	tabla = $('#tablaListado').DataTable();
	Cargar_Combo("../controller/empleado.php", "emp_id", 8, 0);
	Cargar_Combo("../controller/rol.php", "rol_id", 7, 0);
	Buscar()	
});

// Cuando haces clic, abre el select2
$('#emp_id').on('select2:open', function () {
    setTimeout(function () {
        document.querySelector('.select2-container--open .select2-search__field').focus();
    }, 0);
});

// Opcional: abrir al hacer focus (como input)
$('#emp_id').on('focus', function () {
    $(this).select2('open');
});



// Cuando haces clic, abre el select2
$('#rol_id').on('select2:open', function () {
    setTimeout(function () {
        document.querySelector('.select2-container--open .select2-search__field').focus();
    }, 0);
});

// Opcional: abrir al hacer focus (como input)
$('#rol_id').on('focus', function () {
    $(this).select2('open');
});

// Fredy Luis
function Buscar()
{
	if ($.fn.DataTable.isDataTable('#tablaListado')) {
        $('#tablaListado').DataTable().clear().destroy();
        $('#tablaListado tbody').empty();
    }

	tabla = $('#tablaListado').DataTable({

				"pageLength": 50,
				autoWidth: true,

				ajax:{
					url: "../controller/usuario.php?task=1",
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
								data: "token",
								className: "d-none d-md-table-cell",
								render: function(data, type, row) {
                                    return `<span style="cursor:pointer;" onclick="Editar(${row.id})">${data}</span>`;
                                }
							},							
							{
								data: "estado",
								className: "d-none d-md-table-cell",
								render: function(data) {
									return parseInt(data) === 1
										? '<span class="badge bg-success">HABILITADO</span>'
										: '<span class="badge bg-danger">DESHABILITADO</span>';
								}
							},
							{
								data: "id",
								className: "align-middle text-center",
								render: function(data, type, row) {
									
									return '<button type="button" onclick="Eliminar(' + data + ')" class="btn btn-danger btn-sm btnEliminar" title="Eliminar | ' + data + '"><i class="fa fa-trash-alt"></i></button>';
								}
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

// Fredy Luis
function Nuevo()
{
	Opcion = 1
	Limpiar()
	$('#emp_id').prop('disabled', false)
	$('#btnGenerar_Token').hide()
	
	$('#reg_edi').modal('show');
	$('#personales-tab').tab('show');
	$("#listDetalle_min").hide()

	Eliminar_Roles()
	Usuario_Id = 0
}

// Fredy Luis
function Limpiar()
{
	$("#emp_id").val(0).trigger("change"); 
	$('#usu_estado').val('1').trigger('change')
	$('#usu_token').val('')
	$("#rol_id").val(0).trigger("change"); 
}

// Fredy Luis 
function Eliminar_Roles()
{
	var tableReg = document.getElementById('tbListDet_rol')	
	var cellsOfRow = ""	
	var compareWith = ""

	if (tableReg.rows.length > 1)
	{
		for (var i = tableReg.rows.length; i > 1; i--)
		{
			cellsOfRow = tableReg.rows[i - 1].getElementsByTagName('td')
			compareWith = cellsOfRow[0].innerHTML.toLowerCase() // Busca el Id

			$("#rol_"+compareWith).remove()
		}
	}
}

// Fredy Luis
async function Guardar()
{
    if (!Validaciones()) return;
    if (!await ConfirmarGuardadoIFact(Opcion == 1 ? 'registrar' : 'actualizar', 'usuario')) return;
    if (Opcion == 1) Registrar();
    else if (Usuario_Id > 0) Actualizar();
    else Swal.fire({icon: 'error', title: 'No se pudo guardar', text: 'Selecciona un registro válido.'});
}

// Fredy Luis
function Registrar()
{
 	var cols = new Array()
		cols[0] = 0 // rol_id

	var cad_roles = CrearArrayfromTable('#tbListDet_rol', cols)

	$.ajax({
		type: "get",
		dataType: "json",
		url: "../controller/usuario.php",
		data: 	"task=4"+
				"&emp_id="+$("#emp_id").val()+
				"&usu_estado="+$("#usu_estado").val()+
				"&usu_token="+$("#usu_token").val()+
				"&usuario="+$("#usuario_id").val()+
				"&roles="+cad_roles+
				"&rand="+parseInt(Math.random()*99999999),

		success: function(datos){

					if(datos > 0)
					{
						Cerrar();

						Swal.fire({
							icon: 'success',
							title: 'Usuario registrado',
							text: 'El usuario ha sido registrado correctamente.',
							timer: 1500,
							showConfirmButton: false
						}).then(() => {							
							Buscar();
						});

					}else{

						Swal.fire({
							icon: 'error',
							title: 'Error',
							text: 'Error al registrar Usuario'
						}).then(() => {
							Cerrar();
						});
					}
				}
	});	
}

// Fredy Luis
function Actualizar()
{
 	var cols = new Array()
		cols[0] = 0 // rol_id

	var cad_roles = CrearArrayfromTable('#tbListDet_rol', cols)
	
	$.ajax({
		type: "POST",
		dataType: "json",
		url: "../controller/usuario.php",
		data: 	"task=3"+
				"&usu_id="+Usuario_Id+
				"&usu_estado="+$('#usu_estado').val()+				
				"&usuario="+$("#usuario_id").val()+
				"&roles="+cad_roles+
				"&rand="+parseInt(Math.random()*99999999),

		success: function(datos){

					if(datos == 1)
					{
						Swal.fire({
							icon: 'success',
							title: 'Actualización',
							text: 'El usuario ha sido actualizado correctamente.',
							timer: 1500,
							showConfirmButton: false,
							customClass: {
								icon: 'icon-update-color'
							}
						}).then(() => {
							Cerrar();
							Buscar();
						});

					}else{
						var div = document.getElementById("message_error");
						div.textContent = "No se puede Actualizar Usuario";
						$('#modal_error').modal('show')
						Cerrar()
					}

					Usuario_Id = 0;
				}
	});
}

// Fredy Luis
async function Async_Generar_Token()
{
    try {

        $('#btnToken').prop('disabled', true);

        // ==========================================
        // LOADER CON SWEETALERT
        // ==========================================
        Swal.fire({
            title: 'Generando token',
            html: 'Procesando información...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        let res_token = await $.ajax({
            type: "POST",
            dataType: "json",
            url: "../controller/usuario.php",
            data: {
                task: 6,
                usu_id: Usuario_Id,
                usuario: $("#usuario_id").val()
            }
        });

        Swal.close(); // cerrar loader

        if (res_token && res_token.token)
        {
            $('#usu_token').val(res_token.token.trim());

            Swal.fire({
                icon: "success",
                title: "Actualización",
                text: "Token generado correctamente",
                timer: 1500,
                showConfirmButton: false
            });
        }
        else
        {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Error al generar token"
            });
        }

    } catch (e) {

        Swal.close();

        console.error(e);

        Swal.fire({
            icon: "error",
            title: "Error",
            text: "Ocurrió un error en la comunicación con el servidor"
        });

    } finally {
        $('#btnToken').prop('disabled', false);
    }
}

// Fredy Luis
function Cerrar()
{
	$('#reg_edi').modal('hide');
	$('#modal_final').modal('hide');
	$('#modal_eliminar').modal('hide');
	$('#modal_problema').modal('hide');
	$('#modal_edicion').modal('hide');
	$('#modal_registro').modal('hide');
	$('#modal_mensaje').modal('hide');
}

// Fredy Luis
function Editar(usu_id)
{
    Opcion = 2;
    Usuario_Id = usu_id;

    $('#reg_edi').modal('show');
	$('#personales-tab').tab('show');
    $('#emp_id').prop('disabled', true)
	$('#btnGenerar_Token').show()
    $("#listDetalle_min").show()

    $.ajax({
        type: "get",
        dataType: "json",
        url: "../controller/usuario.php",
        data: "task=2" +
              "&usu_id=" + Usuario_Id +
              "&rand=" + parseInt(Math.random() * 99999999),

        success: function(datos){

            let u = datos[0];

            if(u.usu_id != '0')
            {
                $("#emp_id").val(u.emp_id).trigger("change");               
                $("#usu_estado").val(u.usu_estado).trigger("change");
                $("#usu_token").val(u.usu_token);                
            }

            Listar_Perfiles_x_Usuario();
        }
    });
}

// Fredy Luis
function Verificar_Existencia()
{
	$.ajax({
		type: "get",
		dataType: "json",
		url: "../controller/usuario.php",
		async: false,
		data: 	"task=7"+
				"&emp_id="+$('#emp_id').val()+
				"&rand="+parseInt(Math.random()*99999999),

		success: function(datos){
					cantidad_dni_registrados = datos
		}
	});
}

// Fredy Lus
function Agregar_Perfil()
{
	var rol = $('#rol_id').val()

	if (rol != 0)
	{
		var resultado = Buscar_Tabla(rol, 'tbListDet_rol')

		if(resultado == -1)
		{
			$("#listDetalle_min").show()

			var fila =
					"<tr id='rol_"+rol+"'>"+
						"<td align='center'>"+rol+"</td>"+
						"<td>"+$("#rol_id option:selected").text()+"</td>"+
						"<td align='center'><button type='button' class='btn btn-danger btn-sm' style='background-color: #e30613; border-color: #e30613;' title='Eliminar' data-toggle='tooltip' data-placement='top' onClick='Eliminar_Fila_Rol("+rol+");'><i class='fa fa-minus-circle'></i></button></td>"+
					"</tr>";

					$('#tbListDet_rol').append(fila);

			$("#rol_id").val(0).trigger("change"); 

		}else{ // SI ya está en lista
			Swal.fire({
				text: "No se puede agregar porque el ROL ya se encuentra en lista.",
				icon: "warning",
				confirmButtonText: "OK",
				timer: 2000,
				timerProgressBar: true,
				showConfirmButton: true
			});
		}
	}else{
		Swal.fire({
			text: "Debe seleccionar un ROL.",
			icon: "warning",
			confirmButtonText: "OK",
			timer: 1500,
			timerProgressBar: true,
			showConfirmButton: true
		});
	}
}

// Fredy Lus
function Listar_Perfiles_x_Usuario()
{
	Eliminar_Roles()

	$.ajax({
		type: "get",
		dataType: "json",
		url: "../controller/perfil.php",
		data: 	"task=1"+
				"&usu_id="+Usuario_Id+
				"&rand="+parseInt(Math.random()*99999999),

		success: function(datos){

					if (datos != '0')
						for(var i=0;i<datos.length;i++){

							var fila =
								"<tr id='rol_"+datos[i].rol_id+"'>"+
									"<td align='center'>"+datos[i].rol_id+"</td>"+
									"<td>"+datos[i].descripcion+"</td>"+
									"<td align='center'><button type='button' class='btn btn-danger btn-sm' style='background-color: #e30613; border-color: #e30613;' title='Eliminar' data-toggle='tooltip' data-placement='top' onClick='Eliminar_Fila_Rol("+datos[i].rol_id+");'><i class='fa fa-minus-circle'></i></button></td>"+
								"</tr>";

								$('#tbListDet_rol').append(fila);
						}
		}
	});	
}

// Fredy Luis
function Eliminar(id)
{
    Usuario_Id = id;

    Swal.fire({
        title: "¡Alerta!",
        text: "¿Está seguro de eliminar Usuario?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        customClass: {
            confirmButton: "btn btn-danger",
            cancelButton: "btn btn-secondary"
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) 
		{            
            Eliminacion();
        }
    });
}

// Fredy Luis
function Eliminacion() 
{
    $.ajax({
        type: "get",
        dataType: 'json',
        url: "../controller/usuario.php",
        data: "task=5" +
              "&usu_id=" + Usuario_Id +
              "&usuario=" + $("#usuario_id").val() +
              "&rand=" + parseInt(Math.random() * 99999999),

        success: function(datos) {

            if (datos == 1) 
			{
                // 🔥 Eliminación correcta
				Swal.fire({
					icon: 'error',
					title: 'Eliminación',
					text: 'El usuario ha sido eliminado correctamente.',
					timer: 1500,
					showConfirmButton: false,
					customClass: {
						icon: 'icon-delete-color'
					}
				}).then(() => {
					Cerrar();
					Buscar();
				});

            } else {

                // ❌ No se puede eliminar
                Swal.fire({
                    title: 'Problema',
                    text: 'No se puede eliminar Usuario.',
                    icon: 'error',
                    confirmButtonText: 'Cerrar',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                }).then(() => {
                    Cerrar();
                });

            }
        }
    });
}

// Fredy Luis
function Buscar_Tabla(busqueda, tabla)
{
	var tableReg = document.getElementById(tabla)
	var searchText = busqueda
	var cellsOfRow = ""
	var fila = -1
	var compareWith = ""

	for (var i = 1; i < tableReg.rows.length; i++)
	{
		cellsOfRow = tableReg.rows[i].getElementsByTagName('td')
		compareWith = cellsOfRow[0].innerHTML.toLowerCase() // Busca el Id

		if (searchText.length == 0 || (compareWith == searchText)){
			fila = i
			return fila
		}
	}

	return fila
}

// Fredy Luis
function Eliminar_Fila_Rol(fila)
{
    Id_Elemento = fila;

    Swal.fire({
		title: "¡Alerta!",
		text: "¿Está seguro de eliminar el Fila?",
		icon: "warning",
		showCancelButton: true,
		confirmButtonText: "Sí, eliminar",
		cancelButtonText: "Cancelar",
		customClass: {
			confirmButton: "btn btn-warning",
			cancelButton: "btn btn-secondary"
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed) {
			Eliminacion_Fila_Rol();
		}
	});
}

// Fredy Luis
function Eliminacion_Fila_Rol()
{
	$("tr#rol_"+Id_Elemento).fadeOut("normal", function(){
		$(this).remove()
	});

	if($("#tbListDet_rol >tbody >tr").length == 1){
		$("#listDetalle_min").hide()
	}

	$('#modal_eliminar_fila_rol').modal('hide');
}

// Fredy Luis
function CrearArrayfromTable(Table, cols)
{
	var cadena = ""
	var tam = $(Table + " tbody tr").length
	var matriz = new Array(tam)
	var cont_items = 1

	$(Table+" tbody tr").each(function(i){

		matriz[i] = new Array()
		var cont = 0

		$(this).children("td").each(function (j)
		{
			if(ExistElement(j, cols))
			{
				matriz[i][cont] = $(this).text()
				cont++
			}
		})

		if (cont_items == 1)
			cadena += matriz[i].toString();
		else
			cadena += "-" + matriz[i].toString();

		cont_items += 1
	})

	cadena = cadena.substr(0, cadena.length);
	return cadena;
}

// Fredy Luis
function ExistElement(value, array)
{
    for(i = 0; i < array.length; i++)
    	if(value == array[i]) return true;
    return false;
}

//////////////////////////////////////////////////////////////////////
////////////////////// V A L I D A C I O N E S ///////////////////////
//////////////////////////////////////////////////////////////////////

// Fredy Luis
function Validaciones()
{
	if($("#emp_id").val() == 0)
	{
		$('#personales-tab').tab('show');

		Swal.fire({
			title: "¡Validación!",
			text: "Debe seleccionar Empleado",
			icon: "info",
			confirmButtonText: "Ok"
		});

		return false
	}


	if (Opcion == 1)
	{
		Verificar_Existencia()

		if (cantidad_dni_registrados > 0)
		{
			$('#personales-tab').tab('show');

			Swal.fire({
				title: "¡Validación!",
				text: "Empleado ya se encuentra registrado",
				icon: "info",
				confirmButtonText: "Ok"
			});
			
			return false
		}
	} 


	if(document.getElementById("tbListDet_rol").rows.length == 1)
	{
		$('#roles-tab').tab('show');

		Swal.fire({
			title: "¡Validación!",
			text: "Debe tener por lo menos un ROL",
			icon: "info",
			confirmButtonText: "Ok"
		});

		return false
	}


	return true
}

// Fredy Luis
function Solo_Numeros(e){
	var keynum = window.event ? window.event.keyCode : e.which;
	if ((keynum == 8) || (keynum == 46))
	return true;
	 
	return /\d/.test(String.fromCharCode(keynum));
}
