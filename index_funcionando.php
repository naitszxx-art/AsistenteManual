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
// VARIABLES
// =====================================================

$pregunta = "";
$respuesta = "";
$error = "";


// =====================================================
// LEER MANUAL
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

    return $contenido;
}


// =====================================================
// EXTRAER ARTÍCULOS
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
     * También acepta "Articulo" sin tilde.
     *
     * Los artículos no necesitan estar
     * en líneas diferentes.
     */

    preg_match_all(
        '/Art[ií]culo\s+\d+\s*[.:]/iu',
        $manual,
        $coincidencias,
        PREG_OFFSET_CAPTURE
    );

    if (empty($coincidencias[0])) {
        return [];
    }

    $cantidad = count(
        $coincidencias[0]
    );

    for ($i = 0; $i < $cantidad; $i++) {

        $inicio =
            $coincidencias[0][$i][1];

        if ($i + 1 < $cantidad) {

            $fin =
                $coincidencias[0][$i + 1][1];

            $contenido =
                substr(
                    $manual,
                    $inicio,
                    $fin - $inicio
                );

        } else {

            $contenido =
                substr(
                    $manual,
                    $inicio
                );
        }

        $contenido =
            trim($contenido);

        if ($contenido !== "") {

            $articulos[] =
                $contenido;
        }
    }

    return $articulos;
}


// =====================================================
// NORMALIZAR TEXTO
// =====================================================

function normalizarTexto($texto)
{
    $texto = mb_strtolower(
        $texto,
        "UTF-8"
    );

    // Convertir tildes para facilitar las búsquedas
    $texto = strtr(
        $texto,
        [
            "á" => "a",
            "é" => "e",
            "í" => "i",
            "ó" => "o",
            "ú" => "u",
            "ü" => "u",
            "ñ" => "n"
        ]
    );

    return $texto;
}


// =====================================================
// BUSCAR ARTÍCULOS RELEVANTES
// =====================================================

function buscarArticulos($articulos, $pregunta)
{
    $resultados = [];

    $preguntaNormalizada =
        normalizarTexto(
            $pregunta
        );


    // ---------------------------------------------
    // Palabras de la pregunta
    // ---------------------------------------------

    $palabras =
        preg_split(
            '/\s+/u',
            $preguntaNormalizada
        );


    // ---------------------------------------------
    // Palabras que no aportan relevancia
    // ---------------------------------------------

    $ignorar = [

        "que",
        "cuales",
        "como",
        "son",
        "es",
        "los",
        "las",
        "el",
        "la",
        "de",
        "del",
        "un",
        "una",
        "unos",
        "unas",
        "y",
        "o",
        "en",
        "por",
        "para",
        "con",
        "sobre",
        "se",
        "su",
        "sus",
        "me",
        "mi",
        "mis",
        "dice",
        "decir",
        "habla",
        "hablar",
        "puede",
        "pueden",
        "donde",
        "cuando",
        "cuando",
        "quiero",
        "saber",
        "informacion",
        "información"
    ];


    $palabrasBusqueda = [];


    foreach (
        $palabras
        as $palabra
    ) {

        $palabra =
            trim($palabra);


        if (
            $palabra !== "" &&
            mb_strlen(
                $palabra,
                "UTF-8"
            ) >= 3 &&
            !in_array(
                $palabra,
                $ignorar
            )
        ) {

            $palabrasBusqueda[] =
                $palabra;
        }
    }


    // =================================================
    // PALABRAS CLAVE ESPECIALES
    // =================================================

    /*
     * Algunas preguntas utilizan expresiones
     * generales. Añadimos palabras relacionadas
     * para mejorar la búsqueda.
     */

    $palabrasPregunta =
        implode(
            " ",
            $palabrasBusqueda
        );


    if (
        strpos(
            $palabrasPregunta,
            "uniforme"
        ) !== false
    ) {

        $palabrasBusqueda[] =
            "uniforme";
    }


    if (
        strpos(
            $palabrasPregunta,
            "derecho"
        ) !== false
    ) {

        $palabrasBusqueda[] =
            "derechos";
    }


    if (
        strpos(
            $palabrasPregunta,
            "deber"
        ) !== false
    ) {

        $palabrasBusqueda[] =
            "deberes";
    }


    if (
        strpos(
            $palabrasPregunta,
            "convivencia"
        ) !== false
    ) {

        $palabrasBusqueda[] =
            "convivencia";
    }


    // =================================================
    // BUSCAR EN LOS 107 ARTÍCULOS
    // =================================================

    foreach (
        $articulos
        as $indice => $articulo
    ) {

        $textoNormalizado =
            normalizarTexto(
                $articulo
            );


        $puntaje = 0;


        foreach (
            $palabrasBusqueda
            as $palabra
        ) {

            /*
             * Coincidencia exacta de palabra
             */

            if (
                preg_match(
                    '/\b' .
                    preg_quote(
                        $palabra,
                        '/'
                    ) .
                    '\b/u',
                    $textoNormalizado
                )
            ) {

                $puntaje++;
            }
        }


        // ---------------------------------------------
        // Coincidencia con la pregunta completa
        // ---------------------------------------------

        if (
            mb_strlen(
                $preguntaNormalizada,
                "UTF-8"
            ) >= 5
        ) {

            if (
                mb_strpos(
                    $textoNormalizado,
                    $preguntaNormalizada
                ) !== false
            ) {

                $puntaje += 5;
            }
        }


        if ($puntaje > 0) {

            $resultados[] = [

                "articulo" =>
                    $articulo,

                "puntaje" =>
                    $puntaje,

                "indice" =>
                    $indice
            ];
        }
    }


    // =================================================
    // ORDENAR POR RELEVANCIA
    // =================================================

    usort(
        $resultados,
        function ($a, $b) {

            return
                $b["puntaje"]
                -
                $a["puntaje"];
        }
    );


    // =================================================
    // DEVOLVER MÁXIMO 5 ARTÍCULOS
    // =================================================

    return array_slice(
        $resultados,
        0,
        5
    );
}


