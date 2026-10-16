<?php
require_once __DIR__.'/conexion.php';
final class tipo_movimiento
{
    public function __construct(private ?PDO $db=null){$this->db??=(new conexion())->con();}
    private function q(string $sql,array $p=[]):PDOStatement{$s=$this->db->prepare($sql);$s->execute($p);return $s;}
    private function datos(array $d):array{
        $tipo=strtoupper(trim((string)($d['tipo']??'')));$nombre=trim((string)($d['nombre']??''));$estado=(int)($d['estado']??1);
        if(!in_array($tipo,['INGRESO','EGRESO'],true))throw new InvalidArgumentException('Selecciona ingreso o egreso.');
        if(mb_strlen($nombre)<2||mb_strlen($nombre)>120)throw new InvalidArgumentException('La descripción debe tener entre 2 y 120 caracteres.');
        if(!in_array($estado,[0,1],true))throw new InvalidArgumentException('Selecciona un estado válido.');return [$tipo,$nombre,$estado];
    }
    public function listar(bool $activos=false,?string $tipo=null):array{$sql='SELECT tmc_id,tmc_tipo,tmc_nombre,tmc_estado FROM tipo_movimiento WHERE tmc_estado'.($activos?'=1':'<>2');$p=[];if($tipo!==null){$sql.=' AND tmc_tipo=?';$p[]=$tipo;}$sql.=' ORDER BY tmc_tipo,tmc_nombre';return $this->q($sql,$p)->fetchAll(PDO::FETCH_ASSOC);}
    public function obtener(int $id):array{$r=$this->q('SELECT tmc_id,tmc_tipo,tmc_nombre,tmc_estado FROM tipo_movimiento WHERE tmc_id=? AND tmc_estado<>2',[$id])->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('El tipo de movimiento no existe.');return $r;}
    public function guardar(int $usu,array $d):int{[$tipo,$nombre,$estado]=$this->datos($d);$id=(int)($d['id']??0);$dup=(int)$this->q('SELECT COUNT(*) FROM tipo_movimiento WHERE tmc_tipo=? AND UPPER(TRIM(tmc_nombre))=UPPER(TRIM(?)) AND tmc_estado<>2 AND tmc_id<>?',[$tipo,$nombre,$id])->fetchColumn();if($dup)throw new InvalidArgumentException('Ya existe este concepto para el tipo seleccionado.');if($id){$s=$this->q('UPDATE tipo_movimiento SET tmc_tipo=?,tmc_nombre=?,tmc_estado=?,tmc_usuario_modificacion=?,tmc_fecha_modificacion=NOW() WHERE tmc_id=? AND tmc_estado<>2',[$tipo,$nombre,$estado,$usu,$id]);if(!$s->rowCount())$this->obtener($id);return $id;}$this->q('INSERT INTO tipo_movimiento(tmc_tipo,tmc_nombre,tmc_estado,tmc_usuario_creacion) VALUES(?,?,?,?)',[$tipo,$nombre,$estado,$usu]);return (int)$this->db->lastInsertId();}
    public function eliminar(int $id,int $usu):void{if($this->q('SELECT (SELECT COUNT(*) FROM movimiento WHERE tmc_id=?)+(SELECT COUNT(*) FROM movimiento_detalle WHERE tmc_id=?)',[$id,$id])->fetchColumn())throw new InvalidArgumentException('Este tipo tiene movimientos relacionados. Puedes deshabilitarlo.');$s=$this->q('DELETE FROM tipo_movimiento WHERE tmc_id=?',[$id]);if(!$s->rowCount())throw new InvalidArgumentException('El tipo de movimiento no está disponible.');}
}
