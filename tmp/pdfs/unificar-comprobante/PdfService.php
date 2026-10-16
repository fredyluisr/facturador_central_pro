<?php

namespace Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Helpers\NumeroALetras;

class PdfService
{
    public function __construct()
    {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    }

    public function generar(array $empresa, array $payload, array $documento, array $paths): array
    {
        $correlativo = str_pad((string)$documento['correlativo'], 8, '0', STR_PAD_LEFT);
        $numero = $documento['serie'] . '-' . $correlativo;
        $nombreBase = $empresa['em_ruc'] . '-' . $documento['tipo_doc'] . '-' . $documento['serie'] . '-' . $correlativo;
        $qrData = implode('|', [$empresa['em_ruc'],$documento['tipo_doc'],$documento['serie'],$documento['correlativo'],
            number_format($payload['totales']['igv'],2,'.',''),number_format($payload['totales']['total'],2,'.',''),
            $payload['fecha_emision'],$payload['cliente']['tipo_doc'],$payload['cliente']['numero']]);
        $qrFile = $paths['qr'] . '/' . md5($qrData) . '.png';
        $this->generarQrLocal($qrData, $qrFile);
        $qr = 'data:image/png;base64,' . base64_encode(file_get_contents($qrFile));

        $formato = ($payload['formato_pdf'] ?? 'a4') === 'ticket' ? 'ticket' : 'a4';
        $a4 = null;
        $ticket = null;
        if ($formato === 'ticket') {
            $ticket = $paths['pdf'] . '/TICKET-' . $nombreBase . '.pdf';
            $this->render($this->html($empresa,$payload,$numero,$qr,true),$ticket,[0,0,226.77,$this->altoTicket($payload)]);
        } else {
            $a4 = $paths['pdf'] . '/A4-' . $nombreBase . '.pdf';
            $this->render($this->html($empresa,$payload,$numero,$qr,false),$a4,'A4');
        }
        return ['pdf_a4'=>$a4,'pdf_ticket'=>$ticket];
    }

    private function generarQrLocal(string $data, string $file): void
    {
        if (is_file($file)) return;
        $writer = new PngWriter();
        $result = $writer->write(QrCode::create($data)->setSize(180)->setMargin(8));
        $result->saveToFile($file);
    }

    private function render(string $html, string $path, $paper): void
    {
        $options=new Options(); $options->set('isRemoteEnabled',false); $options->set('defaultFont','DejaVu Sans');
        if (is_array($paper)) {
            $ancho = (float)$paper[2];
            $alto = (float)$paper[3];
            $ultimoAltoConCorte = 0.0;
            for ($intento = 0; $intento < 10; $intento++) {
                $pdf = new Dompdf($options);
                $pdf->loadHtml($html, 'UTF-8');
                $pdf->setPaper([0, 0, $ancho, $alto], 'portrait');
                $pdf->render();
                if ($pdf->getCanvas()->get_page_count() === 1) break;
                $ultimoAltoConCorte = $alto;
                $alto += max(80, $alto * 0.2);
            }
            if ($pdf->getCanvas()->get_page_count() !== 1) {
                throw new \RuntimeException('El ticket supera la altura máxima admitida para una sola página.');
            }
            // Recorta el papel hasta quedar cerca del contenido, sin dividir el QR ni el pie.
            $minimo = $ultimoAltoConCorte > 0 ? $ultimoAltoConCorte : 250.0;
            $maximo = $alto;
            for ($intento = 0; $intento < 7 && $maximo - $minimo > 3; $intento++) {
                $prueba = ($minimo + $maximo) / 2;
                $candidato = new Dompdf($options);
                $candidato->loadHtml($html, 'UTF-8');
                $candidato->setPaper([0, 0, $ancho, $prueba], 'portrait');
                $candidato->render();
                if ($candidato->getCanvas()->get_page_count() === 1) {
                    $maximo = $prueba;
                } else {
                    $minimo = $prueba;
                }
            }
            $pdf = new Dompdf($options);
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->setPaper([0, 0, $ancho, ceil($maximo + 10)], 'portrait');
            $pdf->render();
        } else {
            $pdf=new Dompdf($options); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper($paper,'portrait'); $pdf->render();
        }
        if (file_put_contents($path,$pdf->output()) === false) throw new \RuntimeException('No se pudo guardar el PDF');
    }

    private function altoTicket(array $payload): float
    {
        $items = count($payload['items']);
        $lineasExtra = 0;
        foreach ($payload['items'] as $item) {
            $lineasExtra += max(0, (int)ceil(mb_strlen((string)$item['descripcion'], 'UTF-8') / 24) - 1);
        }
        $resumenes = 0;
        foreach (['oper_gravadas','oper_exoneradas','oper_inafectas','oper_exportacion'] as $campo) {
            if ((float)($payload['totales'][$campo] ?? 0) > 0) $resumenes++;
        }
        return max(390, 345 + ($items * 22) + ($lineasExtra * 10) + ($resumenes * 12));
    }

