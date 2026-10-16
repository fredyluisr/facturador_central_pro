var tabla;

$(document).ready(function () {
  let hoy = new Date();
  let dia = String(hoy.getDate()).padStart(2, "0");
  let mes = String(hoy.getMonth() + 1).padStart(2, "0");
  let year = hoy.getFullYear();
  let fechaHoy = `${dia}-${mes}-${year}`;

  if ($(".datetimepicker").length > 0) {
    $(".datetimepicker").datetimepicker({
      format: "DD-MM-YYYY",
      defaultDate: hoy,
      icons: {
        up: "fas fa-angle-up",
        down: "fas fa-angle-down",
        next: "fas fa-angle-right",
        previous: "fas fa-angle-left",
      },
    });
  }

  $("#filtro_fecha_inicio").val(fechaHoy);

  $(".select2").select2();
  Listar();
});

function formatearDuracion(minutos) {
  if (minutos === null) return "-";
  const horas = Math.floor(minutos / 60);
  const mins = minutos % 60;
  return horas > 0 ? `${horas}h ${mins}m` : `${mins}m`;
}

function renderEstado(session_status) {
  return session_status == 1
    ? '<span class="badge bg-success">Activa</span>'
    : '<span class="badge bg-secondary">Cerrada</span>';
}

function renderBotones(data) {
  let botones = "";

  botones += `<button type="button" 
									class="btnVer btn btn-primary btn-sm" 
									title="Ver"
									onclick="Ver_Detalles('${data.id}')"
                                    >
									<i class="fa fa-search"></i>
								</button>&nbsp;`;

  if (data.session_status == 1) {
    botones += `<button type="button" 
									class="btnVer btn btn-danger btn-sm" 
									title="Salir de esta sesión"
									onclick="Cerrar_Sesion('${data.id}')"
                                    >
									<i class="fas fa-sign-out-alt text-white"></i>
								</button>`;
  }

 /*
  if (data.usu_id) {
    botones += ` <button type="button" 
                                    class="btnVer btn btn-info btn-sm" 
                                    title="Ver sesiones del usuario"
                                    onclick="Ver_Sesiones_Usuario('${data.usu_id}')"
                                    >
                                    <i class="fas fa-users text-white"></i>
                                </button>`;
  }
 */

  return botones;
}

// ==========================================
// Listar todas las sesiones
// ==========================================
function Listar() {
  tabla = $("#tablaListado").DataTable({
    destroy: true,
    responsive: true,
    pageLength: 25,
    ajax: {
      method: "GET",
      url: "../controller/acceso.php",
      data: { task: 1 },
      dataSrc: "",
    },
    columns: [
      { data: "id" },
      { data: "usuario_nombre", render: (d) => d || "Anónimo" },
      { data: "rol_nombre", render: (d) => d || "N/A" },
      {
        data: "fecha_login",
        render: (d) => (d ? moment(d).format("DD/MM/YYYY HH:mm") : "-"),
      },
      { data: "ip_address" },
      { data: "duracion_minutos", render: formatearDuracion },
      {
        data: "user_agent",
        render: (d) =>
          d ? (d.length > 30 ? d.substring(0, 30) + "..." : d) : "-",
      },
      { data: "session_status", render: renderEstado },
      { data: null, render: renderBotones },
    ],
    columnDefs: [{ targets: [0], visible: false }],
    language: {
      url: "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json",
    },
    order: [[3, "desc"]],
  });
}

// ==========================================
// Listar solo sesiones activas
// ==========================================
function Listar_Activas() {
  tabla = $("#tablaListado").DataTable({
    destroy: true,
    responsive: true,
    pageLength: 25,
    ajax: {
      method: "GET",
      url: "../controller/acceso.php",
      data: { task: 2 },
      dataSrc: "",
    },
    columns: [
      { data: "id" },
      { data: "usuario_nombre", render: (d) => d || "Anónimo" },
      { data: "rol_nombre", render: (d) => d || "N/A" },
      { data: "duracion_minutos", render: formatearDuracion },
      { data: "ip_address" },
      {
        data: "user_agent",
        render: (d) =>
          d ? (d.length > 30 ? d.substring(0, 30) + "..." : d) : "-",
      },
      {
        data: "session_status",
        render: () => '<span class="badge bg-success">Activa</span>',
      },
      { data: null, render: renderBotones },
    ],
    columnDefs: [{ targets: [0], visible: false }],
    language: {
      url: "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json",
    },
    order: [[3, "desc"]],
  });
}

