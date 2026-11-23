<?php
/**
 * IMP-29 — R8-17 POST /webhooks/mercado-pago.
 *
 * No identidad WP/cookie ni nonce; autoriza SOLAMENTE HMAC oficial
 * x-signature + x-request-id + data.id y la cuenta/ambiente local.
 * ACK únicamente tras COMMIT de Queue_Inbox. El dinero se consulta y
 * reconcilia FUERA del callback mediante Queue_Worker y GET Orders.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Webhook_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    /**
     * La recepción de una orden YA emitida no depende del flag comercial
     * payment_enabled ni de renovar hold. Sigue exigiendo HTTPS, esquema,
     * locks comprobados y una firma secreta realmente configurada.
     */
    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        Module_Gates::registerOperation('payments','webhook',
            static fn():bool=>MP_Credentials::webhookConfigured()
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadWebhook();
        if (!Module_Gates::allows('payments','webhook')) {
            return;
        }
        Access_Control::registerIdentityVerifier(
            'provider',[MP_Webhook_Auth::class,'identity']
        );
        Queue_Inbox::registerAuthenticator(
            [MP_Webhook_Auth::class,'authenticate']
        );
        Rest_Router::bind(
            'R8-17',
            [self::class,'receive'],
            [self::class,'guard']
        );
    }

    /** La firma se comprobó por Access_Control; NO confiar en el body. */
    public static function guard(array $ctx,?string $role): array
    {
        if (null!==$role || (int)\get_current_blog_id()<1
            || !\is_array($ctx['provider_auth']??null)
            || !\is_string($ctx['provider_auth']['raw_body']??null)
            || !Module_Gates::allows('payments','webhook')) {
            return [];
        }
        return ['authorized'=>true];
    }

    /** @return array{status:int,body:array<string,bool>} */
    public static function receive(
        array $ctx,\WP_REST_Request $_request
    ): array {
        $auth=$ctx['provider_auth']??null;
        if (!\is_array($auth)
            || !\is_string($auth['raw_body']??null)) {
            throw new Rest_Failure('pc_access_denied',403);
        }
        // Sin false ACK. Persist() revalida firma y COMMIT real antes
        // de regresar, incluso ante duplicados o carrera SQL.
        Queue_Inbox::persist($auth['raw_body'],$auth);
        return ['status'=>200,'body'=>['received'=>true]];
    }
}
