var pagosVentaId=0, pagosVenta=null, pagoClave='', pagoGuardando=false, pagoCarga=0;
function NuevaClavePago(){return Array.from(crypto.getRandomValues(new Uint8Array(16)),function(n){return n.toString(16).padStart(2,'0');}).join('');}
function MonedaPago(n){return 'S/ '+Number(n||0).toFixed(2);}
function AvisoPago(mensaje,campo){
    return Swal.fire({target:document.getElementById('modalPagosVenta'),icon:'info',title:'Revisa los datos del pago',text:mensaje,returnFocus:false}).then(function(){if(campo){if($(campo).is('select'))$(campo).select2('open');else $(campo).trigger('focus');}});
}
function BotonImportePago(d,type,row,deuda){
    if(type!=='display')return Number(d||0);
    return '<button type="button" class="importe-pago'+(deuda&&Number(d)>0?' deuda':'')+'" onclick="AbrirPagosVenta('+Number(row.ven_id)+')" title="Ver pagos y registrar abono">'+MonedaPago(d)+'</button>';
}
function AbrirPagosVenta(id){
    if(pagoGuardando)return;
    pagosVentaId=id;pagosVenta=null;pagoClave=NuevaClavePago();var carga=++pagoCarga;
    $('#pagosContenido').prop('hidden',true);$('#pagosMensaje').text('Cargando pagos…').removeClass('text-danger');$('#formNuevoPago')[0].reset();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPagosVenta')).show();
    $.when($.getJSON('../controller/venta.php',{task:11,ven_id:id}),$.getJSON('../controller/venta.php',{task:5}),$.getJSON('../controller/cuenta_bancaria.php',{action:1}))
    .done(function(info,metodos,cuentas){
        if(carga!==pagoCarga)return;
        if(info[0].estado!=='ok')return $('#pagosMensaje').addClass('text-danger').text(info[0].mensaje);
        var m=$('#pagoMetodo').empty().append(new Option('Seleccionar método',''));
        metodos[0].forEach(function(t){m.append(new Option(t.tpa_nombre,t.tpa_id));});
        var c=$('#pagoCuenta').empty().append(new Option('Seleccionar cuenta',''));
        if(!cuentas[0]||cuentas[0].status!==1){$('#pagosMensaje').addClass('text-danger').text('No se pudieron cargar las cuentas.');return;}
        (cuentas[0].data||[]).filter(function(t){return Number(t.cba_estado)===1;}).forEach(function(t){c.append(new Option([t.cba_banco,t.cba_moneda,t.cba_numero].join(' · '),t.cba_id));});
        m.add(c).trigger('change');
        MostrarPagosVenta(info[0]);
    }).fail(function(){if(carga===pagoCarga)$('#pagosMensaje').addClass('text-danger').text('No se pudo cargar la información de pagos. Cierra la ventana e inténtalo nuevamente.');});
}
function MostrarPagosVenta(r){
    pagosVenta=r.venta;ventasData[pagosVentaId]=Object.assign(ventasData[pagosVentaId]||{},r.venta);
    $('#pagosMensaje').text('').removeClass('text-danger');$('#pagosContenido').prop('hidden',false);$('#pagosTitulo').text('Pagos · Venta #'+pagosVentaId);
    $('#pagosTotal').text(MonedaPago(r.venta.ven_total));$('#pagosPagado').text(MonedaPago(r.venta.ven_pagado));$('#pagosSaldo').text(MonedaPago(r.venta.ven_saldo));
    var h=$('#pagosHistorial').empty();
    r.pagos.forEach(function(p){var fila=$('<div class="pago-historial-fila">'),info=$('<div>');info.append($('<strong>').text(p.tpa_nombre||'Método registrado'),$('<small>').text(p.cuenta||'Cuenta registrada'),$('<small>').text(p.fecha+(p.num_operacion?' · Operación: '+p.num_operacion:'')));fila.append(info,$('<strong>').text(MonedaPago(p.monto)));h.append(fila);});
    if(!r.pagos.length)h.text('Sin pagos registrados.');
    var activo=Number(r.venta.ven_estado)===1,saldo=Number(r.venta.ven_saldo);
    $('#formNuevoPago').prop('hidden',!activo||saldo<=0);$('#pagoMonto').attr('max',saldo.toFixed(2));
    $('#pagosCompletos').prop('hidden',activo&&saldo>0).text(!activo?'Venta anulada. No admite nuevos pagos.':'Venta completamente pagada.').toggleClass('text-success',activo);
    $('#pagosEmitir').prop('hidden',!activo||saldo>0||!r.venta.tde_id||!!r.venta.fac_id);
    ActualizarSaldoAbono();
}
function ActualizarSaldoAbono(){if(!pagosVenta)return;var monto=Number($('#pagoMonto').val()),saldo=Number(pagosVenta.ven_saldo);$('#pagoSaldoProyectado').text(Number.isFinite(monto)&&monto>=0&&monto<=saldo?MonedaPago(saldo-monto):'Revisa el monto');}
$(function(){
    $('#pagoMetodo,#pagoCuenta').select2({dropdownParent:$('#modalPagosVenta'),language:{noResults:function(){return 'Sin resultados';}},width:'100%'});
    $('#pagoMonto').on('input',ActualizarSaldoAbono);
    $('#pagosEmitir').on('click',function(){bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPagosVenta')).hide();EmitirCPE(pagosVentaId);});
    $('#formNuevoPago').on('submit',async function(e){
        e.preventDefault();if(pagoGuardando||!pagosVenta)return;
        var monto=Number($('#pagoMonto').val());
        if(!$('#pagoMetodo').val())return AvisoPago('Selecciona el método de pago.','#pagoMetodo');
        if(!$('#pagoCuenta').val())return AvisoPago('Selecciona la cuenta donde se recibe el abono.','#pagoCuenta');
        if(!Number.isFinite(monto)||monto<=0||monto>Number(pagosVenta.ven_saldo)||Math.abs(monto-Math.round(monto*100)/100)>0.000001)return AvisoPago('Ingresa un abono mayor a cero, con hasta dos decimales y que no supere '+MonedaPago(pagosVenta.ven_saldo)+'.','#pagoMonto');
        if($('#pagoOperacion').val().length>100)return AvisoPago('El número de operación admite hasta 100 caracteres.','#pagoOperacion');
        var datos={task:12,ven_id:pagosVentaId,tpa_id:$('#pagoMetodo').val(),cba_id:$('#pagoCuenta').val(),monto:monto,num_operacion:$('#pagoOperacion').val(),clave:pagoClave,csrf:window.ventaPagoCsrf};
        pagoGuardando=true;$('#btnRegistrarPago').prop('disabled',true);$('#modalPagosVenta [data-bs-dismiss]').prop('disabled',true);
        var confirmacion=await Swal.fire({
            target:document.getElementById('modalPagosVenta'),icon:'question',title:'¿Registrar este pago?',
            text:'Registrarás '+MonedaPago(monto)+' en la venta #'+datos.ven_id+' mediante '+$('#pagoMetodo option:selected').text()+', en '+$('#pagoCuenta option:selected').text()+'. El saldo quedará en '+MonedaPago(Number(pagosVenta.ven_saldo)-monto)+'.',
            showCancelButton:true,confirmButtonText:'Sí, registrar pago',cancelButtonText:'Cancelar',focusCancel:true,returnFocus:false
        });
        if(!confirmacion.isConfirmed){pagoGuardando=false;$('#btnRegistrarPago').prop('disabled',false).trigger('focus');$('#modalPagosVenta [data-bs-dismiss]').prop('disabled',false);return;}
        $('#btnRegistrarPago').text('Registrando…');
        $.post('../controller/venta.php',datos,null,'json')
        .done(function(r){if(r.estado!=='ok'){AvisoPago(r.mensaje);return; }pagoClave=NuevaClavePago();$('#pagoMonto,#pagoOperacion').val('');MostrarPagosVenta(r);tabla.ajax.reload(null,false);})
        .fail(function(){AvisoPago('No se pudo confirmar el pago. Puedes reintentar; el mismo abono no se duplicará.');})
        .always(function(){pagoGuardando=false;$('#btnRegistrarPago').prop('disabled',false).text('Registrar pago');$('#modalPagosVenta [data-bs-dismiss]').prop('disabled',false);});
    });
    $('#modalPagosVenta').on('hide.bs.modal',function(e){if(pagoGuardando)e.preventDefault();});
});