// ==========================================
// Ver detalles de una sesión -Gerson
// ==========================================
function Ver_Detalles(session_id) {
  $.ajax({
    type: "GET",
    dataType: "json",
    url: "../controller/acceso.php",
    data: { task: 3, session_id },
    success: function (datos) {
      if (datos.length === 0) {
        Swal.fire(
          "Info",
          "No se encontraron detalles para esta sesión",
          "info",
        );
        return;
      }

      const d = datos[0];
      $("#det_session_id").text(d.id);
      $("#det_usuario").text(d.usuario_nombre || "Anónimo");
      $("#det_rol").text(d.rol_nombre || "N/A");

      $("#det_fecha_login").text(
        d.fecha_login
          ? moment(d.fecha_login).format("DD/MM/YYYY HH:mm:ss")
          : "-",
      );
      $("#det_fecha_logout").text(
        d.fecha_logout
          ? moment(d.fecha_logout).format("DD/MM/YYYY HH:mm:ss")
          : "Sesión Activa",
      );

      $("#det_ip").text(d.ip_address || "-");
      $("#det_user_agent").text(d.user_agent || "-");
      $("#det_logout_reason").text(d.logout_reason || "N/A");
      $("#det_estado").html(renderEstado(d.session_status));

      $("#modal_detalles").modal("show");
    },
    error: function () {
      Swal.fire("Error", "No se pudo cargar los detalles", "error");
    },
  });
}

// ==========================================
// Cerrar una sesión específica
// ==========================================
function Cerrar_Sesion(session_id) {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "Se cerrará esta sesión activa",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, cerrar",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "../controller/acceso.php",
        { task: 4, session_id },
        function (response) {
          if (Number(response) === 1) {
            const esSesionActual = String(session_id) === String(window.IFACT_SESSION_ID || "");
            Swal.fire({
              title: "Éxito",
              text: "Sesión cerrada correctamente",
              icon: "success",
              timer: 2000,
            }).then(() => {
              if (esSesionActual) {
                window.location.replace("login.php");
              } else {
                tabla.ajax.reload(null, false);
              }
            });
          } else {
            Swal.fire("Error", "No se pudo cerrar la sesión", "error");
          }
        },
      ).fail(() => Swal.fire("Error", "No se pudo cerrar la sesión", "error"));
    }
  });
}

function Cerrar_Todas_Las_Sesiones() {
  Swal.fire({
    title: "¿Cerrar todas las sesiones activas?",
    text: "Todos los usuarios, incluida esta sesión, deberán iniciar sesión nuevamente.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#e6293f",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Sí, cerrar todas",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.ajax({
      type: "POST",
      dataType: "json",
      url: "../controller/acceso.php",
      data: { task: 9 },
      success: function (response) {
        if (!response || response.success !== true) {
          Swal.fire("Error", "No se pudieron cerrar las sesiones", "error");
          return;
        }

        Swal.fire({
          title: "Sesiones cerradas",
          text: `${response.cerradas} sesión(es) activa(s) cerrada(s).`,
          icon: "success",
          confirmButtonText: "Entendido",
          allowOutsideClick: false,
        }).then(() => {
          window.location.replace("login.php");
        });
      },
      error: function () {
        Swal.fire("Error", "No se pudieron cerrar las sesiones", "error");
      },
    });
  });
}


// ==========================================
// Limpiar sesiones antiguas
// ==========================================
function Limpiar_Antiguas() {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "Se eliminarán las sesiones cerradas de más de 30 días",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, limpiar",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (result.isConfirmed) {
      $.get("../controller/acceso.php", { task: 8 }, function (response) {
        Swal.fire(
          "Éxito",
          `Se eliminaron ${response} sesiones antiguas`,
          "success",
        );
        tabla.ajax.reload();
      }).fail(() =>
        Swal.fire("Error", "No se pudo realizar la limpieza", "error"),
      );
    }
  });
}

// ==========================================
// Aplicar filtros

function Aplicar_Filtros() {
    // 1. Obtener valores
    let fechaInicio = $("#filtro_fecha_inicio").val();
    let fechaFin = $("#filtro_fecha_fin").val();
    let estado = $("#filtro_estado").val(); // Asegúrate de tener este ID en un <select>

    // 2. Destruir y recargar DataTable con parámetros
    tabla = $("#tablaListado").DataTable({
        destroy: true,
        responsive: true,
        pageLength: 25,
        ajax: {
            method: "GET",
            url: "../controller/acceso.php",
            data: { 
                task: 1, 
                f_ini: fechaInicio, 
                f_fin: fechaFin, 
                est: estado 
            },
            dataSrc: "",
        },
        columns: [
            { data: "id" },
            { data: "usuario_nombre", render: (d) => d || "Anónimo" },
            { data: "rol_nombre", render: (d) => d || "N/A" },
            {
                data: "fecha_login",
                render: (d) => (d ? moment(d).format("DD/MM/YYYY HH:mm") : "-"),
            },
            { data: "ip_address" },
            { data: "duracion_minutos", render: formatearDuracion },
            {
                data: "user_agent",
                render: (d) => d ? (d.length > 30 ? d.substring(0, 30) + "..." : d) : "-",
            },
            { data: "session_status", render: renderEstado },
            { data: null, render: renderBotones },
        ],
        columnDefs: [{ targets: [0], visible: false }],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json",
        },
        order: [[3, "desc"]],
    });
}
