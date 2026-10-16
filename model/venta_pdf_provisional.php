<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/configuracion_sistema.php';
require_once __DIR__.'/comprobantes_pos.php';

class venta_pdf_provisional
{
    private static function e($valor): string { return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8'); }

    public static function obtener(int $venId, int $locId): array
    {
        $cn=(new conexion())->con();
        $s=$cn->prepare("SELECT v.ven_id,v.ven_fecha,v.ven_total,v.ven_subtotal,v.ven_igv,v.ven_pagado,v.ven_comentario,v.ven_serie,v.ven_numero,v.ven_nota_serie,v.ven_nota_numero,
                COALESCE(e.ent_nombres,'CLIENTE GENERAL') cliente,COALESCE(e.ent_nro_doc,'') documento,COALESCE(e.ent_tipo_doc,0) cliente_tipo_doc,
                COALESCE(e.ent_direccion,'') cliente_direccion,l.loc_nombre,l.loc_direccion,t.tde_nombre,t.tde_codigo,
                COALESCE(f.fac_estado,0) fac_estado,COALESCE(f.fac_estado_sunat,'') fac_estado_sunat,
                COALESCE(f.fac_respuesta_estado,'') fac_respuesta_estado
            FROM venta v JOIN local l ON l.loc_id=v.loc_id
            LEFT JOIN entidad e ON e.ent_id=v.ent_id AND e.ent_cliente=1
            LEFT JOIN " . comprobantes_pos::SQL . " t ON t.tde_id=v.tde_id
            LEFT JOIN facturacion f ON f.fac_id=v.fac_id
            WHERE v.ven_id=:ven AND v.loc_id=:loc AND v.ven_estado=1 LIMIT 1");
        $s->execute([':ven'=>$venId,':loc'=>$locId]);
        $venta=$s->fetch(PDO::FETCH_ASSOC);
        if (!$venta) throw new InvalidArgumentException('Venta no encontrada');
        $s=$cn->prepare("SELECT COALESCE(vdp_nombre,p.pro_nombre,'Producto') nombre,COALESCE(unidad_abrev_historica,u.und_abrev,'UND') unidad,vdp_cantidad cantidad,vdp_precio_unit precio,vdp_subtotal subtotal
            FROM venta_detalle_producto d LEFT JOIN producto p ON p.pro_id=d.pro_id LEFT JOIN unidad_medida u ON u.und_id=d.und_med
            WHERE d.ven_id=:ven ORDER BY d.vdp_id");
        $s->execute([':ven'=>$venId]);
        return ['venta'=>$venta,'detalle'=>$s->fetchAll(PDO::FETCH_ASSOC),'emisor'=>configuracion_sistema::datosEmisor($cn)];
    }

    public static function generar(array $r,bool $forzarNota=false): string
    {
        $v=$r['venta']; $filas='';
        foreach($r['detalle'] as $d){$filas.='<tr class="producto"><td colspan="4">'.self::e($d['nombre']).'</td></tr><tr class="valores"><td class="c">'.number_format((float)$d['cantidad'],2).'</td><td class="c">'.self::e($d['unidad']).'</td><td class="n">'.number_format((float)$d['precio'],2).'</td><td class="n">'.number_format((float)$d['subtotal'],2).'</td></tr>';}
        $e=$r['emisor']; $total=(float)$v['ven_total']; $igv=(float)($v['ven_igv']??0); $valor=(float)($v['ven_subtotal']??0);
        if($valor<=0){$valor=round($total/1.18,2);$igv=round($total-$valor,2);}
        $esNotaPedido=$forzarNota||empty($v['tde_codigo']);
        $tipo=strtoupper(trim((string)($v['tde_nombre']?:'')));
        $tituloComprobante=$esNotaPedido?'NOTA DE VENTA':($tipo==='BOLETA'?'BOLETA DE VENTA ELECTRÓNICA':($tipo==='FACTURA'?'FACTURA ELECTRÓNICA':$tipo.' ELECTRÓNICO'));
        $serieNumero=$esNotaPedido
            ? [(string)($v['ven_nota_serie']?:$v['ven_serie']),(string)($v['ven_nota_numero']?:$v['ven_numero'])]
            : [(string)$v['ven_serie'],(string)$v['ven_numero']];
        $numero=($serieNumero[0]!==''&&$serieNumero[1]!=='')?self::e($serieNumero[0].'-'.str_pad($serieNumero[1],8,'0',STR_PAD_LEFT)):($esNotaPedido?'SIN NUMERACIÓN':'PENDIENTE DE EMISIÓN');
        $fechaIso=self::e(date('Y-m-d',strtotime((string)$v['ven_fecha'])));
        $fecha=self::e(date('d/m/Y H:i',strtotime((string)$v['ven_fecha'])));
        $qr=self::qrDataUri($e,$v,$igv,$total,$fechaIso);
        $aceptado=(int)($v['fac_estado']??0)===2||(string)($v['fac_estado_sunat']??'')==='0'||(string)($v['fac_respuesta_estado']??'')==='verificado_sunat_sin_cdr';
        $pieQr=!$esNotaPedido&&$qr!==''?'<div class="qr"><img src="'.$qr.'"></div><div class="leyenda">Representación impresa del comprobante electrónico</div><div class="consulta">Consulte mediante el código QR</div>':'';
        // La leyenda del QR ya identifica la representación impresa. Repetirla al
        // final podía desbordar una segunda página en comprobantes de una sola tira.
        $pieEstado='';
        $direccion=trim((string)($e['direccion']??''))?:trim((string)$v['loc_direccion']);
        $alto=($esNotaPedido?410:680)+(max(0,count($r['detalle'])-1)*45);
        $direccionCliente=trim((string)($v['cliente_direccion']??''));
        $filaDireccion=$direccionCliente!==''?'<div><b>DIRECCIÓN:</b> '.self::e($direccionCliente).'</div>':'';
        $claseDocumento=$esNotaPedido?' nota-pedido':' comprobante-electronico';
        $html='<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:14px 13px}*{box-sizing:border-box}body{margin:0;font-family:DejaVu Sans,Arial,sans-serif;color:#111;font-size:7px;line-height:1.3}.head{text-align:center}.comercial{font-size:10.5px;font-weight:700}.razon{margin-top:5px;font-size:6.5px}.ruc,.direccion{margin-top:3px;font-size:6.5px}.titulo{font-size:9.2px;font-weight:700;margin-top:11px}.numero{font-size:9px;font-weight:700;margin-top:3px}.sep{border-top:.7px dashed #111;margin:9px 0}.datos div{margin:3px 0}.datos b{display:inline-block;min-width:49px}.items{width:100%;border-collapse:collapse;table-layout:fixed}.items th{border-bottom:.8px solid #111;padding:4px 2px;font-size:6.4px}.items td{padding:3px 1px}.items .producto td{padding-top:6px;font-weight:700}.n{text-align:right}.c{text-align:center}.totales{width:100%;margin-top:7px;border-top:.7px dashed #111;border-collapse:collapse}.totales td{padding:3px 1px}.total td{font-size:9.5px;font-weight:700;border-top:.8px solid #111;border-bottom:.8px solid #111}.son{font-size:6.5px;font-weight:700;margin-top:8px}.qr{text-align:center;margin-top:15px}.qr img{width:68px;height:68px}.leyenda,.consulta{margin-top:5px;text-align:center;font-size:5.2px}.version{text-align:center;margin-top:8px;font-size:6px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.nota-pedido{font-family:"DejaVu Sans Mono","Courier New",monospace;color:#000;font-size:8.5pt;line-height:1.35}.nota-pedido .comercial{font-size:14pt;letter-spacing:.02em}.nota-pedido .razon{margin-top:5px;font-size:8.5pt;font-weight:700}.nota-pedido .ruc,.nota-pedido .direccion{margin-top:3px;font-size:8pt;font-weight:700}.nota-pedido .titulo{margin-top:14px;padding:8px 3px 4px;border-top:1.2pt solid #000;border-bottom:0;font-size:12pt;letter-spacing:.04em}.nota-pedido .numero{margin-top:4px;font-size:11pt;letter-spacing:.03em}.nota-pedido .sep{border-top:1pt solid #000;margin:10px 0}.nota-pedido .datos div{margin:6px 0;font-size:8.5pt}.nota-pedido .datos b{min-width:72px;font-size:8.5pt}.nota-pedido .items th{padding:7px 1px;border-top:0;border-bottom:1pt solid #000;font-size:7.5pt}.nota-pedido .items td{padding:5px 2px;font-size:8pt}.nota-pedido .items .producto td{padding-top:9px;font-size:9pt;line-height:1.35}.nota-pedido .items .valores td{padding-bottom:8px;border-bottom:1pt solid #000}.nota-pedido .totales{margin-top:10px;border-top:0}.nota-pedido .totales td{padding:6px 2px;font-size:8.5pt}.nota-pedido .total td{padding:8px 2px;border-top:1.2pt solid #000;border-bottom:1.2pt solid #000;font-size:12pt}.nota-pedido .son{margin-top:11px;font-size:7.5pt;line-height:1.4}</style></head><body class="'.$claseDocumento.'"><div class="head"><div class="comercial">'.self::e(strtoupper($e['nombre_comercial']?:'IFACT')).'</div><div class="razon">'.self::e($e['razon_social']).'</div><div class="ruc">RUC: '.self::e($e['ruc']).'</div><div class="direccion">'.self::e($direccion).'</div><div class="titulo">'.$tituloComprobante.'</div><div class="numero">'.$numero.'</div></div><div class="sep"></div><div class="datos"><div><b>CLIENTE:</b> '.self::e($v['cliente']).'</div><div><b>DOCUMENTO:</b> '.self::e($v['documento']?:'-').'</div>'.$filaDireccion.'<div><b>FECHA:</b> '.$fecha.'</div></div><div class="sep"></div><table class="items"><thead><tr><th style="width:18%">CANT.</th><th style="width:18%">UND.</th><th class="n" style="width:28%">P. UNIT.</th><th class="n" style="width:36%">IMPORTE</th></tr></thead><tbody>'.$filas.'</tbody></table><table class="totales"><tr><td>Op. gravadas</td><td class="n">S/ '.number_format($valor,2).'</td></tr><tr><td>IGV (18%)</td><td class="n">S/ '.number_format($igv,2).'</td></tr><tr class="total"><td>TOTAL</td><td class="n">S/ '.number_format($total,2).'</td></tr></table><div class="son">SON: '.self::e(self::montoLetras($total)).'</div>'.$pieQr.$pieEstado.'</body></html>';
        if($esNotaPedido){
            $cssNota='@page{margin:10px 34px 10px 2px}' .
                '.nota-pedido{font-size:8.2pt;line-height:1.2;font-weight:400}' .
                '.nota-pedido *{font-weight:400!important}' .
                '.nota-pedido .comercial{font-size:14pt}' .
                '.nota-pedido .razon{margin-top:2px;font-size:8.2pt;font-weight:400}' .
                '.nota-pedido .ruc,.nota-pedido .direccion{margin-top:1px;font-size:8pt;font-weight:400}' .
                '.nota-pedido .titulo{margin-top:7px;padding:5px 2px 2px;font-size:11.5pt}' .
                '.nota-pedido .numero{margin-top:1px;font-size:10.5pt}' .
                '.nota-pedido .sep{margin:6px 0;border-top:1pt solid #000}' .
                '.nota-pedido .datos div{margin:3px 0;font-size:8.2pt;font-weight:400}' .
                '.nota-pedido .datos b{min-width:72px;font-size:8.2pt;font-weight:400}' .
                '.nota-pedido .items th{padding:4px 1px;font-size:7.3pt;font-weight:400}' .
                '.nota-pedido .items td{padding:3px 2px;font-size:8pt;font-weight:400}' .
                '.nota-pedido .items .producto td{padding-top:5px;font-size:8.4pt;line-height:1.2;font-weight:400}' .
                '.nota-pedido .items .valores td{padding-bottom:4px;border-bottom:1pt solid #000}' .
                '.nota-pedido .totales{margin-top:5px}' .
                '.nota-pedido .totales td{padding:3px 2px;font-size:8.2pt;font-weight:400}' .
                '.nota-pedido .total td{padding:5px 2px;font-size:11.5pt}' .
                '.nota-pedido .son{margin-top:6px;font-size:7.3pt;line-height:1.25;font-weight:400}';
            $html=str_replace('</style>',$cssNota.'</style>',$html);
        }else{
            $cssElectronico='@page{margin:10px 26px 10px 0}.comprobante-electronico{font-family:"DejaVu Sans Mono","Courier New",monospace;color:#000;font-size:8pt;line-height:1.2;font-weight:400}.comprobante-electronico *{font-weight:400!important}.comprobante-electronico .comercial{font-size:13pt}.comprobante-electronico .razon{margin-top:2px;font-size:8pt}.comprobante-electronico .ruc,.comprobante-electronico .direccion{margin-top:1px;font-size:7.5pt}.comprobante-electronico .titulo{font-size:10pt;margin-top:7px}.comprobante-electronico .numero{font-size:10pt;margin-top:1px}.comprobante-electronico .sep{border-top:1pt solid #000;margin:6px 0}.comprobante-electronico .datos div{margin:3px 0}.comprobante-electronico .datos b{min-width:68px}.comprobante-electronico .items th{padding:4px 0;font-size:6.8pt;white-space:nowrap}.comprobante-electronico .items th:nth-child(1){width:17%!important}.comprobante-electronico .items th:nth-child(2){width:17%!important}.comprobante-electronico .items th:nth-child(3){width:29%!important}.comprobante-electronico .items th:nth-child(4){width:37%!important;padding-right:5px}.comprobante-electronico .items td{padding:3px 1px;font-size:7.5pt}.comprobante-electronico .items td:last-child{padding-right:5px}.comprobante-electronico .items .producto td{padding-top:5px;font-size:8pt;line-height:1.2}.comprobante-electronico .totales{margin-top:5px}.comprobante-electronico .totales td{padding:3px 5px 3px 1px;font-size:8pt}.comprobante-electronico .total td{font-size:11pt;padding:5px 5px 5px 1px}.comprobante-electronico .son{margin-top:6px;font-size:7pt;line-height:1.25}.comprobante-electronico .qr{margin-top:10px}.comprobante-electronico .leyenda,.comprobante-electronico .consulta{margin-top:3px;font-size:6.2pt}.comprobante-electronico .version{font-size:7pt}';
            $html=str_replace('</style>',$cssElectronico.'</style>',$html);
        }
        $options=new Dompdf\Options(); $options->set('isRemoteEnabled',false); $options->set('isPhpEnabled',false);
        $tmp=dirname(__DIR__).'/storage/comprobantes'; if(!is_dir($tmp))mkdir($tmp,0770,true); $options->set('tempDir',$tmp);
        $pdf=new Dompdf\Dompdf($options); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper([0,0,226.77,$alto]); $pdf->render(); return $pdf->output();
    }

    public static function obtenerPdfLocal(int $venId,int $locId,bool $forzarNota=false):string
    {
        $r=self::obtener($venId,$locId); $ruc=(string)($r['emisor']['ruc']??'');
        $dir=dirname(__DIR__).'/storage/comprobantes'; if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el almacenamiento de comprobantes.');
        $serie=$forzarNota?(string)($r['venta']['ven_nota_serie']??''):(string)($r['venta']['ven_serie']??'');
        $numero=$forzarNota?(string)($r['venta']['ven_nota_numero']??''):(string)($r['venta']['ven_numero']??'');
        $estado=(string)($r['venta']['fac_estado']??'0').'|'.(string)($r['venta']['fac_estado_sunat']??'').'|'.(string)($r['venta']['fac_respuesta_estado']??'');
        $ruta=$dir.'/representacion-'.hash('sha256','v19|'.($forzarNota?'nota':'cpe').'|'.$ruc.'|'.$locId.'|'.$venId.'|'.$serie.'|'.$numero.'|'.$estado).'.pdf';
        if(is_file($ruta)&&filesize($ruta)>5&&file_get_contents($ruta,false,null,0,5)==='%PDF-')return $ruta;
        $contenido=self::generar($r,$forzarNota); $temporal=tempnam($dir,'repr-');
        if($temporal===false||file_put_contents($temporal,$contenido,LOCK_EX)!==strlen($contenido)){if($temporal&&is_file($temporal))unlink($temporal);throw new RuntimeException('No se pudo guardar la representación impresa.');}
        if(!rename($temporal,$ruta)){unlink($temporal);throw new RuntimeException('No se pudo completar la representación impresa.');}
        return $ruta;
    }

    public static function qrDataUri(array $e,array $v,float $igv,float $total,string $fecha):string
    {
        if(empty($v['tde_codigo'])||empty($v['ven_serie'])||empty($v['ven_numero']))return '';
        $contenido=implode('|',[(string)($e['ruc']??''),(string)($v['tde_codigo']??''),(string)$v['ven_serie'],(string)(int)$v['ven_numero'],number_format($igv,2,'.',''),number_format($total,2,'.',''),$fecha,(string)($v['cliente_tipo_doc']??''),(string)($v['documento']??'')]).'|';
        $qr=Endroid\QrCode\QrCode::create($contenido)->setSize(220)->setMargin(4);
        return (new Endroid\QrCode\Writer\PngWriter())->write($qr)->getDataUri();
    }

    public static function montoLetras(float $monto): string
    {
        $entero=(int)floor($monto); $centavos=(int)round(($monto-$entero)*100);
        $u=['CERO','UNO','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE','DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE','DIECIOCHO','DIECINUEVE','VEINTE','VEINTIUNO','VEINTIDÓS','VEINTITRÉS','VEINTICUATRO','VEINTICINCO','VEINTISÉIS','VEINTISIETE','VEINTIOCHO','VEINTINUEVE'];
        $dec=['','','VEINTE','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
        $cien=['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS','SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
        $convertir=function(int $n) use (&$convertir,$u,$dec,$cien):string {if($n<30)return $u[$n];if($n<100)return $dec[intdiv($n,10)].($n%10?' Y '.$u[$n%10]:'');if($n===100)return 'CIEN';if($n<1000)return $cien[intdiv($n,100)].($n%100?' '.$convertir($n%100):'');if($n<2000)return 'MIL'.($n%1000?' '.$convertir($n%1000):'');if($n<1000000)return $convertir(intdiv($n,1000)).' MIL'.($n%1000?' '.$convertir($n%1000):'');return (string)$n;};
        return $convertir($entero).' Y '.str_pad((string)$centavos,2,'0',STR_PAD_LEFT).'/100 SOLES';
    }
}
