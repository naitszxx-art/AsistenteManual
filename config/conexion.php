<?php

/*=========================================
=   CONFIGURACIÓN DE LA BASE DE DATOS      =
=========================================*/

$host = "localhost";
$usuario = "root";
$password = "";
$basedatos = "chatbot_manual";

/*=========================================
=   CONEXIÓN                              =
=========================================*/

$conn = new mysqli($host, $usuario, $password, $basedatos);

if ($conn->connect_error) {

    die("Error de conexión: " . $conn->connect_error);

}

/*=========================================
=   UTF-8
=========================================*/

$conn->set_charset("utf8");

?>