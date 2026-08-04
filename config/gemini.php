<?php

function consultarGemini($pregunta)
{
    // ===========================================
    // CONFIGURACIÓN
    // ===========================================

    $apiKey = "AIzaSyDV0lJUKEhRpVZbWFScHPHGNM3L7uGltkQ";

    $modelo = "gemini-2.5-flash-lite";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/" .
            $modelo .
            ":generateContent?key=" .
            $apiKey;

    // ===========================================
    // PROMPT
    // ===========================================

    $datos = [

        "contents" => [

            [

                "parts" => [

                    [

                        "text" => $pregunta

                    ]

                ]

            ]

        ]

    ];

    // ===========================================
    // PETICIÓN
    // ===========================================

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [

        "Content-Type: application/json"

    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));

    $respuesta = curl_exec($ch);

    // Error de CURL

    if(curl_errno($ch)){

        return "Error CURL: ".curl_error($ch);

    }

    // Código HTTP

    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // Decodificar JSON

    $json = json_decode($respuesta,true);

    // Si la API devuelve error

    if(isset($json["error"])){

        return "Error ".$json["error"]["code"].": ".$json["error"]["message"];

    }

    // Respuesta correcta

    if(isset($json["candidates"][0]["content"]["parts"][0]["text"])){

        return $json["candidates"][0]["content"]["parts"][0]["text"];

    }

    // Depuración

    return "<pre>".print_r($json,true)."</pre>";

}

?>