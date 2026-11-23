<?php
/** IMP-35 — superficie aislada de descarga: sin tema, scripts de terceros ni token en URL HTTP. */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Download_View
{
    private static bool $registered=false;

    public static function registerHooks(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        \add_action('template_redirect',[self::class,'render'],0);
    }

    public static function pageUrl(): string
    {
        if (!\is_ssl()) {
            throw new Transaction_Failure('pc_digital_reissue_unavailable');
        }
        $page=\get_page_by_path('descarga',OBJECT,'page');
        if (!$page instanceof \WP_Post || 'publish'!==$page->post_status) {
            throw new Transaction_Failure('pc_digital_reissue_unavailable');
        }
        $url=\get_permalink($page->ID);
        $host=\wp_parse_url(\home_url('/'));
        $target=\is_string($url)?\wp_parse_url($url):false;
        if (!\is_string($url) || !\is_array($host)
            || !\is_array($target)
            || 'https'!==($target['scheme']??null)
            || \strtolower((string)($target['host']??''))!==
                \strtolower((string)($host['host']??''))
            || ($target['port']??443)!==($host['port']??443)
            || isset($target['user']) || isset($target['pass'])
            || isset($target['fragment'])) {
            throw new Transaction_Failure('pc_digital_reissue_unavailable');
        }
        return $url;
    }

    public static function render(): void
    {
        if (!\is_page('descarga')) { return; }
        $page=\get_queried_object();
        if (!$page instanceof \WP_Post || 'page'!==$page->post_type
            || 'publish'!==$page->post_status) { return; }
        if (!\is_ssl() || \headers_sent()) {
            \status_header(503);
            exit;
        }
        \status_header(200);
        \header('Content-Type: text/html; charset=UTF-8');
        \header('Cache-Control: private, no-store, max-age=0');
        \header('Pragma: no-cache');
        \header('Expires: 0');
        \header('Referrer-Policy: no-referrer');
        \header('X-Content-Type-Options: nosniff');
        \header('X-Frame-Options: DENY');
        \header('X-Robots-Tag: noindex, nofollow');
        \header('Content-Security-Policy: default-src \'none\'; '
            ."script-src 'self'; style-src 'self'; connect-src 'self'; "
            ."img-src 'none'; base-uri 'none'; form-action 'none'; "
            ."frame-ancestors 'none'; object-src 'none'; upgrade-insecure-requests");
        $script=\plugins_url('assets/js/digital-download.js',PERUCOMMERCE_PLUGIN_FILE);
        $style=\plugins_url('assets/css/digital-download.css',PERUCOMMERCE_PLUGIN_FILE);
        $exchange=\rest_url('peru-commerce/v1/guest/digital/sessions');
        $template=\rest_url('peru-commerce/v1/guest/digital/__RIGHT_ID__/file');
        echo '<!doctype html><html lang="es"><head>'
            .'<meta charset="utf-8"><meta name="viewport" '
            .'content="width=device-width, initial-scale=1">'
            .'<meta name="referrer" content="no-referrer">'
            .'<title>Descarga privada — PeruCommerce</title>'
            .'<link rel="stylesheet" href="'.\esc_url($style).'">'
            .'<script src="'.\esc_url($script).'" defer></script>'
            .'</head><body><main class="pc-download" id="pc-download" '
            .'data-exchange="'.\esc_attr($exchange).'" '
            .'data-file-template="'.\esc_attr($template).'">'
            .'<h1>Tu descarga digital</h1>'
            .'<p>El enlace dura 24 horas; una vez vencido, puedes solicitar '
            .'una reemisión al comercio tras verificar tu identidad.</p>'
            .'<p id="pc-download-status" role="status" aria-live="polite">'
            .'Verificando el enlace privado…</p>'
            .'<a id="pc-download-action" hidden '
            .'rel="noreferrer" aria-label="Descargar archivo digital">'
            .'Descargar archivo</a>'
            .'<p>El límite de descargas, cuando está configurado, se '
            .'descuenta al iniciar una transferencia autorizada. '
            .'Una desconexión posterior puede consumir un uso.</p>'
            .'</main></body></html>';
        exit;
    }
}
