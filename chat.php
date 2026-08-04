<?php

require_once("config/conexion.php");
require_once("guardar.php");
require_once("config/gemini.php");

$pregunta = "";

$respuesta = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $pregunta = trim($_POST["pregunta"]);

    if ($pregunta == "") {

        $respuesta = "Debes escribir una pregunta.";

    } else {

        /*
            Aquí después llamaremos a Gemini.
        */

        $respuesta = consultarGemini($pregunta);

         guardarConversacion($conn,$pregunta,$respuesta);

    }

}

?>