$(document).ready(function () {
  $('#reg_edi form').on('submit', function (event) {
    event.preventDefault();
    window.Guardar();
  });
  var tabla;
  var ent_id_editar = 0;
  var guardandoEntidad = false;
  function actualizarConsultaDocumento() {
    const tipo = String($('#ent_tipo_doc').val() || '');
    const disponible = tipo === '1' || tipo === '6';
    const servicio = tipo === '1' ? 'RENIEC' : 'SUNAT';
    $('#btn_buscar_doc').prop('hidden', !disponible).attr({title:'Consultar en ' + servicio, 'aria-label':'Consultar en ' + servicio});
    $('.entity-document-field').toggleClass('has-document-search', disponible);
    $('#ent_nro_doc').attr({maxlength:tipo === '1' ? 8 : tipo === '6' ? 11 : 15,inputmode:disponible ? 'numeric' : 'text'});
  }
  $('#ent_tipo_doc').on('change', actualizarConsultaDocumento);
  $('#reg_edi').on('show.bs.modal', actualizarConsultaDocumento);
  actualizarConsultaDocumento();

  function inicializarTabla() {
    if ($.fn.DataTable.isDataTable("#tablaListado")) {
      $("#tablaListado").DataTable().destroy();
    }

    tabla = $("#tablaListado").DataTable({
      ajax: {
        url: "../controller/entidad.php",
        type: "POST",
        data: { task: 1 },
        dataSrc: function (response) {
          if (response.status === 1) {
            return response.data;
          } else {
            console.error("Error al cargar datos:", response.message);
            return [];
          }
        },
        error: function (xhr, status, error) {
          console.error("Error AJAX:", error);
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "No se pudieron cargar los datos",
            confirmButtonColor: "#d33",
          });
        },
      },
      columns: [
        {
          data: "tipo_doc_desc",
          render: function (data, type, row) {
            var badge_color = "";
            switch (row.ent_tipo_doc) {
              case "1":
                badge_color = "bg-primary";
                break; // DNI
              case "6":
                badge_color = "bg-success";
                break; // RUC
              case "7":
                badge_color = "bg-info";
                break; // Pasaporte
              case "4":
                badge_color = "bg-warning";
                break; // Carnet Extranjería
              case "0":
                badge_color = "bg-secondary";
                break; // Doc. Trib. No Dom.
              default:
                badge_color = "bg-dark";
                break;
            }
            return (
              '<span class="badge ' +
              badge_color +
              '" style="cursor: pointer;" onclick="Editar(' +
              row.ent_id +
              ')" title="Clic para editar">' +
              data +
              "</span>"
            );
          },
        },
        {
          data: "ent_nro_doc",
          render: function (data, type, row) {
            return (
              '<span style="cursor: pointer;" onclick="Editar(' +
              row.ent_id +
              ')" title="Clic para editar" title="' +
              data +
              '">' +
              data +
              "</span>"
            );
          },
        },

        {
          data: "ent_nombres",
          render: function (data, type, row) {
            return (
              '<span style="cursor: pointer;" onclick="Editar(' +
              row.ent_id +
              ')" title="Clic para editar" title="' +
              data +
              '">' +
              data +
              "</span>"
            );
          },
        },
        {
          data: "ent_direccion",
          render: function (data, type, row) {
            return (
              '<span style="cursor: pointer;" onclick="Editar(' +
              row.ent_id +
              ')" title="Clic para editar" title="' +
              data +
              '">' +
              data +
              "</span>"
            );
          },
        },
        {
          data: "ent_email",
          render: function (data, type, row) {
            if (data) {
              return (
                '<span style="cursor: pointer;" onclick="Editar(' +
                row.ent_id +
                ')" title="Clic para editar" href="mailto:' +
                data +
                '" title="Enviar email">' +
                data +
                "</span>"
              );
            }
            return "-";
          },
        },
        {
          data: "ent_contacto",
          render: function (data, type, row) {
            if (data) {
              return (
                '<span style="cursor: pointer;" onclick="Editar(' +
                row.ent_id +
                ')" title="Clic para editar" class="badge bg-info">' +
                data +
                "</span>"
              );
            }
            return "-";
          },
        },
        {
          data: "tipo_entidad",
          render: function (data, type, row) {
            var badge_color = "";
            var icon = "";

            if (data === "Cliente y Proveedor") {
              badge_color = "bg-success";
              icon = '<i  class="fas fa-users"></i> ';
            } else if (data === "Cliente") {
              badge_color = "bg-primary";
              icon = '<i class="fas fa-user"></i> ';
            } else if (data === "Proveedor") {
              badge_color = "bg-warning";
              icon = '<i class="fas fa-truck"></i> ';
            } else {
              badge_color = "bg-secondary";
              icon = '<i class="fas fa-question"></i> ';
            }

            return (
              '<span style="cursor: pointer;" onclick="Editar(' +
              row.ent_id +
              ')" title="Clic para editar" class="badge ' +
              badge_color +
              '">' +
              icon +
              data +
              "</span>"
            );
          },
        },
        {
          data: "estado_desc",
          render: function (data, type, row) {
            var clase = "";
            if (data === "Habilitado") {
              clase = "bg-success";
            } else if (data === "Deshabilitado") {
              clase = "bg-warning";
            } else if (data === "Anulado") {
              clase = "bg-danger";
            }
            return '<span class="badge ' + clase + '">' + data + "</span>";
          },
        },
        {
          data: null,
          orderable: false,
          render: function (data, type, row) {
            var botones = "";

            // Botón anular (si está habilitado o deshabilitado)
            if (row.ent_estado != 2) {
              botones +=
                '<button class="btn btn-sm btn-danger" onclick="Anular(' +
                row.ent_id +
                ')" title="Anular">';
              botones += '<i class="fas fa-trash-alt"></i>';
              botones += "</button>";
            }

            return botones;
          },
        },
      ],
      createdRow: function (row) {
        $(row).find('[onclick]').not('button').removeAttr('onclick');
        $(row).find('td').attr({tabindex:0,title:'Clic para editar'}).css('cursor','pointer');
      },
      language: {
        url: "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json",
      },
    });
  }

  $('#tablaListado').on('click keydown', 'tbody td', function (event) {
    if ($(event.target).closest('button, a, input, select').length) return;
    if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
    const registro = tabla.row($(this).closest('tr')).data();
    if (!registro || guardandoEntidad) return;
    event.preventDefault();
    window.Editar(registro.ent_id);
  });

  function ConsultarDNI(dni) {
    // Validar formato
    if (!/^[0-9]{8}$/.test(dni)) {
      return;
    }

    // Mostrar loading
    Swal.fire({
      title: "Consultando DNI...",
      text: "Por favor espere",
      icon: "info",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    $.ajax({
      url: "../controller/consultas_api.php",
      type: "GET",
      data: {
        task: 7,
        dni: dni,
      },
      dataType: "json",
      success: function (response) {
        Swal.close();

        if (response && response.length > 0 && response[0].success == 1) {
          var datos = response[0];

          // Llenar campos
          var nombreCompleto =
            datos.nombres + " " + datos.ape_paterno + " " + datos.ape_materno;
          $("#ent_nombres").val(nombreCompleto.trim());

          Swal.fire({
            icon: "success",
            title: "¡Datos encontrados!",
            text: "Se encontraron los datos del DNI",
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: true,
          });
        } else {
          Swal.fire({
            icon: "warning",
            title: "No encontrado",
            text: response.message || "No se encontraron datos para este DNI",
            confirmButtonColor: "#3085d6",
          });
        }
      },
      error: function (xhr, status, error) {
        Swal.close();
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "No se pudo consultar el DNI. Intente nuevamente.",
          confirmButtonColor: "#d33",
        });
      },
    });
  }

  // Consultar RUC en SUNAT
  function ConsultarRUC(ruc) {
    // Validar formato
    if (!/^[0-9]{11}$/.test(ruc)) {
      return;
    }

    // Mostrar loading
    Swal.fire({
      title: "Consultando RUC...",
      text: "Por favor espere",
      icon: "info",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    $.ajax({
      url: "../controller/consultas_api.php",
      type: "GET",
      data: {
        task: 8,
        ruc: ruc,
      },
      dataType: "json",
      success: function (response) {
        Swal.close();

        var datos = Array.isArray(response) ? response[0] : response;

        if (datos && datos.success == true) {
          $("#ent_nombres").val(datos.razon_social.trim());
          if (datos.direccion) $("#ent_direccion").val(datos.direccion.trim());

          Swal.fire({
            icon: "success",
            title: "¡Datos encontrados!",
            text: "Se encontraron los datos del RUC",
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: true,
          });
        } else {
          Swal.fire({
            icon: "warning",
            title: "No encontrado",
            text: (datos && (datos.message || datos.error)) || "No se encontraron datos para este RUC",
            confirmButtonColor: "#3085d6",
          });
        }
      },
      error: function () {
        Swal.close();
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "No se pudo consultar el RUC. Intente nuevamente.",
          confirmButtonColor: "#d33",
        });
      },
    });
  }

  // =========================
  // FUNCIONES PRINCIPALES
  // =========================

  // Nuevo registro
  window.Nuevo = function () {
    ent_id_editar = 0;
    LimpiarFormulario();
    $("#nombre_opcion").text("Nueva Entidad");
    $("#reg_edi").modal("show");
    HabilitarBotonGuardar();
  };

  // Evento para el botón de búsqueda
  $(document).on("click", "#btn_buscar_doc", function () {
    var ent_tipo_doc = $("#ent_tipo_doc").val();
    var ent_nro_doc = $("#ent_nro_doc").val().trim();

    if (!ent_tipo_doc) {
      Swal.fire({
        icon: "info",
        title: "Falta completar un dato",
        text: "Seleccione el tipo de documento",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    if (!ent_nro_doc) {
      Swal.fire({
        icon: "info",
        title: "Falta completar un dato",
        text: "Ingrese el número de documento",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    if (!ValidarDocumentoPorTipo(ent_tipo_doc, ent_nro_doc)) {
      return;
    }

    if (ent_tipo_doc == "1") {
      // DNI
      ConsultarDNI(ent_nro_doc);
    } else if (ent_tipo_doc == "6") {
      // RUC
      ConsultarRUC(ent_nro_doc);
    } else {
      Swal.fire({
        icon: "info",
        title: "Información",
        text: "La consulta solo está disponible para DNI y RUC",
        confirmButtonColor: "#3085d6",
      });
    }
  });

  function HabilitarBotonGuardar() {
    var botonGuardar = $("#btn_guardar");
    botonGuardar.prop("disabled", false);
    botonGuardar.removeClass("btn-secondary").addClass("btn-primary");
    botonGuardar.html('<i class="fa fa-check me-1"></i> Guardar');
  }

  // Editar registro
  window.Editar = function (ent_id) {
    ent_id_editar = ent_id;
    HabilitarBotonGuardar();

    $.ajax({
      url: "../controller/entidad.php",
      type: "POST",
      data: {
        task: 4,
        ent_id: ent_id,
      },
      dataType: "json",
      success: function (response) {
        if (response.status === 1) {
          $("#ent_tipo_doc").val(response.data.ent_tipo_doc);
          $("#ent_nro_doc").val(response.data.ent_nro_doc);
          $("#ent_nombres").val(response.data.ent_nombres);
          $("#ent_direccion").val(response.data.ent_direccion);
          $("#ent_marca").val(response.data.ent_marca || "");
          $("#ent_email").val(response.data.ent_email);
          $("#ent_contacto").val(response.data.ent_contacto);
          $("#ent_cliente").prop("checked", Number(response.data.ent_cliente) === 1);
          $("#ent_proveedor").prop("checked", Number(response.data.ent_proveedor) === 1);
          $("#ent_estado").val(response.data.ent_estado);

          $("#nombre_opcion").text("Editar Entidad");
          $("#reg_edi").modal("show");
        } else {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: response.message,
            confirmButtonColor: "#d33",
          });
        }
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "No se pudieron obtener los datos",
          confirmButtonColor: "#d33",
        });
      },
    });
  };

  // Anular registro
  window.Anular = function (ent_id) {
    Swal.fire({
      title: "¿Anular Entidad?",
      text: "Esta acción no se puede revertir. La entidad quedará inactiva.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Sí, anular",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "../controller/entidad.php",
          type: "POST",
          data: {
            task: 7,
            ent_id: ent_id,
          },
          dataType: "json",
          success: function (response) {
            if (response.status === 1) {
              Swal.fire({
                icon: "success",
                title: "¡Anulado!",
                text: response.message,
                timer: 1500,
                timerProgressBar: true,
                showConfirmButton: true,
              });
              tabla.ajax.reload();
            } else {
              Swal.fire({
                icon: "error",
                title: "Error",
                text: response.message,
                confirmButtonColor: "#d33",
              });
            }
          },
          error: function () {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: "No se pudo anular la entidad",
              confirmButtonColor: "#d33",
            });
          },
        });
      }
    });
  };

  // Guardar registro (nuevo o edición)
  window.Guardar = async function () {
    if (guardandoEntidad) return;
    var ent_tipo_doc = $("#ent_tipo_doc").val();
    var ent_nro_doc = $("#ent_nro_doc").val().trim();
    var ent_nombres = $("#ent_nombres").val().trim();
    var ent_direccion = $("#ent_direccion").val().trim();
    var ent_marca = $("#ent_marca").val().trim();
    var ent_email = $("#ent_email").val().trim();
    var ent_contacto = $("#ent_contacto").val().trim();
    var ent_cliente = $("#ent_cliente").is(":checked") ? 1 : 0;
    var ent_proveedor = $("#ent_proveedor").is(":checked") ? 1 : 0;
    var ent_estado = $("#ent_estado").val();

    if (!ent_tipo_doc) {
      Swal.fire({
        icon: "info",
        title: "Falta completar un dato",
        text: "Debe seleccionar un tipo de documento",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    if (ent_nro_doc === "") {
      Swal.fire({
        icon: "info",
        title: "Falta completar un dato",
        text: "El número de documento es obligatorio",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    if (!ValidarDocumentoPorTipo(ent_tipo_doc, ent_nro_doc)) {
      return;
    }

    if (ent_nombres === "") {
      Swal.fire({
        icon: "info",
        title: "Falta completar un dato",
        text: "El nombre o razón social es obligatorio",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    if (ent_email && !isValidEmail(ent_email)) {
      Swal.fire({
        icon: "info",
        title: "Falta completar un dato",
        text: "El email no es válido",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    if (ent_cliente == 0 && ent_proveedor == 0) {
      Swal.fire({
        icon: "info",
        title: "Falta completar un dato",
        text: "La entidad debe ser cliente, proveedor o ambos",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    if (await ConfirmarGuardadoIFact(ent_id_editar > 0 ? 'actualizar' : 'registrar', 'entidad')) {
      ejecutarGuardar();
    }
  };
  // Función para ejecutar el guardado (extraída del código original)
  function ejecutarGuardar() {
    if (guardandoEntidad) return;
    guardandoEntidad = true;
    $('#btn_guardar').prop('disabled', true);
    var ent_tipo_doc = $("#ent_tipo_doc").val();
    var ent_nro_doc = $("#ent_nro_doc").val().trim();
    var ent_nombres = $("#ent_nombres").val().trim();
    var ent_direccion = $("#ent_direccion").val().trim();
    var ent_marca = $("#ent_marca").val().trim();
    var ent_email = $("#ent_email").val().trim();
    var ent_contacto = $("#ent_contacto").val().trim();
    var ent_cliente = $("#ent_cliente").is(":checked") ? 1 : 0;
    var ent_proveedor = $("#ent_proveedor").is(":checked") ? 1 : 0;
    var ent_estado = $("#ent_estado").val();

    var action = ent_id_editar > 0 ? 6 : 5;
    var data = {
      task: action,
      ent_tipo_doc: ent_tipo_doc,
      ent_nro_doc: ent_nro_doc,
      ent_nombres: ent_nombres,
      ent_direccion: ent_direccion,
      ent_marca: ent_marca,
      ent_email: ent_email,
      ent_contacto: ent_contacto,
      ent_cliente: ent_cliente,
      ent_proveedor: ent_proveedor,
      ent_estado: ent_estado,
    };

    // Agregar ID si es edición
    if (ent_id_editar > 0) {
      data.ent_id = ent_id_editar;
    }

    // Mostrar loading
    Swal.fire({
      title: "Procesando...",
      text: "Por favor espere",
      icon: "info",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    // Enviar datos
    $.ajax({
      url: "../controller/entidad.php",
      type: "POST",
      data: data,
      dataType: "json",
      complete: function () {
        guardandoEntidad = false;
        $('#btn_guardar').prop('disabled', false);
      },
      success: function (response) {
        Swal.close();

        if (response.status === 1) {
          Swal.fire({
            icon: "success",
            title: "Listo",
            text: response.message,
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: true,
          });

          // Cerrar modal y recargar tabla
          $("#reg_edi").modal("hide");
          tabla.ajax.reload();
        } else {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: response.message,
            confirmButtonColor: "#d33",
          });
        }
      },
      error: function () {
        Swal.close();
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "No se pudo procesar la solicitud",
          confirmButtonColor: "#d33",
        });
      },
    });
  }

  // =========================
  // FUNCIONES AUXILIARES
  // =========================

  // Limpiar formulario
  function LimpiarFormulario() {
    $("#ent_tipo_doc").val("");
    $("#ent_nro_doc").val("");
    $("#ent_nombres").val("");
    $("#ent_direccion").val("");
    $("#ent_marca").val("");
    $("#ent_marca").val("");
    $("#ent_email").val("");
    $("#ent_contacto").val("");
    $("#ent_cliente").prop("checked", false);
    $("#ent_proveedor").prop("checked", false);
    $("#ent_estado").val("1");
  }

  // Validar email
  function isValidEmail(email) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  function ValidarDocumentoPorTipo(tipoDoc, numeroDocumento) {
    var configuracion = {
      "1": { nombre: "DNI", longitud: 8 },
      "6": { nombre: "RUC", longitud: 11 },
    };
    var tipo = configuracion[tipoDoc];

    if (!tipo) return true;

    var expresion = new RegExp("^[0-9]{" + tipo.longitud + "}$");
    if (expresion.test(numeroDocumento)) return true;

    Swal.fire({
      icon: "warning",
      title: "Documento inválido",
      text: "El " + tipo.nombre + " debe tener exactamente " + tipo.longitud + " dígitos numéricos.",
      confirmButtonColor: "#3085d6",
    });
    $("#ent_nro_doc").focus();
    return false;
  }

  // =========================
  // EVENTOS DE CAMPOS
  // =========================

  // Validar que solo se ingresen números en el documento
  $("#ent_nro_doc").on("keypress", function (e) {
    var charCode = e.which ? e.which : e.keyCode;
    // Permitir números y backspace
    if ((charCode < 48 || charCode > 57) && charCode != 8) {
      e.preventDefault();
      return false;
    }
  });

  // Evento cuando cambia el tipo de documento
  $("#ent_tipo_doc").on("change", function () {
    var tipoDoc = $(this).val();

    var longitudMaxima = tipoDoc === "1" ? 8 : (tipoDoc === "6" ? 11 : 15);
    $("#ent_nro_doc").attr("maxlength", longitudMaxima).val(function (_i, valor) {
      return valor.substring(0, longitudMaxima);
    });

    // Solo limpiar campos si cambia el tipo de documento (no consultar automáticamente)
    $("#ent_nombres").val("");
    $("#ent_direccion").val("");
  });

  // Validar que no se ingresen caracteres especiales en nombres
  $("#ent_nombres").on("keypress", function (e) {
    var charCode = e.which ? e.which : e.keyCode;
    // Permitir letras, números, espacios y algunos caracteres especiales
    if (
      charCode == 32 ||
      charCode == 45 ||
      charCode == 46 ||
      charCode == 44 || // espacio, guion, punto, coma
      (charCode >= 65 && charCode <= 90) || // A-Z
      (charCode >= 97 && charCode <= 122) || // a-z
      (charCode >= 48 && charCode <= 57)
    ) {
      // 0-9
      return true;
    }
    e.preventDefault();
    return false;
  });

  // Validar contacto (solo números y algunos caracteres)
  $("#ent_contacto").on("keypress", function (e) {
    var charCode = e.which ? e.which : e.keyCode;
    // Permitir números, +, -, (, ), espacio y backspace
    if (
      charCode == 32 ||
      charCode == 40 ||
      charCode == 41 ||
      charCode == 43 ||
      charCode == 45 || // espacio, (), +-
      (charCode >= 48 && charCode <= 57) || // 0-9
      charCode == 8
    ) {
      // backspace
      return true;
    }
    e.preventDefault();
    return false;
  });

  // =========================
  // INICIALIZACIÓN
  // =========================

  // Inicializar la tabla al cargar la página
  inicializarTabla();

  // Limpiar formulario al cerrar el modal
  $("#reg_edi").on("hidden.bs.modal", function () {
    LimpiarFormulario();
    ent_id_editar = 0;
  });
});
