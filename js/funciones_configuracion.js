$(function(){cargarConfiguracion();$('#btn_guardar_facturacion').on('click',guardarFacturacionElectronica);$('input[name="cfg_facturacion"]').on('change',actualizarEstadoFacturacion);$('#btn_guardar_nota').on('click',guardarNotaPedido);$('#btn_guardar_nombre_comercial').on('click',guardarNombreComercial);$('#btn_buscar_empresa').on('click',buscarEmpresas);$('#cfg_buscar_empresa').on('keydown',function(e){if(e.key==='Enter'){e.preventDefault();buscarEmpresas();}});$('#cfg_resultados_empresa').on('click','.btn-select-company',seleccionarEmpresa);});
function ModalConfiguracion(icono,mensaje,titulo){return Swal.fire({icon:icono,title:titulo||'Configuración del sistema',text:mensaje,confirmButtonText:'Entendido',returnFocus:true});}
function escaparHtml(v){return $('<div>').text(v==null?'':String(v)).html();}
$(function() {
    $('#btn_guardar_stock').on('click', function() {
        var b=$(this).prop('disabled',true);
        $.post('../controller/configuracion_sistema.php', {task:5,vender_sin_stock:$('input[name="vender_sin_stock"]:checked').val()},null,'json')
            .done(function(r) { if (r.ok) { AvisoIFact(r.mensaje); $('#cfg_stock_estado').text('Modo guardado. Aplica al abrir una nueva venta.'); } else ModalConfiguracion('error',r.mensaje); })
            .fail(function() { ModalConfiguracion('error','No se pudo guardar el modo de venta.'); }).always(function() { b.prop('disabled',false); });
    });
});
function buscarEmpresas(){const q=$('#cfg_buscar_empresa').val().trim(),b=$('#btn_buscar_empresa');if(q.length<3){ModalConfiguracion('warning','Ingrese al menos 3 caracteres','Empresa emisora');return;}b.prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span> Buscando…');$('#cfg_resultados_empresa').removeClass('d-none').html('<div class="company-empty">Consultando empresas…</div>');$.getJSON('../controller/configuracion_sistema.php',{task:3,q:q}).done(function(r){if(!r.ok){$('#cfg_resultados_empresa').html('<div class="company-empty text-danger">'+escaparHtml(r.mensaje||'No se pudo realizar la búsqueda')+'</div>');return;}const e=r.empresas||[];if(!e.length){$('#cfg_resultados_empresa').html('<div class="company-empty"><i class="fas fa-search"></i> No se encontraron empresas activas.</div>');return;}$('#cfg_resultados_empresa').html(e.map(function(x){const d=encodeURIComponent(JSON.stringify({id:x.em_id,ruc:x.em_ruc,nombre:x.em_razon_social}));return '<article class="company-result"><span class="company-result-icon"><i class="fas fa-building"></i></span><div><strong>'+escaparHtml(x.em_razon_social)+'</strong><span>RUC '+escaparHtml(x.em_ruc)+'</span><small>'+escaparHtml(x.em_direccion||'Dirección no registrada')+'</small></div><span class="company-mode">'+escaparHtml((x.qpse_modo||'sin modo').toUpperCase())+'</span><button type="button" class="btn btn-select-company" data-company="'+d+'">Seleccionar</button></article>';}).join(''));}).fail(function(x){$('#cfg_resultados_empresa').html('<div class="company-empty text-danger">'+escaparHtml(x.responseJSON&&x.responseJSON.mensaje||'No se pudo consultar el registro central')+'</div>');}).always(function(){b.prop('disabled',false).html('<i class="fas fa-search"></i> Buscar');});}
async function seleccionarEmpresa(){const e=JSON.parse(decodeURIComponent($(this).attr('data-company')));if(String(e.ruc)===$('#cfg_ruc').text().replace(/\D/g,'')){ModalConfiguracion('info','Esta empresa ya se encuentra configurada','Empresa emisora');return;}if(!await Swal.fire({icon:'question',title:'Cambiar empresa emisora',text:'¿Deseas vincular '+e.nombre+' (RUC '+e.ruc+')? Los comprobantes nuevos se emitirán con esta empresa.',showCancelButton:true,confirmButtonText:'Sí, cambiar',cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed))return;const b=$(this).prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span> Guardando…');$.post('../controller/configuracion_sistema.php',{task:4,empresa_id:e.id},null,'json').done(function(r){if(!r.ok){ModalConfiguracion('error',r.mensaje||'No se pudo cambiar la empresa','Error');b.prop('disabled',false).text('Seleccionar');return;}AvisoIFact(r.mensaje);$('#cfg_buscar_empresa').val('');$('#cfg_resultados_empresa').addClass('d-none').empty();cargarConfiguracion();}).fail(function(x){ModalConfiguracion('error',x.responseJSON&&x.responseJSON.mensaje||'No se pudo cambiar la empresa','Error');b.prop('disabled',false).text('Seleccionar');});}
function cargarConfiguracion(){
    $.getJSON('../controller/configuracion_sistema.php',{task:1}).done(function(r){
        if(!r.ok){
            ModalConfiguracion('error',r.mensaje||'No se pudo cargar la configuración','Error de configuración');
            return;
        }
        const d=r.datos||{};
        $('input[name="vender_sin_stock"][value="'+(Number(d.csi_vender_sin_stock)===1?1:0)+'"]').prop('checked',true);
        $('input[name="cfg_facturacion"][value="'+(Number(d.csi_facturacion_electronica)===1?1:0)+'"]').prop('checked',true);
        actualizarEstadoFacturacion();
        $('#cfg_empresa').text(d.csi_razon_social||'No disponible');
        $('#cfg_ruc').text(d.csi_ruc?'RUC '+d.csi_ruc:'RUC no disponible');
        $('#cfg_nombre_comercial').val(d.csi_nombre_comercial||'');
        $('#cfg_razon_social').text(d.csi_razon_social||'No disponible');
        $('#cfg_emisor_ruc').text('RUC '+(d.csi_ruc||'no disponible'));
        $('#cfg_nota_serie').val(d.csi_nota_pedido_serie||'NV01');
        $('#cfg_nota_siguiente').val(Number(d.csi_nota_pedido_siguiente)||1);
        $('#cfg_modo').text('VERIFICANDO…').removeClass('text-warning text-danger text-success');
        $('#cfg_conexion').text('Verificando…').removeClass('text-danger text-success').attr('title','Consultando servicio central');
        $('#cfg_estado').text('Sin verificar').removeClass('text-danger text-success');
        $('#config_content').removeClass('d-none');
        cargarEstadoCentral();
    }).fail(function(x){
        ModalConfiguracion('error',x.responseJSON&&x.responseJSON.mensaje||'No se pudo consultar la configuración','Error de conexión');
    });
}
function actualizarEstadoFacturacion(){const activa=Number($('input[name="cfg_facturacion"]:checked').val())===1;$('#cfg_facturacion_estado').text(activa?'Actualmente: con facturación electrónica.':'Actualmente: sin facturación electrónica.');}
function guardarFacturacionElectronica(){const valor=$('input[name="cfg_facturacion"]:checked').val(),b=$('#btn_guardar_facturacion');if(valor!=='0'&&valor!=='1')return ModalConfiguracion('warning','Seleccione una opción de facturación.','Facturación electrónica');b.prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span> Guardando…');$.post('../controller/configuracion_sistema.php',{task:9,habilitada:valor},null,'json').done(function(r){if(r.ok){actualizarEstadoFacturacion();AvisoIFact(r.mensaje);}else ModalConfiguracion('error',r.mensaje||'No se pudo guardar.','Facturación electrónica');}).fail(function(x){ModalConfiguracion('error',x.responseJSON&&x.responseJSON.mensaje||'No se pudo guardar la configuración.','Facturación electrónica');}).always(function(){b.prop('disabled',false).html('<i class="fas fa-save"></i> Guardar configuración');});}
function cargarEstadoCentral(){
    $.getJSON('../controller/configuracion_sistema.php',{task:7}).done(function(r){
        if(!r.ok)throw new Error(r.mensaje||'No se pudo consultar el servicio central');
        const c=r.central||{};
        $('#cfg_empresa').text(c.razon_social||$('#cfg_empresa').text()||'No disponible');
        $('#cfg_ruc').text(c.ruc?'RUC '+c.ruc:$('#cfg_ruc').text());
        $('#cfg_razon_social').text(c.razon_social||$('#cfg_razon_social').text()||'No disponible');
        $('#cfg_emisor_ruc').text('RUC '+(c.ruc||'no disponible'));
        $('#cfg_modo').text((c.modo||'Sin definir').toUpperCase()).toggleClass('text-warning',c.modo==='beta');
        $('#cfg_conexion').text(c.conectado?'Conectado':'Sin conexión').toggleClass('text-success',!!c.conectado).toggleClass('text-danger',!c.conectado).attr('title',c.mensaje||'');
        $('#cfg_estado').text(c.activo==null?'Sin verificar':Number(c.activo)===1?'Activo':'Inactivo').toggleClass('text-success',Number(c.activo)===1).toggleClass('text-danger',c.activo!=null&&Number(c.activo)!==1);
    }).fail(function(x){
        const mensaje=x.responseJSON&&x.responseJSON.mensaje||'No se pudo consultar el servicio central';
        $('#cfg_modo').text('SIN VERIFICAR').removeClass('text-warning text-success').addClass('text-danger');
        $('#cfg_conexion').text('Sin conexión').removeClass('text-success').addClass('text-danger').attr('title',mensaje);
        $('#cfg_estado').text('Sin verificar').removeClass('text-success text-danger');
    });
}
function guardarNombreComercial(){const nombre=$('#cfg_nombre_comercial').val().trim(),b=$('#btn_guardar_nombre_comercial');if(nombre.length<2||nombre.length>100)return ModalConfiguracion('warning','Ingresa un nombre comercial de 2 a 100 caracteres.');b.prop('disabled',true);$.post('../controller/configuracion_sistema.php',{task:6,nombre_comercial:nombre},null,'json').done(function(r){r.ok?AvisoIFact(r.mensaje):ModalConfiguracion('error',r.mensaje||'No se pudo guardar.');}).fail(function(x){ModalConfiguracion('error',x.responseJSON?.mensaje||'No se pudo guardar el nombre comercial.');}).always(function(){b.prop('disabled',false);});}
function guardarNotaPedido(){const serie=$('#cfg_nota_serie').val().trim().toUpperCase(),siguiente=Number($('#cfg_nota_siguiente').val()),b=$('#btn_guardar_nota');if(!/^[A-Z0-9]{1,4}$/.test(serie))return ModalConfiguracion('warning','La serie debe tener entre 1 y 4 letras o números.','Nota de venta');if(!Number.isInteger(siguiente)||siguiente<1||siguiente>99999999)return ModalConfiguracion('warning','Ingrese un próximo correlativo válido.','Nota de venta');b.prop('disabled',true).html('<span class="spinner-border spinner-border-sm"></span> Guardando…');$.post('../controller/configuracion_sistema.php',{task:8,nota_pedido_serie:serie,nota_pedido_siguiente:siguiente},null,'json').done(function(r){if(r.ok){AvisoIFact(r.mensaje);$('#cfg_nota_estado').text('Próxima nota: '+r.serie+'-'+String(r.siguiente).padStart(8,'0'));}else ModalConfiguracion('error',r.mensaje||'No se pudo guardar.','Nota de venta');}).fail(function(x){ModalConfiguracion('error',x.responseJSON&&x.responseJSON.mensaje||'No se pudo guardar el correlativo.','Nota de venta');}).always(function(){b.prop('disabled',false).html('<i class="fas fa-save"></i> Guardar correlativo');});}
