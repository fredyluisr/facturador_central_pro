<?php
declare(strict_types=1);
require_once __DIR__.'/../model/sesion_central.php';
require_once __DIR__.'/../model/panel_central.php';
exigirSesionCentral();
$page='comprobantes';$title='Comprobantes';$user=(string)($_SESSION['facturador_admin_usuario']??'Administrador');
liberarSesionCentral();
$filters=['empresa'=>max(0,(int)($_GET['empresa']??0)),'local'=>max(0,(int)($_GET['local']??0)),'tipo'=>(string)($_GET['tipo']??''),'estado'=>(string)($_GET['estado']??''),'desde'=>(string)($_GET['desde']??''),'hasta'=>(string)($_GET['hasta']??''),'buscar'=>trim((string)($_GET['buscar']??''))];
if($filters['desde']!==''&&$filters['hasta']!==''&&$filters['desde']>$filters['hasta']){[$filters['desde'],$filters['hasta']]=[$filters['hasta'],$filters['desde']];}
$activeFilters=count(array_filter($filters,static fn($value):bool=>$value!==''&&$value!==0));
$perPage=in_array((int)($_GET['por_pagina']??25),[25,50,100,200],true)?(int)($_GET['por_pagina']??25):25;$requestedPage=max(1,(int)($_GET['pagina']??1));
$historyFragment=($_GET['fragment']??'')==='history';
$rows=[];$companies=[];$locations=[];$total=0;$currentPage=1;$totalPages=1;$error=null;
try{if(!$historyFragment){$companies=PanelCentral::opcionesEmpresas();$locations=PanelCentral::opcionesLocales();}$result=PanelCentral::paginaComprobantes($filters,$perPage,$requestedPage);$rows=$result['rows'];$total=$result['total'];$currentPage=$result['page'];$totalPages=$result['pages'];}catch(Throwable $exception){$error='No fue posible consultar los comprobantes.';}
$e=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$types=['01'=>'Factura','03'=>'Boleta','07'=>'Nota de crédito','08'=>'Nota de débito','09'=>'Guía de remisión'];
$states=['aceptado'=>'Aceptado','procesando'=>'Procesando','enviado'=>'Enviado','rechazado'=>'Rechazado','error'=>'Error'];
$pageUrl=static function(int $page,?int $size=null)use($filters,$perPage):string{$query=$filters;$query['por_pagina']=$size??$perPage;$query['pagina']=$page;return 'comprobantes_central.php?'.http_build_query(array_filter($query,static fn($value):bool=>$value!==''&&$value!==0));};
$firstResult=$total?($currentPage-1)*$perPage+1:0;$lastResult=min($total,$currentPage*$perPage);
$pageStart=max(1,$currentPage-2);$pageEnd=min($totalPages,$currentPage+2);if($pageEnd-$pageStart<4){$pageStart=max(1,$pageEnd-4);$pageEnd=min($totalPages,$pageStart+4);}
if($historyFragment){require __DIR__.'/partials/comprobantes_history.php';exit;}
require __DIR__.'/partials/central_top.php';
?>
<section class="hero compact"><div><span class="eyebrow">Operación tributaria</span><h1>Comprobantes electrónicos</h1><p>Consulta el historial por empresa, local, fecha, documento y estado.</p></div></section>
<?php if($error):?><div class="notice error"><?=$e($error)?></div><?php endif;?>
<form class="document-filter" id="documentFilterForm" method="get" action="comprobantes_central.php"><input type="hidden" name="por_pagina" id="documentPerPage" value="<?=$perPage?>"><input type="hidden" name="pagina" value="1">
 <div class="document-filter-head"><div class="filter-heading-icon"><i class="fas fa-filter"></i></div><div><span>CONSULTA AVANZADA</span><h2>Filtrar comprobantes</h2><p>Combina los criterios que necesites. Los campos vacíos incluyen todos los resultados.</p></div><?php if($activeFilters):?><strong class="filter-result-chip"><i class="fas fa-sliders-h"></i> <?=$activeFilters?> <?=$activeFilters===1?'filtro activo':'filtros activos'?></strong><?php endif;?></div>
 <div class="document-filter-grid">
  <label><span>Empresa</span><div class="filter-control"><i class="fas fa-building"></i><select name="empresa" id="documentCompanyFilter"><option value="0">Todas las empresas</option><?php foreach($companies as $company):$name=$company['em_nombre_comercial']?:$company['em_razon_social'];?><option value="<?=(int)$company['em_id']?>" <?=$filters['empresa']===(int)$company['em_id']?'selected':''?>><?=$e($name)?> · <?=$e($company['em_ruc'])?></option><?php endforeach;?></select></div></label>
  <label><span>Local</span><div class="filter-control"><i class="fas fa-store"></i><select name="local" id="documentLocationFilter"><option value="0">Todos los locales</option><?php foreach($locations as $location):$companyName=$location['em_nombre_comercial']?:$location['em_razon_social'];?><option value="<?=(int)$location['loc_id']?>" data-company="<?=(int)$location['em_id']?>" <?=$filters['local']===(int)$location['loc_id']?'selected':''?>><?=$e($companyName.' — '.$location['loc_nombre'])?></option><?php endforeach;?></select></div></label>
  <label><span>Tipo de documento</span><div class="filter-control"><i class="fas fa-file-invoice"></i><select name="tipo"><option value="">Todos los documentos</option><?php foreach($types as $code=>$label):?><option value="<?=$code?>" <?=$filters['tipo']===$code?'selected':''?>><?=$e($label)?></option><?php endforeach;?></select></div></label>
  <label><span>Estado</span><div class="filter-control"><i class="fas fa-check-circle"></i><select name="estado"><option value="">Todos los estados</option><?php foreach($states as $code=>$label):?><option value="<?=$code?>" <?=$filters['estado']===$code?'selected':''?>><?=$e($label)?></option><?php endforeach;?></select></div></label>
  <label><span>Desde</span><div class="filter-control"><i class="far fa-calendar-alt"></i><input type="date" name="desde" value="<?=$e($filters['desde'])?>"></div></label>
  <label><span>Hasta</span><div class="filter-control"><i class="far fa-calendar-check"></i><input type="date" name="hasta" value="<?=$e($filters['hasta'])?>"></div></label>
  <label class="filter-search"><span>Buscar documento o cliente</span><div class="filter-control"><i class="fas fa-search"></i><input type="search" name="buscar" maxlength="80" value="<?=$e($filters['buscar'])?>" placeholder="Serie, correlativo, cliente o documento"></div></label>
  <div class="filter-actions"><button type="submit" class="filter-submit"><i class="fas fa-search"></i><span>Aplicar filtros</span></button><a href="comprobantes_central.php" class="filter-clear"><i class="fas fa-undo-alt"></i><span>Limpiar</span></a></div>
 </div>
</form>
<div id="documentHistoryShell" class="history-shell"><?php require __DIR__.'/partials/comprobantes_history.php';?></div>
<?php require __DIR__.'/partials/central_bottom.php';?>
