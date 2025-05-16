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

// Shortcode para mostrar el formulario de registro de coches
function taller_coches_formulario_registro() {
    if ( !is_user_logged_in() ) {
        return '<p>Debes iniciar sesión para registrar un coche.</p>';
    }

    // Procesar el formulario si se ha enviado
    if ( isset($_POST['taller_registrar_coche_nonce']) && wp_verify_nonce($_POST['taller_registrar_coche_nonce'], 'taller_registrar_coche') ) {
        $marca = sanitize_text_field($_POST['marca']);
        $modelo = sanitize_text_field($_POST['modelo']);
        $anio = intval($_POST['anio']);
        $matricula = sanitize_text_field($_POST['matricula']);
        $vin = sanitize_text_field($_POST['vin']);
        $user_id = get_current_user_id();

        global $wpdb;
        $table_name = $wpdb->prefix . 'taller_coches';
        $existe = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE matricula = %s", $matricula));
        if ($existe > 0) {
            $mensaje = '<div style="color:red;">Ya existe un coche con esa matrícula.</div>';
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'id_usuario' => $user_id,
                    'marca' => $marca,
                    'modelo' => $modelo,
                    'anio' => $anio,
                    'matricula' => $matricula,
                    'vin' => $vin
                ]
            );
            $mensaje = '<div style="color:green;">Coche registrado correctamente.</div>';
        }
    }

    ob_start();
    if (isset($mensaje)) echo $mensaje;
    ?>
    <form method="post">
        <?php wp_nonce_field('taller_registrar_coche', 'taller_registrar_coche_nonce'); ?>
        <p><label>Marca: <input type="text" name="marca" required></label></p>
        <p><label>Modelo: <input type="text" name="modelo" required></label></p>
        <p><label>Año: <input type="number" name="anio" min="1900" max="2100"></label></p>
        <p><label>Matrícula: <input type="text" name="matricula" required></label></p>
        <p><label>VIN: <input type="text" name="vin"></label></p>
        <p><input type="submit" value="Registrar coche"></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('taller_registro_coche', 'taller_coches_formulario_registro');

