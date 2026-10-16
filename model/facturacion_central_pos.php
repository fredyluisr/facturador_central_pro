<?php
require_once __DIR__ . "/comprobantes_pos.php";
require_once __DIR__ . "/costos_fifo.php";

require_once __DIR__ . '/facturacion.php';

/**
 * Adaptador POS para la facturación centralizada.
 *
 * La API central es la única autoridad que reserva series y correlativos. La
 * clave id_externo hace que repetir una solicitud de la misma venta sea seguro.
 */
class facturacion_central_pos extends facturacion
{
    private function instanciaUuid(PDO $cn): string
    {
        $uuid = strtolower((string)$cn->query("SELECT csi_instancia_uuid FROM configuracion_sistema WHERE csi_id=1")->fetchColumn());
        if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
            throw new RuntimeException('La instalación no tiene un identificador válido para facturación');
        }
        return $uuid;
    }

    private function idExterno(PDO $cn, array $config, string $tipo, int $id, string $extra = ''): string
    {
        $sufijo = $extra !== '' ? '-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $extra) : '';
        return 'ifact-' . $config['ruc'] . '-' . $this->instanciaUuid($cn) . '-' . $tipo . '-' . $id . $sufijo;
    }

    private function nombreBloqueo(PDO $cn, array $config, string $tipo, int $id, string $extra = ''): string
    {
        return 'ifact:' . substr(hash('sha256', $this->idExterno($cn, $config, $tipo, $id, $extra)), 0, 50);
    }

    private function actualizarCola(PDO $cn, int $venId, string $estado, ?string $error = null, ?string $proximo = null, bool $sumarIntento = false): void
    {
        $sql = "UPDATE facturacion_cola SET fcq_estado=:estado,fcq_ultimo_error=:error,
                    fcq_proxima_ejecucion=:proximo,fcq_bloqueado_hasta=NULL,
                    fcq_intentos=fcq_intentos+" . ($sumarIntento ? '1' : '0') . ",fcq_fecha_actualizacion=NOW()
                WHERE ven_id=:ven_id";
        $cn->prepare($sql)->execute([':estado'=>$estado, ':error'=>$error, ':proximo'=>$proximo, ':ven_id'=>$venId]);
    }
    private function configuracionCentral(): array
    {
        $config = [
            'base_url' => trim((string) conexion::config('FACTURACION_API_URL')),
            'ruc' => preg_replace('/\D/', '', (string) conexion::config('FACTURACION_API_RUC')),
            'token' => trim((string) conexion::config('FACTURACION_API_TOKEN')),
            'timeout' => (int) conexion::config('FACTURACION_API_TIMEOUT', '60'),
            'series_notas' => [
                '07' => [
                    '01' => strtoupper(trim((string)conexion::config('FACTURACION_SERIE_NC_FACTURA', 'FC01'))),
                    '03' => strtoupper(trim((string)conexion::config('FACTURACION_SERIE_NC_BOLETA', 'BC01'))),
                ],
                '08' => [
                    '01' => strtoupper(trim((string)conexion::config('FACTURACION_SERIE_ND_FACTURA', 'FD01'))),
                    '03' => strtoupper(trim((string)conexion::config('FACTURACION_SERIE_ND_BOLETA', 'BD01'))),
                ],
            ],
        ];

        if (!filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
            throw new RuntimeException('FACTURACION_API_URL no es una URL válida');
        }
        if (!preg_match('/^\d{11}$/', $config['ruc'])) {
            throw new RuntimeException('FACTURACION_API_RUC debe tener 11 dígitos');
        }
        if ($config['token'] === '') {
            throw new RuntimeException('Falta configurar FACTURACION_API_TOKEN en .env');
        }

        return $config;
    }

    public function registrar_desde_venta(): array
    {
        $cn = $this->cnx->con();
        $venId = isset($_REQUEST['ven_id']) ? (int) $_REQUEST['ven_id'] : 0;
        $reintentar = !empty($_REQUEST['reintentar']);
        $soloConsultar = !empty($_REQUEST['solo_consultar']);
        $soloReservar = !empty($_REQUEST['solo_reservar']);
        $usuario = isset($_SESSION['usu_id_ifact']) ? (int) $_SESSION['usu_id_ifact'] : 0;
        $lockName = '';
        $lockTomado = false;

        if ($venId <= 0) {
            return ['estado' => 'error', 'mensaje' => 'Venta no válida'];
        }

        try {
            $config = $this->configuracionCentral();
            $lockName = $this->nombreBloqueo($cn, $config, 'venta', $venId);
            $stmtLock = $cn->prepare('SELECT GET_LOCK(:nombre, 10)');
            $stmtLock->execute([':nombre' => $lockName]);
            $lockTomado = (int) $stmtLock->fetchColumn() === 1;
            if (!$lockTomado) {
                throw new Exception('La venta está siendo emitida por otro proceso. Intente nuevamente.');
            }
            self::validarPagoCompleto($cn, $venId);
            $idExterno = $this->idExterno($cn, $config, 'venta', $venId);
            $cn->prepare("INSERT INTO facturacion_cola(ven_id,fcq_id_externo,fcq_estado,fcq_proxima_ejecucion,fcq_bloqueado_hasta)
                          VALUES(:ven,:externo,'procesando',NOW(),DATE_ADD(NOW(),INTERVAL 2 MINUTE))
                          ON DUPLICATE KEY UPDATE fcq_id_externo=VALUES(fcq_id_externo),fcq_estado='procesando',fcq_bloqueado_hasta=VALUES(fcq_bloqueado_hasta),fcq_fecha_actualizacion=NOW()")
                ->execute([':ven'=>$venId, ':externo'=>$idExterno]);

            $existente = $cn->prepare(
                "SELECT f.fac_id, f.fac_central_id, f.fac_id_externo, f.fac_serie, f.fac_correlativo,
                        f.fac_estado, f.fac_estado_sunat, f.fac_respuesta_estado, f.fac_fecha_emision
                 FROM venta v
                 INNER JOIN facturacion f ON f.fac_id = v.fac_id
                 WHERE v.ven_id = :ven_id
                 LIMIT 1"
            );
            $existente->execute([':ven_id' => $venId]);
            $facExistente = $existente->fetch(PDO::FETCH_ASSOC);
            if ($facExistente && $reintentar) {
                if ((string)$facExistente['fac_estado_sunat'] === '0' || $facExistente['fac_respuesta_estado'] === 'verificado_sunat_sin_cdr') {
                    throw new Exception('El comprobante ya figura en SUNAT y no debe reenviarse');
                }
                if (in_array($facExistente['fac_respuesta_estado'], ['rechazado','anulado_sunat','no_autorizado_sunat'], true)) {
                    throw new Exception('El estado tributario del comprobante no permite reenviar el mismo documento');
                }
                if (time() >= strtotime((string)$facExistente['fac_fecha_emision'].' +3 days')) {
                    throw new Exception('El plazo operativo de reenvío venció. Consulte primero el comprobante en SUNAT');
                }
            }
            if ($facExistente && !$reintentar) {
                $apiActual = $this->consultarEstadoCentral($config, (string)$facExistente['fac_id_externo']);
                if (!empty($apiActual['id'])) {
                    $aceptadoActual = $this->respuestaAceptada($apiActual);
                    $cn->prepare('UPDATE facturacion SET fac_central_id=:central,fac_estado_sunat=:sunat,fac_estado=:estado WHERE fac_id=:id')
                        ->execute([':central'=>(int)$apiActual['id'],':sunat'=>(string)($apiActual['codigo_sunat']??''),':estado'=>$aceptadoActual?2:0,':id'=>$facExistente['fac_id']]);
                    $this->guardarRespuesta($cn, (int)$facExistente['fac_id'], $apiActual, false);
                    $facExistente['fac_estado'] = $aceptadoActual ? 2 : 0;
                    $facExistente['fac_estado_sunat'] = (string)($apiActual['codigo_sunat'] ?? '');
                } else {
                    $this->actualizarCola($cn, $venId, 'pendiente', (string)($apiActual['mensaje'] ?? 'La central no confirmó el comprobante'), date('Y-m-d H:i:s', time()+300));
                }
                return [
                    'estado' => 'ok',
                    'fac_id' => (int) $facExistente['fac_id'],
                    'serie' => $facExistente['fac_serie'],
                    'correlativo' => (int) $facExistente['fac_correlativo'],
                    'comprobante' => $facExistente['fac_serie'] . '-' . str_pad((string) $facExistente['fac_correlativo'], 8, '0', STR_PAD_LEFT),
                    'api' => !empty($apiActual['id']) ? $apiActual : [
                        'success' => (int) $facExistente['fac_estado'] === 2,
                        'id' => (int) $facExistente['fac_central_id'],
                        'estado' => (int) $facExistente['fac_estado'] === 2 ? 'aceptado' : 'pendiente',
                        'codigo_sunat' => $facExistente['fac_estado_sunat'],
                        'idempotente' => true,
                    ],
                    'mensaje' => 'La venta ya tiene un comprobante asignado',
                ];
            }

            $stmtVenta = $cn->prepare(
                "SELECT v.ven_id, v.ven_fecha, v.ven_total, v.ven_tipo, v.tde_id,
                        tde.tde_codigo,
                        cp.ent_nombres AS cli_nombres, cp.ent_nro_doc AS cli_nro_doc,
                        cp.ent_tipo_doc AS cli_tipo_doc, cp.ent_direccion AS cli_direccion
                 FROM venta v
                 LEFT JOIN " . comprobantes_pos::SQL . " tde ON tde.tde_id = v.tde_id
                 LEFT JOIN entidad cp ON cp.ent_id = v.ent_id AND cp.ent_cliente = 1
                 WHERE v.ven_id = :ven_id AND v.ven_estado = 1"
            );
            $stmtVenta->execute([':ven_id' => $venId]);
            $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

            if (!$venta) {
                throw new Exception('Venta no encontrada o anulada');
            }
            if (!in_array((string) $venta['tde_codigo'], ['01', '03'], true)) {
                throw new Exception('La venta no tiene una factura o boleta válida para emitir');
            }
            if ($venta['tde_codigo'] === '01' && ((int) $venta['cli_tipo_doc'] !== 6 || strlen(trim((string) $venta['cli_nro_doc'])) !== 11)) {
                throw new Exception('Para emitir factura, el cliente debe tener un RUC válido');
            }

            $items = $this->itemsVenta($cn, $venta);
            if (!$items) {
                throw new Exception('La venta no tiene ítems para facturar');
            }

            $formatoPdf = $this->formatoPdfConfigurado($cn);
            $configTributaria = $this->configuracionTributariaCentral($config);
            $tipoAfectacion = $configTributaria['tipo_afectacion_igv'];
            $porcentajeIgv = $configTributaria['porcentaje_igv'];
            $payload = [
                'ruc_emisor' => (string) ($config['ruc'] ?? ''),
                'formato_pdf' => $formatoPdf,
                'id_externo' => $idExterno,
                'origen_venta_id' => (string) $venId,
                'origen_usuario_id' => (string) $usuario,
                'tipo_doc' => (string) $venta['tde_codigo'],
                // La serie se omite deliberadamente: la elige la configuración central.
                'fecha_emision' => date('Y-m-d', strtotime((string) $venta['ven_fecha'])),
                'reintentar' => true,
                'tipo_afectacion_igv' => $tipoAfectacion,
                'porcentaje_igv' => $porcentajeIgv,
                'cliente' => [
                    'tipo_doc' => (string) ($venta['cli_tipo_doc'] ?: '0'),
                    'numero' => trim((string) ($venta['cli_nro_doc'] ?: '')),
                    'razon_social' => trim((string) ($venta['cli_nombres'] ?: 'CLIENTE GENERAL')),
                    'direccion' => trim((string) ($venta['cli_direccion'] ?: '')),
                ],
                'items' => array_map(function (array $item) use ($tipoAfectacion, $porcentajeIgv): array {
                    return [
                        'descripcion' => $item['descripcion'],
                        'codigo_interno' => $item['codigo'],
                        'cantidad' => (float) $item['cantidad'],
                        'unidad' => $this->codigoUnidadSunat((string) ($item['unidad_abrev'] ?? 'UND')),
                        'unidad_nombre' => (string) ($item['unidad_abrev'] ?? 'UND'),
                        'precio_unitario' => (float) $item['precio_unitario'],
                        'tipo_afectacion_igv' => $tipoAfectacion,
                        'porcentaje_igv' => $porcentajeIgv,
                    ];
                }, $items),
            ];

            // Antes de emitir, buscar el identificador idempotente. Esto recupera
            // ventas cuyo envío llegó a la central pero cuya respuesta se perdió.
            // La reserva ya es idempotente en la central; evitar una consulta
            // previa ahorra un viaje HTTP en la ruta que debe responder rápido.
            $apiExistente = $soloReservar ? [] : $this->consultarEstadoCentral($config, $idExterno);
            if (!empty($apiExistente['id'])) {
                // Consultar y reenviar son acciones distintas. Si el usuario pidió
                // reintentar y la central todavía no lo aceptó, se vuelve a enviar
                // usando el mismo id_externo para no reservar otro correlativo.
                $api = ($reintentar && !$this->respuestaAceptada($apiExistente))
                    ? $this->llamarCentral($config, $payload)
                    : $apiExistente;
            } elseif ($soloConsultar) {
                $this->actualizarCola($cn, $venId, 'pendiente', 'La central todavía no tiene un comprobante asignado', date('Y-m-d H:i:s', time()+300));
                return ['estado'=>'error','mensaje'=>'La central todavía no tiene un comprobante asignado a esta venta. No se realizó ningún reenvío.'];
            } else {
                $api = $this->llamarCentral($config, $payload, $soloReservar ? '/api/comprobantes/reservar' : '/api/comprobantes/emitir');
            }
            if (empty($api['id']) || !preg_match('/^([A-Z0-9]+)-(\d+)$/', (string) ($api['documento'] ?? ''), $numero)) {
                throw new Exception($api['mensaje'] ?? $api['message'] ?? $api['mensaje_error'] ?? 'El servicio central no asignó el comprobante');
            }

            $serie = $numero[1];
            $correlativo = (int) $numero[2];
            $total = round((float) $venta['ven_total'], 2);
            $esGravado = $tipoAfectacion === '10';
            $valorVenta = $esGravado ? round($total / (1 + $porcentajeIgv / 100), 2) : $total;
            $igv = $esGravado ? round($total - $valorVenta, 2) : 0.0;
            $aceptado = $this->respuestaAceptada($api);

            // Un reenvío actualiza el mismo comprobante local. Nunca crea otra
            // factura ni reserva un segundo correlativo para la misma venta.
            if ($facExistente) {
                $cn->beginTransaction();
                $facId = (int) $facExistente['fac_id'];
                $cn->prepare(
                    "UPDATE facturacion
                     SET fac_central_id=:central,fac_serie=:serie,fac_correlativo=:correlativo,
                         fac_formato_pdf=:formato,fac_estado_sunat=:sunat,fac_estado=:estado,usuario=:usuario,fechahora=NOW()
                     WHERE fac_id=:fac_id"
                )->execute([
                    ':central' => (int) $api['id'], ':serie' => $serie, ':formato' => $formatoPdf,
                    ':correlativo' => $correlativo,
                    ':sunat' => (string) ($api['codigo_sunat'] ?? ''),
                    ':estado' => $aceptado ? 2 : 0, ':usuario' => $usuario,
                    ':fac_id' => $facId,
                ]);
                $cn->prepare(
                    'UPDATE venta SET fac_id=:fac_id,ven_serie=:serie,ven_numero=:numero WHERE ven_id=:ven_id'
                )->execute([':fac_id' => $facId, ':serie' => $serie, ':numero' => $correlativo, ':ven_id' => $venId]);
                $this->guardarRespuesta($cn, $facId, $api, $reintentar);
                $cn->commit();
                return [
                    'estado' => $aceptado ? 'ok' : 'error',
                    'fac_id' => $facId,
                    'serie' => $serie,
                    'correlativo' => $correlativo,
                    'comprobante' => $api['documento'],
                    'archivos' => $api['archivos'] ?? [],
                    'api' => $api,
                    'mensaje' => $aceptado ? 'Comprobante reenviado y confirmado' : ($api['mensaje_error'] ?? 'El comprobante continúa pendiente'),
                ];
            }

            $cn->beginTransaction();
            $stmtFac = $cn->prepare(
                "INSERT INTO facturacion (
                    fac_central_id, fac_id_externo, ven_id, fac_formato_pdf, fac_tipo_valor,
                    fac_tipo_operacion, fac_tipo_doc, fac_serie, fac_correlativo,
                    fac_tipo_moneda, fac_tipo_cambio, fac_fecha_emision, fac_empresa_ruc,
                    fac_cliente_tipo_doc, fac_cliente_num_doc, fac_cliente_razon_social,
                    fac_cliente_direccion, fac_monto_oper_gravadas,
                    fac_monto_oper_exoneradas, fac_monto_oper_inafectas,
                    fac_monto_igv, fac_total_impuestos, fac_valor_venta, fac_sub_total,
                    fac_monto_imp_venta, fac_total_letras, fac_estado_sunat,
                    fac_estado, usuario, fechahora
                ) VALUES (
                    :central_id, :externo, :ven_id, :formato_pdf, 1, '0101', :tipo_doc, :serie,
                    :correlativo, 'PEN', 0, :fecha, :ruc, :cli_tipo, :cli_num,
                    :cli_nombre, :cli_direccion, :gravadas, 0, :inafectas, :igv,
                    :igv, :valor_venta, :total, :total, '', :estado_sunat,
                    :estado, :usuario, NOW()
                )"
            );
            $stmtFac->execute([
                ':central_id' => (int) $api['id'], ':externo' => $idExterno,
                ':ven_id' => $venId, ':formato_pdf' => $formatoPdf, ':tipo_doc' => $venta['tde_codigo'],
                ':serie' => $serie, ':correlativo' => $correlativo,
                ':fecha' => $venta['ven_fecha'], ':ruc' => (string) $config['ruc'],
                ':cli_tipo' => (string) ($venta['cli_tipo_doc'] ?: '0'),
                ':cli_num' => trim((string) ($venta['cli_nro_doc'] ?: '')),
                ':cli_nombre' => trim((string) ($venta['cli_nombres'] ?: 'CLIENTE GENERAL')),
                ':cli_direccion' => trim((string) ($venta['cli_direccion'] ?: '')),
                ':gravadas' => $esGravado ? $valorVenta : 0,
                ':inafectas' => $esGravado ? 0 : $valorVenta,
                ':igv' => $igv, ':valor_venta' => $valorVenta, ':total' => $total,
                ':estado_sunat' => (string) ($api['codigo_sunat'] ?? ''),
                ':estado' => $aceptado ? 2 : 0, ':usuario' => $usuario,
            ]);
            $facId = (int) $cn->lastInsertId();

            $stmtDetalle = $cn->prepare(
                "INSERT INTO facturacion_detalle (
                    fac_id, fde_unidad, fde_cantidad, fde_cod_producto,
                    fde_descripcion, fde_monto_valor_unitario, fde_monto_base_igv,
                    fde_porcentaje_igv, fde_igv, fde_tip_afe_igv,
                    fde_total_impuestos, fde_monto_precio_unitario,
                    fde_monto_valor_venta, fde_factor_icbper
                ) VALUES (
                    :fac_id, 'NIU', :cantidad, :codigo, :descripcion, :valor_unitario,
                    :base_igv, :porcentaje, :igv, :afectacion, :igv,
                    :precio_unitario, :base_igv, 0
                )"
            );
            foreach ($items as $item) {
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $subtotal = round($cantidad * $precio, 2);
                $valorUnitario = $esGravado ? round($precio / 1.18, 6) : $precio;
                $baseIgv = round($valorUnitario * $cantidad, 2);
                $igvItem = $esGravado ? round($subtotal - $baseIgv, 2) : 0;
                $stmtDetalle->execute([
                    ':fac_id' => $facId, ':cantidad' => $cantidad,
                    ':codigo' => $item['codigo'], ':descripcion' => $item['descripcion'],
                    ':valor_unitario' => $valorUnitario, ':base_igv' => $baseIgv,
                    ':porcentaje' => $esGravado ? 18 : 0, ':igv' => $igvItem,
                    ':afectacion' => $esGravado ? '10' : '30', ':precio_unitario' => $precio,
                ]);
            }

            $cn->prepare(
                'UPDATE venta SET fac_id=:fac_id,ven_serie=:serie,ven_numero=:numero WHERE ven_id=:ven_id'
            )->execute([':fac_id' => $facId, ':serie' => $serie, ':numero' => $correlativo, ':ven_id' => $venId]);
            $this->guardarRespuesta($cn, $facId, $api, false);
            $cn->commit();
            return [
                'estado' => ($aceptado || $soloReservar) ? 'ok' : 'error',
                'fac_id' => $facId,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'comprobante' => $api['documento'],
                'archivos' => $api['archivos'] ?? [],
                'api' => $api,
                'mensaje' => $soloReservar ? 'Numeración reservada por el servicio central' : ($aceptado ? 'Comprobante emitido por el servicio central' : ($api['mensaje_error'] ?? 'El comprobante central quedó pendiente')),
            ];
        } catch (Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            try {
                $intentos = (int)$cn->query('SELECT COALESCE(MAX(fcq_intentos),0) FROM facturacion_cola WHERE ven_id='.(int)$venId)->fetchColumn() + 1;
                $requiereRevision = preg_match('/(no válida|no encontrado|anulada|saldo pendiente|RUC válido|no tiene ítems|configurar|identificador válido)/iu', $e->getMessage()) === 1;
                $estadoCola = $requiereRevision ? 'requiere_revision' : ($intentos >= 10 ? 'agotado' : 'pendiente');
                $proximo = $estadoCola === 'agotado' ? null : date('Y-m-d H:i:s', time() + min(60, max(1, $intentos * 5)) * 60);
                $this->actualizarCola($cn, $venId, $estadoCola, $e->getMessage(), $proximo, true);
            } catch (Throwable $colaError) { error_log('ERROR cola facturacion - '.$colaError->getMessage()); }
            error_log('ERROR facturacion_central_pos::registrar_desde_venta - ' . $e->getMessage());
            return ['estado' => 'error', 'mensaje' => $e->getMessage()];
        } finally {
            if ($lockTomado && $lockName !== '') {
                $stmtUnlock = $cn->prepare('SELECT RELEASE_LOCK(:nombre)');
                $stmtUnlock->execute([':nombre' => $lockName]);
            }
        }
    }

    public static function validarPagoCompleto(PDO $cn, int $venId): void
    {
        $s=$cn->prepare('SELECT ven_total,ven_pagado FROM venta WHERE ven_id=? AND ven_estado=1');
        $s->execute([$venId]); $v=$s->fetch(PDO::FETCH_ASSOC);
        if (!$v) throw new RuntimeException('Venta no encontrada o anulada');
        if ((int)round((float)$v['ven_pagado']*100)<(int)round((float)$v['ven_total']*100)) {
            throw new RuntimeException('La venta tiene saldo pendiente. Complete el pago antes de emitir el comprobante.');
        }
    }

    private function itemsVenta(PDO $cn, array $venta): array
    {
        $sql = "SELECT COALESCE(vd.vdp_sku,vd.pro_id,vd.vdp_id) AS codigo,COALESCE(vd.vdp_nombre,p.pro_nombre) AS descripcion,
                       vd.vdp_cantidad AS cantidad,vd.vdp_precio_unit AS precio_unitario,
                       COALESCE(vd.unidad_abrev_historica,u.und_abrev) AS unidad_abrev
                FROM venta_detalle_producto vd
                LEFT JOIN producto p ON p.pro_id=vd.pro_id
                LEFT JOIN unidad_medida u ON u.und_id = vd.und_med
                WHERE vd.ven_id=:ven_id ORDER BY vd.vdp_id";
        $stmt = $cn->prepare($sql);
        $stmt->execute([':ven_id' => $venta['ven_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function configuracionTributariaCentral(array $config): array
    {
        $respuesta = $this->llamarGetCentral($config, '/api/empresa?' . http_build_query(['ruc'=>$config['ruc']]));
        $empresa = is_array($respuesta['empresa'] ?? null) ? $respuesta['empresa'] : [];
        $tipo = (string) ($empresa['em_tipo_afectacion_igv'] ?? '10');
        $porcentaje = (float) ($empresa['em_porcentaje_igv'] ?? 18);
        if (!in_array($tipo, ['10','20','30','31','32','33','34','35','36','40'], true)) $tipo = '10';
        if ($tipo !== '10') $porcentaje = 0.0;
        return ['tipo_afectacion_igv'=>$tipo, 'porcentaje_igv'=>$porcentaje];
    }

    private function codigoUnidadSunat(string $abreviatura): string
    {
        return match (strtoupper(trim($abreviatura))) {
            'DO', 'DOC', 'DOCENA' => 'DZN',
            'KG' => 'KGM',
            'GR' => 'GRM',
            'LT', 'L' => 'LTR',
            'M', 'MT', 'MTS' => 'MTR',
            'CM' => 'CMT',
            'GL', 'GAL' => 'GLL',
            default => 'NIU',
        };
    }

    private function llamarCentral(array $config, array $payload, string $ruta = '/api/comprobantes/emitir'): array
    {
        $url = rtrim((string) ($config['base_url'] ?? ''), '/') . $ruta;
        // Reservar numeración es la única llamada que bloquea la interfaz de
        // venta. Si la central no responde pronto, la venta ya está guardada y
        // debe pasar a la cola en vez de inmovilizar la pantalla un minuto.
        $esReserva = $ruta === '/api/comprobantes/reservar';
        $timeout = $esReserva ? min(12, (int)($config['timeout'] ?? 60)) : (int)($config['timeout'] ?? 60);
        $connectTimeout = $esReserva ? min(5, $timeout) : 10;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . (string) ($config['token'] ?? ''),
            ],
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $this->aplicarTlsCentral($ch);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            return ['success' => false, 'mensaje' => 'No se pudo conectar con el API central: ' . $curlError, 'http_code' => $httpCode];
        }
        $respuesta = json_decode($raw, true);
        if (!is_array($respuesta)) {
            return ['success' => false, 'mensaje' => 'El API central devolvió una respuesta inválida', 'http_code' => $httpCode];
        }
        $respuesta['http_code'] = $httpCode;
        return $respuesta;
    }

    public function emitir_nota_venta(string $tipoNota): array
    {
        $cn = $this->cnx->con();
        $venId = isset($_REQUEST['ven_id']) ? (int) $_REQUEST['ven_id'] : 0;
        $usuario = isset($_SESSION['usu_id_ifact']) ? (int) $_SESSION['usu_id_ifact'] : 0;
        $lockName = '';
        $lockTomado = false;

        if ($venId <= 0 || !in_array($tipoNota, ['07', '08'], true)) {
            return ['estado' => 'error', 'mensaje' => 'Venta o tipo de nota no válido'];
        }

        try {
            $config = $this->configuracionCentral();
            $lockName = $this->nombreBloqueo($cn, $config, 'nota', $venId, $tipoNota);
            $stmtLock = $cn->prepare('SELECT GET_LOCK(:nombre, 10)');
            $stmtLock->execute([':nombre' => $lockName]);
            $lockTomado = (int) $stmtLock->fetchColumn() === 1;
            if (!$lockTomado) {
                throw new Exception('La nota está siendo emitida por otro proceso. Intente nuevamente.');
            }

            $stmtVenta = $cn->prepare(
                "SELECT v.ven_id,v.ven_total,v.ven_serie,v.ven_numero,v.fac_id,v.ven_estado,
                        tde.tde_codigo,
                        cp.ent_tipo_doc AS cli_tipo_doc, cp.ent_nro_doc AS cli_nro_doc,
                        cp.ent_nombres AS cli_nombres, cp.ent_direccion AS cli_direccion,
                        f.fac_central_id,
                        f.fac_monto_oper_gravadas AS original_gravadas,
                        f.fac_monto_oper_exoneradas AS original_exoneradas,
                        f.fac_monto_oper_inafectas AS original_inafectas,
                        f.fac_monto_igv AS original_igv,
                        (SELECT fd.fde_tip_afe_igv
                           FROM facturacion_detalle fd
                          WHERE fd.fac_id=f.fac_id
                          ORDER BY fd.fde_id LIMIT 1) AS original_tipo_afectacion,
                        (SELECT fd.fde_porcentaje_igv
                           FROM facturacion_detalle fd
                          WHERE fd.fac_id=f.fac_id
                          ORDER BY fd.fde_id LIMIT 1) AS original_porcentaje_igv
                 FROM venta v
                 INNER JOIN facturacion f ON f.fac_id=v.fac_id
                 LEFT JOIN " . comprobantes_pos::SQL . " tde ON tde.tde_id=v.tde_id
                 LEFT JOIN entidad cp ON cp.ent_id=v.ent_id AND cp.ent_cliente = 1
                 WHERE v.ven_id=:ven_id LIMIT 1"
            );
            $stmtVenta->execute([':ven_id' => $venId]);
            $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);
            if (!$venta || empty($venta['fac_id']) || empty($venta['fac_central_id'])) {
                throw new Exception('Primero debe emitir el comprobante central de la venta');
            }
            if (!in_array((string) $venta['tde_codigo'], ['01', '03'], true)) {
                throw new Exception('Solo se puede emitir una nota sobre factura o boleta');
            }

            $yaExiste = $cn->prepare(
                "SELECT fac_id,fac_central_id,fac_serie,fac_correlativo,fac_estado,fac_estado_sunat,
                        fac_motivo_codigo,fac_motivo_descripcion,fac_monto_imp_venta
                 FROM facturacion
                 WHERE ven_id=:ven_id AND fac_tipo_doc=:tipo
                 ORDER BY fac_id DESC LIMIT 1"
            );
            $yaExiste->execute([':ven_id' => $venId, ':tipo' => $tipoNota]);
            $notaExistente = $yaExiste->fetch(PDO::FETCH_ASSOC);
            if ($notaExistente) {
                $aceptadaExistente = (int) $notaExistente['fac_estado'] === 2
                    || (string) $notaExistente['fac_estado_sunat'] === '0';
                $stockDevuelto = false;
                $esAnulacionTotalExistente = $tipoNota === '07'
                    && (string) $notaExistente['fac_motivo_codigo'] === '01';
                if ($esAnulacionTotalExistente && $aceptadaExistente) {
                    $cn->beginTransaction();
                    $stockDevuelto = $this->anularVentaPorNotaCredito(
                        $cn,
                        $venId,
                        $usuario
                    );
                    $cn->commit();
                }
                return [
                    'estado' => 'ok', 'fac_id' => (int) $notaExistente['fac_id'],
                    'comprobante' => $notaExistente['fac_serie'] . '-' . str_pad((string) $notaExistente['fac_correlativo'], 8, '0', STR_PAD_LEFT),
                    'api' => ['success' => $aceptadaExistente, 'idempotente' => true,
                        'id' => (int) $notaExistente['fac_central_id'], 'codigo_sunat' => $notaExistente['fac_estado_sunat']],
                    'mensaje' => 'Esta venta ya tiene la nota solicitada',
                    'tipo_nota' => $tipoNota,
                    'ya_existia' => true,
                    'referencia' => $venta['ven_serie'] . '-' . str_pad((string) $venta['ven_numero'], 8, '0', STR_PAD_LEFT),
                    'motivo' => (string) ($notaExistente['fac_motivo_descripcion'] ?: 'Anulación de la operación'),
                    'total' => (float) $notaExistente['fac_monto_imp_venta'],
                    'stock_devuelto' => $stockDevuelto,
                    'venta_anulada' => $esAnulacionTotalExistente && $aceptadaExistente,
                ];
            }

            if ((int) $venta['ven_estado'] !== 1) {
                throw new Exception('La venta está anulada y no admite una nueva nota');
            }

            $serie = $this->serieNotaCentral($config, $tipoNota, (string)$venta['tde_codigo']);
            if ($serie === '') {
                throw new Exception('No está configurada la serie central para esta nota');
            }

            $total = $tipoNota === '08'
                ? round((float) ($_REQUEST['monto'] ?? 0), 2)
                : round((float) $venta['ven_total'], 2);
            if ($total <= 0) {
                throw new Exception('El monto de la nota debe ser mayor a cero');
            }

            $motivoCodigo = trim((string) ($_REQUEST['motivo_codigo'] ?? '01'));
            $motivoDescripcion = trim((string) ($_REQUEST['motivo_descripcion'] ?? ''));
            if ($motivoDescripcion === '') {
                $motivoDescripcion = $tipoNota === '07' ? 'Anulación de la operación' : 'Intereses por mora';
            }
            $esAnulacionTotal = $tipoNota === '07' && $motivoCodigo === '01';
            $referencia = $venta['ven_serie'] . '-' . str_pad((string) $venta['ven_numero'], 8, '0', STR_PAD_LEFT);
            $descripcionItem = trim((string) ($_REQUEST['descripcion'] ?? ''));
            if ($descripcionItem === '') {
                $descripcionItem = $motivoDescripcion . ' - ' . $referencia;
            }
            // La nota debe conservar el tratamiento tributario del comprobante
            // que modifica. El tipo de documento (factura/boleta) no determina
            // si la operación es gravada, exonerada o inafecta.
            $tipoAfectacion = trim((string) ($venta['original_tipo_afectacion'] ?? ''));
            if ($tipoAfectacion === '') {
                if ((float) ($venta['original_gravadas'] ?? 0) > 0 || (float) ($venta['original_igv'] ?? 0) > 0) {
                    $tipoAfectacion = '10';
                } elseif ((float) ($venta['original_exoneradas'] ?? 0) > 0) {
                    $tipoAfectacion = '20';
                } else {
                    $tipoAfectacion = '30';
                }
            }
            $esGravado = $tipoAfectacion === '10';
            $esExonerado = $tipoAfectacion === '20';
            $porcentajeIgv = $esGravado ? (float) ($venta['original_porcentaje_igv'] ?? 0) : 0.0;
            if ($esGravado && $porcentajeIgv <= 0) {
                $porcentajeIgv = 18.0;
            }
            $idExterno = $this->idExterno($cn, $config, 'nota', $venId, $tipoNota);
            $payload = [
                'ruc_emisor' => (string) ($config['ruc'] ?? ''),
                'formato_pdf' => $this->formatoPdfConfigurado($cn),
                'id_externo' => $idExterno,
                'origen_venta_id' => (string) $venId,
                'origen_usuario_id' => (string) $usuario,
                'tipo_doc' => $tipoNota,
                'serie' => $serie,
                'fecha_emision' => date('Y-m-d'),
                'reintentar' => true,
                'tipo_afectacion_igv' => $tipoAfectacion,
                'porcentaje_igv' => $porcentajeIgv,
                'doc_referencia' => ['tipo_doc' => (string) $venta['tde_codigo'], 'numero' => $referencia],
                'motivo_codigo' => $motivoCodigo,
                'motivo_descripcion' => $motivoDescripcion,
                'cliente' => [
                    'tipo_doc' => (string) ($venta['cli_tipo_doc'] ?: '0'),
                    'numero' => trim((string) ($venta['cli_nro_doc'] ?: '99999999')),
                    'razon_social' => trim((string) ($venta['cli_nombres'] ?: 'CLIENTE GENERAL')),
                    'direccion' => trim((string) ($venta['cli_direccion'] ?: '')),
                ],
                'items' => [[
                    'descripcion' => $descripcionItem, 'codigo_interno' => $referencia,
                    'cantidad' => 1, 'unidad' => 'NIU', 'precio_unitario' => $total,
                    'tipo_afectacion_igv' => $tipoAfectacion,
                    'porcentaje_igv' => $porcentajeIgv,
                ]],
            ];

            $api = $this->llamarCentral($config, $payload);
            if (empty($api['id']) || !preg_match('/^([A-Z0-9]+)-(\d+)$/', (string) ($api['documento'] ?? ''), $numero)) {
                throw new Exception($api['mensaje'] ?? $api['message'] ?? $api['mensaje_error'] ?? 'El servicio central no asignó la nota');
            }

            $serieCentral = $numero[1];
            $correlativo = (int) $numero[2];
            $divisorIgv = 1 + ($porcentajeIgv / 100);
            $valorVenta = $esGravado ? round($total / $divisorIgv, 2) : $total;
            $igv = $esGravado ? round($total - $valorVenta, 2) : 0.0;
            $aceptada = $this->respuestaAceptada($api);

            $cn->beginTransaction();
            $stmtFac = $cn->prepare(
                "INSERT INTO facturacion (
                    fac_central_id,fac_id_externo,ven_id,fac_doc_referencia_id,
                    fac_motivo_codigo,fac_motivo_descripcion,fac_tipo_valor,
                    fac_tipo_operacion,fac_tipo_doc,fac_serie,fac_correlativo,
                    fac_tipo_moneda,fac_tipo_cambio,fac_fecha_emision,fac_empresa_ruc,
                    fac_cliente_tipo_doc,fac_cliente_num_doc,fac_cliente_razon_social,
                    fac_cliente_direccion,fac_monto_oper_gravadas,
                    fac_monto_oper_exoneradas,fac_monto_oper_inafectas,fac_monto_igv,
                    fac_total_impuestos,fac_valor_venta,fac_sub_total,
                    fac_monto_imp_venta,fac_total_letras,fac_estado_sunat,
                    fac_estado,usuario,fechahora
                 ) VALUES (
                    :central,:externo,:ven_id,:referencia,:motivo,:descripcion,1,
                    '0101',:tipo,:serie,:correlativo,'PEN',0,NOW(),:ruc,
                    :cli_tipo,:cli_num,:cli_nombre,:cli_direccion,:gravadas,:exoneradas,
                    :inafectas,:igv,:igv,:valor,:total,:total,'',:sunat,
                    :estado,:usuario,NOW())"
            );
            $stmtFac->execute([
                ':central' => (int) $api['id'], ':externo' => $idExterno,
                ':ven_id' => $venId, ':referencia' => (int) $venta['fac_id'],
                ':motivo' => $motivoCodigo, ':descripcion' => $motivoDescripcion,
                ':tipo' => $tipoNota, ':serie' => $serieCentral, ':correlativo' => $correlativo,
                ':ruc' => (string) $config['ruc'], ':cli_tipo' => (string) ($venta['cli_tipo_doc'] ?: '0'),
                ':cli_num' => trim((string) ($venta['cli_nro_doc'] ?: '99999999')),
                ':cli_nombre' => trim((string) ($venta['cli_nombres'] ?: 'CLIENTE GENERAL')),
                ':cli_direccion' => trim((string) ($venta['cli_direccion'] ?: '')),
                ':gravadas' => $esGravado ? $valorVenta : 0,
                ':exoneradas' => $esExonerado ? $valorVenta : 0,
                ':inafectas' => (!$esGravado && !$esExonerado) ? $valorVenta : 0,
                ':igv' => $igv, ':valor' => $valorVenta, ':total' => $total,
                ':sunat' => (string) ($api['codigo_sunat'] ?? ''),
                ':estado' => $aceptada ? 2 : 0, ':usuario' => $usuario,
            ]);
            $facId = (int) $cn->lastInsertId();
            $stmtDetalle = $cn->prepare(
                "INSERT INTO facturacion_detalle (
                    fac_id,fde_unidad,fde_cantidad,fde_cod_producto,fde_descripcion,
                    fde_monto_valor_unitario,fde_monto_base_igv,fde_porcentaje_igv,
                    fde_igv,fde_tip_afe_igv,fde_total_impuestos,
                    fde_monto_precio_unitario,fde_monto_valor_venta,fde_factor_icbper
                 ) VALUES (:fac_id,'NIU',1,:codigo,:descripcion,:valor,:valor,
                    :porcentaje,:igv,:afectacion,:igv,:total,:valor,0)"
            );
            $stmtDetalle->execute([
                ':fac_id' => $facId, ':codigo' => $referencia,
                ':descripcion' => $descripcionItem, ':valor' => $valorVenta,
                ':porcentaje' => $porcentajeIgv, ':igv' => $igv,
                ':afectacion' => $tipoAfectacion, ':total' => $total,
            ]);
            $this->guardarRespuesta($cn, $facId, $api, false);
            $stockDevuelto = false;
            if ($esAnulacionTotal && $aceptada) {
                $stockDevuelto = $this->anularVentaPorNotaCredito($cn, $venId, $usuario);
            }
            $cn->commit();
            return [
                'estado' => $aceptada ? 'ok' : 'error', 'fac_id' => $facId,
                'comprobante' => $api['documento'], 'api' => $api,
                'tipo_nota' => $tipoNota,
                'ya_existia' => false,
                'referencia' => $referencia,
                'motivo' => $motivoDescripcion,
                'total' => $total,
                'stock_devuelto' => $stockDevuelto,
                'venta_anulada' => $esAnulacionTotal && $aceptada,
                'mensaje' => $aceptada
                    ? ($tipoNota === '07' ? 'Nota de crédito emitida por el servicio central' : 'Nota de débito emitida por el servicio central')
                    : ($api['mensaje_error'] ?? 'La nota central quedó pendiente'),
            ];
        } catch (Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            error_log('ERROR facturacion_central_pos::emitir_nota_venta - ' . $e->getMessage());
            return ['estado' => 'error', 'mensaje' => $e->getMessage()];
        } finally {
            if ($lockTomado && $lockName !== '') {
                $stmtUnlock = $cn->prepare('SELECT RELEASE_LOCK(:nombre)');
                $stmtUnlock->execute([':nombre' => $lockName]);
            }
        }
    }

    /**
     * Anula la operación comercial y devuelve sus unidades al inventario una
     * sola vez después de que SUNAT acepta la nota de crédito.
     */
    private function anularVentaPorNotaCredito(PDO $cn, int $venId, int $usuario): bool
    {
        $stmtVenta = $cn->prepare('SELECT loc_id,ven_estado FROM venta WHERE ven_id=:ven_id FOR UPDATE');
        $stmtVenta->execute([':ven_id' => $venId]);
        $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);
        if (!$venta) {
            throw new RuntimeException('No se encontró la venta asociada a la nota de crédito');
        }

        $stmtMovimiento = $cn->prepare(
            'SELECT COUNT(*) FROM movimiento_stock_variante WHERE ven_id=:ven_id AND tipo=3'
        );
        $stmtMovimiento->execute([':ven_id' => $venId]);
        if ((int) $stmtMovimiento->fetchColumn() > 0) {
            $stmtEstado = $cn->prepare(
                'UPDATE venta SET ven_estado=0,ven_usu_anulado=:usuario,
                 ven_fec_anulado=COALESCE(ven_fec_anulado,NOW()) WHERE ven_id=:ven_id'
            );
            $stmtEstado->execute([':usuario' => $usuario, ':ven_id' => $venId]);
            return false;
        }

        $stmtDetalles = $cn->prepare(
            'SELECT vdp_id,pva_id,vdp_cantidad FROM venta_detalle_producto
             WHERE ven_id=:ven_id ORDER BY pva_id,vdp_id FOR UPDATE'
        );
        $stmtDetalles->execute([':ven_id' => $venId]);
        $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
        $fifo = new costos_fifo($cn);
        $huboMovimiento = false;
        foreach ($detalles as $detalle) {
            $pvaId = (int) $detalle['pva_id'];
            $cantidad = (float) $detalle['vdp_cantidad'];
            if ($pvaId <= 0 || $cantidad <= 0) {
                continue;
            }
            $fifo->revertirVenta((int) $detalle['vdp_id']);
            $stmtStock = $cn->prepare(
                'INSERT INTO stock_variante(pva_id,loc_id,cantidad) VALUES(:pva,:loc,:cantidad)
                 ON DUPLICATE KEY UPDATE cantidad=cantidad+VALUES(cantidad)'
            );
            $stmtStock->execute([':pva' => $pvaId, ':loc' => (int) $venta['loc_id'], ':cantidad' => $cantidad]);
            $stmtMov = $cn->prepare(
                'INSERT INTO movimiento_stock_variante(pva_id,loc_id,cantidad,tipo,ven_id,usuario)
                 VALUES(:pva,:loc,:cantidad,3,:ven_id,:usuario)'
            );
            $stmtMov->execute([
                ':pva' => $pvaId, ':loc' => (int) $venta['loc_id'], ':cantidad' => $cantidad,
                ':ven_id' => $venId, ':usuario' => $usuario,
            ]);
            $huboMovimiento = true;
        }

        $stmtEstado = $cn->prepare(
            'UPDATE venta SET ven_estado=0,ven_usu_anulado=:usuario,ven_fec_anulado=NOW()
             WHERE ven_id=:ven_id'
        );
        $stmtEstado->execute([':usuario' => $usuario, ':ven_id' => $venId]);
        return $huboMovimiento;
    }

    public function obtener_archivo_documento_emitido(): array
    {
        $facId = isset($_REQUEST['fac_id']) ? (int) $_REQUEST['fac_id'] : 0;
        $modo = (string) ($_REQUEST['modo'] ?? 'inline');
        $tipo = strtolower((string) ($_REQUEST['tipo'] ?? 'pdf'));
        if (!in_array($tipo, ['pdf', 'xml', 'cdr'], true)) {
            return ['success' => false, 'http' => 400, 'mensaje' => 'Tipo de archivo no válido'];
        }
        if ($facId <= 0) {
            return ['success' => false, 'http' => 400, 'mensaje' => 'Comprobante no válido'];
        }

        $cn = $this->cnx->con();
        $stmt = $cn->prepare(
            'SELECT * FROM facturacion WHERE fac_id=:id LIMIT 1'
        );
        $stmt->execute([':id' => $facId]);
        $fac = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fac || empty($fac['fac_central_id'])) {
            return ['success' => false, 'http' => 404, 'mensaje' => 'El comprobante no tiene un ID central asociado'];
        }

        $rutaLocal=$this->rutaPdfLocal($fac);
        if ($tipo==='pdf' && is_file($rutaLocal)) {
            $contenido=file_get_contents($rutaLocal);
            if ($contenido!==false && strncmp($contenido,'%PDF-',5)===0) {
                return ['success'=>true,'http'=>200,'contenido'=>$contenido,'mime'=>'application/pdf',
                    'nombre'=>$fac['fac_serie'].'-'.str_pad((string)$fac['fac_correlativo'],8,'0',STR_PAD_LEFT).'.pdf',
                    'modo'=>$modo==='attachment'?'attachment':'inline','origen'=>'local'];
            }
        }
        $config = $this->configuracionCentral();
        $query = http_build_query([
            'ruc_emisor' => $config['ruc'],
            'id' => (int) $fac['fac_central_id'],
            'tipo' => $tipo,
            'disposicion' => $tipo === 'pdf' && $modo !== 'attachment' ? 'inline' : 'attachment',
        ]);
        $url = rtrim((string) $config['base_url'], '/') . '/api/comprobantes/descargar?' . $query;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . (string) $config['token'], 'Accept: */*'],
            CURLOPT_TIMEOUT => (int) ($config['timeout'] ?? 60),
        ]);
        $this->aplicarTlsCentral($ch);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $mime = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if (($raw === false || $http !== 200) && $tipo === 'pdf') {
            $estadoCentral = $this->consultarEstadoCentral($config, (string)($fac['fac_id_externo'] ?? ''));
            $archivos = is_array($estadoCentral['archivos'] ?? null) ? $estadoCentral['archivos'] : [];
            foreach (['pdf_ticket', 'pdf_a4'] as $formato) {
                if (empty($archivos[$formato])) continue;
                $pdfCentral = $this->descargarUrlCentral((string)$archivos[$formato], $config);
                if (!empty($pdfCentral['success'])) {
                    $this->guardarPdfLocal($rutaLocal,(string)$pdfCentral['contenido']);
                    $pdfCentral['nombre'] = $fac['fac_serie'] . '-' . str_pad((string)$fac['fac_correlativo'], 8, '0', STR_PAD_LEFT) . '.pdf';
                    $pdfCentral['modo'] = $modo === 'attachment' ? 'attachment' : 'inline';
                    return $pdfCentral;
                }
            }
            return ['success' => false, 'http' => $http ?: 502, 'mensaje' => $error ?: 'El PDF no está disponible en el servicio central'];
        }
        if ($raw === false || $http !== 200) {
            return ['success' => false, 'http' => $http ?: 502, 'mensaje' => $error ?: strtoupper($tipo) . ' no disponible en el servicio central'];
        }

        $contenido=substr($raw,$headerSize);
        if ($tipo==='pdf') {
            if (strncmp($contenido,'%PDF-',5)!==0) return ['success'=>false,'http'=>502,'mensaje'=>'La central no devolvió un PDF válido'];
            $this->guardarPdfLocal($rutaLocal,$contenido);
        }
        return [
            'success' => true,
            'http' => 200,
            'contenido' => $contenido,
            'mime' => $mime ?: ($tipo === 'pdf' ? 'application/pdf' : 'application/xml'),
            'nombre' => $fac['fac_serie'] . '-' . str_pad((string) $fac['fac_correlativo'], 8, '0', STR_PAD_LEFT) . '.' . $tipo,
            'modo' => $tipo === 'pdf' && $modo !== 'attachment' ? 'inline' : 'attachment',
        ];
    }

    private function rutaPdfLocal(array $fac): string
    {
        // Versionar la identidad invalida las representaciones antiguas cuando
        // cambia la plantilla central, sin alterar XML, CDR ni numeración.
        $identidad='v6|'.implode('|',array_map('strval',[$fac['fac_empresa_ruc'],$fac['fac_central_id'],$fac['fac_id_externo'],$fac['fac_tipo_doc'],$fac['fac_serie'],$fac['fac_correlativo'],$fac['fac_formato_pdf']]));
        return dirname(__DIR__).'/storage/comprobantes/'.hash('sha256',$identidad).'.pdf';
    }

    private function guardarPdfLocal(string $ruta,string $contenido): void
    {
        if (strncmp($contenido,'%PDF-',5)!==0) return;
        $temporal=false;
        try {
            $dir=dirname($ruta);
            if (!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('No se pudo crear el directorio PDF');
            $temporal=tempnam($dir,'pdf-');
            if ($temporal===false || file_put_contents($temporal,$contenido,LOCK_EX)!==strlen($contenido)) throw new RuntimeException('No se pudo guardar el PDF');
            if (!rename($temporal,$ruta)) throw new RuntimeException('No se pudo completar el guardado PDF');
        } catch (Throwable $e) { error_log('guardarPdfLocal: '.$e->getMessage()); }
        finally { if ($temporal && is_file($temporal)) unlink($temporal); }
    }

    private function formatoPdfConfigurado(PDO $cn): string
    {
        return 'ticket';
    }

    /** Recupera como ticket comprobantes históricos cuyo PDF no quedó guardado en la central. */
    private function generarTicketTemporal(PDO $cn, array $fac, array $config): array
    {
        $stmt = $cn->prepare('SELECT fde_unidad,fde_cantidad,fde_cod_producto,fde_descripcion,fde_monto_precio_unitario,fde_tip_afe_igv,fde_porcentaje_igv FROM facturacion_detalle WHERE fac_id=:id ORDER BY fde_id');
        $stmt->execute([':id' => $fac['fac_id']]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $items[] = [
                'descripcion' => $item['fde_descripcion'], 'codigo_interno' => (string)$item['fde_cod_producto'],
                'cantidad' => (float)$item['fde_cantidad'], 'unidad' => $item['fde_unidad'] ?: 'NIU',
                'precio_unitario' => (float)$item['fde_monto_precio_unitario'],
                'tipo_afectacion_igv' => (string)$item['fde_tip_afe_igv'], 'porcentaje_igv' => (float)$item['fde_porcentaje_igv'],
            ];
        }
        if (!$items) return ['success' => false, 'mensaje' => 'El comprobante no tiene detalle para reconstruir el ticket'];
        $payload = [
            'ruc_emisor' => (string)$config['ruc'], 'formato_pdf' => 'ticket',
            'tipo_doc' => (string)$fac['fac_tipo_doc'], 'serie' => (string)$fac['fac_serie'],
            'correlativo' => (int)$fac['fac_correlativo'], 'fecha_emision' => substr((string)$fac['fac_fecha_emision'], 0, 10),
            'moneda' => $fac['fac_tipo_moneda'] ?: 'PEN',
            'cliente' => ['tipo_doc'=>(string)$fac['fac_cliente_tipo_doc'],'numero'=>(string)$fac['fac_cliente_num_doc'],'razon_social'=>(string)$fac['fac_cliente_razon_social'],'direccion'=>(string)$fac['fac_cliente_direccion']],
            'items' => $items,
            'totales' => ['oper_gravadas'=>(float)$fac['fac_monto_oper_gravadas'],'oper_exoneradas'=>(float)$fac['fac_monto_oper_exoneradas'],'oper_inafectas'=>(float)$fac['fac_monto_oper_inafectas'],'igv'=>(float)$fac['fac_monto_igv'],'total_impuestos'=>(float)$fac['fac_total_impuestos'],'valor_venta'=>(float)$fac['fac_valor_venta'],'sub_total'=>(float)$fac['fac_sub_total'],'importe_total'=>(float)$fac['fac_monto_imp_venta'],'total'=>(float)$fac['fac_monto_imp_venta'],'total_letras'=>(string)$fac['fac_total_letras']],
        ];
        $ch = curl_init(rtrim((string)$config['base_url'], '/') . '/api/comprobantes/generar-pdf');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: Bearer '.(string)$config['token']],CURLOPT_TIMEOUT=>(int)($config['timeout']??60)]);
        $this->aplicarTlsCentral($ch);
        $raw = curl_exec($ch); $error = curl_error($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $json = is_string($raw) ? trim($raw) : '';
        if ($json !== '' && $json[0] !== '{') {
            $inicio = strpos($json, '{'); $fin = strrpos($json, '}');
            if ($inicio !== false && $fin !== false) $json = substr($json, $inicio, $fin - $inicio + 1);
        }
        $respuesta = $json !== '' ? json_decode($json, true) : null;
        if ($http !== 200 || !is_array($respuesta) || empty($respuesta['pdf_base64'])) return ['success'=>false,'mensaje'=>$respuesta['mensaje']??$respuesta['error']??($error?:('No se pudo reconstruir el ticket (HTTP '.$http.', respuesta '.(is_array($respuesta)?implode(',',array_keys($respuesta)):'inválida').')'))];
        $contenido = base64_decode((string)$respuesta['pdf_base64'], true);
        if ($contenido === false) return ['success'=>false,'mensaje'=>'La central devolvió un ticket inválido'];
        return ['success'=>true,'http'=>200,'contenido'=>$contenido,'mime'=>'application/pdf','nombre'=>$fac['fac_serie'].'-'.str_pad((string)$fac['fac_correlativo'],8,'0',STR_PAD_LEFT).'.pdf'];
    }

    /** Guarda la respuesta completa y programa reintentos progresivos sin duplicar el CPE. */
    private function guardarRespuesta(PDO $cn, int $facId, array $api, bool $esReintento): void
    {
        $aceptado = $this->respuestaAceptada($api);
        $estado = $aceptado ? 'aceptado' : (string)($api['estado'] ?? 'error');
        if ($estado === '') $estado = 'error';
        $mensaje = (string)($api['descripcion'] ?? $api['mensaje_error'] ?? $api['mensaje'] ?? $api['error'] ?? ($aceptado ? 'Comprobante aceptado por SUNAT' : 'No se obtuvo confirmación de SUNAT'));
        $stmt = $cn->prepare('SELECT fac_reintentos,fac_fecha_emision FROM facturacion WHERE fac_id=:id');
        $stmt->execute([':id'=>$facId]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $reintentos = (int)($actual['fac_reintentos'] ?? 0) + ($esReintento ? 1 : 0);
        $esperas = [1,5,15,30,60];
        $minutos = $esperas[min(max($reintentos,1)-1,count($esperas)-1)];
        $limite = strtotime((string)($actual['fac_fecha_emision'] ?? '').' +3 days');
        $proximo = (!$aceptado && time() < $limite) ? date('Y-m-d H:i:s',time()+$minutos*60) : null;
        $archivos = is_array($api['archivos'] ?? null) ? $api['archivos'] : [];
        $urlPdf = (string)($archivos['pdf_ticket'] ?? $archivos['pdf_a4'] ?? '');
        $cn->prepare('UPDATE facturacion SET fac_respuesta_estado=:estado,fac_respuesta_mensaje=:mensaje,fac_respuesta_json=:json,fac_respuesta_fecha=NOW(),fac_reintentos=:reintentos,fac_ultimo_reintento=:ultimo,fac_proximo_reintento=:proximo,fac_url_pdf=:pdf,fac_url_xml=:xml,fac_url_cdr=:cdr,fac_bloqueado_hasta=NULL,fac_fecha_aceptacion=:aceptacion,fac_pdf_disponible=:pdf_ok,fac_xml_disponible=:xml_ok,fac_cdr_disponible=:cdr_ok WHERE fac_id=:id')
            ->execute([':estado'=>$estado,':mensaje'=>$mensaje,':json'=>json_encode($api,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':reintentos'=>$reintentos,':ultimo'=>$esReintento?date('Y-m-d H:i:s'):null,':proximo'=>$proximo,':pdf'=>$urlPdf?:null,':xml'=>($archivos['xml']??null)?:null,':cdr'=>($archivos['cdr']??null)?:null,':aceptacion'=>$aceptado?date('Y-m-d H:i:s'):null,':pdf_ok'=>$urlPdf!==''?1:0,':xml_ok'=>!empty($archivos['xml'])?1:0,':cdr_ok'=>!empty($archivos['cdr'])?1:0,':id'=>$facId]);
        $fac = $cn->prepare('SELECT ven_id FROM facturacion WHERE fac_id=:id');
        $fac->execute([':id'=>$facId]);
        $venId = (int)$fac->fetchColumn();
        if ($venId > 0) {
            $estadoCola = $aceptado ? 'aceptado' : (in_array($estado, ['rechazado','anulado_sunat','no_autorizado_sunat'], true) ? 'rechazado' : 'enviado');
            $cn->prepare('UPDATE facturacion_cola SET fcq_estado=:estado,fcq_proxima_ejecucion=:proximo,fcq_ultimo_error=:error,fcq_bloqueado_hasta=NULL,fcq_fecha_aceptacion=:aceptacion,fcq_pdf_disponible=:pdf_ok,fcq_xml_disponible=:xml_ok,fcq_cdr_disponible=:cdr_ok,fcq_fecha_actualizacion=NOW() WHERE ven_id=:ven')
                ->execute([':estado'=>$estadoCola,':proximo'=>$aceptado?null:$proximo,':error'=>$aceptado?null:$mensaje,':aceptacion'=>$aceptado?date('Y-m-d H:i:s'):null,':pdf_ok'=>$urlPdf!==''?1:0,':xml_ok'=>!empty($archivos['xml'])?1:0,':cdr_ok'=>!empty($archivos['cdr'])?1:0,':ven'=>$venId]);
        }
    }

    public function obtener_trazabilidad_venta(): array
    {
        $venId = (int)($_REQUEST['ven_id'] ?? 0);
        $cn = $this->cnx->con();
        $stmt = $cn->prepare("SELECT f.fac_id,f.fac_id_externo,f.fac_serie,f.fac_correlativo,f.fac_estado_sunat,f.fac_respuesta_estado,f.fac_respuesta_mensaje,f.fac_respuesta_json,f.fac_respuesta_fecha,f.fac_reintentos,f.fac_ultimo_reintento,f.fac_proximo_reintento,f.fac_fecha_emision,DATE_ADD(f.fac_fecha_emision,INTERVAL 3 DAY) plazo_limite,CASE WHEN NOW()>=DATE_ADD(COALESCE(f.fac_fecha_emision,NOW()),INTERVAL 3 DAY) THEN 1 ELSE 0 END plazo_vencido FROM venta v JOIN facturacion f ON f.fac_id=v.fac_id WHERE v.ven_id=:id LIMIT 1");
        $stmt->execute([':id'=>$venId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) return ['estado'=>'error','mensaje'=>'La venta no tiene un comprobante electrónico asociado'];
        if (!empty($doc['fac_id_externo'])) {
            $api = $this->consultarEstadoCentral($this->configuracionCentral(), (string)$doc['fac_id_externo']);
            if (!empty($api['id'])) {
                $aceptado = $this->respuestaAceptada($api);
                $cn->prepare('UPDATE facturacion SET fac_central_id=:central,fac_estado_sunat=:sunat,fac_estado=:estado WHERE fac_id=:id')
                    ->execute([':central'=>(int)$api['id'],':sunat'=>(string)($api['codigo_sunat']??''),':estado'=>$aceptado?2:0,':id'=>$doc['fac_id']]);
                $this->guardarRespuesta($cn, (int)$doc['fac_id'], $api, false);
                $stmt->execute([':id'=>$venId]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        $doc['documento']=$doc['fac_serie'].'-'.str_pad((string)$doc['fac_correlativo'],8,'0',STR_PAD_LEFT);
        return ['estado'=>'ok','documento'=>$doc];
    }

    /** Consulta el servicio central y sincroniza el POS si SUNAT confirma el documento. */
    public function verificar_sunat_venta(): array
    {
        $venId=(int)($_REQUEST['ven_id']??0);
        $cn=$this->cnx->con();
        $stmt=$cn->prepare("SELECT v.ven_id,v.fac_id,f.* FROM venta v JOIN facturacion f ON f.fac_id=v.fac_id WHERE v.ven_id=:id LIMIT 1");
        $stmt->execute([':id'=>$venId]);
        $fac=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$fac) {
            // Recuperar únicamente lo que ya exista en la central usando el id
            // idempotente de la venta. registrar_desde_venta no enviará a SUNAT
            // cuando solo_consultar está activo.
            $anterior=$_REQUEST;
            $_REQUEST['ven_id']=$venId;
            $_REQUEST['solo_consultar']=1;
            try{$recuperado=$this->registrar_desde_venta();}finally{$_REQUEST=$anterior;}
            if(empty($recuperado['fac_id'])) {
                return ['estado'=>'error','mensaje'=>$recuperado['mensaje']??'No se encontró un comprobante central para esta venta'];
            }
            $stmt->execute([':id'=>$venId]);
            $fac=$stmt->fetch(PDO::FETCH_ASSOC);
        }
        if(!$fac) return ['estado'=>'error','mensaje'=>'No se pudo vincular el comprobante central con la venta'];
        $config=$this->configuracionCentral();
        $respuesta=$this->llamarEndpointCentral($config,'/api/verificar-sunat',[
            'ruc_emisor'=>(string)($config['ruc']??''),'tipo_doc'=>(string)$fac['fac_tipo_doc'],
            'serie'=>(string)$fac['fac_serie'],'correlativo'=>(string)(int)$fac['fac_correlativo'],
            'fecha_emision'=>substr((string)$fac['fac_fecha_emision'],0,10),'monto'=>number_format((float)$fac['fac_monto_imp_venta'],2,'.',''),
            'recuperar_cdr'=>true
        ]);
        $existe=!empty($respuesta['success'])&&!empty($respuesta['existe_sunat']);
        $codigo=(string)($respuesta['estado_cp']??'');
        $estado=$existe?'verificado_sunat_sin_cdr':(!empty($respuesta['success'])?'no_existe_sunat':'error_consulta');
        if($codigo==='2')$estado='anulado_sunat'; elseif($codigo==='4')$estado='no_autorizado_sunat';
        $mensaje=(string)($respuesta['descripcion']??$respuesta['mensaje']??($existe?'El comprobante existe en SUNAT':'SUNAT no confirmó el comprobante'));
        $cdrRecuperado=$existe&&!empty($respuesta['cdr_disponible']);
        if($cdrRecuperado)$estado='aceptado';

        // La central guarda y descomprime el CDR recuperado. Consultar luego su
        // registro permite sincronizar el id y las rutas sin almacenar Base64 localmente.
        $estadoCentral=[];
        if($existe&&!empty($fac['fac_id_externo'])) {
            $estadoCentral=$this->consultarEstadoCentral($config,(string)$fac['fac_id_externo']);
        }
        $archivos=is_array($estadoCentral['archivos']??null)?$estadoCentral['archivos']:[];
        $auditoria=$respuesta;
        unset($auditoria['cdr_xml_base64']);
        if(isset($auditoria['cdr'])&&is_array($auditoria['cdr']))unset($auditoria['cdr']['xml_base64']);
        $proximo=(!$existe&&!empty($respuesta['success'])&&time()<strtotime((string)$fac['fac_fecha_emision'].' +3 days'))
            ? date('Y-m-d H:i:s',time()+300) : null;
        $cn->prepare('UPDATE facturacion SET fac_central_id=COALESCE(:central,fac_central_id),fac_estado_sunat=:codigo,fac_estado=:fac_estado,fac_respuesta_estado=:estado,fac_respuesta_mensaje=:mensaje,fac_respuesta_json=:json,fac_respuesta_fecha=NOW(),fac_proximo_reintento=:proximo,fac_url_pdf=COALESCE(:pdf,fac_url_pdf),fac_url_xml=COALESCE(:xml,fac_url_xml),fac_url_cdr=COALESCE(:cdr,fac_url_cdr) WHERE fac_id=:id')->execute([
            ':central'=>!empty($estadoCentral['id'])?(int)$estadoCentral['id']:null,
            ':codigo'=>$existe?'0':$fac['fac_estado_sunat'],':fac_estado'=>$existe?2:$fac['fac_estado'],':estado'=>$estado,':mensaje'=>$mensaje,
            ':json'=>json_encode($auditoria,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':proximo'=>$proximo,
            ':pdf'=>($archivos['pdf_ticket']??$archivos['pdf_a4']??null)?:null,':xml'=>($archivos['xml']??null)?:null,
            ':cdr'=>($archivos['cdr']??null)?:null,':id'=>$fac['fac_id']]);
        return ['estado'=>!empty($respuesta['success'])?'ok':'error','mensaje'=>$mensaje,'actualizado'=>$existe,
            'cdr_recuperado'=>$cdrRecuperado,'cdr_nombre'=>$respuesta['cdr_nombre']??($respuesta['cdr']['nombre']??null),
            'advertencia_cdr'=>$respuesta['advertencia_cdr']??null,'pdf_generado'=>!empty($respuesta['pdf_generado']),
            'advertencia_pdf'=>$respuesta['advertencia_pdf']??null,'fac_id'=>(int)$fac['fac_id'],
            'comprobante'=>$fac['fac_serie'].'-'.str_pad((string)$fac['fac_correlativo'],8,'0',STR_PAD_LEFT),'verificacion'=>$auditoria];
    }

    public function procesar_reintento_automatico(): array
    {
        $cn=$this->cnx->con();
        $venId=0;
        try {
            $cn->beginTransaction();
            $stmt=$cn->query("SELECT fcq_id,ven_id FROM facturacion_cola
                WHERE fcq_estado IN ('pendiente','enviado')
                  AND (fcq_proxima_ejecucion IS NULL OR fcq_proxima_ejecucion<=NOW())
                  AND (fcq_bloqueado_hasta IS NULL OR fcq_bloqueado_hasta<NOW())
                ORDER BY COALESCE(fcq_proxima_ejecucion,fcq_fecha_creacion),fcq_id LIMIT 1 FOR UPDATE");
            $cola=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cola) { $cn->commit(); return ['estado'=>'ok','procesado'=>false]; }
            $venId=(int)$cola['ven_id'];
            $cn->prepare("UPDATE facturacion_cola SET fcq_estado='procesando',fcq_bloqueado_hasta=DATE_ADD(NOW(),INTERVAL 2 MINUTE),fcq_fecha_actualizacion=NOW() WHERE fcq_id=:id")
                ->execute([':id'=>$cola['fcq_id']]);
            $cn->commit();
        } catch (Throwable $e) {
            if ($cn->inTransaction()) $cn->rollBack();
            return ['estado'=>'error','procesado'=>false,'mensaje'=>$e->getMessage()];
        }
        $anterior=$_REQUEST;
        $_REQUEST=['ven_id'=>$venId,'reintentar'=>1];
        try {
            $res=$this->registrar_desde_venta();
            $res['procesado']=true;
            return $res;
        } finally { $_REQUEST=$anterior; }
    }

    private function llamarEndpointCentral(array $config,string $ruta,array $payload): array
    {
        $ch=curl_init(rtrim((string)$config['base_url'],'/').$ruta);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: Bearer '.(string)($config['token']??'')],CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>(int)($config['timeout']??60)]);
        $this->aplicarTlsCentral($ch);
        $raw=curl_exec($ch);$error=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $json=json_decode((string)$raw,true);
        if(!is_array($json))return ['success'=>false,'mensaje'=>$error?:'El servicio central devolvió una respuesta inválida','http_code'=>$http];
        $json['http_code']=$http;return $json;
    }

    private function consultarEstadoCentral(array $config, string $idExterno): array
    {
        if ($idExterno === '') return [];
        $query = http_build_query(['ruc_emisor'=>$config['ruc'],'id_externo'=>$idExterno]);
        return $this->llamarGetCentral($config, '/api/comprobantes/estado?' . $query);
    }

    private function serieNotaCentral(array $config, string $tipoNota, string $tipoReferencia): string
    {
        $serieConfigurada = strtoupper(trim((string)($config['series_notas'][$tipoNota][$tipoReferencia] ?? '')));
        if ($serieConfigurada !== '') return $serieConfigurada;

        $respuesta = $this->llamarGetCentral($config, '/api/empresa?' . http_build_query(['ruc'=>$config['ruc']]));
        $empresa = is_array($respuesta['empresa'] ?? null) ? $respuesta['empresa'] : [];
        $esFactura = $tipoReferencia === '01';
        if ($tipoNota === '07') {
            $campo = $esFactura ? 'serie_nota_credito_factura' : 'serie_nota_credito_boleta';
        } else {
            $campo = $esFactura ? 'serie_nota_debito_factura' : 'serie_nota_debito_boleta';
        }
        $serie = strtoupper(trim((string)($empresa[$campo] ?? '')));
        if ($serie === '') throw new RuntimeException('No está configurada la serie central para esta nota');
        return $serie;
    }

    private function llamarGetCentral(array $config, string $ruta): array
    {
        $ch = curl_init(rtrim((string)$config['base_url'], '/') . $ruta);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer '.(string)$config['token']],CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>(int)($config['timeout']??60)]);
        $this->aplicarTlsCentral($ch);
        $raw=curl_exec($ch);$error=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $json=json_decode((string)$raw,true);
        if(!is_array($json))return ['success'=>false,'mensaje'=>$error?:'El servicio central devolvió una respuesta inválida','http_code'=>$http];
        $json['http_code']=$http;return $json;
    }

    private function descargarUrlCentral(string $url, array $config): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return ['success'=>false];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>(int)($config['timeout']??60)]);
        $this->aplicarTlsCentral($ch);
        $contenido = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if (!is_string($contenido) || $http !== 200 || !str_starts_with($contenido, '%PDF-')) return ['success'=>false];
        return ['success'=>true,'http'=>200,'contenido'=>$contenido,'mime'=>$mime?:'application/pdf'];
    }

    private function respuestaAceptada(array $api): bool
    {
        $estado = strtolower(trim((string)($api['estado'] ?? '')));
        if ($estado !== '') {
            return $estado === 'aceptado';
        }
        return !empty($api['success']) && (string)($api['codigo_sunat'] ?? '') === '0';
    }

    private function aplicarTlsCentral($ch): void
    {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $caBundle = trim((string)conexion::config('FACTURACION_CA_BUNDLE', ''));
        if ($caBundle === '') return;
        if (!is_file($caBundle) || !is_readable($caBundle)) {
            throw new RuntimeException('FACTURACION_CA_BUNDLE no existe o no se puede leer');
        }
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    }
}
