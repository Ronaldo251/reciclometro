<?php
/**
 * Plugin Name: Reciclômetro
 * Description: Exibe uma caixa indicativa com o total de papel reciclado e permite inserção via formulário (armazenado em JSON).
 * Version: 1.2.9
 * Author: Ronaldo Fraga
 */

if (!defined('ABSPATH')) exit;

// Enfileira CSS personalizado
function reciclometro_enqueue_styles() {
    wp_enqueue_style('reciclometro-css', plugins_url('css/estilo.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'reciclometro_enqueue_styles');

// Caminho para o arquivo JSON
function reciclometro_json_path() {
    $upload_dir = wp_upload_dir();
    $data_dir = $upload_dir['basedir'] . '/reciclometro/';

    if (!file_exists($data_dir)) {
        mkdir($data_dir, 0755, true); // Cria a pasta dentro de uploads
    }

    return $data_dir . 'reciclados.json';
}



// Shortcode para exibir o total de papel reciclado
function reciclometro_exibir_banner() {
    $json_path = reciclometro_json_path();
    $total = 0;

    if (file_exists($json_path)) {
        $dados = json_decode(file_get_contents($json_path), true);
        if (is_array($dados)) {
            foreach ($dados as $registro) {
                $total += floatval($registro['quilos'] ?? 0);
            }
        }
    }

    $total_formatado = number_format($total, 2, ',', '.');

    return "<div class='reciclometro-banner'>
                <h3>♻️ Papel Reciclado</h3>
                <p><strong>{$total_formatado} kg</strong> já reciclados!</p>
            </div>";
}
add_shortcode('reciclometro', 'reciclometro_exibir_banner');

// Shortcode para o formulário com autenticação
function reciclometro_formulario_json() {
    if (!is_user_logged_in()) {
        return '<p>🔒 Por favor, <a href="' . wp_login_url(get_permalink()) . '">faça login</a> para acessar o formulário.</p>';
    }

    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    if (!array_intersect($roles, ['administrator', 'editor', 'author'])) {
        return '<p>🚫 Acesso restrito. Apenas administradores, editores e autores podem registrar reciclagem.</p>';
    }


    if (isset($_POST['rec_local']) && isset($_POST['rec_quilos'])) {
        $local = sanitize_text_field($_POST['rec_local']);
        $quilos = floatval($_POST['rec_quilos']);
        $json_path = reciclometro_json_path();

        $dados = [];
        if (file_exists($json_path)) {
            $dados = json_decode(file_get_contents($json_path), true);
            if (!is_array($dados)) $dados = [];
        }

        $dados[] = [
            'local' => $local,
            'quilos' => $quilos,
            'data' => current_time('mysql')
        ];

        file_put_contents($json_path, json_encode($dados, JSON_PRETTY_PRINT));

        // Redireciona para evitar reenvio
        wp_redirect(add_query_arg('reciclometro_sucesso', '1', wp_get_referer()));
        exit;  // MUITO IMPORTANTE terminar o script aqui
    }

    // Mostrar mensagem de sucesso após redirecionamento
    if (isset($_GET['reciclometro_sucesso'])) {
        echo '<p style="color:green">✅ Registro salvo com sucesso!</p>';
    }

    return '
        <form method="post" class="reciclometro-form">
            <label>Local:<br><input type="text" name="rec_local" required></label><br>
            <label>Quilos reciclados:<br><input type="number" step="1" name="rec_quilos" required></label><br><br>
            <button type="submit">Registrar Reciclagem</button>
        </form>
    ';
}

add_shortcode('form_reciclometro', 'reciclometro_formulario_json');