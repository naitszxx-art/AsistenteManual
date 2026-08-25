<?php

// =====================================================
// CONEXIÓN A LA BASE DE DATOS
// =====================================================

$conexion = new mysqli(
    "localhost",
    "root",
    "",
    "chatbot_manual"
);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");


// =====================================================
// CONEXIÓN CON GEMINI
// =====================================================

require_once __DIR__ . "/config/gemini.php";


// =====================================================
// UBICACIÓN DEL MANUAL
// =====================================================

$archivoManual = __DIR__ . "/manual/manual.txt";


// =====================================================
// VARIABLES DE ESTADO
// =====================================================

$pregunta = "";
$respuesta = "";
$error = "";


// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

function leerManual($archivo)
{
    if (!file_exists($archivo)) {
        return false;
    }

    $contenido = file_get_contents($archivo);
    return ($contenido === false) ? false : $contenido;
}

function obtenerArticulos($manual)
{
    $articulos = [];
    $manual = str_replace(["\r\n", "\r"], "\n", $manual);

    preg_match_all(
        '/Art[ií]culo\s+\d+\s*[.:]/iu',
        $manual,
        $coincidencias,
        PREG_OFFSET_CAPTURE
    );

    if (empty($coincidencias[0])) {
        return [];
    }

    $cantidad = count($coincidencias[0]);

    for ($i = 0; $i < $cantidad; $i++) {
        $inicio = $coincidencias[0][$i][1];
        if ($i + 1 < $cantidad) {
            $fin = $coincidencias[0][$i + 1][1];
            $contenido = substr($manual, $inicio, $fin - $inicio);
        } else {
            $contenido = substr($manual, $inicio);
        }

        $contenido = trim($contenido);
        if ($contenido !== "") {
            $articulos[] = $contenido;
        }
    }

    return $articulos;
}

function normalizarTexto($texto)
{
    $texto = mb_strtolower($texto, "UTF-8");
    $texto = strtr($texto, [
        "á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ü" => "u", "ñ" => "n"
    ]);
    $texto = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto);
    return trim(preg_replace('/\s+/u', ' ', $texto));
}

function obtenerPalabrasBusqueda($pregunta)
{
    $preguntaNormalizada = normalizarTexto($pregunta);
    $palabras = preg_split('/\s+/u', $preguntaNormalizada);

    $ignorar = [
        "que", "cuales", "cual", "como", "son", "es", "los", "las", "el", "la", "de", "del", 
        "un", "una", "unos", "unas", "y", "o", "en", "por", "para", "con", "sobre", "se", 
        "su", "sus", "me", "mi", "mis", "dice", "decir", "habla", "hablar", "puede", "pueden", 
        "debe", "deben", "donde", "cuando", "quiero", "saber", "informacion", "manual", "articulo"
    ];

    $resultado = [];
    foreach ($palabras as $palabra) {
        $palabra = trim($palabra);
        if ($palabra !== "" && mb_strlen($palabra, "UTF-8") >= 3 && !in_array($palabra, $ignorar)) {
            $resultado[] = $palabra;
        }
    }

    return array_values(array_unique($resultado));
}

function obtenerFrasesImportantes($pregunta)
{
    $preguntaNormalizada = normalizarTexto($pregunta);
    $frases = [
        "basica secundaria", "basica primaria", "media tecnica", "educacion secundaria",
        "educacion primaria", "educacion media", "educacion fisica", "jornada unica",
        "jornada nocturna", "ciclo lectivo", "ciclos lectivos", "manual de convivencia",
        "presentacion personal", "uniforme de diario", "uniforme de educacion fisica",
        "uniforme escolar", "derechos de los estudiantes", "deberes de los estudiantes",
        "derechos y deberes", "convivencia escolar", "debido proceso", "ruta de atencion",
        "rutas de atencion", "educacion basica"
    ];

    $encontradas = [];
    foreach ($frases as $frase) {
        if (mb_strpos(" " . $preguntaNormalizada . " ", " " . $frase . " ") !== false) {
            $encontradas[] = $frase;
        }
    }

    return $encontradas;
}