    private function html(array $e, array $p, string $numero, string $qr, bool $ticket): string
    {
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $tipo = match ($p['tipo_doc']) {
            '01' => 'FACTURA ELECTRÓNICA',
            '03' => 'BOLETA DE VENTA ELECTRÓNICA',
            '07' => 'NOTA DE CRÉDITO ELECTRÓNICA',
            '08' => 'NOTA DE DÉBITO ELECTRÓNICA',
            default => 'COMPROBANTE ELECTRÓNICO',
        };
        $moneda = ($p['moneda'] ?? 'PEN') === 'USD' ? '$' : 'S/';
        $letras = $p['totales']['total_letras'] ?: NumeroALetras::convertir($p['totales']['total']);
        $letras = preg_replace('/^SON\s*:?\s*/iu', '', trim((string)$letras));
        $porcentajeIgv = 0.0;
        foreach ($p['items'] as $itemIgv) {
            $porcentajeIgv = max($porcentajeIgv, (float)($itemIgv['porcentaje_igv'] ?? 0));
        }
        $nombreUnidad = static function (array $item): string {
            $nombre = trim((string)($item['unidad_nombre'] ?? ''));
            if ($nombre !== '') return $nombre;
            return match (strtoupper((string)($item['unidad'] ?? 'NIU'))) {
                'DZN' => 'DOC.', 'KGM' => 'KG', 'GRM' => 'GR', 'LTR' => 'LT',
                'MTR' => 'M', 'CMT' => 'CM', 'GLL' => 'GL', default => 'UND',
            };
        };

        $rows = '';
        foreach ($p['items'] as $i) {
            if ($ticket) {
                $rows .= '<tr class="descripcion"><td colspan="4">'.$h($i['descripcion']).'</td></tr>'
                    .'<tr><td class="c">'.number_format($i['cantidad'], 2).'</td>'
                    .'<td class="c">'.$h($nombreUnidad($i)).'</td>'
                    .'<td class="r">'.number_format($i['precio_unitario'], 2).'</td>'
                    .'<td class="r">'.number_format($i['precio_unitario'] * $i['cantidad'], 2).'</td></tr>';
            } else {
                $rows .= '<tr><td>'.$h($i['descripcion']).'</td><td class="r">'.number_format($i['cantidad'],2).'</td>'
                    .'<td class="r">'.number_format($i['precio_unitario'],2).'</td>'
                    .'<td class="r">'.number_format($i['precio_unitario']*$i['cantidad'],2).'</td></tr>';
            }
        }

        $resumen = '';
        foreach (['oper_gravadas'=>'Op. gravadas','oper_exoneradas'=>'Op. exoneradas','oper_inafectas'=>'Op. inafectas','oper_exportacion'=>'Exportación'] as $campo=>$etiqueta) {
            $monto = (float)($p['totales'][$campo] ?? 0);
            if ($monto > 0) $resumen .= '<tr><td>'.$etiqueta.'</td><td class="r">'.$moneda.' '.number_format($monto,2).'</td></tr>';
        }

        if ($ticket) {
            return '<!doctype html><html><head><meta charset="UTF-8"><style>'
                .'@page{margin:0}*{box-sizing:border-box}body{margin:0;padding:5px 34px 5px 0;font-family:"DejaVu Sans Mono","Courier New",monospace;font-size:10px;line-height:1.16;color:#000;font-weight:400}body *{font-weight:400!important}'
                .'.c{text-align:center}.r{text-align:right}.empresa{font-size:14px;margin-bottom:1px}.razon{font-size:9px}.documento{font-size:12px;margin-top:3px}'
                .'.separador{border-top:1px dashed #000;margin:3px 0}.datos{width:100%;border-collapse:collapse;table-layout:fixed}.datos td{padding:0;vertical-align:top;line-height:1.08}.datos .etiqueta{width:62px;padding-right:2px}.datos .valor{white-space:normal;overflow-wrap:break-word;word-wrap:break-word}'
                .'.items{width:100%;border-collapse:collapse;table-layout:fixed}.items th{padding:2px 1px;border-bottom:1px solid #000;font-size:8.5px}.items td{padding:1px;vertical-align:top}.items .descripcion td{padding-top:3px;word-wrap:break-word}'
                .'.totales{width:100%;border-collapse:collapse}.totales td{padding:1px 0}.gran-total{font-size:12.5px;border-top:1px solid #000;border-bottom:1px solid #000}'
                .'.letras{margin:5px 0;font-size:9px}.qr{width:82px;height:82px}.pie{font-size:8px;margin-top:2px}.consulta{font-size:7.5px;margin-top:2px}'
                .'</style></head><body>'
                .'<div class="c"><div class="empresa">'.$h($e['em_nombre_comercial'] ?: $e['em_razon_social']).'</div>'
                .'<div class="razon">'.$h($e['em_razon_social']).'</div><div>RUC: '.$h($e['em_ruc']).'</div><div>'.$h($e['em_direccion']).'</div>'
                .'<div class="documento">'.$tipo.'</div><div class="documento" style="margin-top:1px">'.$h($numero).'</div></div>'
                .'<div class="separador"></div><table class="datos"><colgroup><col style="width:62px"><col></colgroup><tbody>'
                .'<tr><td class="etiqueta">CLIENTE:</td><td class="valor">'.$h($p['cliente']['razon_social']).'</td></tr>'
                .'<tr><td class="etiqueta">DOCUMENTO:</td><td class="valor">'.$h($p['cliente']['numero']).'</td></tr>'
                .(!empty($p['cliente']['direccion'])?'<tr><td class="etiqueta">DIRECCIÓN:</td><td class="valor">'.$h($p['cliente']['direccion']).'</td></tr>':'')
                .'<tr><td class="etiqueta">FECHA:</td><td class="valor">'.$h($p['fecha_emision']).'</td></tr></tbody></table>'
                .'<div class="separador"></div><table class="items"><thead><tr><th class="c" style="width:20%">CANT.</th><th class="c" style="width:17%">UND.</th><th class="r" style="width:27%">P.UNIT.</th><th class="r" style="width:36%">IMPORTE</th></tr></thead><tbody>'.$rows.'</tbody></table>'
                .'<div class="separador"></div><table class="totales">'.$resumen
                .'<tr><td>IGV ('.number_format($porcentajeIgv, $porcentajeIgv == floor($porcentajeIgv) ? 0 : 2).'%)</td><td class="r">'.$moneda.' '.number_format($p['totales']['igv'],2).'</td></tr>'
                .'<tr class="gran-total"><td>TOTAL</td><td class="r">'.$moneda.' '.number_format($p['totales']['total'],2).'</td></tr></table>'
                .'<div class="letras"><b>SON:</b> '.$h($letras).'</div><div class="c"><img class="qr" src="'.$qr.'">'
                .'<div class="pie">Representación impresa del comprobante electrónico</div>'
                .'<div class="consulta">Consulte mediante el código QR</div></div></body></html>';
        }

        $fecha = date('d/m/Y', strtotime((string)$p['fecha_emision']));
        $monedaNombre = ($p['moneda'] ?? 'PEN') === 'USD' ? 'DÓLARES AMERICANOS' : 'SOLES';
        $documentoCliente = (string)($p['cliente']['tipo_doc'] ?? '') === '6' ? 'RUC' : 'DNI';
        $ubicacion = trim(implode(' - ', array_filter([
            $e['em_distrito'] ?? '',
            $e['em_provincia'] ?? '',
            $e['em_departamento'] ?? '',
        ])));

        $rowsA4 = '';
        foreach ($p['items'] as $i) {
            $importe = (float)$i['precio_unitario'] * (float)$i['cantidad'];
            $rowsA4 .= '<tr>'
                .'<td class="c">'.number_format((float)$i['cantidad'], 0).'</td>'
                .'<td class="c">'.$h($nombreUnidad($i)).'</td>'
                .'<td class="desc">'.$h($i['descripcion']).'</td>'
                .'<td class="r">'.number_format((float)$i['precio_unitario'], 2).'</td>'
                .'<td class="r">0.00</td>'
                .'<td class="r">'.number_format($importe, 2).'</td>'
                .'</tr>';
        }

        $t = $p['totales'];
        return '<!doctype html><html><head><meta charset="UTF-8"><style>'
            .'@page{margin:34px 40px 28px}*{box-sizing:border-box}body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#000;margin:0}.c{text-align:center}.r{text-align:right}.b{font-weight:bold}'
            .'.cabecera{width:100%;border:1px solid #000;border-collapse:collapse;margin-bottom:8px}.cabecera td{vertical-align:middle;padding:7px}.empresa{width:65%;line-height:1.35}.empresa strong{font-size:10px;display:block}.doc-box{width:35%;padding:7px!important}.doc-inner{border:2px solid #000;text-align:center;padding:8px 4px;line-height:1.45;font-weight:bold;font-size:10px}'
            .'.datos{width:100%;border:1px solid #000;border-collapse:collapse;margin-bottom:8px}.datos td{padding:3px 5px;vertical-align:top}.datos .label{width:145px}.datos .sep{width:12px;text-align:center}.datos .value{font-weight:bold}'
            .'.items{width:100%;border:1px solid #000;border-collapse:collapse;table-layout:fixed;margin-bottom:8px}.items th,.items td{border:1px solid #000;padding:4px 5px;vertical-align:top}.items th{text-align:center;font-size:8px}.items .desc{line-height:1.3}'
            .'.totales{width:100%;height:128px;border:1px solid #000;border-collapse:collapse}.totales td{padding:1px 6px}.totales .space{width:67%}.totales .label{width:21%;text-align:right}.totales .amount{width:12%;text-align:right}.totales .final td{font-weight:bold;font-size:10px;padding-bottom:4px}'
            .'.letras{font-weight:bold;font-size:9px;margin:10px 0 7px}.pie{width:100%;border-collapse:collapse}.pie td{vertical-align:top}.qr{width:122px;height:122px}.leyenda{padding:11px 0 0 20px;font-size:7.5px;line-height:1.5}.leyenda strong{font-weight:bold}'
            .'</style></head><body>'
            .'<table class="cabecera"><tr><td class="empresa"><strong>'.$h($e['em_nombre_comercial'] ?: $e['em_razon_social']).'</strong><strong>'.$h($e['em_razon_social']).'</strong><div>'.$h($e['em_direccion']).'</div>'.($ubicacion !== ''?'<div>'.$h($ubicacion).'</div>':'').'</td>'
            .'<td class="doc-box"><div class="doc-inner">'.$tipo.'<br>RUC: '.$h($e['em_ruc']).'<br>'.$h($numero).'</div></td></tr></table>'
            .'<table class="datos">'
            .'<tr><td class="label">Fecha de Vencimiento</td><td class="sep">:</td><td class="value"></td></tr>'
            .'<tr><td class="label">Fecha de Emisión</td><td class="sep">:</td><td class="value">'.$h($fecha).'</td></tr>'
            .'<tr><td class="label">Señor(es)</td><td class="sep">:</td><td class="value">'.$h($p['cliente']['razon_social']).'</td></tr>'
            .'<tr><td class="label">'.$documentoCliente.'</td><td class="sep">:</td><td class="value">'.$h($p['cliente']['numero']).'</td></tr>'
            .'<tr><td class="label">Tipo de Moneda</td><td class="sep">:</td><td class="value">'.$monedaNombre.'</td></tr></table>'
            .'<table class="items"><thead><tr><th style="width:8%">Cant.</th><th style="width:10%">Unidad</th><th style="width:42%">Descripción</th><th style="width:13%">Valor Unit.</th><th style="width:12%">Desc.</th><th style="width:15%">Importe</th></tr></thead><tbody>'.$rowsA4.'</tbody></table>'
            .'<table class="totales"><tr><td class="space" rowspan="8"></td><td class="label">Op. Gravada :</td><td class="amount">'.$moneda.' '.number_format((float)($t['oper_gravadas'] ?? 0),2).'</td></tr>'
            .'<tr><td class="label">Op. Exonerada :</td><td class="amount">'.$moneda.' '.number_format((float)($t['oper_exoneradas'] ?? 0),2).'</td></tr>'
            .'<tr><td class="label">Op. Inafecta :</td><td class="amount">'.$moneda.' '.number_format((float)($t['oper_inafectas'] ?? 0),2).'</td></tr>'
            .'<tr><td class="label">ISC :</td><td class="amount">'.$moneda.' 0.00</td></tr><tr><td class="label">IGV :</td><td class="amount">'.$moneda.' '.number_format((float)($t['igv'] ?? 0),2).'</td></tr>'
            .'<tr><td class="label">Otros Tributos :</td><td class="amount">'.$moneda.' 0.00</td></tr><tr><td class="label">Otros Cargos :</td><td class="amount">'.$moneda.' 0.00</td></tr>'
            .'<tr class="final"><td class="label">Importe Total :</td><td class="amount">'.$moneda.' '.number_format((float)($t['total'] ?? 0),2).'</td></tr></table>'
            .'<div class="letras">SON '.$h($letras).'</div><table class="pie"><tr><td style="width:28%" class="c"><img class="qr" src="'.$qr.'"></td><td class="leyenda">Representación impresa de la '.$h(ucwords(mb_strtolower($tipo, 'UTF-8'), ' ')).'.<br>Consulte su comprobante en <strong>www.sunat.gob.pe</strong>.</td></tr></table>'
            .'</body></html>';
    }
}
