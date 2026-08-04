<?php

function guardarConversacion($conn,$pregunta,$respuesta){

    $sql="INSERT INTO conversaciones(pregunta,respuesta,ip_usuario)

          VALUES(?,?,?)";

    $stmt=$conn->prepare($sql);

    $ip=$_SERVER['REMOTE_ADDR'];

    $stmt->bind_param("sss",$pregunta,$respuesta,$ip);

    $stmt->execute();

}