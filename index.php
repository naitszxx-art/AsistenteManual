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

    $manual = str_replace("\r\n", "\n", $manual);
    $manual = str_replace("\r", "\n", $manual);

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

            $contenido = substr(
                $manual,
                $inicio,
                $fin - $inicio
            );

        } else {

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
// NORMALIZAR TEXTO
// =====================================================

function normalizarTexto($texto)
{
    $texto = mb_strtolower(
        $texto,
        "UTF-8"
    );

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

    $texto = preg_replace(
        '/[^\p{L}\p{N}\s]/u',
        ' ',
        $texto
    );

    $texto = preg_replace(
        '/\s+/u',
        ' ',
        $texto
    );

    return trim($texto);
}


// =====================================================
// PALABRAS DE LA PREGUNTA
// =====================================================

function obtenerPalabrasBusqueda($pregunta)
{
    $preguntaNormalizada =
        normalizarTexto($pregunta);

    $palabras =
        preg_split(
            '/\s+/u',
            $preguntaNormalizada
        );

    $ignorar = [

        "que",
        "cuales",
        "cual",
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
        "debe",
        "deben",
        "donde",
        "cuando",
        "quiero",
        "saber",
        "informacion",
        "manual",
        "articulo"
    ];

    $resultado = [];

    foreach ($palabras as $palabra) {

        $palabra = trim($palabra);

        if (
            $palabra !== "" &&
            mb_strlen($palabra, "UTF-8") >= 3 &&
            !in_array($palabra, $ignorar)
        ) {

            $resultado[] = $palabra;
        }
    }

    return array_values(
        array_unique($resultado)
    );
}


// =====================================================
// FRASES IMPORTANTES
// =====================================================

function obtenerFrasesImportantes($pregunta)
{
    $preguntaNormalizada =
        normalizarTexto($pregunta);

    $frases = [

        "basica secundaria",
        "basica primaria",
        "media tecnica",
        "educacion secundaria",
        "educacion primaria",
        "educacion media",
        "educacion fisica",
        "jornada unica",
        "jornada nocturna",
        "ciclo lectivo",
        "ciclos lectivos",
        "manual de convivencia",
        "presentacion personal",
        "uniforme de diario",
        "uniforme de educacion fisica",
        "uniforme escolar",
        "derechos de los estudiantes",
        "deberes de los estudiantes",
        "derechos y deberes",
        "convivencia escolar",
        "debido proceso",
        "ruta de atencion",
        "rutas de atencion",
        "educacion basica"
    ];

    $encontradas = [];

    foreach ($frases as $frase) {

        $textoBusqueda =
            " " .
            $preguntaNormalizada .
            " ";

        $fraseBusqueda =
            " " .
            $frase .
            " ";

        if (
            mb_strpos(
                $textoBusqueda,
                $fraseBusqueda
            ) !== false
        ) {

            $encontradas[] = $frase;
        }
    }

    return $encontradas;
}


// =====================================================
// DETERMINAR FRASE PRINCIPAL
// =====================================================

function obtenerFrasePrincipal($frases)
{
    if (empty($frases)) {
        return "";
    }

    $prioridad = [

        "basica secundaria" => 100,
        "basica primaria" => 95,
        "media tecnica" => 95,

        "educacion secundaria" => 90,
        "educacion primaria" => 90,
        "educacion media" => 90,
        "educacion fisica" => 90,

        "jornada unica" => 85,
        "jornada nocturna" => 85,

        "manual de convivencia" => 80,

        "uniforme de diario" => 80,
        "uniforme de educacion fisica" => 80,

        "derechos y deberes" => 80,

        "debido proceso" => 80,

        "ruta de atencion" => 80,
        "rutas de atencion" => 80,

        "educacion basica" => 20
    ];

    $mejorFrase = "";

    $mejorPrioridad = -1;

    foreach ($frases as $frase) {

        $valor =
            $prioridad[$frase] ?? 10;

        if ($valor > $mejorPrioridad) {

            $mejorPrioridad =
                $valor;

            $mejorFrase =
                $frase;
        }
    }

    return $mejorFrase;
}


// =====================================================
// CALCULAR PROXIMIDAD
// =====================================================

function calcularProximidad(
    $texto,
    $termino1,
    $termino2
) {

    $posiciones1 = [];

    $posiciones2 = [];

    $offset = 0;

    while (
        ($pos = mb_strpos(
            $texto,
            $termino1,
            $offset,
            "UTF-8"
        )) !== false
    ) {

        $posiciones1[] = $pos;

        $offset =
            $pos +
            mb_strlen(
                $termino1,
                "UTF-8"
            );
    }

    $offset = 0;

    while (
        ($pos = mb_strpos(
            $texto,
            $termino2,
            $offset,
            "UTF-8"
        )) !== false
    ) {

        $posiciones2[] = $pos;

        $offset =
            $pos +
            mb_strlen(
                $termino2,
                "UTF-8"
            );
    }

    if (
        empty($posiciones1) ||
        empty($posiciones2)
    ) {

        return 0;
    }

    $distanciaMinima = PHP_INT_MAX;

    foreach ($posiciones1 as $pos1) {

        foreach ($posiciones2 as $pos2) {

            $distancia =
                abs(
                    $pos1 - $pos2
                );

            if (
                $distancia <
                $distanciaMinima
            ) {

                $distanciaMinima =
                    $distancia;
            }
        }
    }

    if ($distanciaMinima <= 100) {

        return 500;

    } elseif ($distanciaMinima <= 250) {

        return 300;

    } elseif ($distanciaMinima <= 500) {

        return 150;

    } elseif ($distanciaMinima <= 1000) {

        return 50;
    }

    return 0;
}


// =====================================================
// BUSCAR ARTÍCULOS
// =====================================================

function buscarArticulos(
    $articulos,
    $pregunta
) {

    $palabrasBusqueda =
        obtenerPalabrasBusqueda(
            $pregunta
        );

    $frasesImportantes =
        obtenerFrasesImportantes(
            $pregunta
        );

    $frasePrincipal =
        obtenerFrasePrincipal(
            $frasesImportantes
        );

    $preguntaNormalizada =
        normalizarTexto(
            $pregunta
        );

    $resultados = [];

    foreach (
        $articulos as $indice => $articulo
    ) {

        $textoNormalizado =
            normalizarTexto(
                $articulo
            );

        $puntaje = 0;

        $coincidencias = 0;

        $frasesEncontradas = [];

        $proximidad = 0;


        foreach (
            $frasesImportantes as $frase
        ) {

            $textoBusqueda =
                " " .
                $textoNormalizado .
                " ";

            $fraseBusqueda =
                " " .
                $frase .
                " ";

            if (
                mb_strpos(
                    $textoBusqueda,
                    $fraseBusqueda
                ) !== false
            ) {

                if (
                    $frase ===
                    $frasePrincipal
                ) {

                    $puntaje += 700;

                } else {

                    $puntaje += 30;
                }

                $frasesEncontradas[] =
                    $frase;
            }
        }


        foreach (
            $palabrasBusqueda as $palabra
        ) {

            $encontrado =
                preg_match(
                    '/\b' .
                    preg_quote(
                        $palabra,
                        '/'
                    ) .
                    '\b/u',
                    $textoNormalizado
                );

            if ($encontrado) {

                $coincidencias++;

                $puntaje += 2;

                $cantidad =
                    preg_match_all(
                        '/\b' .
                        preg_quote(
                            $palabra,
                            '/'
                        ) .
                        '\b/u',
                        $textoNormalizado
                    );

                if ($cantidad > 1) {

                    $puntaje +=
                        min(
                            $cantidad - 1,
                            3
                        );
                }
            }
        }


        if (
            $frasePrincipal !== ""
        ) {

            $terminosProximidad = [];

            foreach (
                $palabrasBusqueda as $palabra
            ) {

                if (
                    mb_strpos(
                        $frasePrincipal,
                        $palabra,
                        0,
                        "UTF-8"
                    ) !== false
                ) {

                    continue;
                }

                if (
                    mb_strlen(
                        $palabra,
                        "UTF-8"
                    ) >= 5
                ) {

                    $terminosProximidad[] =
                        $palabra;
                }
            }

            foreach (
                $terminosProximidad as $termino
            ) {

                $valorProximidad =
                    calcularProximidad(
                        $textoNormalizado,
                        $frasePrincipal,
                        $termino
                    );

                if (
                    $valorProximidad >
                    $proximidad
                ) {

                    $proximidad =
                        $valorProximidad;
                }
            }

            $puntaje +=
                $proximidad;
        }


        if ($coincidencias >= 2) {
            $puntaje += 4;
        }

        if ($coincidencias >= 3) {
            $puntaje += 5;
        }

        if ($coincidencias >= 4) {
            $puntaje += 6;
        }


        if (
            mb_strpos(
                $preguntaNormalizada,
                "horario",
                0,
                "UTF-8"
            ) !== false
        ) {

            if (
                mb_strpos(
                    $textoNormalizado,
                    "horario",
                    0,
                    "UTF-8"
                ) !== false
            ) {

                $puntaje += 100;
            }
        }


        if ($puntaje > 0) {

            $resultados[] = [

                "articulo" =>
                    $articulo,

                "puntaje" =>
                    $puntaje,

                "coincidencias" =>
                    $coincidencias,

                "frases" =>
                    $frasesEncontradas,

                "proximidad" =>
                    $proximidad,

                "indice" =>
                    $indice
            ];
        }
    }


    usort(
        $resultados,
        function ($a, $b) {

            if (
                $a["puntaje"] !=
                $b["puntaje"]
            ) {

                return
                    $b["puntaje"]
                    -
                    $a["puntaje"];
            }

            if (
                $a["proximidad"] !=
                $b["proximidad"]
            ) {

                return
                    $b["proximidad"]
                    -
                    $a["proximidad"];
            }

            return
                $b["coincidencias"]
                -
                $a["coincidencias"];
        }
    );


    if (
        $frasePrincipal !== ""
    ) {

        $resultadosExactos = [];

        foreach (
            $resultados as $resultado
        ) {

            if (
                in_array(
                    $frasePrincipal,
                    $resultado["frases"]
                )
            ) {

                $resultadosExactos[] =
                    $resultado;
            }
        }

        if (
            !empty(
                $resultadosExactos
            )
        ) {

            usort(
                $resultadosExactos,
                function ($a, $b) {

                    if (
                        $a["puntaje"] !=
                        $b["puntaje"]
                    ) {

                        return
                            $b["puntaje"]
                            -
                            $a["puntaje"];
                    }

                    return
                        $b["proximidad"]
                        -
                        $a["proximidad"];
                }
            );

            return array_slice(
                $resultadosExactos,
                0,
                4
            );
        }
    }


    if (
        !empty($resultados)
    ) {

        $mejorPuntaje =
            $resultados[0]["puntaje"];

        $seleccionados = [];

        foreach (
            $resultados as $resultado
        ) {

            if (
                $resultado["puntaje"]
                >=
                max(
                    2,
                    $mejorPuntaje * 0.5
                )
            ) {

                $seleccionados[] =
                    $resultado;
            }

            if (
                count(
                    $seleccionados
                ) >= 4
            ) {

                break;
            }
        }

        return $seleccionados;
    }

    return [];
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

        $manual =
            leerManual(
                $archivoManual
            );

        if (
            $manual === false
        ) {

            $error =
                "No fue posible leer el Manual de Convivencia.";

        } else {

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

                $resultados =
                    buscarArticulos(
                        $articulos,
                        $pregunta
                    );

                if (
                    !empty($resultados)
                ) {

                    $contexto = "";

                    foreach (
                        $resultados as $resultado
                    ) {

                        $contexto .=
                            $resultado["articulo"];

                        $contexto .=
                            "\n\n";
                    }


                    $respuesta =
                        consultarGemini(
                            $pregunta,
                            $contexto
                        );


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

                } else {

                    $error =
                        "No encontré información relacionada con tu pregunta dentro del Manual de Convivencia.";
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

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f1f5f9;

            color:
                #1e293b;
        }


        .app {

            width: 100%;

            min-height: 100vh;

            display: flex;

            flex-direction: column;
        }


        /* ==========================================
           ENCABEZADO
           ========================================== */

        .header {

            background:
                #063b70;

            color:
                white;

            height:
                78px;

            display: flex;

            align-items: center;

            padding:
                0 35px;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,.15);

            position: relative;

            z-index: 2;
        }


        .header-logo {

            width:
                48px;

            height:
                48px;

            border-radius:
                50%;

            background:
                #ffffff;

            color:
                #063b70;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size:
                20px;

            font-weight:
                bold;

            margin-right:
                14px;
        }


        .header-text h1 {

            margin:
                0;

            font-size:
                20px;

            font-weight:
                700;
        }


        .header-text p {

            margin:
                4px 0 0;

            font-size:
                13px;

            opacity:
                .85;
        }


        /* ==========================================
           AREA PRINCIPAL
           ========================================== */

        .chat-container {

            width:
                100%;

            max-width:
                1000px;

            margin:
                0 auto;

            flex:
                1;

            display: flex;

            flex-direction:
                column;

            padding:
                25px 20px 20px;
        }


        .welcome {

            text-align:
                center;

            padding:
                25px 15px 20px;
        }


        .welcome-icon {

            width:
                65px;

            height:
                65px;

            margin:
                0 auto 15px;

            border-radius:
                50%;

            background:
                #063b70;

            color:
                white;

            display: flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                28px;

            box-shadow:
                0 5px 15px
                rgba(6,59,112,.25);
        }


        .welcome h2 {

            margin:
                0 0 8px;

            color:
                #063b70;

            font-size:
                24px;
        }


        .welcome p {

            max-width:
                650px;

            margin:
                0 auto;

            color:
                #64748b;

            line-height:
                1.6;

            font-size:
                14px;
        }


        /* ==========================================
           ZONA DEL CHAT
           ========================================== */

        .messages {

            flex:
                1;

            display:
                flex;

            flex-direction:
                column;

            gap:
                18px;

            margin-top:
                10px;
        }


        .message {

            display:
                flex;

            width:
                100%;
        }


        .message.user {

            justify-content:
                flex-end;
        }


        .message.assistant {

            justify-content:
                flex-start;
        }


        .message-content {

            max-width:
                78%;

            padding:
                15px 18px;

            border-radius:
                14px;

            line-height:
                1.65;

            font-size:
                15px;

            white-space:
                normal;
        }


        .message.user
        .message-content {

            background:
                #063b70;

            color:
                white;

            border-bottom-right-radius:
                4px;
        }


        .message.assistant
        .message-content {

            background:
                white;

            color:
                #334155;

            border:
                1px solid #e2e8f0;

            border-bottom-left-radius:
                4px;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,.05);
        }


        .message-label {

            display:
                block;

            font-size:
                11px;

            font-weight:
                bold;

            margin-bottom:
                6px;

            opacity:
                .75;
        }


        .message.assistant
        .message-label {

            color:
                #063b70;
        }


        .message.user
        .message-label {

            color:
                rgba(255,255,255,.8);
        }


        /* ==========================================
           ERROR
           ========================================== */

        .error {

            margin:
                20px 0;

            padding:
                14px 17px;

            background:
                #fef2f2;

            border:
                1px solid #fecaca;

            color:
                #b91c1c;

            border-radius:
                10px;

            font-size:
                14px;
        }


        /* ==========================================
           CAJA DE PREGUNTA
           ========================================== */

        .input-area {

            position:
                sticky;

            bottom:
                0;

            padding-top:
                15px;

            background:
                #f1f5f9;
        }


        .input-box {

            background:
                white;

            border:
                1px solid #cbd5e1;

            border-radius:
                14px;

            padding:
                8px;

            display:
                flex;

            align-items:
                flex-end;

            gap:
                8px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,.08);
        }


        .input-box textarea {

            flex:
                1;

            min-height:
                45px;

            max-height:
                150px;

            resize:
                none;

            border:
                none;

            outline:
                none;

            padding:
                12px;

            font-family:
                inherit;

            font-size:
                15px;

            color:
                #1e293b;

            background:
                transparent;
        }


        .input-box textarea::placeholder {

            color:
                #94a3b8;
        }


        .send-button {

            width:
                45px;

            height:
                45px;

            border:
                none;

            border-radius:
                10px;

            background:
                #063b70;

            color:
                white;

            cursor:
                pointer;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                20px;

            transition:
                .2s;
        }


        .send-button:hover {

            background:
                #052f59;

            transform:
                translateY(-1px);
        }


        .input-help {

            text-align:
                center;

            color:
                #94a3b8;

            font-size:
                11px;

            margin-top:
                8px;
        }


        /* ==========================================
           PIE DE PÁGINA
           ========================================== */

        .footer {

            text-align:
                center;

            color:
                #94a3b8;

            font-size:
                11px;

            padding:
                8px 0 12px;
        }


        /* ==========================================
           MÓVILES
           ========================================== */

        @media (max-width: 650px) {

            .header {

                height:
                    70px;

                padding:
                    0 18px;
            }


            .header-logo {

                width:
                    42px;

                height:
                    42px;

                font-size:
                    17px;
            }


            .header-text h1 {

                font-size:
                    16px;
            }


            .header-text p {

                font-size:
                    11px;
            }


            .chat-container {

                padding:
                    15px 12px 12px;
            }


            .welcome {

                padding:
                    18px 10px;
            }


            .welcome-icon {

                width:
                    55px;

                height:
                    55px;

                font-size:
                    23px;
            }


            .welcome h2 {

                font-size:
                    21px;
            }


            .message-content {

                max-width:
                    90%;

                font-size:
                    14px;
            }


            .input-help {

                display:
                    none;
            }
        }

    </style>

