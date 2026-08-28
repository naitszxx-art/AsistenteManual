<?php
/**
 * config/gemini.php
 *
 * Configuración segura para Google Gemini.
 *
 * La API Key NO se almacena en este archivo.
 * Se obtiene desde la variable GOOGLE_API_KEY del archivo .env.
 */

declare(strict_types=1);

/**
 * Carga las variables del archivo .env ubicado en la raíz del proyecto.
 *
 * Estructura esperada:
 * ASISTENTEMANUAL/
 * ├── .env
 * ├── config/
 * │   └── gemini.php
 * └── index.php
 */
function cargarVariablesEnv(): void
{
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envFile) || !is_readable($envFile)) {
        throw new RuntimeException(
            'No se encontró el archivo .env en la raíz del proyecto.'
        );
    }

    $lineas = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lineas === false) {
        throw new RuntimeException('No fue posible leer el archivo .env.');
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        // Ignorar comentarios.
        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        // Separar únicamente en el primer "=".
        $posicion = strpos($linea, '=');

        if ($posicion === false) {
            continue;
        }

        $nombre = trim(substr($linea, 0, $posicion));
        $valor  = trim(substr($linea, $posicion + 1));

        if ($nombre === '') {
            continue;
        }

        // Quitar comillas simples o dobles que rodeen el valor.
        if (
            strlen($valor) >= 2 &&
            (
                ($valor[0] === '"' && $valor[strlen($valor) - 1] === '"') ||
                ($valor[0] === "'" && $valor[strlen($valor) - 1] === "'")
            )
        ) {
            $valor = substr($valor, 1, -1);
        }

        // No sobrescribir una variable de entorno ya existente.
        if (getenv($nombre) === false) {
            putenv($nombre . '=' . $valor);
        }
    }
}

cargarVariablesEnv();

/**
 * API Key de Google Gemini.
 */
$GOOGLE_API_KEY = getenv('GOOGLE_API_KEY');

if ($GOOGLE_API_KEY === false || trim($GOOGLE_API_KEY) === '') {
    throw new RuntimeException(
        'La variable GOOGLE_API_KEY no está configurada en el archivo .env.'
    );
}

/**
 * Modelo utilizado por el asistente.
 *
 * Puedes cambiarlo desde .env agregando:
 * GEMINI_MODEL=gemini-3.5-flash
 */
$GEMINI_MODEL = getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash';

/**
 * Consulta Google Gemini con sistema de reintentos automáticos para errores temporales (HTTP 503 / 429).
 *
 * @param string $prompt Pregunta/instrucción que se enviará a Gemini.
 * @param string|null $systemInstruction Instrucción opcional para definir el comportamiento.
 * @return string Respuesta de texto de Gemini.
 */
function consultarGemini(
    string $prompt,
    ?string $systemInstruction = null
): string {
    global $GOOGLE_API_KEY, $GEMINI_MODEL;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($GEMINI_MODEL)
        . ':generateContent';

    $contenido = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ]
    ];

    if ($systemInstruction !== null && trim($systemInstruction) !== '') {
        $contenido['system_instruction'] = [
            'parts' => [
                [
                    'text' => $systemInstruction
                ]
            ]
        ];
    }

    $payload = json_encode(
        $contenido,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($payload === false) {
        throw new RuntimeException(
            'No fue posible preparar la solicitud para Gemini.'
        );
    }

    // Configuración de reintentos
    $maxIntentos = 3;
    $esperaSegundos = 2;

    for ($intento = 1; $intento <= $maxIntentos; $intento++) {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException(
                'No fue posible inicializar cURL.'
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $GOOGLE_API_KEY
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60
        ]);

        $respuesta = curl_exec($ch);
        $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);

        curl_close($ch);

        if ($respuesta === false) {
            if ($intento < $maxIntentos) {
                sleep($esperaSegundos);
                continue;
            }
            throw new RuntimeException(
                'Error de conexión con Google Gemini: ' . $errorCurl
            );
        }

        $datos = json_decode($respuesta, true);

        if (!is_array($datos)) {
            if ($intento < $maxIntentos) {
                sleep($esperaSegundos);
                continue;
            }
            throw new RuntimeException(
                'Gemini devolvió una respuesta que no tiene formato JSON válido.'
            );
        }

        // Si la respuesta fue exitosa (200 OK)
        if ($codigoHttp >= 200 && $codigoHttp < 300) {
            $texto = $datos['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!is_string($texto) || trim($texto) === '') {
                throw new RuntimeException(
                    'Gemini no devolvió contenido de texto.'
                );
            }

            return trim($texto);
        }

        // Si es un error de saturación (503) o límite de tasa (429), se reintenta
        if (($codigoHttp === 503 || $codigoHttp === 429) && $intento < $maxIntentos) {
            sleep($esperaSegundos);
            $esperaSegundos *= 2; // Incrementa el tiempo de espera gradualmente
            continue;
        }

        // Para cualquier otro error permanente
        $mensaje = $datos['error']['message'] ?? 'Error desconocido de Google Gemini.';
        throw new RuntimeException(
            'Error de Gemini (HTTP ' . $codigoHttp . '): ' . $mensaje
        );
    }

    throw new RuntimeException('No fue posible comunicarse con Gemini tras varios intentos.');
}
?>