// Protección CSRF común. Se agrega el token a toda petición POST autenticada,
// incluidas las cargas FormData, sin obligar a repetirlo en cada pantalla.
(function configurarCsrfIFact() {
    var contexto = document.getElementById('ifact-security-context');
    var token = contexto ? contexto.dataset.csrf : '';
    if (!token) return;

    if (window.jQuery) {
        $.ajaxPrefilter(function (opciones, originales, xhr) {
            var metodo = String(opciones.type || opciones.method || 'GET').toUpperCase();
            var destino = new URL(opciones.url || window.location.href, window.location.href);
            if (destino.origin === window.location.origin && metodo === 'POST') {
                xhr.setRequestHeader('X-CSRF-Token', token);
            }
        });
    }

    if (window.fetch) {
        var fetchOriginal = window.fetch.bind(window);
        window.fetch = function (recurso, opciones) {
            var config = Object.assign({}, opciones || {});
            var metodo = String(config.method || (recurso instanceof Request ? recurso.method : 'GET')).toUpperCase();
            var destino = new URL(recurso instanceof Request ? recurso.url : String(recurso), window.location.href);
            if (destino.origin === window.location.origin && metodo === 'POST') {
                var headers = new Headers(config.headers || (recurso instanceof Request ? recurso.headers : undefined));
                headers.set('X-CSRF-Token', token);
                config.headers = headers;
            }
            return fetchOriginal(recurso, config);
        };
    }
})();

// Presentación común de avisos en las pantallas iFact.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[data-ifact-state]').forEach(function (select) {
        var group = document.createElement('div');
        group.className = 'ifact-state-options';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', 'Estado');
        ['1', '0'].forEach(function (value) {
            var label = document.createElement('label');
            var radio = document.createElement('input');
            radio.type = 'radio'; radio.name = select.id + '_state_option'; radio.value = value;
            var text = document.createElement('span');
            text.innerHTML = '<i class="' + (value === '1' ? 'far fa-check-circle' : 'fas fa-ban') + '" aria-hidden="true"></i> ' + (value === '1' ? 'Habilitado' : 'Deshabilitado');
            label.append(radio, text); group.append(label);
            radio.addEventListener('change', function () { select.value = value; select.dispatchEvent(new Event('change', {bubbles:true})); });
        });
        select.after(group);
        function sync() { group.querySelectorAll('input').forEach(function (radio) { radio.checked = radio.value === select.value; }); }
        select.addEventListener('change', sync);
        var modal = select.closest('.modal');
        if (modal && window.jQuery) $(modal).on('show.bs.modal shown.bs.modal', sync);
        var form = select.closest('form');
        if (form) form.addEventListener('reset', function () { setTimeout(sync, 0); });
        sync();
    });
});
document.addEventListener('DOMContentLoaded', function () {
    if (!document.body.classList.contains('pos-page') || !window.Swal) return;
    var originalFire = Swal.fire;
    Swal.fire = function (options) {
        var config = typeof options === 'object' ? Object.assign({}, options) : { title: options, html: arguments[1], icon: arguments[2] };
        config.customClass = Object.assign({}, config.customClass, {
            popup: 'ifact-feedback', confirmButton: 'ifact-feedback-confirm', cancelButton: 'ifact-feedback-cancel'
        });
        if (config.showCancelButton) {
            config.customClass.popup += ' ifact-confirmation';
            config.customClass.container = 'ifact-confirmation-backdrop';
            config.reverseButtons = true;
            config.focusCancel = true;
        }
        config.buttonsStyling = false;
        config.background = '#fff';
        if (config.icon === 'warning' || config.icon === 'info') config.iconColor = '#14c9eb';
        if (!config.showCancelButton && config.showConfirmButton !== false) config.confirmButtonText = 'Entendido';
        // Respetar los modales interactivos que ocultan expresamente el botón
        // estándar (por ejemplo, la elección posterior a registrar una venta).
        if (config.icon === 'success' && !config.showCancelButton && config.showConfirmButton !== false && config.timer === undefined) {
            config.timer = 1500;
            config.showConfirmButton = true;
            config.confirmButtonText = 'Entendido';
        }
        return originalFire.call(this, config);
    };
});