function obtenerFrasePrincipal($frases)
{
    if (empty($frases)) return "";

    $prioridad = [
        "basica secundaria" => 100, "basica primaria" => 95, "media tecnica" => 95,
        "educacion secundaria" => 90, "educacion primaria" => 90, "educacion media" => 90,
        "educacion fisica" => 90, "jornada unica" => 85, "jornada nocturna" => 85,
        "manual de convivencia" => 80, "uniforme de diario" => 80, "uniforme de educacion fisica" => 80,
        "derechos y deberes" => 80, "debido proceso" => 80, "ruta de atencion" => 80,
        "rutas de atencion" => 80, "educacion basica" => 20
    ];

    $mejorFrase = "";
    $mejorPrioridad = -1;

    foreach ($frases as $frase) {
        $valor = $prioridad[$frase] ?? 10;
        if ($valor > $mejorPrioridad) {
            $mejorPrioridad = $valor;
            $mejorFrase = $frase;
        }
    }

    return $mejorFrase;
}

function calcularProximidad($texto, $termino1, $termino2)
{
    $posiciones1 = [];
    $posiciones2 = [];
    $offset = 0;

    while (($pos = mb_strpos($texto, $termino1, $offset, "UTF-8")) !== false) {
        $posiciones1[] = $pos;
        $offset = $pos + mb_strlen($termino1, "UTF-8");
    }

    $offset = 0;
    while (($pos = mb_strpos($texto, $termino2, $offset, "UTF-8")) !== false) {
        $posiciones2[] = $pos;
        $offset = $pos + mb_strlen($termino2, "UTF-8");
    }

    if (empty($posiciones1) || empty($posiciones2)) return 0;

    $distanciaMinima = PHP_INT_MAX;
    foreach ($posiciones1 as $pos1) {
        foreach ($posiciones2 as $pos2) {
            $distancia = abs($pos1 - $pos2);
            if ($distancia < $distanciaMinima) {
                $distanciaMinima = $distancia;
            }
        }
    }

    if ($distanciaMinima <= 100) return 500;
    if ($distanciaMinima <= 250) return 300;
    if ($distanciaMinima <= 500) return 150;
    if ($distanciaMinima <= 1000) return 50;

    return 0;
}

function buscarArticulos($articulos, $pregunta)
{
    $palabrasBusqueda = obtenerPalabrasBusqueda($pregunta);
    $frasesImportantes = obtenerFrasesImportantes($pregunta);
    $frasePrincipal = obtenerFrasePrincipal($frasesImportantes);
    $preguntaNormalizada = normalizarTexto($pregunta);

    $resultados = [];

    foreach ($articulos as $indice => $articulo) {
        $textoNormalizado = normalizarTexto($articulo);
        $puntaje = 0;
        $coincidencias = 0;
        $frasesEncontradas = [];
        $proximidad = 0;

        foreach ($frasesImportantes as $frase) {
            if (mb_strpos(" " . $textoNormalizado . " ", " " . $frase . " ") !== false) {
                $puntaje += ($frase === $frasePrincipal) ? 700 : 30;
                $frasesEncontradas[] = $frase;
            }
        }

        foreach ($palabrasBusqueda as $palabra) {
            $encontrado = preg_match('/\b' . preg_quote($palabra, '/') . '\b/u', $textoNormalizado);
            if ($encontrado) {
                $coincidencias++;
                $puntaje += 2;
                $cantidad = preg_match_all('/\b' . preg_quote($palabra, '/') . '\b/u', $textoNormalizado);
                if ($cantidad > 1) {
                    $puntaje += min($cantidad - 1, 3);
                }
            }
        }

        if ($frasePrincipal !== "") {
            $terminosProximidad = [];
            foreach ($palabrasBusqueda as $palabra) {
                if (mb_strpos($frasePrincipal, $palabra, 0, "UTF-8") === false && mb_strlen($palabra, "UTF-8") >= 5) {
                    $terminosProximidad[] = $palabra;
                }
            }

            foreach ($terminosProximidad as $termino) {
                $valorProximidad = calcularProximidad($textoNormalizado, $frasePrincipal, $termino);
                if ($valorProximidad > $proximidad) {
                    $proximidad = $valorProximidad;
                }
            }
            $puntaje += $proximidad;
        }

        if ($coincidencias >= 2) $puntaje += 4;
        if ($coincidencias >= 3) $puntaje += 5;
        if ($coincidencias >= 4) $puntaje += 6;

        if (mb_strpos($preguntaNormalizada, "horario", 0, "UTF-8") !== false) {
            if (mb_strpos($textoNormalizado, "horario", 0, "UTF-8") !== false) {
                $puntaje += 100;
            }
        }

        if ($puntaje > 0) {
            $resultados[] = [
                "articulo" => $articulo,
                "puntaje" => $puntaje,
                "coincidencias" => $coincidencias,
                "frases" => $frasesEncontradas,
                "proximidad" => $proximidad,
                "indice" => $indice
            ];
        }
    }

    usort($resultados, function ($a, $b) {
        if ($a["puntaje"] != $b["puntaje"]) return $b["puntaje"] - $a["puntaje"];
        if ($a["proximidad"] != $b["proximidad"]) return $b["proximidad"] - $a["proximidad"];
        return $b["coincidencias"] - $a["coincidencias"];
    });

    if ($frasePrincipal !== "") {
        $resultadosExactos = array_filter($resultados, function ($res) use ($frasePrincipal) {
            return in_array($frasePrincipal, $res["frases"]);
        });

        if (!empty($resultadosExactos)) {
            usort($resultadosExactos, function ($a, $b) {
                if ($a["puntaje"] != $b["puntaje"]) return $b["puntaje"] - $a["puntaje"];
                return $b["proximidad"] - $a["proximidad"];
            });
            return array_slice(array_values($resultadosExactos), 0, 4);
        }
    }

    if (!empty($resultados)) {
        $mejorPuntaje = $resultados[0]["puntaje"];
        $seleccionados = [];

        foreach ($resultados as $resultado) {
            if ($resultado["puntaje"] >= max(2, $mejorPuntaje * 0.5)) {
                $seleccionados[] = $resultado;
            }
            if (count($seleccionados) >= 4) break;
        }

        return $seleccionados;
    }

    return [];
}


