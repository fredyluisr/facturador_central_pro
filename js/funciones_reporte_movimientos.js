$(function(){
 const money=n=>'S/ '+Number(n||0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});
 const esc=v=>$('<div>').text(v??'').html();
 const date=d=>{const p=x=>String(x).padStart(2,'0');return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())};
 const hoy=new Date();$('#fechaInicio').val(date(new Date(hoy.getFullYear(),hoy.getMonth(),1)));$('#fechaFin').val(date(hoy));let movimientos=[],graficos=[];
 function tabla(){
  const q=$('#buscarTabla').val().trim().toLocaleLowerCase('es');
  const items=movimientos.filter(m=>[m.mov_tipo,m.entidad,m.conceptos,m.com_serie,m.com_correlativo,m.com_id].join(' ').toLocaleLowerCase('es').includes(q));
  $('#movimientosFilas').html(items.map(m=>{const doc=m.com_tipo_doc==='00'?'Sin comprobante':(m.com_tipo_doc==='01'?'Factura':'Boleta')+' '+esc(m.com_serie)+'-'+esc(m.com_correlativo),tipo=String(m.mov_tipo||'').toLowerCase();return '<tr data-id="'+Number(m.com_id)+'"><td>'+esc(m.com_fecha)+'</td><td><span class="movimiento-reporte-tipo '+tipo+'">'+esc(tipo==='ingreso'?'Ingreso':'Egreso')+'</span></td><td>'+doc+'</td><td>'+esc(m.entidad||'Sin entidad')+'</td><td>'+esc(m.conceptos||'Sin concepto')+'</td><td class="text-end">'+money(m.com_total)+'</td><td class="text-end">'+money(m.pagado)+'</td><td class="text-end">'+money(m.saldo)+'</td><td><span class="movimiento-estado '+esc(m.estado_pago)+'">'+esc(m.estado_pago==='parcial'?'Parcialmente pagado':m.estado_pago)+'</span></td></tr>'}).join('')||'<tr><td colspan="9" class="text-center text-muted">Sin movimientos para estos filtros.</td></tr>');
  $('#conteoFilas').text(items.length+' de '+movimientos.length+' movimientos');
 }
 function render(r){
  movimientos=r.movimientos||[];const k=r.kpis;
  $('#kpiIngresos').text(money(k.ingresos));$('#kpiIngresosN').text(k.ingresos_n+' ingresos');$('#kpiEgresos').text(money(k.egresos));$('#kpiEgresosN').text(k.egresos_n+' egresos');$('#kpiPagado').text(money(k.pagado));$('#kpiSaldo').text(money(k.saldo));$('#kpiEstados').text(k.pagados+' pagados · '+k.parciales+' parciales · '+k.pendientes+' pendientes');tabla();graficos.forEach(g=>g.destroy());graficos=[];
  const base={chart:{height:280,toolbar:{show:false}},dataLabels:{enabled:false},noData:{text:'Sin movimientos en el periodo'}};
  const a=new ApexCharts(document.querySelector('#chartMovimientos'),{...base,chart:{...base.chart,type:'area'},series:[{name:'Ingresos',data:r.diario.map(x=>Number(x.ingresos))},{name:'Egresos',data:r.diario.map(x=>Number(x.egresos))}],xaxis:{categories:r.diario.map(x=>x.fecha)},stroke:{curve:'smooth',width:2},colors:['#12a784','#5267f8'],tooltip:{y:{formatter:money}}});a.render();graficos.push(a);
  const tipos=(r.tipos||[]).filter(x=>Number(x.total)>0),b=new ApexCharts(document.querySelector('#chartTipos'),{...base,chart:{...base.chart,type:'donut'},series:tipos.map(x=>Number(x.total)),labels:tipos.map(x=>x.tipo),colors:['#12a784','#5267f8'],tooltip:{y:{formatter:money}},legend:{position:'bottom'}});b.render();graficos.push(b);
  const top=(r.conceptos||[]).slice(0,10),c=new ApexCharts(document.querySelector('#chartConceptos'),{...base,chart:{...base.chart,type:'bar'},series:[{name:'Importe',data:top.map(x=>Number(x.total))}],xaxis:{categories:top.map(x=>(x.tipo==='INGRESO'?'Ingreso · ':'Egreso · ')+x.concepto)},plotOptions:{bar:{horizontal:true,borderRadius:4}},colors:['#6577ef'],tooltip:{y:{formatter:money}}});c.render();graficos.push(c);
 }
 function cargar(){const p={inicio:$('#fechaInicio').val(),fin:$('#fechaFin').val(),tipo:$('#tipoMovimiento').val(),estado:$('#estadoPago').val()};if(!p.inicio||!p.fin||p.inicio>p.fin){$('#reporteError').prop('hidden',false).text('Selecciona un rango válido.');return}$('#reporteError').prop('hidden',true);$('#btnBuscar').prop('disabled',true);$.getJSON('../controller/reporte_movimientos.php',p).done(r=>r.estado==='ok'?render(r):$('#reporteError').prop('hidden',false).text(r.mensaje||'No se pudo cargar el reporte.')).fail(x=>$('#reporteError').prop('hidden',false).text(x.responseJSON?.mensaje||'No se pudo cargar el reporte.')).always(()=>$('#btnBuscar').prop('disabled',false));}
 $('#btnBuscar').on('click',cargar);$('#buscarTabla').on('input',tabla);$('#movimientosFilas').on('click','tr[data-id]',function(){location.href='list_movimientos.php?com_id='+$(this).data('id')});cargar();
});
