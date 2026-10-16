<?php
require_once __DIR__.'/conexion.php';

final class local
{
    public function __construct(private ?PDO $db=null){$this->db??=(new conexion())->con();}
    private function q(string $sql,array $p=[]):PDOStatement{$s=$this->db->prepare($sql);$s->execute($p);return $s;}
    private function texto($v,int $max,string $campo,bool $requerido=false):?string{$v=trim((string)$v);if($requerido&&$v==='')throw new InvalidArgumentException("$campo es obligatorio.");if(mb_strlen($v)>$max)throw new InvalidArgumentException("$campo admite hasta $max caracteres.");return $v===''?null:$v;}
    public function listar():array{return $this->q("SELECT l.*,u.ubg_departamento,u.ubg_provincia,u.ubg_distrito FROM `local` l LEFT JOIN ubigeo u ON u.ubg_codigo=l.loc_ubigeo ORDER BY l.loc_estado,l.loc_nombre")->fetchAll(PDO::FETCH_ASSOC);}
    public function obtener(int $id):array{$s=$this->q("SELECT l.*,u.ubg_departamento,u.ubg_provincia,u.ubg_distrito FROM `local` l LEFT JOIN ubigeo u ON u.ubg_codigo=l.loc_ubigeo WHERE l.loc_id=?",[$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('El local no existe.');return $r;}
    public function departamentos():array{return $this->q('SELECT ubg_departamento nombre,MIN(LEFT(ubg_codigo,2)) codigo FROM ubigeo GROUP BY ubg_departamento ORDER BY ubg_departamento')->fetchAll(PDO::FETCH_ASSOC);}
    public function provincias(string $departamento):array{return $this->q('SELECT ubg_provincia nombre,MIN(LEFT(ubg_codigo,4)) codigo FROM ubigeo WHERE ubg_departamento=? GROUP BY ubg_provincia ORDER BY ubg_provincia',[$departamento])->fetchAll(PDO::FETCH_ASSOC);}
    public function distritos(string $departamento,string $provincia):array{return $this->q('SELECT ubg_codigo codigo,ubg_distrito nombre FROM ubigeo WHERE ubg_departamento=? AND ubg_provincia=? ORDER BY ubg_distrito',[$departamento,$provincia])->fetchAll(PDO::FETCH_ASSOC);}
    public function ubigeo(string $codigo):array{$r=$this->q('SELECT ubg_codigo codigo,ubg_departamento departamento,ubg_provincia provincia,ubg_distrito distrito FROM ubigeo WHERE ubg_codigo=?',[$codigo])->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('El ubigeo seleccionado no existe.');return $r;}
    public function guardar(int $usu,array $d):int{
        $id=(int)($d['loc_id']??0);$nombre=$this->texto($d['loc_nombre']??'',150,'El nombre',true);$direccion=$this->texto($d['loc_direccion']??'',250,'La dirección',true);$ubigeo=(string)$this->texto($d['loc_ubigeo']??'',6,'El ubigeo',true);if(!preg_match('/^\d{6}$/',$ubigeo)||!$this->q('SELECT ubg_codigo FROM ubigeo WHERE ubg_codigo=?',[$ubigeo])->fetchColumn())throw new InvalidArgumentException('Selecciona un ubigeo válido.');
        $telefono=$this->texto($d['loc_telefono']??'',30,'El teléfono');$email=$this->texto($d['loc_email']??'',150,'El correo');if($email!==null&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Ingresa un correo válido.');$estado=(int)($d['loc_estado']??0);if(!in_array($estado,[1,2],true))throw new InvalidArgumentException('Selecciona un estado válido.');
        $this->db->beginTransaction();try{
            if($id){if(!$this->q('SELECT loc_id FROM `local` WHERE loc_id=? FOR UPDATE',[$id])->fetchColumn())throw new InvalidArgumentException('El local no existe.');$this->q('UPDATE `local` SET loc_nombre=?,loc_direccion=?,loc_ubigeo=?,loc_telefono=?,loc_email=?,loc_estado=?,loc_usuario_modificacion=?,loc_fecha_modificacion=NOW() WHERE loc_id=?',[$nombre,$direccion,$ubigeo,$telefono,$email,$estado,$usu,$id]);}
            else{$this->q('INSERT INTO `local`(loc_nombre,loc_direccion,loc_ubigeo,loc_telefono,loc_email,loc_estado,loc_usuario_creacion) VALUES(?,?,?,?,?,?,?)',[$nombre,$direccion,$ubigeo,$telefono,$email,$estado,$usu]);$id=(int)$this->db->lastInsertId();}
            $this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
