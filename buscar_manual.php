<?php

// =====================================================
// CONFIGURACIÓN
// =====================================================

$archivoManual = __DIR__ . "/manual/manual.txt";

$pregunta = "";

$resultados = [];

$error = "";


// =====================================================
// FUNCIÓN: LEER EL MANUAL
// =====================================================

function leerManual($archivo)
{
    if (!file_exists($archivo)) {
        return false;
    }

    $contenido = file_get_contents($archivo);

    if ($contenido === false) {
        return false;
    }

    // Intentar convertir a UTF-8 si el archivo no está correctamente codificado
    if (!mb_check_encoding($contenido, 'UTF-8')) {
        $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    }

    return $contenido;
}


// =====================================================
// FUNCIÓN: EXTRAER LOS ARTÍCULOS
// =====================================================

function obtenerArticulos($manual)
{
    $articulos = [];

    // Normalizar saltos de línea
    $manual = str_replace("\r\n", "\n", $manual);
    $manual = str_replace("\r", "\n", $manual);

    /*
     * Detecta:
     *
     * Artículo 1.
     * Artículo 2.
     * Artículo 3.
     *
     * También permite que aparezca "Articulo"
     * sin tilde.
     */

    preg_match_all(
        '/Art[ií]culo\s+\d+\s*[.:]/iu',
        $manual,
        $coincidencias,
        PREG_OFFSET_CAPTURE
    );

    // Si no encontramos artículos
    if (empty($coincidencias[0])) {
        return [];
    }

    $cantidad = count($coincidencias[0]);

    for ($i = 0; $i < $cantidad; $i++) {

        // Posición inicial del artículo
        $inicio = $coincidencias[0][$i][1];

        // Posición del siguiente artículo
        if ($i + 1 < $cantidad) {

            $fin = $coincidencias[0][$i + 1][1];

            $contenido = substr(
                $manual,
                $inicio,
                $fin - $inicio
            );

        } else {

            // Último artículo
            $contenido = substr(
                $manual,
                $inicio
            );
        }

        $contenido = trim($contenido);

        if ($contenido !== "") {
            $articulos[] = $contenido;
        }
    }

    return $articulos;
}


// =====================================================
// FUNCIÓN: BUSCAR PALABRAS
// =====================================================

function buscarArticulos($articulos, $pregunta)
{
    $resultados = [];

    // Convertir pregunta a minúsculas
    $preguntaMinuscula = mb_strtolower($pregunta, 'UTF-8');

    // Eliminar signos de interrogación y puntuación
    $preguntaMinuscula = preg_replace(
        '/[¿?¡!,.;:()"\']/',
        ' ',
        $preguntaMinuscula
    );

    // Separar palabras
    $palabras = preg_split(
        '/\s+/',
        trim($preguntaMinuscula)
    );

    // Palabras que no aportan mucho a la búsqueda
    $palabrasIgnorar = [
        'que',
        'qué',
        'cuales',
        'cuáles',
        'como',
        'cómo',
        'son',
        'es',
        'los',
        'las',
        'el',
        'la',
        'de',
        'del',
        'un',
        'una',
        'unos',
        'unas',
        'y',
        'o',
        'en',
        'por',
        'para',
        'con',
        'sobre',
        'se',
        'su',
        'sus',
        'me',
        'puede',
        'pueden',
        'deben',
        'deber',
        'deberes'
    ];

    // Filtrar palabras demasiado pequeñas
    $palabrasBusqueda = [];

    foreach ($palabras as $palabra) {

        $palabra = trim($palabra);

        if (
            strlen($palabra) >= 3 &&
            !in_array($palabra, $palabrasIgnorar)
        ) {
            $palabrasBusqueda[] = $palabra;
        }
    }


    // =================================================
    // BUSCAR COINCIDENCIAS
    // =================================================

    foreach ($articulos as $articulo) {

        $texto = mb_strtolower($articulo, 'UTF-8');

        $puntaje = 0;

        foreach ($palabrasBusqueda as $palabra) {

            if (mb_strpos($texto, $palabra) !== false) {
                $puntaje++;
            }
        }

        if ($puntaje > 0) {

            $resultados[] = [
                'articulo' => $articulo,
                'puntaje' => $puntaje
            ];
        }
    }


    // Ordenar de mayor a menor coincidencia
    usort(
        $resultados,
        function ($a, $b) {
            return $b['puntaje'] <=> $a['puntaje'];
        }
    );


    // Devolver máximo 5 resultados
    return array_slice($resultados, 0, 5);
}


// =====================================================
// PROCESAR BÚSQUEDA
// =====================================================

