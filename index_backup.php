<?php
include("chat.php");
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Asistente Virtual | Manual de Convivencia</title>

    <link rel="stylesheet" href="css/estilos.css">

</head>

<body>

<div class="contenedor">

    <header>

        <img src="img/logo.png" class="logo" alt="Logo del Colegio">

        <div>

            <h1>Asistente Virtual</h1>

            <h3>Manual de Convivencia</h3>

        </div>

    </header>

    <main>

        <div class="tarjeta">

            <h2>¡Bienvenido!</h2>

            <p>
                Este asistente responderá las preguntas relacionadas con el
                Manual de Convivencia del colegio utilizando Inteligencia Artificial.
            </p>

        </div>

        <div class="tarjeta">

            <form action="" method="POST">

                <label for="pregunta">Escribe tu pregunta</label>

                <textarea
                    id="pregunta"
                    name="pregunta"
                    placeholder="Ejemplo: ¿Qué sucede si un estudiante llega tarde?"
                    required><?php echo isset($pregunta) ? htmlspecialchars($pregunta) : ""; ?></textarea>

                <button type="submit">
                    Consultar
                </button>

            </form>

        </div>

        <div class="tarjeta respuesta">

            <h2>Respuesta</h2>

            <?php if (!empty($respuesta)) : ?>

                <p><?php echo nl2br(htmlspecialchars($respuesta)); ?></p>

            <?php else : ?>

                <p class="gris">
                    La respuesta aparecerá aquí después de realizar una consulta.
                </p>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>

</html>