</head>


<body>


<div class="app">


    <!-- ==========================================
         ENCABEZADO
         ========================================== -->

    <header class="header">

        <div class="header-logo">
            CG
        </div>

        <div class="header-text">

            <h1>
                Asistente Virtual
            </h1>

            <p>
                I.E.D. Presbítero Carlos Garavito Acosta
            </p>

        </div>

    </header>


    <!-- ==========================================
         CHAT
         ========================================== -->

    <main class="chat-container">


        <?php if ($pregunta === ""): ?>

            <section class="welcome">

                <div class="welcome-icon">
                    💬
                </div>

                <h2>
                    ¡Hola! ¿En qué puedo ayudarte?
                </h2>

                <p>
                    Puedes hacerme preguntas sobre el
                    Manual de Convivencia de la institución.
                    Buscaré la información correspondiente
                    y te daré una respuesta basada en el Manual.
                </p>

            </section>

        <?php endif; ?>


        <section class="messages">


            <?php if ($pregunta !== ""): ?>

                <!-- ==================================
                     PREGUNTA DEL USUARIO
                     ================================== -->

                <div class="message user">

                    <div class="message-content">

                        <span class="message-label">
                            Tú
                        </span>

                        <?= nl2br(
                            htmlspecialchars(
                                $pregunta,
                                ENT_QUOTES,
                                "UTF-8"
                            )
                        ) ?>

                    </div>

                </div>


                <!-- ==================================
                     RESPUESTA DEL ASISTENTE
                     ================================== -->

                <?php if ($respuesta !== ""): ?>

                    <div class="message assistant">

                        <div class="message-content">

                            <span class="message-label">
                                Asistente virtual
                            </span>

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


            <?php endif; ?>


        </section>


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


        <!-- ==========================================
             CAJA DE PREGUNTA
             ========================================== -->

        <div class="input-area">

            <form
                method="POST"
                action="index.php"
                class="input-box"
            >

                <textarea
                    name="pregunta"
                    id="pregunta"
                    placeholder="Escribe tu pregunta sobre el Manual de Convivencia..."
                    required
                ></textarea>


                <button
                    type="submit"
                    class="send-button"
                    title="Enviar pregunta"
                >
                    ➤
                </button>

            </form>


            <div class="input-help">

                Las respuestas se generan utilizando
                la información del Manual de Convivencia.

            </div>

        </div>


        <footer class="footer">

            Asistente Virtual Institucional

        </footer>


    </main>


</div>


<script>

    // =================================================
    // AJUSTAR ALTURA DEL TEXTAREA
    // =================================================

    const textarea =
        document.getElementById("pregunta");


    if (textarea) {

        textarea.addEventListener(
            "input",
            function () {

                this.style.height =
                    "auto";

                this.style.height =
                    Math.min(
                        this.scrollHeight,
                        150
                    ) + "px";
            }
        );


        // =============================================
        // ENTER PARA ENVIAR
        // SHIFT + ENTER = SALTO DE LÍNEA
        // =============================================

        textarea.addEventListener(
            "keydown",
            function (evento) {

                if (
                    evento.key === "Enter" &&
                    !evento.shiftKey
                ) {

                    evento.preventDefault();

                    this.form.submit();
                }
            }
        );

    }

</script>


</body>

</html>