if (isset($_GET['pregunta'])) {

    $pregunta = trim($_GET['pregunta']);

    if ($pregunta === "") {

        $error = "Por favor escribe una pregunta.";

    } else {

        // Leer manual
        $manual = leerManual($archivoManual);

        if ($manual === false) {

            $error = "No se pudo leer el archivo manual.txt.";

        } else {

            // Extraer artículos
            $articulos = obtenerArticulos($manual);

            if (empty($articulos)) {

                $error = "No se pudieron detectar artículos dentro de manual.txt.";

            } else {

                // Buscar artículos relacionados
                $resultados = buscarArticulos(
                    $articulos,
                    $pregunta
                );

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

    <title>Buscador del Manual de Convivencia</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family: Arial, Helvetica, sans-serif;

            background: #eef3f8;

            color: #1f2937;
        }


        .contenedor {

            width: 90%;

            max-width: 950px;

            margin: 50px auto;

            background: white;

            border-radius: 14px;

            box-shadow: 0 8px 30px rgba(0,0,0,0.10);

            overflow: hidden;
        }


        .encabezado {

            background: #063b70;

            color: white;

            padding: 28px 35px;
        }


        .encabezado h1 {

            margin: 0;

            font-size: 28px;
        }


        .encabezado p {

            margin: 8px 0 0;

            opacity: 0.9;
        }


        .contenido {

            padding: 30px 35px;
        }


        .formulario {

            display: flex;

            gap: 10px;

            margin-bottom: 25px;
        }


        .formulario input {

            flex: 1;

            padding: 13px 15px;

            border: 1px solid #cbd5e1;

            border-radius: 7px;

            font-size: 15px;

            outline: none;
        }


        .formulario input:focus {

            border-color: #063b70;

            box-shadow: 0 0 0 2px rgba(6,59,112,0.1);
        }


        .formulario button {

            background: #063b70;

            color: white;

            border: none;

            padding: 0 25px;

            border-radius: 7px;

            cursor: pointer;

            font-weight: bold;
        }


        .formulario button:hover {

            background: #052f59;
        }


        .separador {

            border: 0;

            border-top: 1px solid #e5e7eb;

            margin: 20px 0;
        }


        .titulo-resultados {

            font-size: 20px;

            margin-bottom: 20px;

            color: #111827;
        }


        .resultado {

            border: 1px solid #dbe3ec;

            border-left: 5px solid #063b70;

            border-radius: 8px;

            padding: 18px;

            margin-bottom: 15px;

            background: #f8fafc;
        }


        .resultado h3 {

            margin-top: 0;

            color: #063b70;

            font-size: 17px;
        }


        .resultado p {

            line-height: 1.6;

            white-space: pre-line;
        }


        .mensaje {

            padding: 15px;

            border-radius: 8px;

            background: #fef3c7;

            color: #92400e;

            margin-top: 15px;
        }


        .error {

            padding: 15px;

            border-radius: 8px;

            background: #fee2e2;

            color: #b91c1c;

            margin-top: 15px;

        }


        .exito {

            color: #166534;

            background: #dcfce7;

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        .pie {

            text-align: center;

            padding: 18px;

            color: #64748b;

            font-size: 13px;

            border-top: 1px solid #e5e7eb;
        }


        @media (max-width: 700px) {

            .contenedor {
                width: 95%;
                margin: 20px auto;
            }

            .contenido {
                padding: 20px;
            }

            .formulario {
                flex-direction: column;
            }

            .formulario button {
                height: 45px;
            }
        }

    </style>

</head>


<body>


<div class="contenedor">


    <!-- ENCABEZADO -->

    <div class="encabezado">

        <h1>
            Buscador del Manual de Convivencia
        </h1>

        <p>
            Consulta información directamente desde el Manual de Convivencia.
        </p>

    </div>


    <!-- CONTENIDO -->

    <div class="contenido">


        <!-- FORMULARIO -->

        <form
            method="GET"
            action="buscar_manual.php"
            class="formulario"
        >

            <input
                type="text"
                name="pregunta"
                placeholder="Ejemplo: ¿Cuáles son los deberes de los padres?"
                value="<?= htmlspecialchars($pregunta, ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <button type="submit">
                Buscar
            </button>

        </form>


        <?php if ($error !== ""): ?>

            <div class="error">

                <strong>Error:</strong>

                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

            </div>

        <?php endif; ?>


        <?php if ($pregunta !== "" && empty($error)): ?>


            <hr class="separador">


            <h2 class="titulo-resultados">

                Resultados para:

                "<?= htmlspecialchars($pregunta, ENT_QUOTES, 'UTF-8') ?>"

            </h2>


            <?php if (empty($resultados)): ?>

                <div class="mensaje">

                    No se encontraron artículos relacionados
                    con tu pregunta.

                </div>

            <?php else: ?>


                <div class="exito">

                    Se encontraron
                    <strong><?= count($resultados) ?></strong>
                    artículos relacionados.

                </div>


                <?php foreach ($resultados as $resultado): ?>


                    <div class="resultado">


                        <h3>

                            <?= htmlspecialchars(
                                strtok($resultado['articulo'], "\n"),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h3>


                        <p>

                            <?= htmlspecialchars(
                                $resultado['articulo'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </p>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        <?php endif; ?>


    </div>


    <!-- PIE -->

    <div class="pie">

        Asistente Virtual · Manual de Convivencia

    </div>


</div>


</body>

</html>