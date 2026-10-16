// Componentes comunes de los paneles de ventas e historial.
function TextoDetalleVenta(valor){return $('<div>').text(valor==null?'':String(valor)).html();}
function FilaVentaDetalle(icono,etiqueta,valor){
    return '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa '+icono+' me-1"></i>'+etiqueta+'</span><span class="oc-meta-val">'+TextoDetalleVenta(valor)+'</span></div>';
}
function CabeceraDetalleVenta(row){
    var esNotaPedido=Number(row.tde_id||0)===0||!String(row.tde_codigo||'').trim();
    var comprobante=row.ven_serie&&row.ven_numero?(esNotaPedido?'Nota de venta':(row.tde_nombre||'Comprobante'))+' '+row.ven_serie+'-'+String(row.ven_numero).padStart(8,'0'):(esNotaPedido?'Nota de venta sin numeración':'Comprobante pendiente');
    var cliente=row.cliente||'Cliente general';
    if(row.cliente_documento)cliente+=' · '+row.cliente_documento;
    var estado=row.ven_estado==1?'Activo':'Anulado';
    var sunat=!row.tde_codigo?'No aplica':(Number(row.fac_estado)===2?'Enviado':'No enviado');
    return '<div class="detalle-cpe"><div class="detalle-cpe-icon"><i class="fa fa-file-invoice"></i></div><div><div class="detalle-cpe-label">'+(esNotaPedido?'Documento interno':'Comprobante')+'</div><div class="detalle-cpe-numero">'+TextoDetalleVenta(comprobante)+'</div></div></div>'+
        '<div class="oc-meta">'+FilaVentaDetalle('fa-calendar','Fecha',row.ven_fecha||'—')+FilaVentaDetalle('fa-user','Cliente',cliente)+FilaVentaDetalle('fa-store','Tienda',row.alm_nombre||'—')+
        FilaVentaDetalle('fa-user-check','Registrado por',row.usuario||'—')+
        '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa fa-toggle-on me-1"></i>Estado de venta</span><span class="oc-meta-val"><span class="detalle-estado '+(row.ven_estado==1?'activo':'anulado')+'">'+estado+'</span></span></div>'+
        '<div class="oc-meta-row"><span class="oc-meta-label"><i class="fa fa-cloud-upload-alt me-1"></i>Estado SUNAT</span><span class="oc-meta-val"><span class="detalle-estado">'+sunat+'</span></span></div></div>';
}
function CargarPagosDetalleVenta(id,destino){
    var contenedor=$(destino),elemento=contenedor[0];
    contenedor.html('<div class="text-muted small py-2">Cargando pagos…</div>');
    $.getJSON('../controller/venta.php',{task:11,ven_id:id}).done(function(r){
        if($(destino)[0]!==elemento)return;
        if(r.estado!=='ok'){contenedor.text(r.mensaje||'No se pudieron cargar los pagos.');return;}
        var html='';
        (r.pagos||[]).forEach(function(p){
            html+='<div class="oc-item"><div class="d-flex justify-content-between align-items-start gap-2"><div><div class="oc-item-nombre">'+TextoDetalleVenta(p.tpa_nombre||'Método registrado')+'</div><div class="oc-item-detalle">'+TextoDetalleVenta(p.cuenta||'Cuenta registrada')+'</div><div class="oc-item-detalle">'+TextoDetalleVenta(p.fecha)+(p.inicial?' · Pago inicial':'')+'</div>'+(p.num_operacion?'<div class="oc-item-detalle">Operación: '+TextoDetalleVenta(p.num_operacion)+'</div>':'')+'</div><span class="oc-item-subtotal">S/ '+Number(p.monto).toFixed(2)+'</span></div></div>';
        });
        contenedor.html(html||'<div class="oc-item text-muted">Sin pagos registrados.</div>');
    }).fail(function(){if($(destino)[0]===elemento)contenedor.text('No se pudieron cargar los pagos. Cierra el panel y vuelve a abrirlo.');});
}
