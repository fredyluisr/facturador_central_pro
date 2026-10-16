<?php
	require_once("../model/sesion.php"); // aquí session_start() ya debería estar incluido
	require_once("../model/var_global.php");

	// Redirección si ya hay sesión
	if (isset($_SESSION["rol_id_ifact"]) && $_SESSION["rol_id_ifact"] != 0) {
		header("Location: index.php");
		exit();
	}else{
		header("Location: login.php");
		exit();
	}
?>