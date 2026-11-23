<?php
/**
 * IMP-31 — plantilla mínima autónoma de /seguimiento.
 *
 * No usa theme, wp_head/wp_footer, widgets, tiles, terceros ni analytics:
 * ningún script ajeno puede leer el fragmento antes de que el JS propio
 * lo retire del historial. La página debe existir y estar publicada.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Guest_Tracking_View
{
    private static bool $registered=false;

    public static function registerHooks(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        \add_action('template_redirect',[self::class,'render'],0);
    }

    public static function render(): void
    {
        if (!\is_page('seguimiento')) { return; }
        $page=\get_queried_object();
        if (!$page instanceof \WP_Post
            || $page->post_type!=='page'
            || $page->post_status!=='publish') {
            return;
        }
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
        \header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        \header("Content-Security-Policy: default-src 'none'; script-src 'self'; "
            . "style-src 'self'; connect-src 'self'; img-src 'none'; "
            . "base-uri 'none'; form-action 'none'; frame-ancestors 'none'; "
            . "object-src 'none'; upgrade-insecure-requests");
        $script=\plugins_url('assets/js/guest-tracking.js',
            PERUCOMMERCE_PLUGIN_FILE);
        $actions=\plugins_url('assets/js/guest-actions.js', PERUCOMMERCE_PLUGIN_FILE);
        $challenge=\rest_url('peru-commerce/v1/guest/challenges');
        $verify=\rest_url('peru-commerce/v1/guest/challenges/verify');
        $requests=\rest_url('peru-commerce/v1/guest/requests');
        $style=\plugins_url('assets/css/guest-tracking.css',
            PERUCOMMERCE_PLUGIN_FILE);
        $exchange=\rest_url('peru-commerce/v1/guest/sessions');
        $order=\rest_url('peru-commerce/v1/guest/order');
        echo '<!doctype html><html lang="es"><head>'
            . '<meta charset="utf-8"><meta name="viewport" '
            . 'content="width=device-width, initial-scale=1">'
            . '<meta name="referrer" content="no-referrer">'
            . '<title>Seguimiento privado — PeruCommerce</title>'
            . '<link rel="stylesheet" href="'.\esc_url($style).'">'
            . '<script src="'.\esc_url($script).'" defer></script>'
            . '<script src="'.\esc_url($actions).'" defer></script>'
            . '</head><body><main class="pc-track" id="pc-track" '
            . 'data-exchange="'.\esc_attr($exchange).'" '
            . 'data-order="'.\esc_attr($order).'"'
            . ' data-challenge="'.\esc_attr($challenge).'"'
            . ' data-verify="'.\esc_attr($verify).'"'
            . ' data-guest-requests="'.\esc_attr($requests).'">'
            . '<header><p class="pc-track__eyebrow">PeruCommerce</p>'
            . '<h1>Seguimiento privado</h1>'
            . '<p>Consulta únicamente la información de tu pedido.</p>'
            . '</header>'
            . '<section aria-live="polite" aria-atomic="true" '
            . 'id="pc-track-status"><p>Verificando acceso…</p></section>'
            . '<section id="pc-track-details" hidden '
            . 'aria-label="Detalle del pedido"></section>'
            . '<div class="pc-track__actions"><button type="button" '
            . 'id="pc-track-refresh" hidden>Actualizar estado</button></div>'
            . '<section id="pc-guest-actions" class="pc-track__panel" hidden>'
            . '<h2>Solicitar una gestión</h2>'
            . '<p>Las solicitudes no modifican por sí mismas el pedido. '
            . 'Si solo cuentas con teléfono, solicita una revisión manual al comercio.</p>'
            . '<label for="pc-guest-purpose">Gestión</label>'
            . '<select id="pc-guest-purpose">'
            . '<option value="reissue_tracking">Recuperar seguimiento</option>'
            . '<option value="delivery_change">Solicitar cambio de dirección</option>'
            . '<option value="request_refund">Solicitar revisión de reembolso</option>'
            . '<option value="reissue_download">Solicitar nuevo enlace digital</option>'
            . '<option value="resend_notification">Solicitar reenvío de aviso</option>'
            . '</select>'
            . '<div id="pc-guest-address-fields" hidden>'
            . '<label for="pc-guest-ubigeo">Nuevo distrito UBIGEO (6 dígitos)</label>'
            . '<input id="pc-guest-ubigeo" inputmode="numeric" maxlength="6">'
            . '<label for="pc-guest-address">Nueva dirección</label>'
            . '<textarea id="pc-guest-address" maxlength="500"></textarea></div>'
            . '<label for="pc-guest-reason">Motivo de la solicitud (opcional)</label>'
            . '<textarea id="pc-guest-reason" maxlength="1000"></textarea>'
            . '<div id="pc-guest-action-status" role="status" aria-live="polite"></div>'
            . '<div class="pc-track__actions"><button type="button" '
            . 'id="pc-guest-send-code">Solicitar código al correo original</button></div>'
            . '<div id="pc-guest-verify-panel" hidden>'
            . '<label for="pc-guest-code">Código de 8 dígitos</label>'
            . '<input id="pc-guest-code" inputmode="numeric" maxlength="8" '
            . 'autocomplete="one-time-code" pattern="[0-9]{8}">'
            . '<div class="pc-track__actions"><button type="button" '
            . 'id="pc-guest-verify-code">Verificar código</button></div></div>'
            . '<div id="pc-guest-submit-panel" hidden>'
            . '<div class="pc-track__actions"><button type="button" '
            . 'id="pc-guest-submit">Enviar solicitud a revisión</button></div></div>'
            . '</section>'
            . '<footer>Este enlace no autoriza cancelaciones, devoluciones '
            . 'ni cambios de datos. Para esas gestiones se requiere '
            . 'verificación adicional con el comercio.</footer>'
            . '</main></body></html>';
        exit;
    }
}
