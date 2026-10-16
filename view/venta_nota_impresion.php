<?php
require_once __DIR__.'/../model/sesion.php';
require_once __DIR__.'/../model/venta_pdf_provisional.php';

$locId=(int)($_SESSION['loc_id_ifact']??0);
$usuId=(int)($_SESSION['usu_id_ifact']??0);
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!$locId||!$usuId){http_response_code(401);exit('Inicia sesión nuevamente.');}

$venId=(int)($_GET['ven_id']??0);
try{
    $r=venta_pdf_provisional::obtener($venId,$locId);
    $v=$r['venta'];$e=$r['emisor'];$detalle=$r['detalle'];
}catch(Throwable $error){
    http_response_code($error instanceof InvalidArgumentException?404:500);
    exit('No se pudo preparar la nota de venta.');
}

function h($valor):string{return htmlspecialchars((string)($valor??''),ENT_QUOTES,'UTF-8');}
$esCpe=($_GET['tipo']??'')==='cpe';
$serie=$esCpe?(string)$v['ven_serie']:(string)($v['ven_nota_serie']?:$v['ven_serie']);
$correlativo=$esCpe?(string)$v['ven_numero']:(string)($v['ven_nota_numero']?:$v['ven_numero']);
$numero=$serie!==''&&$correlativo!==''?$serie.'-'.str_pad($correlativo,8,'0',STR_PAD_LEFT):'SIN NUMERACIÓN';
$total=(float)$v['ven_total'];$valor=(float)($v['ven_subtotal']??0);$igv=(float)($v['ven_igv']??0);
if($valor<=0){$valor=round($total/1.18,2);$igv=round($total-$valor,2);}
$direccion=trim((string)($e['direccion']??''))?:trim((string)($v['loc_direccion']??''));
$direccionCliente=trim((string)($v['cliente_direccion']??''));
$tipoDocumento=strtoupper(trim((string)($v['tde_nombre']??'')));
$titulo=$esCpe?($tipoDocumento==='FACTURA'?'FACTURA ELECTRÓNICA':'BOLETA DE VENTA ELECTRÓNICA'):'NOTA DE VENTA';
$tituloPagina=$esCpe?'Comprobante '.$numero:'Nota de venta '.$numero;
$fechaIso=date('Y-m-d',strtotime((string)$v['ven_fecha']));
$qr=$esCpe?venta_pdf_provisional::qrDataUri($e,$v,$igv,$total,$fechaIso):'';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($tituloPagina)?></title>
<style>
@page{size:80mm auto;margin:0}
@font-face{font-family:"IFact Mono";src:url("../vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono.ttf") format("truetype");font-weight:400;font-style:normal;font-display:block}
*{box-sizing:border-box}
body{margin:0;background:#e8ebef;color:#000;font-family:"IFact Mono","Courier New",Courier,monospace;font-size:11px;line-height:1.2;font-weight:400;text-rendering:geometricPrecision}
.acciones{position:sticky;top:0;z-index:2;padding:10px;background:#fff;border-bottom:1px solid #ccd2d8;text-align:center}
.acciones button{border:0;border-radius:8px;background:#172033;color:#fff;font:700 13px Arial,sans-serif;padding:10px 22px;cursor:pointer}
.ticket{width:80mm;min-height:80mm;margin:14px auto;background:#fff;padding:4mm 3.5mm 5mm 2mm}
.centro{text-align:center}.comercial{font-size:16px;letter-spacing:.08em}.razon{margin-top:4px}.ruc,.direccion{margin-top:2px}.titulo{font-size:15px;letter-spacing:.05em;margin-top:9px}.numero{font-size:14px;letter-spacing:.04em;margin-top:3px}.linea{border-top:1px dashed #000;margin:6px 0}.dato{display:grid;grid-template-columns:70px minmax(0,1fr);gap:3px;margin:3px 0}.dato span:first-child{white-space:nowrap}.dato-direccion{display:block;line-height:1.25}.dato-direccion .etiqueta{margin-right:4px}.items{width:100%;border-collapse:collapse;table-layout:fixed}.items th{font-weight:400;border-bottom:1px solid #000;padding:4px 1px;white-space:nowrap}.items td{padding:3px 1px}.items .descripcion td{padding-top:6px;white-space:normal}.items .valores td{padding-bottom:5px}.c{text-align:center}.n{text-align:right}.totales{width:100%;border-collapse:collapse;margin-top:2px;border-top:1px dashed #000}.totales td{padding:3px 1px}.total td{border-top:1px solid #000;border-bottom:1px solid #000;font-size:16px;padding:5px 1px}.son{font-size:9.5px;margin-top:7px;text-transform:uppercase}.qr{text-align:center;margin-top:9px}.qr img{width:70px;height:70px}.leyenda{text-align:center;font-size:8px;margin-top:3px}
@media print{body{background:#fff}.acciones{display:none}.ticket{margin:0;width:80mm;padding:3mm 3mm 3mm 1.5mm}}
</style>
</head>
<body>
<div class="acciones"><button type="button" onclick="window.print()">Imprimir <?=h($esCpe?'comprobante':'nota')?></button></div>
<main class="ticket">
  <header class="centro">
    <div class="comercial"><?=h(strtoupper((string)($e['nombre_comercial']?:'IFACT')))?></div>
    <div class="razon"><?=h($e['razon_social']??'')?></div>
    <div class="ruc">RUC: <?=h($e['ruc']??'')?></div>
    <div class="direccion"><?=h($direccion)?></div>
    <div class="titulo"><?=h($titulo)?></div>
    <div class="numero"><?=h($numero)?></div>
  </header>
  <div class="linea"></div>
  <section>
    <div class="dato"><span>CLIENTE:</span><span><?=h($v['cliente'])?></span></div>
    <div class="dato"><span>DOCUMENTO:</span><span><?=h($v['documento']?:'-')?></span></div>
    <?php if($direccionCliente!==''):?><div class="dato dato-direccion"><span class="etiqueta">DIRECCIÓN:</span><span><?=h($direccionCliente)?></span></div><?php endif;?>
    <div class="dato"><span>FECHA:</span><span><?=h(date('d/m/Y H:i',strtotime((string)$v['ven_fecha'])))?></span></div>
  </section>
  <div class="linea"></div>
  <table class="items">
    <thead><tr><th style="width:18%">CANT.</th><th style="width:18%">UND.</th><th class="n" style="width:28%">P. UNIT.</th><th class="n" style="width:36%">IMPORTE</th></tr></thead>
    <tbody>
    <?php foreach($detalle as $item):?>
      <tr class="descripcion"><td colspan="4"><?=h($item['nombre'])?></td></tr>
      <tr class="valores"><td class="c"><?=number_format((float)$item['cantidad'],2)?></td><td class="c"><?=h($item['unidad'])?></td><td class="n"><?=number_format((float)$item['precio'],2)?></td><td class="n"><?=number_format((float)$item['subtotal'],2)?></td></tr>
    <?php endforeach;?>
    </tbody>
  </table>
  <table class="totales">
    <tr><td>Op. gravadas</td><td class="n">S/ <?=number_format($valor,2)?></td></tr>
    <tr><td>IGV (18%)</td><td class="n">S/ <?=number_format($igv,2)?></td></tr>
    <tr class="total"><td>TOTAL</td><td class="n">S/ <?=number_format($total,2)?></td></tr>
  </table>
  <div class="son">SON: <?=h(venta_pdf_provisional::montoLetras($total))?></div>
  <?php if($esCpe&&$qr!==''):?><div class="qr"><img src="<?=h($qr)?>" alt="Código QR"></div><div class="leyenda">Representación impresa del comprobante electrónico<br>Consulte mediante el código QR</div><?php endif;?>
</main>
<script>
function avisarFin(){
    var receptor=window.opener&&!window.opener.closed?window.opener:(window.parent!==window?window.parent:null);
    if(receptor)receptor.postMessage({type:'ifact-impresion-finalizada',ven_id:<?=json_encode($venId)?>,documento:<?=json_encode($esCpe?'cpe':'nota')?>},window.location.origin);
}
window.addEventListener('afterprint',function(){avisarFin();if(window.opener&&!window.opener.closed)window.close();});
window.addEventListener('load',function(){
    var fuentes=document.fonts&&document.fonts.ready?document.fonts.ready:Promise.resolve();
    fuentes.catch(function(){}).then(function(){window.setTimeout(function(){window.print();},120);});
});
</script>
</body>
</html>