function AvisoIFact(mensaje) {
    return Swal.fire({ icon: 'success', title: 'Listo', text: mensaje });
}

function AvisoValidacionIFact(mensaje, campo, icono = 'info') {
    return Swal.fire({icon:icono, title:'Falta completar un dato', text:mensaje}).then(function () {
        if (campo) document.querySelector(campo)?.focus();
    });
}

var confirmacionIFactAbierta = false;
async function ConfirmarGuardadoIFact(accion, registro) {
    if (confirmacionIFactAbierta) return false;
    confirmacionIFactAbierta = true;
    var actualizar = accion === 'actualizar';
    try {
        var resultado = await Swal.fire({
            icon: 'question',
            title: actualizar ? 'Actualizar registro' : 'Registrar ' + registro,
            text: actualizar ? '¿Estás seguro de actualizar los datos de ' + registro + '?' : '¿Quieres registrar ' + registro + '?',
            showCancelButton: true,
            confirmButtonText: actualizar ? 'Actualizar' : 'Registrar',
            cancelButtonText: 'Cancelar'
        });
        return resultado.isConfirmed === true;
    } finally {
        confirmacionIFactAbierta = false;
    }
}

// Fredy Luis
function showLoader(
    texto = 'Procesando comprobante…',
    subtexto = 'Conectando con SUNAT, por favor espere'
) {
    $("#loader h4").text(texto);
    $("#loader p").text(subtexto);

    $("#loader").addClass("active");
}

// Fredy Luis
function hideLoader() {
    $("#loader").removeClass("active");
}

// Fredy Luis - Cargar combos genérico
function Cargar_Combo(url, select_id, task, selected = 0) {

    $.ajax({
        url: url,
        type: "POST",
        data: {
            task: task
        },
        dataType: "json",

        success: function (data) {

            let html = '<option value="0">Seleccionar</option>';

            if (data && data.length > 0) {

                data.forEach(item => {

                    let sel = (item.id == selected)
                        ? 'selected'
                        : '';

                    html += `
                        <option value="${item.id}" ${sel}>
                            ${item.descripcion}
                        </option>
                    `;
                });
            }

            $("#" + select_id).html(html);

            // Forma correcta
            $("#" + select_id).val(selected);
        },

        error: function () {

            $("#" + select_id).html(
                '<option value="0">Seleccionar</option>'
            );

            $("#" + select_id).val(0);
        }
    });
}







$(document).ready(function(){

    $('#fabMain').on('click', function(e){

        e.stopPropagation();

        $('#fabOptions').toggleClass('show');

        $(this).toggleClass('active');

    });

    $(document).on('click', function(e){

        if(!$(e.target).closest('.fab-container').length){

            $('#fabOptions').removeClass('show');

            $('#fabMain').removeClass('active');

        }

    });

});

// Apoyo oportunista a la cola CPE mientras cualquier pantalla del sistema esté
// abierta. El futuro trabajador externo será el responsable principal; este
// ciclo solo adelanta emisiones pendientes sin depender de Historial de ventas.
(function () {
    if (window.iFactCpeRetryTimer || !window.jQuery) return;
    var ejecutando = false;
    function procesarColaCpe() {
        if (ejecutando || document.hidden) return;
        ejecutando = true;
        $.ajax({type:"POST",url:"../controller/facturacion.php",dataType:"json",data:{task:13,rand:Math.random()},timeout:70000})
            .always(function(){ ejecutando=false; });
    }
    window.setTimeout(procesarColaCpe, 4000);
    window.iFactCpeRetryTimer = window.setInterval(procesarColaCpe, 60000);
})();
