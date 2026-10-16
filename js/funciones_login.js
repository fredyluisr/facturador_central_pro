
// Fredy Luis
function verificar()
{
	if ($("#usu_token").val().length == 8)
	{
	    $.ajax({
		    	type: "get",
				dataType: 'json',
				url:  "../model/verificar_sesion.php",
				async: false,
				data: "usu_token="+$("#usu_token").val()+
					  "&rand="+parseInt(Math.random()*99999999),
					
				success: function(datos){

							if(parseInt(datos[0].cant_perfiles) > 0)
							{
								if(parseInt(datos[0].cant_perfiles) == 1) // Cuando sólo tiene un perfil
									Ingresar_Sistema(1, datos[0].prf_id)
								else{
									var selector = document.getElementById("perfil")

									$.ajax({
										type: "get",
										dataType: "json",
										url: "../controller/perfil.php",
										async: false,
										data: 	"task=5"+ // mostrar_lista_perfiles
												"&usu_token="+$("#usu_token").val()+
												"&rand="+parseInt(Math.random()*99999999),

										success: function(datos){

													document.getElementById("perfil").length = 0;
													selector.options[0] = new Option("Seleccionar Perfil", "0")

													if (datos != '0')
														for(var i=0;i<datos.length;i++){
															selector.options[i+1] = new Option(datos[i].nombre, datos[i].id)
														}

													$("#perfil").val(0)
													var modalRol = new bootstrap.Modal(document.getElementById('seleccion_perfil'));
													modalRol.show();
										}
									});
								}
							}
							else
							{
								Swal.fire({
									title: "Error!",
									text: "¡Token incorrecto!",
									icon: "error",
									confirmButtonClass: "btn btn-danger",
									buttonsStyling: false,
								});
							}
				}
			});

	}else{

		Swal.fire({
      		title: "El tamaño de su Token debe ser 8 caracteres",
			icon: "info",
			confirmButtonClass: "btn btn-primary",
			buttonsStyling: false,
    	});
	}
}

// Fredy Luis
function Ingresar_Sistema(tipo, prf_id)
{
	var perfil

	if (tipo == 1) // Tiene sólo 1 perfil
		perfil = prf_id
	else {
		if ($('#perfil').val() == 0) {
			Swal.fire({
				title: "Selecciona un rol",
				text: "Debes seleccionar un rol para continuar.",
				icon: "warning",
				confirmButtonText: "Entendido",
				confirmButtonClass: "btn btn-warning",
				buttonsStyling: false
			});
			return;
		}
		perfil = $('#perfil').val();
	}

	var modalEl = document.getElementById('seleccion_perfil');
	if (modalEl) {
		var modalInst = bootstrap.Modal.getInstance(modalEl);
		if (modalInst) modalInst.hide();
	}

	var locales = Array.isArray(window.ifactLocalesLogin) ? window.ifactLocalesLogin : [];
	if (locales.length === 0) {
		Swal.fire({title:"Acceso no disponible",text:"No existe un local habilitado para iniciar la jornada.",icon:"error",confirmButtonText:"Entendido"});
		return;
	}
	if (locales.length === 1) {
		$("#loc_id").val(locales[0].loc_id);
		CompletarIngreso(perfil);
		return;
	}

	var contenedor = $("#opciones_local").empty();
	locales.forEach(function(local){
		var boton = $('<button type="button" class="local-login-card"><span><i class="fas fa-store"></i></span><div><strong></strong><small></small></div></button>');
		boton.find('strong').text(local.loc_nombre);
		boton.find('small').text(local.loc_direccion || 'Punto de venta');
		boton.on('click',function(){
			$("#loc_id").val(local.loc_id);
			var instancia=bootstrap.Modal.getInstance(document.getElementById('seleccion_local'));
			if(instancia)instancia.hide();
			CompletarIngreso(perfil);
		});
		contenedor.append(boton);
	});
	bootstrap.Modal.getOrCreateInstance(document.getElementById('seleccion_local')).show();
}

function CompletarIngreso(perfil)
{
	$.ajax({
		type: "post",
		dataType: 'json',
		url: "../model/obtener_datos_usuario.php",
		data: {
			usu_token: $("#usu_token").val(),
			prf_id: perfil,
			loc_id: parseInt($("#loc_id").val()),
            remember_me: $("#remember_me").is(":checked") ? 1 : 0,
			rand: parseInt(Math.random() * 99999999)
		},
		success: function (datos) {

			// Validar estructura correcta
			if (datos.status === 1) {

				Swal.fire({
						title: "Bienvenido a iFact",
						html: "Acceso confirmado. Todo está listo para empezar a vender.",
						icon: "success",
						confirmButtonText: "Entrar al sistema",
						timer: 1500,
						timerProgressBar: true,
						backdrop: "rgba(1, 4, 12, .82)",
						allowOutsideClick: false,
						allowEscapeKey: false,
						buttonsStyling: false,
						customClass: {
							popup: "ifact-alert",
							confirmButton: "ifact-alert-confirm"
						}
					}).then(() => {
						window.location.href = "index.php";
					});

			} else {
				Swal.fire({title:"No se pudo ingresar",text:datos.message||"Revisa el local y vuelve a intentarlo.",icon:"error",confirmButtonText:"Entendido"});
			}
		},
		error: function (xhr, status, error) {
			console.error("Error AJAX:", error);
		}
	});
}

// Fredy Luis
function Verificar_enter(e)
{
	if(e.keyCode === 13){
		e.preventDefault();

		verificar()
	}
}
