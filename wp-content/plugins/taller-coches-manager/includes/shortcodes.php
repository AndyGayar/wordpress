<?php
// Shortcodes del plugin Taller Coches Manager
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Shortcode para mostrar el formulario de registro de coches
default function taller_coches_formulario_registro() {
    if ( !is_user_logged_in() ) {
        return '<p>Debes iniciar sesión para registrar un coche.</p>';
    }
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
    $table_coches = $wpdb->prefix . 'taller_coches';
    $table_servicios = $wpdb->prefix . 'taller_servicios';
    $coches = tcm_get_coches_usuario($user_id);
    ob_start();
    if (empty($coches)) {
        echo '<p>No tienes coches registrados.</p>';
    } else {
        echo '<table style="width:100%;border-collapse:collapse;">';
        echo '<tr><th>Marca</th><th>Modelo</th><th>Año</th><th>Matrícula</th><th>VIN</th><th>Estado actual</th><th>Historial</th><th>Solicitar servicio</th><th>Eliminar coche</th></tr>';
        foreach ($coches as $coche) {
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
            echo '<td><button type="button" onclick="tallerVerHistorial(' . $coche->id_coche . ')">Ver historial</button></td>';
            echo '<td>';
            echo '<form method="post" style="margin:0;display:inline;">';
            wp_nonce_field('taller_solicitar_servicio_' . $coche->id_coche, 'taller_solicitar_servicio_nonce_' . $coche->id_coche);
            echo '<input type="hidden" name="id_coche" value="' . esc_attr($coche->id_coche) . '">';
            echo '<input type="text" name="descripcion_solicitud" placeholder="Motivo o problema" required style="width:120px;"> ';
            echo '<input type="submit" name="solicitar_servicio_' . $coche->id_coche . '" value="Solicitar">';
            echo '</form>';
            echo '</td>';
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
    foreach ($coches as $coche) {
        if (
            isset($_POST['eliminar_coche_' . $coche->id_coche]) &&
            isset($_POST['taller_eliminar_coche_nonce_' . $coche->id_coche]) &&
            wp_verify_nonce($_POST['taller_eliminar_coche_nonce_' . $coche->id_coche], 'taller_eliminar_coche_' . $coche->id_coche)
        ) {
            tcm_eliminar_coche($coche->id_coche, $user_id);
            echo '<div style="color:red;">Coche eliminado correctamente.</div>';
            echo '<meta http-equiv="refresh" content="0">';
            return ob_get_clean();
        }
    }
    if (isset($_GET['taller_historial_coche'])) {
        $id_coche = intval($_GET['taller_historial_coche']);
        $coche = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_coches WHERE id_coche = %d AND id_usuario = %d", $id_coche, $user_id));
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