// Shortcode para mostrar la lista de coches del usuario logueado y su estado
function taller_coches_mis_coches() {
    if ( !is_user_logged_in() ) {
        return '<p>Debes iniciar sesión para ver tus coches.</p>';
    }
    global $wpdb;
    $user_id = get_current_user_id();

    // Obtener los coches del usuario
    $coches = tcm_get_coches_usuario($user_id);

    ob_start();
    if (empty($coches)) {
        echo '<p>No tienes coches registrados.</p>';
    } else {
        echo '<table style="width:100%;border-collapse:collapse;">';
        echo '<tr><th>Marca</th><th>Modelo</th><th>Año</th><th>Matrícula</th><th>VIN</th><th>Estado actual</th><th>Historial</th><th>Solicitar servicio</th><th>Eliminar coche</th></tr>';
        foreach ($coches as $coche) {
            // Obtener el último estado del coche (último servicio)
            $servicio = null;
            $servicios_coche = tcm_get_servicios_coche($coche->id_coche);
            if (!empty($servicios_coche)) {
                $servicio = $servicios_coche[0];
            }
            $estado = $servicio ? esc_html($servicio->estado_servicio) : 'Sin servicios registrados';
            echo '<tr>';
            echo '<td>' . esc_html($coche->marca) . '</td>';
            echo '<td>' . esc_html($coche->modelo) . '</td>';
            echo '<td>' . esc_html($coche->anio) . '</td>';
            echo '<td>' . esc_html($coche->matricula) . '</td>';
            echo '<td>' . esc_html($coche->vin) . '</td>';
            echo '<td>' . $estado . '</td>';
            // Botón para ver historial de servicios
            echo '<td><button type="button" onclick="tallerVerHistorial(' . $coche->id_coche . ')">Ver historial</button></td>';
            // Formulario para solicitar servicio
            echo '<td>';
            echo '<form method="post" style="margin:0;display:inline;">';
            wp_nonce_field('taller_solicitar_servicio_' . $coche->id_coche, 'taller_solicitar_servicio_nonce_' . $coche->id_coche);
            echo '<input type="hidden" name="id_coche" value="' . esc_attr($coche->id_coche) . '">';
            echo '<input type="text" name="descripcion_solicitud" placeholder="Motivo o problema" required style="width:120px;"> ';
            echo '<input type="submit" name="solicitar_servicio_' . $coche->id_coche . '" value="Solicitar">';
            echo '</form>';
            echo '</td>';
            // Formulario para eliminar coche
            echo '<td>';
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field('taller_eliminar_coche_' . $coche->id_coche, 'taller_eliminar_coche_nonce_' . $coche->id_coche);
            echo '<input type="submit" name="eliminar_coche_' . $coche->id_coche . '" value="Eliminar" onclick="return confirm(\'¿Seguro que quieres eliminar este coche?\');">';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        ?>
        <script>
        function tallerVerHistorial(idCoche) {
            var win = window.open('?taller_historial_coche=' + idCoche, '_blank');
            win.focus();
        }
        </script>
        <?php
    }
    // Procesar solicitud de servicio
    foreach ($coches as $coche) {
        if (
            isset($_POST['solicitar_servicio_' . $coche->id_coche]) &&
            isset($_POST['taller_solicitar_servicio_nonce_' . $coche->id_coche]) &&
            wp_verify_nonce($_POST['taller_solicitar_servicio_nonce_' . $coche->id_coche], 'taller_solicitar_servicio_' . $coche->id_coche)
        ) {
            $descripcion = sanitize_text_field($_POST['descripcion_solicitud']);
            tcm_insertar_servicio($coche->id_coche, $descripcion);
            echo '<div style="color:green;">Solicitud de servicio registrada para el coche ' . esc_html($coche->matricula) . '.</div>';
        }
    }
    // Procesar eliminación de coche
    foreach ($coches as $coche) {
        if (
            isset($_POST['eliminar_coche_' . $coche->id_coche]) &&
            isset($_POST['taller_eliminar_coche_nonce_' . $coche->id_coche]) &&
            wp_verify_nonce($_POST['taller_eliminar_coche_nonce_' . $coche->id_coche], 'taller_eliminar_coche_' . $coche->id_coche)
        ) {
            tcm_eliminar_coche($coche->id_coche, $user_id);
            echo '<div style="color:red;">Coche eliminado correctamente.</div>';
            // Recargar la página para actualizar la lista
            echo '<meta http-equiv="refresh" content="0">';
            return ob_get_clean();
        }
    }
    // Mostrar historial si se solicita por GET
    if (isset($_GET['taller_historial_coche'])) {
        $id_coche = intval($_GET['taller_historial_coche']);
        // Comprobar que el coche pertenece al usuario
        $coche = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}taller_coches WHERE id_coche = %d AND id_usuario = %d", $id_coche, $user_id));
        if ($coche) {
            echo '<h3>Historial de servicios para ' . esc_html($coche->marca) . ' ' . esc_html($coche->modelo) . ' (' . esc_html($coche->matricula) . ')</h3>';
            $servicios = tcm_get_servicios_coche($id_coche);
            if ($servicios) {
                echo '<ul>';
                foreach ($servicios as $servicio) {
                    echo '<li><strong>' . esc_html($servicio->fecha_entrada) . ':</strong> ' . esc_html($servicio->estado_servicio) . ' - ' . esc_html($servicio->descripcion_solicitud) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>Este coche no tiene servicios registrados.</p>';
            }
        } else {
            echo '<p>No tienes permiso para ver el historial de este coche.</p>';
        }
    }
    return ob_get_clean();
}
add_shortcode('taller_mis_coches', 'taller_coches_mis_coches');
?>
