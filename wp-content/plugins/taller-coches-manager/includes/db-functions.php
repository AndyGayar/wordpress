<?php
// Funciones relacionadas con la base de datos del plugin Taller Coches Manager
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function tcm_get_coches_usuario($user_id) {
    global $wpdb;
    $table_coches = $wpdb->prefix . 'taller_coches';
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_coches WHERE id_usuario = %d", $user_id));
}

function tcm_get_servicios_coche($id_coche) {
    global $wpdb;
    $table_servicios = $wpdb->prefix . 'taller_servicios';
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_servicios WHERE id_coche = %d ORDER BY fecha_entrada DESC", $id_coche));
}

function tcm_insertar_servicio($id_coche, $descripcion) {
    global $wpdb;
    $table_servicios = $wpdb->prefix . 'taller_servicios';
    return $wpdb->insert(
        $table_servicios,
        [
            'id_coche' => $id_coche,
            'descripcion_solicitud' => $descripcion,
            'estado_servicio' => 'Pendiente de revisión'
        ]
    );
}

function tcm_eliminar_coche($id_coche, $user_id) {
    global $wpdb;
    $table_coches = $wpdb->prefix . 'taller_coches';
    return $wpdb->delete($table_coches, ['id_coche' => $id_coche, 'id_usuario' => $user_id]);
}
