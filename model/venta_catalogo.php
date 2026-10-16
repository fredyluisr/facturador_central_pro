<?php
require_once __DIR__ . '/venta.php';
require_once __DIR__ . '/costos_fifo.php';

class venta_catalogo extends venta
{
    private PDO $db;
    public function __construct(?PDO $db = null)
    {
        parent::__construct();
        $this->db = $db ?? (new conexion())->con();
    }
    public function listar_productos($loc_id)
    {
        $s = $this->db->prepare("SELECT v.pva_id,v.pro_id,v.pva_sku,v.pva_codigo_barras,p.pro_imagen,
            v.pva_precio AS pro_prec_venta,p.pro_nombre AS producto_nombre,
            CONCAT(p.pro_nombre,COALESCE(CONCAT(' · ',attrs.descripcion),'')) AS pro_nombre,
            c.cat_nombre,u.und_id,u.und_abrev,u.und_decimales,
            COALESCE(st.cantidad,0) AS pro_stock
            FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id
            JOIN unidad_medida u ON u.und_id=p.und_med
            LEFT JOIN categoria c ON c.cat_id=p.cat_id
            LEFT JOIN stock_variante st ON st.pva_id=v.pva_id AND st.loc_id=?
            LEFT JOIN (SELECT vv.pva_id,GROUP_CONCAT(CONCAT(a.atr_nombre,': ',av.ava_nombre)
                ORDER BY a.atr_id SEPARATOR ' · ') AS descripcion
                FROM producto_variante_valor vv JOIN atributo a ON a.atr_id=vv.atr_id
                JOIN atributo_valor av ON av.ava_id=vv.ava_id GROUP BY vv.pva_id) attrs ON attrs.pva_id=v.pva_id
            WHERE p.pro_estado=1 AND v.pva_estado=1 AND u.und_estado=1
            AND (c.cat_id IS NULL OR c.cat_estado=1)
            ORDER BY p.pro_nombre,v.pva_sku");
        $s->execute([(int)$loc_id]);
        $rows=$s->fetchAll(PDO::FETCH_ASSOC);
        $attrs=$this->db->query('SELECT vv.pva_id,a.atr_id,a.atr_nombre,av.ava_id,av.ava_nombre,av.ava_color
            FROM producto_variante_valor vv JOIN atributo a ON a.atr_id=vv.atr_id
            JOIN atributo_valor av ON av.ava_id=vv.ava_id ORDER BY a.atr_id,av.ava_nombre')->fetchAll(PDO::FETCH_ASSOC);
        $map=[]; foreach ($attrs as $attr) $map[$attr['pva_id']][]=$attr;
        foreach ($rows as &$row) $row['atributos']=$map[$row['pva_id']]??[];
        return $rows;
    }
    // El identificador recibido es el de la variante, no el del producto padre.
    public function obtener_stock_producto($pva_id, $loc_id)
    {
        foreach ($this->listar_productos($loc_id) as $v) {
            if ((int)$v['pva_id'] === (int)$pva_id) return $v;
        }
        return false;
    }
    public function registrar($loc_id, $usu_id, $ent_id, $tpa_id, $num_operacion, $comentario, $detalle, $tde_id = 0, $cba_id = null, $ven_tipo = 2, $monto_pago = null)
    {
        try {
            if ($loc_id <= 0 || $usu_id <= 0 || !$detalle || count($detalle)>300 || (int)$ven_tipo!==2) throw new InvalidArgumentException('Seleccione productos del catálogo.');
            foreach (['tipo_pago'=>['tpa_id',$tpa_id,'tpa_estado'], 'cuenta_bancaria'=>['cba_id',$cba_id,'cba_estado']] as $table=>$cfg) {
                $s=$this->db->prepare("SELECT COUNT(*) FROM $table WHERE {$cfg[0]}=? AND {$cfg[2]}=1");
                $s->execute([$cfg[1]]);
                if (!$s->fetchColumn()) throw new InvalidArgumentException('Seleccione un método de pago y una cuenta habilitados.');
            }
            if ($tde_id) {
                $code=comprobantes_pos::codigo((int)$tde_id);
                if (!$code) throw new InvalidArgumentException('Comprobante no válido.');
                if ($code==='01') {
                    $s=$this->db->prepare('SELECT COUNT(*) FROM entidad WHERE ent_id=? AND ent_cliente=1 AND ent_tipo_doc=6'); $s->execute([$ent_id]);
                    if (!$s->fetchColumn()) throw new InvalidArgumentException('La factura requiere un cliente con RUC.');
                }
            }
            $this->db->beginTransaction();
            $sinStock=(int)$this->db->query('SELECT csi_vender_sin_stock FROM configuracion_sistema WHERE csi_id=1')->fetchColumn()===1;
            $rows=[]; $total=0; $seen=[];
            usort($detalle,fn($a,$b)=>(int)($a['pva_id']??0)<=>(int)($b['pva_id']??0));
            foreach ($detalle as $item) {
                $id=(int)($item['pva_id']??0);
                $v=$this->obtener_stock_producto($id,$loc_id);
                $qty=(float)($item['cantidad']??0); $price=(float)($item['precio_unit']??-1);
                if (!$v || isset($seen[$id]) || !is_finite($qty) || !is_finite($price) || $qty<=0 || $price<0 || $price>9999999 || $qty>9999999) throw new InvalidArgumentException('Producto, cantidad o precio no válido.');
                $seen[$id]=true;
                if (abs($qty-round($qty,(int)$v['und_decimales']))>0.0000001) throw new InvalidArgumentException('La cantidad de '.$v['pro_nombre'].' no respeta los decimales de su unidad.');
                $price=round($price,2);
                $s=$this->db->prepare('INSERT IGNORE INTO stock_variante(pva_id,loc_id,cantidad) VALUES(?,?,0)'); $s->execute([$id,$loc_id]);
                $s=$this->db->prepare('SELECT cantidad FROM stock_variante WHERE pva_id=? AND loc_id=? FOR UPDATE'); $s->execute([$id,$loc_id]);
                $available=$s->fetchColumn();
                if (!$sinStock && ($available===false || (float)$available<$qty)) throw new InvalidArgumentException('Stock insuficiente para: '.$v['pro_nombre']);
                $line=round($qty*$price,2); $total+=$line;
                $rows[]=[$v,$qty,$price,$line];
            }
            if ($total>99999999.99) throw new InvalidArgumentException('El total supera el límite permitido.');
            if (!is_numeric($monto_pago) || !is_finite((float)$monto_pago) || (float)$monto_pago<0 || abs((float)$monto_pago-round((float)$monto_pago,2))>0.000001) throw new InvalidArgumentException('Ingrese un monto de pago válido con hasta dos decimales.');
            $pagado=round((float)$monto_pago,2); $total=round($total,2);
            if ($pagado>$total) throw new InvalidArgumentException('El pago no puede superar el total de la venta.');
            $base=round($total/1.18,2);
            $serieVenta=null; $numeroVenta=null;
            if (!$tde_id) {
                $s=$this->db->query('SELECT csi_nota_pedido_serie,csi_nota_pedido_siguiente FROM configuracion_sistema WHERE csi_id=1 FOR UPDATE');
                $nota=$s->fetch(PDO::FETCH_ASSOC)?:[];
                $serieVenta=strtoupper(trim((string)($nota['csi_nota_pedido_serie']??'NV01')));
                $numeroVenta=max(1,(int)($nota['csi_nota_pedido_siguiente']??1));
                if (!preg_match('/^[A-Z0-9]{1,4}$/',$serieVenta)) throw new RuntimeException('La serie interna de nota de venta no es válida.');
                $s=$this->db->prepare('UPDATE configuracion_sistema SET csi_nota_pedido_siguiente=?,csi_fecha_modificacion=NOW() WHERE csi_id=1');
                $s->execute([$numeroVenta+1]);
            }
            $s=$this->db->prepare('INSERT INTO venta(loc_id,ven_tipo,usu_id,ent_id,tde_id,ven_serie,ven_numero,ven_nota_serie,ven_nota_numero,ven_total,ven_subtotal,ven_igv,ven_tpa_id,ven_cba_id,ven_num_operacion,ven_comentario,ven_ip) VALUES(?,2,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $s->execute([$loc_id,$usu_id,$ent_id?:null,$tde_id?:null,$serieVenta,$numeroVenta,$tde_id?null:$serieVenta,$tde_id?null:$numeroVenta,$total,$base,round($total-$base,2),$tpa_id,$cba_id,$num_operacion?:null,$comentario?:null,$_SERVER['REMOTE_ADDR']??null]);
            $vid=(int)$this->db->lastInsertId();
            $s=$this->db->prepare('UPDATE venta SET ven_pagado=? WHERE ven_id=?'); $s->execute([$pagado,$vid]);
            if ($pagado>0) {
                $s=$this->db->prepare('INSERT INTO venta_pago(ven_id,tpa_id,cba_id,monto,num_operacion,usuario) VALUES(?,?,?,?,?,?)');
                $s->execute([$vid,$tpa_id,$cba_id,$pagado,$num_operacion?:null,$usu_id]);
            }
            $fifo=new costos_fifo($this->db);foreach ($rows as [$v,$qty,$price,$line]) {
                $s=$this->db->prepare('INSERT INTO venta_detalle_producto(ven_id,pro_id,pva_id,und_med,vdp_cantidad,vdp_precio_unit,vdp_subtotal,vdp_nombre,vdp_sku,unidad_abrev_historica) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$vid,$v['pro_id'],$v['pva_id'],$v['und_id'],$qty,$price,$line,$v['pro_nombre'],$v['pva_sku'],$v['und_abrev']]);
                $fifo->consumir((int)$loc_id,(int)$v['pva_id'],$qty,'venta',(int)$this->db->lastInsertId());
                $s=$this->db->prepare('UPDATE stock_variante SET cantidad=cantidad-? WHERE pva_id=? AND loc_id=?'); $s->execute([$qty,$v['pva_id'],$loc_id]);
                $s=$this->db->prepare('INSERT INTO movimiento_stock_variante(pva_id,loc_id,cantidad,tipo,ven_id,usuario) VALUES(?,?,?,2,?,?)'); $s->execute([$v['pva_id'],$loc_id,$qty,$vid,$usu_id]);
            }
            $s=$this->db->prepare('SELECT COUNT(*) FROM cuenta_bancaria WHERE cba_id=? AND tpa_id=? AND cba_estado=1');
            $s->execute([$cba_id,$tpa_id]);
            if (!$s->fetchColumn()) throw new InvalidArgumentException('La cuenta seleccionada no pertenece a la forma de pago.');
            if ($tde_id && (int)round($pagado*100)===(int)round($total*100)) {
                $instancia=(string)$this->db->query('SELECT csi_instancia_uuid FROM configuracion_sistema WHERE csi_id=1')->fetchColumn();
                if (!preg_match('/^[a-f0-9]{32}$/',$instancia)) throw new RuntimeException('La instalación de facturación no tiene una identidad válida.');
                $ruc=preg_replace('/\D/','',(string)conexion::config('FACTURACION_API_RUC'));
                $externo='ifact-'.$ruc.'-'.$instancia.'-venta-'.$vid;
                $s=$this->db->prepare("INSERT INTO facturacion_cola(ven_id,fcq_id_externo,fcq_estado,fcq_proxima_ejecucion) VALUES(?,?,'pendiente',NOW())");
                $s->execute([$vid,$externo]);
            }
            $this->db->commit();
            return ['estado'=>'ok','ven_id'=>$vid,'ven_serie'=>$serieVenta,'ven_numero'=>$numeroVenta,'ven_pagado'=>$pagado,'ven_saldo'=>round($total-$pagado,2),'mensaje'=>'Venta registrada correctamente'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if (!($e instanceof InvalidArgumentException)) error_log('venta_catalogo: '.$e->getMessage());
            return ['estado'=>'error','mensaje'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo registrar la venta.'];
        }
    }
    public function informacionPagos(int $venId, int $locId): array
    {
        $s=$this->db->prepare('SELECT ven_id,ven_total,ven_pagado,ven_total-ven_pagado AS ven_saldo,ven_estado,tde_id,fac_id FROM venta WHERE ven_id=? AND loc_id=?');
        $s->execute([$venId,$locId]); $v=$s->fetch(PDO::FETCH_ASSOC);
        if (!$v) throw new InvalidArgumentException('Venta no encontrada.');
        $s=$this->db->prepare("SELECT p.vpa_id,p.monto,p.num_operacion,p.fecha,t.tpa_nombre,
            CONCAT(c.cba_banco,' · ',c.cba_moneda,' · ',c.cba_numero) AS cuenta
            FROM venta_pago p LEFT JOIN tipo_pago t ON t.tpa_id=p.tpa_id
            LEFT JOIN cuenta_bancaria c ON c.cba_id=p.cba_id WHERE p.ven_id=? ORDER BY p.vpa_id");
        $s->execute([$venId]);
        $pagos=$s->fetchAll(PDO::FETCH_ASSOC);
        // Pagos anteriores a la tabla: conservar el importe inicial sin escribir
        // movimientos ficticios ni cambiar el saldo persistido.
        $inicial=(int)round((float)$v['ven_pagado']*100)-array_sum(array_map(static fn($p)=>(int)round((float)$p['monto']*100),$pagos));
        if ($inicial>0) {
            $s=$this->db->prepare("SELECT v.ven_fecha AS fecha,v.ven_num_operacion AS num_operacion,t.tpa_nombre,
                CONCAT(c.cba_banco,' · ',c.cba_moneda,' · ',c.cba_numero) AS cuenta
                FROM venta v LEFT JOIN tipo_pago t ON t.tpa_id=v.ven_tpa_id
                LEFT JOIN cuenta_bancaria c ON c.cba_id=v.ven_cba_id WHERE v.ven_id=? AND v.loc_id=?");
            $s->execute([$venId,$locId]);
            array_unshift($pagos,array_merge($s->fetch(PDO::FETCH_ASSOC),['vpa_id'=>null,'monto'=>$inicial/100,'inicial'=>true]));
        }
        return ['estado'=>'ok','venta'=>$v,'pagos'=>$pagos];
    }
    public function asignarComprobante(int $venId,int $locId,int $entId,int $tdeId): array
    {
        try {
            $codigo=comprobantes_pos::codigo($tdeId);
            if (!$codigo || !in_array($codigo,['01','03'],true)) throw new InvalidArgumentException('Seleccione una boleta o factura válida.');
            $this->db->beginTransaction();
            $s=$this->db->prepare('SELECT ven_id,ven_total,ven_pagado,ven_estado,tde_id,fac_id,ven_serie,ven_numero,ven_nota_serie,ven_nota_numero FROM venta WHERE ven_id=? AND loc_id=? FOR UPDATE');
            $s->execute([$venId,$locId]); $v=$s->fetch(PDO::FETCH_ASSOC);
            if (!$v || !(int)$v['ven_estado']) throw new InvalidArgumentException('Venta no encontrada o anulada.');
            if (!empty($v['fac_id'])) throw new InvalidArgumentException('La venta ya tiene un comprobante electrónico asignado.');
            if ((int)round((float)$v['ven_pagado']*100)!==(int)round((float)$v['ven_total']*100)) throw new InvalidArgumentException('Complete el pago antes de emitir el comprobante.');
            if ($codigo==='01') {
                $s=$this->db->prepare('SELECT COUNT(*) FROM entidad WHERE ent_id=? AND ent_cliente=1 AND ent_tipo_doc=6 AND ent_estado=1');
                $s->execute([$entId]);
                if (!$s->fetchColumn()) throw new InvalidArgumentException('La factura requiere un cliente habilitado con RUC.');
            }
            if (empty($v['ven_nota_serie']) && !empty($v['ven_serie']) && !empty($v['ven_numero'])) {
                $s=$this->db->prepare('UPDATE venta SET ven_nota_serie=ven_serie,ven_nota_numero=ven_numero WHERE ven_id=?');
                $s->execute([$venId]);
            }
            $s=$this->db->prepare('UPDATE venta SET ent_id=?,tde_id=?,ven_serie=NULL,ven_numero=NULL WHERE ven_id=?');
            $s->execute([$entId?:null,$tdeId,$venId]);
            $instancia=(string)$this->db->query('SELECT csi_instancia_uuid FROM configuracion_sistema WHERE csi_id=1')->fetchColumn();
            if (!preg_match('/^[a-f0-9]{32}$/',$instancia)) throw new RuntimeException('La instalación de facturación no tiene una identidad válida.');
            $ruc=preg_replace('/\D/','',(string)conexion::config('FACTURACION_API_RUC'));
            $externo='ifact-'.$ruc.'-'.$instancia.'-venta-'.$venId;
            $s=$this->db->prepare("INSERT INTO facturacion_cola(ven_id,fcq_id_externo,fcq_estado,fcq_proxima_ejecucion) VALUES(?,?,'pendiente',NOW()) ON DUPLICATE KEY UPDATE fcq_id_externo=VALUES(fcq_id_externo),fcq_estado='pendiente',fcq_ultimo_error=NULL,fcq_proxima_ejecucion=NOW(),fcq_fecha_actualizacion=NOW()");
            $s->execute([$venId,$externo]);
            $this->db->commit();
            return ['estado'=>'ok','ven_id'=>$venId,'tde_id'=>$tdeId,'mensaje'=>'La venta quedó preparada para emitir el comprobante.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if (!$e instanceof InvalidArgumentException) error_log('asignarComprobante: '.$e->getMessage());
            return ['estado'=>'error','mensaje'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo preparar el comprobante.'];
        }
    }
    public function registrarPago(int $venId,int $locId,int $usuId,int $tipo,int $cuenta,$monto,string $operacion,string $clave): array
    {
        try {
            if (!is_numeric($monto) || !is_finite((float)$monto) || (float)$monto<=0 || abs((float)$monto-round((float)$monto,2))>0.000001) throw new InvalidArgumentException('Ingrese un pago mayor a cero, con hasta dos decimales.');
            if (!preg_match('/^[a-f0-9]{32}$/',$clave) || mb_strlen($operacion)>100) throw new InvalidArgumentException('Datos del pago no válidos.');
            $this->db->beginTransaction();
            $s=$this->db->prepare('SELECT ven_total,ven_pagado,ven_estado FROM venta WHERE ven_id=? AND loc_id=? FOR UPDATE'); $s->execute([$venId,$locId]); $v=$s->fetch();
            if (!$v || !$v['ven_estado']) throw new InvalidArgumentException('Venta no encontrada o anulada.');
            $s=$this->db->prepare('SELECT ven_id FROM venta_pago WHERE clave=?'); $s->execute([$clave]); $previous=$s->fetchColumn();
            if ($previous!==false) {
                if ((int)$previous!==$venId) throw new InvalidArgumentException('Identificador de pago no válido.');
                $this->db->commit(); return $this->informacionPagos($venId,$locId);
            }
            $cents=(int)round((float)$monto*100); $saldo=(int)round((float)$v['ven_total']*100)-(int)round((float)$v['ven_pagado']*100);
            if ($cents>$saldo) throw new InvalidArgumentException('El pago no puede superar el saldo pendiente.');
            foreach (['tipo_pago'=>['tpa_id',$tipo,'tpa_estado'],'cuenta_bancaria'=>['cba_id',$cuenta,'cba_estado']] as $table=>$cfg) {
                $s=$this->db->prepare("SELECT COUNT(*) FROM $table WHERE {$cfg[0]}=? AND {$cfg[2]}=1"); $s->execute([$cfg[1]]);
                if (!$s->fetchColumn()) throw new InvalidArgumentException('Seleccione un método y una cuenta habilitados.');
            }
            $s=$this->db->prepare('INSERT INTO venta_pago(ven_id,tpa_id,cba_id,monto,num_operacion,usuario,clave) VALUES(?,?,?,?,?,?,?)'); $s->execute([$venId,$tipo,$cuenta,$cents/100,$operacion?:null,$usuId,$clave]);
            $s=$this->db->prepare('UPDATE venta SET ven_pagado=ven_pagado+? WHERE ven_id=?'); $s->execute([$cents/100,$venId]);
            $this->db->commit(); return $this->informacionPagos($venId,$locId);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if (!$e instanceof InvalidArgumentException) error_log('registrarPago: '.$e->getMessage());
            return ['estado'=>'error','mensaje'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo registrar el pago.'];
        }
    }
    public function anular($ven_id, $usu_id)
    {
        try {
            $this->db->beginTransaction();
            $s=$this->db->prepare('SELECT v.*,f.fac_estado,f.fac_estado_sunat FROM venta v LEFT JOIN facturacion f ON f.fac_id=v.fac_id WHERE v.ven_id=? FOR UPDATE'); $s->execute([$ven_id]); $v=$s->fetch();
            if (!$v || !$v['ven_estado']) throw new InvalidArgumentException('Venta no encontrada o ya anulada.');
            if (!empty($v['fac_id'])) {
                $aceptadoSunat=(string)($v['fac_estado_sunat']??'')==='0'||(int)($v['fac_estado']??0)===2;
                throw new InvalidArgumentException($aceptadoSunat
                    ? 'Esta venta tiene un comprobante electrónico asociado. Para anular la operación debe emitir una nota de crédito.'
                    : 'Esta venta tiene un comprobante electrónico asociado. Verifique primero su estado en SUNAT.');
            }
            $s=$this->db->prepare('SELECT COUNT(*) FROM cuenta_bancaria WHERE cba_id=? AND tpa_id=? AND cba_estado=1'); $s->execute([$cuenta,$tipo]);
            if (!$s->fetchColumn()) throw new InvalidArgumentException('La cuenta seleccionada no pertenece a la forma de pago.');
            $s=$this->db->prepare('SELECT * FROM venta_detalle_producto WHERE ven_id=? ORDER BY pva_id'); $s->execute([$ven_id]);
            $fifo=new costos_fifo($this->db);foreach ($s->fetchAll() as $d) {
                $fifo->revertirVenta((int)$d['vdp_id']);
                $q=$this->db->prepare('UPDATE stock_variante SET cantidad=cantidad+? WHERE pva_id=? AND loc_id=?'); $q->execute([$d['vdp_cantidad'],$d['pva_id'],$v['loc_id']]);
                $q=$this->db->prepare('INSERT INTO movimiento_stock_variante(pva_id,loc_id,cantidad,tipo,ven_id,usuario) VALUES(?,?,?,3,?,?)'); $q->execute([$d['pva_id'],$v['loc_id'],$d['vdp_cantidad'],$ven_id,$usu_id]);
            }
            $s=$this->db->prepare('UPDATE venta SET ven_estado=0,ven_usu_anulado=?,ven_fec_anulado=NOW() WHERE ven_id=?'); $s->execute([$usu_id,$ven_id]);
            $this->db->commit(); return ['estado'=>'ok','mensaje'=>'Venta anulada correctamente'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['estado'=>'error','mensaje'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo anular la venta.'];
        }
    }
}