// =====================================================
// PROCESAR PREGUNTA
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["pregunta"])
) {

    $pregunta =
        trim(
            $_POST["pregunta"]
        );


    if ($pregunta === "") {

        $error =
            "Por favor escribe una pregunta.";

    } else {


        // =============================================
        // LEER MANUAL
        // =============================================

        $manual =
            leerManual(
                $archivoManual
            );


        if ($manual === false) {

            $error =
                "No fue posible leer el Manual de Convivencia.";

        } else {


            // =========================================
            // OBTENER ARTÍCULOS
            // =========================================

            $articulos =
                obtenerArticulos(
                    $manual
                );


            if (
                empty($articulos)
            ) {

                $error =
                    "No se encontraron artículos en el Manual.";

            } else {


                // =====================================
                // BUSCAR INFORMACIÓN
                // =====================================

                $resultados =
                    buscarArticulos(
                        $articulos,
                        $pregunta
                    );


                if (
                    empty($resultados)
                ) {

                    $error =
                        "No encontré información relacionada con tu pregunta dentro del Manual de Convivencia.";

                } else {


                    // =================================
                    // CREAR CONTEXTO PARA GEMINI
                    // =================================

                    $contexto = "";


                    foreach (
                        $resultados
                        as $resultado
                    ) {

                        $contexto .=
                            $resultado["articulo"];

                        $contexto .=
                            "\n\n";
                    }


                    // =================================
                    // CONSULTAR GEMINI
                    // =================================

                    $respuesta =
                        consultarGemini(
                            $pregunta,
                            $contexto
                        );


                    // =================================
                    // GUARDAR CONVERSACIÓN
                    // =================================

                    $stmt =
                        $conexion->prepare(
                            "INSERT INTO conversaciones
                            (pregunta, respuesta)
                            VALUES (?, ?)"
                        );


                    if ($stmt) {

                        $stmt->bind_param(
                            "ss",
                            $pregunta,
                            $respuesta
                        );

                        $stmt->execute();

                        $stmt->close();
                    }
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Asistente Virtual | Manual de Convivencia
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #eef3f8;

            color:
                #1f2937;
        }


        .contenedor {

            width: 90%;

            max-width: 900px;

            margin:
                45px auto;

            background:
                white;

            border-radius:
                16px;

            overflow:
                hidden;

            box-shadow:
                0 10px 35px
                rgba(0,0,0,0.12);
        }


        .encabezado {

            background:
                #063b70;

            color:
                white;

            padding:
                35px;
        }


        .encabezado h1 {

            margin: 0;

            font-size:
                30px;
        }


        .encabezado p {

            margin:
                8px 0 0;

            font-size:
                16px;
        }


        .contenido {

            padding:
                35px;
        }


        .bienvenida {

            margin-bottom:
                30px;
        }


        .bienvenida h2 {

            color:
                #063b70;

            margin-top:
                0;
        }


        .bienvenida p {

            line-height:
                1.7;

            color:
                #475569;
        }


        .formulario {

            display:
                flex;

            flex-direction:
                column;

            gap:
                12px;
        }


        textarea {

            width: 100%;

            min-height:
                130px;

            resize:
                vertical;

            padding:
                15px;

            border:
                1px solid #cbd5e1;

            border-radius:
                9px;

            font-family:
                inherit;

            font-size:
                15px;

            outline:
                none;
        }


        textarea:focus {

            border-color:
                #063b70;

            box-shadow:
                0 0 0 3px
                rgba(6,59,112,.10);
        }


        .boton {

            width: 100%;

            height:
                48px;

            border:
                none;

            border-radius:
                8px;

            background:
                #063b70;

            color:
                white;

            font-weight:
                bold;

            cursor:
                pointer;

            font-size:
                15px;
        }


        .boton:hover {

            background:
                #052f59;
        }


        .respuesta {

            margin-top:
                25px;

            padding:
                25px;

            border:
                1px solid #dbe3ec;

            border-left:
                5px solid #063b70;

            border-radius:
                10px;

            background:
                #f8fafc;
        }


        .respuesta h2 {

            margin-top:
                0;

            color:
                #063b70;
        }


        .respuesta-texto {

            line-height:
                1.7;
        }


        .error {

            margin-top:
                25px;

            padding:
                15px;

            background:
                #fee2e2;

            color:
                #b91c1c;

            border-radius:
                8px;
        }


        .pie {

            padding:
                18px;

            text-align:
                center;

            color:
                #64748b;

            font-size:
                13px;

            border-top:
                1px solid #e5e7eb;
        }

    </style>

</head>


<body>


<div class="contenedor">


    <div class="encabezado">

        <h1>
            Asistente Virtual
        </h1>

        <p>
            Manual de Convivencia
        </p>

    </div>


    <div class="contenido">


        <div class="bienvenida">

            <h2>
                ¡Bienvenido!
            </h2>

            <p>
                Este asistente responderá las preguntas
                relacionadas con el Manual de Convivencia
                del colegio utilizando la información
                disponible en el documento institucional.
            </p>

        </div>


        <form
            method="POST"
            action="index.php"
            class="formulario"
        >

            <textarea
                name="pregunta"
                placeholder="Escribe tu pregunta aquí..."
                required
            ><?= htmlspecialchars(
                $pregunta,
                ENT_QUOTES,
                "UTF-8"
            ) ?></textarea>


            <button
                type="submit"
                class="boton"
            >

                Consultar

            </button>

        </form>


        <?php if ($error !== ""): ?>

            <div class="error">

                <strong>
                    Atención:
                </strong>

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($respuesta !== ""): ?>

            <div class="respuesta">

                <h2>
                    Respuesta
                </h2>

                <div class="respuesta-texto">

                    <?= nl2br(
                        htmlspecialchars(
                            $respuesta,
                            ENT_QUOTES,
                            "UTF-8"
                        )
                    ) ?>

                </div>

            </div>

        <?php endif; ?>


    </div>


    <div class="pie">

        Asistente Virtual ·
        Manual de Convivencia

    </div>


</div>


</body>

</html>