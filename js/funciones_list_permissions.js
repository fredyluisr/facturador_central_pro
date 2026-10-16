
// Variables Globales
var Rol_id_seleccionado = 0

// Fredy Luis
window.onload = function(){
	Listar_Menu_Pagina()
}

// Fredy Luis
function Listar_Menu_Pagina()
{
	Rol_id_seleccionado = 0

	$.ajax({
		type: 	"POST",
		url: 	"../controller/menu.php",
		data: 	"task=5"+
				"&rand="+parseInt(Math.random()*99999999),

		success:function(data){

					var array = eval(data);

					if (data == '0'){
						$('#lista_menu').html("No hay registros para mostrar")
						$('#lista_pagina').html("No hay registros para mostrar")
					}else{
						$('#lista_menu').html(array[0]);
						$('#lista_pagina').html(array[1]);
					}
		}
	});
}

// Fredy Luis
function Cargar_Rol_Menu(rol_id, rol_nombre, boton)
{
	Rol_id_seleccionado = rol_id

	var div = document.getElementById("rol");
    div.textContent = rol_nombre;
    $(".permission-role-item").removeClass("is-active");
    if (boton) $(boton).addClass("is-active");

	if (rol_id > 0)
	{
		Listar_Menu_Pagina_Activos(rol_id)
	}else{
		var div = document.getElementById("message_mensaje");
		div.textContent = "Debe seleccionar un Rol";
		$('#modal_mensaje').modal('show')
	}
}

// Fredy Luis
function Listar_Menu_Pagina_Activos(rol_id)
{
	$.ajax({
		type: 	"POST",
		url: 	"../controller/pagina.php",
		dataType: "json",
		data: 	"task=5"+
				"&rol_id="+rol_id+
				"&rand="+parseInt(Math.random()*99999999),

		success:function(array){
					if (!Array.isArray(array)){
						$('#listado_menu').hide()
						$('#listado_pagina').hide()

						var div = document.getElementById("message_mensaje");
						div.textContent = "No hay menús disponibles";
						$('#modal_mensaje').modal('show')

					}else{
						$('#lista_menu').html(array[0]);
						$('#lista_pagina').html(array[1]);
						$('#listado_menu').show()
						$('#listado_pagina').show()
					}
		},
		error:function(xhr){
			console.error('No se pudieron cargar los permisos del rol', xhr.responseText);
			Swal.fire({icon:'error',title:'No se pudieron cargar los permisos',text:'Actualiza la página e inténtalo nuevamente.'});
		}
	});
}

// Fredy Luis
function Guardar_Permisos()
{
	if (Rol_id_seleccionado > 0)
	{
		Swal.fire({
			title: "¡Confirmación!",
			text: "¿Seguro de Guardar Permisos?",
			icon: "question",
			showCancelButton: true,
			confirmButtonText: "Sí, Guardar",
			cancelButtonText: "Cancelar",
			customClass: {
				confirmButton: "btn btn-warning",
				cancelButton: "btn btn-secondary"
			},
			buttonsStyling: false
		}).then((result) => {
			if (result.isConfirmed) {
				Actualizar();
			}
		});

	}else{

		Swal.fire({
			icon: 'error',
			title: 'Error',
			text: 'No ha seleccionado ningún rol'
		});
	}
}

// Fredy Luis
async function Actualizar()
{
    let result

	$('#btnRegistrar').prop('disabled', true)

	try {

		result = await $.ajax({
								type: "get",
								dataType: "json",
								url: "../controller/rol.php",
								data: 	"task=8"+
										"&rol_id="+Rol_id_seleccionado+
										"&lista_rol_menu="+Nodo_Rol_Menu()+
										"&lista_rol_pagina="+Nodo_Rol_Pagina()
							});

        $('#modal_edicion').modal('hide')

		if (result > 0)
		{
            Swal.fire({
				title: "Actualización",
				text: "Permisos actualizados correctamente",
				icon: "warning",
				timer: 1000,
				showConfirmButton: false
			}).then(() => {
				location.href = "../view/list_permissions.php";
			});
        }else{
			var div = document.getElementById("message_problema");
			div.textContent = "No se puede Actualizar Permisos";
			$('#modal_problema').modal('show')
		}

	}catch (e){
		console.error(e)
	}
}

// Fredy Luis
function Cerrar()
{
	location.href="../view/list_permissions.php";
}

// Fredy Luis
function Nodo_Rol_Menu()
{
	var checkboxes = form_lista_menu.checkbox_rol_menu
	var cadena = ""
	var cont = 0

	for (var i = 0; i < checkboxes.length; i++)
	{
		if ((i+1) ==  checkboxes.length){
			if (checkboxes[i].checked){
				if (cont > 0){
					cadena =  cadena + "," + checkboxes[i].value 
				}else{
					cadena = cadena + checkboxes[i].value
				}

				cont +=1
			}
		}else{
			if (checkboxes[i].checked){
				if (cont > 0){
					cadena =  cadena + "," + checkboxes[i].value
				}else{
					cadena = cadena + checkboxes[i].value
				}

				cont +=1
			}
		}
	}

	return cadena
}

// Fredy Luis
function Nodo_Rol_Pagina(){

	var checkboxes = form_lista_pagina.checkbox_rol_pagina
	var cadena = ""
	var cont = 0

	for (var i = 0; i < checkboxes.length; i++)
	{
		if ((i+1) ==  checkboxes.length){
			if (checkboxes[i].checked){
				cadena = cadena + "," + checkboxes[i].value
				cont +=1
			}
		}else{
			if (checkboxes[i].checked){

				if (cont > 0){
					cadena =  cadena + "," + checkboxes[i].value
				}else{
					cadena = cadena + checkboxes[i].value
				}

				cont +=1
			}
		}
	}

	return cadena
}
