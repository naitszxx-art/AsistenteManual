<?php

require_once("config/gemini.php");

$pregunta = "¿Qué es una computadora?";

$respuesta = consultarGemini($pregunta);

echo "<h1>Prueba de Gemini</h1>";

echo "<p><strong>Pregunta:</strong></p>";

echo "<p>" . htmlspecialchars($pregunta) . "</p>";

echo "<hr>";

echo "<p><strong>Respuesta:</strong></p>";

echo "<p>";

echo nl2br(
    htmlspecialchars($respuesta)
);

echo "</p>";

?>