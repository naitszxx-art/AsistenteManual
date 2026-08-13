<?php

// Ubicación del Manual de Convivencia
$archivo = "manual/manual.txt";

// Comprobar que el archivo existe
if (!file_exists($archivo)) {

    die("Error: no se encontró el archivo manual.txt");

}

// Leer todo el contenido
$contenido = file_get_contents($archivo);

// Comprobar que se pudo leer
if ($contenido === false) {

    die("Error: no fue posible leer el Manual de Convivencia");

}

// Mostrar el contenido
echo "<h1>Manual de Convivencia</h1>";

echo "<pre>";
echo htmlspecialchars($contenido);
echo "</pre>";

?>