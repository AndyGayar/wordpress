<?php
/**
 * Plugin Name: Taller Coches Manager
 * Description: Plugin para gestionar coches y servicios de un taller mecánico.
 * Version: 1.0
 * Author: GitHub Copilot
 * Author URI: https://github.com/features/copilot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Incluir funciones de base de datos compartimentadas
require_once plugin_dir_path(__FILE__) . 'includes/db-functions.php';

// Incluir shortcodes compartimentados
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';

/**
 * Función que se ejecuta al activar el plugin.
 * Crea las tablas necesarias en la base de datos.
 */
function taller_coches_manager_activar() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Nombre de la tabla de usuarios de WordPress
    $table_name_users = $wpdb->prefix . 'users';

    // SQL para crear la tabla de coches
    $table_name_coches = $wpdb->prefix . 'taller_coches';
    $sql_coches = "CREATE TABLE $table_name_coches (
        id_coche INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario BIGINT(20) UNSIGNED NOT NULL COMMENT 'ID del usuario de WordPress propietario del coche',
        marca VARCHAR(100) NOT NULL COMMENT 'Marca del coche (Ej: Ford, Toyota)',
        modelo VARCHAR(100) NOT NULL COMMENT 'Modelo del coche (Ej: Focus, Corolla)',
        anio INT COMMENT 'Año de fabricación del coche',
        matricula VARCHAR(20) UNIQUE NOT NULL COMMENT 'Matrícula del coche',
        vin VARCHAR(50) UNIQUE DEFAULT NULL COMMENT 'Número de Identificación Vehicular (VIN)',
        fecha_registro_coche TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha en que se registró el coche en el sistema',
        FOREIGN KEY (id_usuario) REFERENCES {$table_name_users}(ID) ON DELETE CASCADE
    ) $charset_collate;";

    // SQL para crear la tabla de servicios
    $table_name_servicios = $wpdb->prefix . 'taller_servicios';
    $sql_servicios = "CREATE TABLE $table_name_servicios (
        id_servicio INT AUTO_INCREMENT PRIMARY KEY,
        id_coche INT NOT NULL COMMENT 'ID del coche al que pertenece este servicio',
        fecha_entrada TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha en que el coche ingresó para el servicio',
        descripcion_solicitud TEXT COMMENT 'Descripción del problema o servicio solicitado por el cliente',
        estado_servicio VARCHAR(100) DEFAULT 'Pendiente de revisión' COMMENT 'Estado actual del servicio (Ej: Pendiente de revisión, En diagnóstico, Esperando aprobación, En reparación, Listo para recoger, Entregado)',
        notas_internas TEXT COMMENT 'Notas para el personal del taller, no visibles para el cliente',
        fecha_ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de la última actualización del estado del servicio',
        FOREIGN KEY (id_coche) REFERENCES {$table_name_coches}(id_coche) ON DELETE CASCADE
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_coches );
    dbDelta( $sql_servicios );
}
register_activation_hook( __FILE__, 'taller_coches_manager_activar' );
?>