// =====================================================
// PROCESAR PETICIÓN FORMULARIO
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["pregunta"])) {
    $pregunta = trim($_POST["pregunta"]);

    if ($pregunta === "") {
        $error = "Por favor escribe una pregunta.";
    } else {
        $manual = leerManual($archivoManual);

        if ($manual === false) {
            $error = "No fue posible leer el Manual de Convivencia.";
        } else {
            $articulos = obtenerArticulos($manual);

            if (empty($articulos)) {
                $error = "No se encontraron artículos en el Manual.";
            } else {
                $resultados = buscarArticulos($articulos, $pregunta);

                if (!empty($resultados)) {
                    $contexto = "";
                    foreach ($resultados as $resultado) {
                        $contexto .= $resultado["articulo"] . "\n\n";
                    }

                    $respuesta = consultarGemini($pregunta, $contexto);

                    $stmt = $conexion->prepare(
                        "INSERT INTO conversaciones (pregunta, respuesta) VALUES (?, ?)"
                    );

                    if ($stmt) {
                        $stmt->bind_param("ss", $pregunta, $respuesta);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    $error = "No encontré información relacionada con tu pregunta dentro del Manual de Convivencia.";
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente Virtual | Manual de Convivencia</title>
    <!-- Vinculación del archivo CSS externo -->
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<div class="app">
    <header class="header">
        
     <img src="logo2.png" alt="Logo Manual de Convivencia" class="header-logo">
        <div class="header-text">
            <h1>GaraBOT</h1>
            <p>Asistente Virtual con IA (Gemini)</p>
        </div>
    </header>

    <main class="chat-container">
        <?php if (empty($pregunta) && empty($respuesta) && empty($error)): ?>
            <div class="welcome">
                <div class="welcome-icon">💬</div>
                <h2>¿En qué te puedo ayudar hoy?</h2>
                <p>Hazme cualquier pregunta sobre el Manual de Convivencia (normas, uniformes, derechos, deberes o procesos) y buscaré la mejor respuesta.</p>
            </div>
        <?php endif; ?>

        <div class="messages">
            <?php if (!empty($pregunta)): ?>
                <div class="message user">
                    <div class="message-content">
                        <span class="message-label">Tú</span>
                        <?= htmlspecialchars($pregunta) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($respuesta)): ?>
                <div class="message assistant">
                    <div class="message-content">
                        <span class="message-label">Asistente</span>
                        <?= htmlspecialchars($respuesta) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="input-area">
            <form method="POST" action="" class="input-box">
                <textarea 
    name="pregunta" 
    placeholder="Escribe tu consulta sobre el manual..." 
    required
    onkeydown="if(event.keyCode == 13 && !event.shiftKey) { event.preventDefault(); this.form.submit(); }"
></textarea>
                <button type="submit" class="send-button" title="Enviar mensaje">
                    <svg viewBox="0 0 24 24">
                        <path d="M2,21L23,12L2,3V10L17,12L2,14V21Z"/>
                    </svg>
                </button>
            </form>
        </div>
    </main>
</div>

    <footer class="footer">
        <p>Creado por <span>Sebastian Celis</span> y <span>Jaider Sanchez</span> — <strong>Prom 2026</strong></p>
    </footer>
</div>

</body>
</html>

</body>
</html>