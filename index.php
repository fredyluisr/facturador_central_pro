<?php
require_once __DIR__.'/model/sesion_central.php';
header('Location: view/'.(!empty($_SESSION['facturador_admin_id'])?'dashboard_central.php':'login.php'));exit;
