<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/configuracion_sistema.php';
class cotizacion_pdf
{
    private static function e($v): string {return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
    private static function imagen(?string $ruta): string {
        if(!$ruta)return '';
        $root=realpath(dirname(__DIR__));
        foreach([$root.'/img/'.$ruta,$root.'/assets/img/'.$ruta,$root.'/'.ltrim($ruta,'/')] as $candidate){
            $path=realpath($candidate);
            if(!$path||!str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR))||!is_file($path))continue;
            $info=@getimagesize($path);if(!$info||filesize($path)>10*1024*1024)continue;
            if($info['mime']==='image/webp'&&function_exists('imagecreatefromwebp')){$im=@imagecreatefromwebp($path);if(!$im)continue;ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);if($png!==false)return 'data:image/png;base64,'.base64_encode($png);continue;}
            if(!in_array($info['mime'],['image/jpeg','image/png','image/gif'],true))continue;
            return 'data:'.$info['mime'].';base64,'.base64_encode(file_get_contents($path));
        }
        return '';
    }
    public static function generar(array $r): string {
        $c=$r['cotizacion'];$rows='';
        foreach($r['detalle'] as $i=>$d){$img=self::imagen($d['ctd_imagen']??null)?:self::imagen($d['variante_imagen']??null)?:self::imagen($d['producto_imagen']??null);$rows.='<tr><td>'.($i+1).'</td><td class="foto">'.($img?'<img src="'.$img.'">':'<span class="sin-foto">Sin imagen</span>').'</td><td><strong>'.self::e($d['ctd_nombre']).'</strong><div class="sku">SKU: '.self::e($d['ctd_sku']??'—').'</div></td><td class="unidad">'.self::e($d['ctd_und_med']).'</td><td class="numero">'.number_format((float)$d['ctd_cantidad'],3).'</td><td class="numero">'.number_format((float)$d['ctd_prec_unit'],2).'</td><td class="numero">'.number_format((float)$d['ctd_subtotal'],2).'</td></tr>';}
        $html='<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:30px 32px 42px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#24324a}h1{font-size:24px;margin:0}h2{font-size:18px;margin:4px 0}.cab{width:100%;border-bottom:3px solid #5866ed;padding-bottom:18px;margin-bottom:20px}.cab td{border:0}.muted,.sku{color:#718099}.sku{font-size:8px;margin-top:6px}.cliente{background:#f4f6fb;padding:14px;border:1px solid #e4e9f1;margin-bottom:20px}.items{width:100%;border-collapse:collapse;table-layout:fixed}.items thead{display:table-header-group}.items th{background:#24324a;color:white;font-size:8px;text-align:left;padding:9px 5px}.items td{border-bottom:1px solid #e4e9f1;padding:10px 5px;vertical-align:middle;word-wrap:break-word}.items tr{page-break-inside:avoid}.numero{text-align:right!important}.unidad{text-align:center!important}.foto img{max-width:60px;max-height:70px}.sin-foto{color:#94a3b8;font-size:8px}.total{margin-top:18px;background:#eef2ff;padding:15px;text-align:right;font-size:17px;font-weight:bold}.observacion{margin-top:20px;line-height:1.6;word-wrap:break-word}.pie{position:fixed;bottom:-23px;font-size:8px;color:#718099}.anulada{color:#bd3e3e;font-weight:bold}</style></head><body><table class="cab"><tr><td><h1>iFact</h1><div>'.self::e($c['alm_nombre']??'').'</div></td><td style="text-align:right"><div class="muted">COTIZACIÓN</div><h2>'.self::e($c['cot_numero']).'</h2><div>Fecha: '.self::e($c['cot_fecha']).'</div>'.((int)$c['cot_estado']===2?'<div class="anulada">ANULADA</div>':'').'</td></tr></table><div class="cliente"><strong>CLIENTE</strong><h2 style="font-size:13px">'.self::e($c['ent_nombres']).'</h2><div>Documento: '.self::e($c['ent_nro_doc']).'</div><div>Dirección: '.self::e($c['ent_direccion']).'</div><div>Contacto: '.self::e($c['ent_contacto']).'</div></div><table class="items"><thead><tr><th style="width:4%">N.º</th><th style="width:13%">Imagen</th><th style="width:34%">Producto / variante</th><th class="unidad" style="width:8%">Unidad</th><th class="numero" style="width:11%">Cantidad</th><th class="numero" style="width:14%">P. unit. S/</th><th class="numero" style="width:16%">Importe S/</th></tr></thead><tbody>'.$rows.'</tbody></table><div class="total">TOTAL: S/ '.number_format((float)$c['cot_total'],2).'</div>'.(!empty($c['cot_observaciones'])?'<div class="observacion"><strong>OBSERVACIONES / CONDICIONES</strong><p>'.nl2br(self::e($c['cot_observaciones'])).'</p></div>':'').'<div class="pie">Esta cotización no es un comprobante de pago.</div></body></html>';
        $emisor=$r['emisor']??configuracion_sistema::datosEmisor((new conexion())->con());
        $comercial=trim((string)($emisor['nombre_comercial']??''));$razon=trim((string)($emisor['razon_social']??''));$ruc=trim((string)($emisor['ruc']??''));
        $identidad='<h1>iFact</h1>'.($comercial!==''?'<div style="font-size:10px;font-weight:bold;color:#5866ed;letter-spacing:.12em">'.self::e($comercial).'</div>':'').'<div style="font-size:9px;font-weight:bold;margin-top:5px">'.self::e($razon?:($ruc?'RUC '.$ruc:'Empresa emisora no configurada')).'</div>'.($razon!==''&&$ruc!==''?'<div style="font-size:8px;color:#718099">RUC '.self::e($ruc).'</div>':'');
        $html=str_replace('<h1>iFact</h1><div>'.self::e($c['alm_nombre']??'').'</div>',$identidad,$html);
        $options=new Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);
        $cache=dirname(__DIR__).'/storage/cotizaciones';if(!is_dir($cache))mkdir($cache,0770,true);
        $options->set('fontDir',$cache);$options->set('fontCache',$cache);$options->set('tempDir',$cache);
        $pdf=new Dompdf\Dompdf($options);$pdf->loadHtml($html,'UTF-8');$pdf->setPaper('A4');$pdf->render();return $pdf->output();
    }
}
