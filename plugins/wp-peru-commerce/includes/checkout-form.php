<?php
/**
 * IMP-26 — identidad efímera del formulario público, sin cuenta comprador.
 *
 * R8-05 establece una cookie firmada Secure/HttpOnly/SameSite=Strict.
 * R8-07 exige la misma cookie, Origin HTTPS exacto y JSON same-origin.
 * La cookie NO autoriza lectura de pedidos, pagos, archivos ni cambios
 * sensibles; sólo delimita la idempotencia de este intento de checkout.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Checkout_Form
{
    private const COOKIE = '__Host-pc_checkout_form';
    private const TTL_SECONDS = 7200;
    private static bool $verifiersRegistered = false;

    /** Dos verificadores globales y únicos para los controladores R8-06/07. */
    public static function registerVerifiers(): void
    {
        if (self::$verifiersRegistered) { return; }
        Event_Actor::registerCheckoutVerifier([self::class,'verifiesActor']);
        Idempotency::registerScopeVerifier([self::class,'verifiesScope']);
        self::$verifiersRegistered = true;
    }

    /**
     * Tras R8-05 exitoso y FUERA de SQL. Los requests cross-origin pueden
     * consultar cotización pública, pero nunca reciben credencial de compra.
     */
    public static function issueForQuote(): void
    {
        // R8-05 sigue siendo una cotización pública. Si procede de otro
        // origen se devuelve la cotización, pero NO se emite cookie.
        try {
            self::requireSameOrigin();
        } catch (Transaction_Failure $ignored) {
            return;
        }
        if (null !== self::verifiedCookieDigest()) {
            return;
        }
        if (\headers_sent()) {
            throw new Transaction_Failure('pc_checkout_form_not_ready');
        }
        $secret = self::serverKey();
        $token = \rtrim(\strtr(\base64_encode(\random_bytes(32)), '+/', '-_'), '=');
        $expiry = \time() + self::TTL_SECONDS;
        $body = $token . '.' . $expiry;
        $signature = \hash_hmac('sha256', self::macMessage($body), $secret);
        if (!\setcookie(self::COOKIE, $body . '.' . $signature, [
            'expires' => $expiry,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ])) {
            throw new Transaction_Failure('pc_checkout_form_not_ready');
        }
    }

    /**
     * Contexto de formulario (NO identidad civil); el valor retornado es
     * únicamente HMAC hexadecimal, nunca el token de la cookie en claro.
     */
    public static function verifiedDigest(): string
    {
        self::requireSameOrigin();
        $digest = self::verifiedCookieDigest();
        if (null === $digest) {
            throw new Transaction_Failure('pc_checkout_form_invalid');
        }
        return $digest;
    }

    public static function verifiesScope(array $scope): bool
    {
        if (($scope['actor_kind'] ?? null) !== 'form'
            || ($scope['resource_kind'] ?? null) !== 'checkout'
            || ($scope['resource_id'] ?? null) !== '0'
            || !\in_array(($scope['purpose'] ?? null), ['checkout_cash','checkout_mp'],true)
            || !\is_string($scope['actor_ref'] ?? null)) {
            return false;
        }
        try {
            return \hash_equals(self::verifiedDigest(), $scope['actor_ref']);
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    public static function verifiesActor(string $digest, string $purpose): bool
    {
        if (!\in_array($purpose, ['checkout_cash','checkout_mp'], true)) {
            return false;
        }
        try {
            return \hash_equals(self::verifiedDigest(), $digest);
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Solo HTTPS + Origin preciso. Referer, Host y nonce WordPress no
     * reemplazan esta comprobación. Fetch-Site, cuando exista, debe ser
     * same-origin; un Origin ausente o null es denegado para checkout.
     */
    private static function requireSameOrigin(): void
    {
        if (!\is_ssl()
            || 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? null)
            || !\is_string($_SERVER['HTTP_ORIGIN'] ?? null)
            || !\is_string($_SERVER['CONTENT_TYPE'] ?? null)
            || !\preg_match('~\Aapplication/json(?:\s*;\s*charset=utf-8)?\z~iD',
                \trim($_SERVER['CONTENT_TYPE']))
            || (isset($_SERVER['HTTP_SEC_FETCH_SITE'])
                && 'same-origin' !== $_SERVER['HTTP_SEC_FETCH_SITE'])) {
            throw new Transaction_Failure('pc_checkout_form_invalid');
        }
        $origin = \wp_parse_url($_SERVER['HTTP_ORIGIN']);
        $site = \wp_parse_url(\home_url('/'));
        if (!\is_array($origin) || !\is_array($site)
            || 'https' !== ($origin['scheme'] ?? null)
            || 'https' !== ($site['scheme'] ?? null)
            || !\is_string($origin['host'] ?? null)
            || !\is_string($site['host'] ?? null)
            || \strtolower($origin['host']) !== \strtolower($site['host'])
            || ($origin['port'] ?? 443) !== ($site['port'] ?? 443)
            || isset($origin['path']) || isset($origin['query'])
            || isset($origin['fragment']) || isset($origin['user'])
            || isset($origin['pass'])) {
            throw new Transaction_Failure('pc_checkout_form_invalid');
        }
    }

    private static function verifiedCookieDigest(): ?string
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (!\is_string($cookie) || \strlen($cookie) > 160) {
            return null;
        }
        $parts = \explode('.', $cookie);
        if (3 !== \count($parts)
            || !\preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $parts[0])
            || !\preg_match('/\A[1-9][0-9]{9,12}\z/D',$parts[1])
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$parts[2])) {
            return null;
        }
        $now = \time();
        $end = (int) $parts[1];
        if ($end <= $now || $end > $now + self::TTL_SECONDS) {
            return null;
        }
        $body = $parts[0] . '.' . $parts[1];
        $key = self::serverKey();
        if (!\hash_equals(\hash_hmac('sha256',self::macMessage($body),$key),
            $parts[2])) {
            return null;
        }
        // Una cookie antigua para otra instalación/secret no es aceptada.
        return \hash_hmac('sha256',
            'pc-checkout-form-scope-v1:' . \get_current_blog_id() . ':' . $parts[0],
            $key
        );
    }

    private static function macMessage(string $body): string
    {
        return 'pc-checkout-cookie-v1:' . \get_current_blog_id() . ':' . $body;
    }

    private static function serverKey(): string
    {
        $key = \wp_salt('nonce');
        if (!\is_string($key) || \strlen($key) < 32) {
            throw new Transaction_Failure('pc_checkout_form_not_ready');
        }
        return $key;
    }

}
