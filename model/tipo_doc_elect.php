<?php
require_once __DIR__ . '/comprobantes_pos.php';
// Compatibilidad de lectura para el selector POS; sin series ni correlativos locales.
class tipo_doc_elect {
    public function listar_para_pos(){ return comprobantes_pos::listar(); }
}
