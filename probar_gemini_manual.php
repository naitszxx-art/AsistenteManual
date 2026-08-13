<?php

require_once "config/gemini.php";

$pregunta = "¿Cuáles son los deberes de los padres?";

$contexto = "
Artículo 53. Deberes de los padres de familia.

1. Proveer a sus hijos los recursos necesarios para su desarrollo integral.

2. Conocer y apoyar las normas establecidas en el Manual de Convivencia.

3. Participar activamente en las actividades de la institución.
";

$respuesta = consultarGemini(
    $pregunta,
    $contexto
);

echo "<h1>Prueba de Gemini</h1>";

echo "<h2>Pregunta:</h2>";

echo "<p>";
echo htmlspecialchars($pregunta);
echo "</p>";

echo "<h2>Respuesta:</h2>";

echo "<div style='padding:20px; background:#f2f2f2;'>";

echo nl2br(
    htmlspecialchars($respuesta)
);

echo "</div>";