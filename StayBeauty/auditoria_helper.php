<?php
/**
 * auditoria_helper.php
 * ---------------------
 * Los 4 triggers de la base de datos (tr_aud_reserva_insert,
 * tr_aud_reserva_update, tr_aud_reserva_delete, tr_aud_servicio_update)
 * siguen existiendo en el archivo .sql tal como se diseñaron - ese sigue
 * siendo el mecanismo real en cualquier base de datos que SI permita
 * triggers (como tu XAMPP local).
 *
 * Pero varios hostings gratuitos (InfinityFree incluido) no dan permiso
 * para crear triggers en bases de datos gratuitas (#1142 - TRIGGER
 * comando denegado). Esta funcion hace exactamente lo mismo que hace
 * cada trigger, pero llamada desde PHP, para que la auditoria siga
 * funcionando igual en esos hostings.
 *
 * OJO: si corres esto en una base de datos que SI tiene los triggers
 * activos (tu XAMPP local), vas a ver el registro DOS veces (uno del
 * trigger, uno de esta funcion) - es solo un duplicado visual en tu
 * ambiente local, no afecta el sitio en vivo ni ningun otro dato.
 */
function registrarAuditoriaPHP($pdo, $tabla, $idRegistro, $operacion, $idUsuario, $detalle) {
    try {
        $pdo->prepare("INSERT INTO auditoria (tabla_afectada, id_registro, operacion, id_usuario, detalle)
                        VALUES (?, ?, ?, ?, ?)")
            ->execute([$tabla, $idRegistro, $operacion, $idUsuario, $detalle]);
    } catch (PDOException $e) {
        error_log("No se pudo registrar auditoria: " . $e->getMessage());
    }
}
?>
