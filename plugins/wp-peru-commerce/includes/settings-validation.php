<?php
/**
 * IMP-13 — validación de identidad comercial y proveedor opcional de tiles.
 *
 * El navegador solicita la capa cartográfica voluntariamente. No realizar
 * HTTP/DNS desde PHP (SSRF), no generar token ni habilitar delivery por mapa.
 * Un proveedor externo solo se activa si host/licencia/CSP fueron aprobados.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Settings_Validation
{
    /** @var callable|null Verifica proveedor de teselas, licencia y CSP real. */
    private static $tileVerifier = null;

    /** @var callable|null Revisa legalmente la alternativa al Libro nativo. */
    private static $externalComplaintVerifier = null;

    public static function registerExternalComplaintVerifier(callable $verifier): void
    {
        if (null !== self::$externalComplaintVerifier) {
            throw new \LogicException('pc_complaint_verifier_duplicate');
        }
        self::$externalComplaintVerifier = $verifier;
    }

    public static function registerTileVerifier(callable $verifier): void
    {
        if (null !== self::$tileVerifier) {
            throw new \LogicException('pc_map_verifier_duplicate');
        }
        self::$tileVerifier = $verifier;
    }

    /**
     * Acepta subobjetos JSON ya validados por Rest_Validator, pero no confía
     * en que la UI haya mantenido la identidad o remitente correctos.
     *
     * @param array<string,mixed> $patch
     */
    public static function patch(array $patch): void
    {
        $mapped = (object) $patch;
        foreach (['business', 'fiscal', 'map_tiles', 'mail_sender'] as $key) {
            if (\array_key_exists($key, $patch)) {
                if (!\is_array($patch[$key])) {
                    throw new Rest_Failure('pc_field_invalid', 422, false, $key);
                }
                $mapped->$key = (object) $patch[$key];
            }
        }
        Rest_Validator::namedObject('SettingsPatch', $mapped);

        // La infraestructura idempotente también rechaza caracteres de
        // control/espacios; devolver 422 antes del TX en lugar de 500.
        $key = $patch['idempotency_key'] ?? null;
        if (!\is_string($key)
            || !\mb_check_encoding($key, 'UTF-8')
            || \mb_strlen($key, 'UTF-8') < 16
            || \mb_strlen($key, 'UTF-8') > 128
            || \preg_match('/[\x00-\x20\x7F]/', $key)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'idempotency_key');
        }

        if (isset($patch['business'])) {
            self::business($patch['business']);
        }
        if (isset($patch['mail_sender'])) {
            self::sender($patch['mail_sender']);
        }
        if (isset($patch['map_tiles'])) {
            self::map($patch['map_tiles']);
        }
        foreach (['policy_notice', 'privacy_notice'] as $key) {
            if (!isset($patch[$key])) {
                continue;
            }
            $text = $patch[$key];
            if (!\is_string($text) || '' === \trim($text)
                || !\mb_check_encoding($text, 'UTF-8')
                || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
                throw new Rest_Failure('pc_field_invalid', 422, false, $key);
            }
        }
    }

    /** @param array<string,mixed> $business */
    public static function business(array $business): void
    {
        if (!\is_string($business['business_name'] ?? null)
            || '' === \trim($business['business_name'])
            || !\mb_check_encoding($business['business_name'], 'UTF-8')
            || \preg_match('/[\x00-\x1F\x7F]/', $business['business_name'])) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'business');
        }
        foreach (['complaint_url', 'privacy_url'] as $key) {
            if (!isset($business[$key])) {
                continue;
            }
            $url = $business[$key];
            if (!\is_string($url) || !self::safeReference($url)) {
                throw new Rest_Failure('pc_field_invalid', 422, false, 'business.' . $key);
            }
        }
        if ('external' === ($business['complaint_mode'] ?? null)) {
            if (!isset($business['complaint_url'])
                || null === self::$externalComplaintVerifier) {
                throw new Rest_Failure('pc_module_not_ready', 423);
            }
            try {
                if (true !== (self::$externalComplaintVerifier)($business['complaint_url'])) {
                    throw new Rest_Failure('pc_module_not_ready', 423);
                }
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_module_not_ready', 423);
            }
        }
    }

    /** @param array<string,mixed> $sender */
    public static function sender(array $sender): void
    {
        if (isset($sender['from_name'])
            && (!\is_string($sender['from_name'])
                || '' === \trim($sender['from_name'])
                || \preg_match('/[\x00-\x1F\x7F]/', $sender['from_name']))) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'mail_sender.from_name');
        }
        if (isset($sender['from_email'])) {
            try {
                Domain_Validation::email($sender['from_email']);
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid', 422, false, 'mail_sender.from_email');
            }
        }
        if (isset($sender['signature'])
            && (!\is_string($sender['signature'])
                || !\mb_check_encoding($sender['signature'], 'UTF-8')
                || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sender['signature']))) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'mail_sender.signature');
        }
    }

    /** @param array<string,mixed> $map */
    public static function map(array $map): void
    {
        if (true !== ($map['enabled'] ?? null)) {
            // Capa apagada: no se cargan tiles. El origen previamente
            // seleccionado puede conservarse como configuración, sin IO.
            return;
        }
        $url = $map['tile_url_template'] ?? null;
        $attribution = $map['attribution_url'] ?? null;
        if (!\is_string($url) || !\is_string($attribution)
            || !\is_string($map['attribution_text'] ?? null)
            || '' === \trim($map['attribution_text'])
            || !self::safeHttps($url, true)
            || !self::safeHttps($attribution, false)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'map_tiles');
        }
        if (null === self::$tileVerifier) {
            throw new Rest_Failure('pc_module_not_ready', 423);
        }
        try {
            if (true !== (self::$tileVerifier)(
                \parse_url($url, \PHP_URL_HOST), $url, $attribution,
                $map['attribution_text']
            )) {
                throw new Rest_Failure('pc_module_not_ready', 423);
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_module_not_ready', 423);
        }
    }

    private static function safeReference(string $url): bool
    {
        if (\preg_match('/\A\/(?!\/)[A-Za-z0-9_\/.-]*\z/D', $url)) {
            return !\str_contains($url, '..');
        }
        return self::safeHttps($url, false);
    }

    private static function safeHttps(string $url, bool $template): bool
    {
        if ('' === $url || \strlen($url) > 600
            || !\preg_match('/\Ahttps:\/\//i', $url)
            || \str_contains($url, '@')
            || \str_contains($url, '\\')
            || \str_contains($url, '?')
            || \str_contains($url, '#')
            || \str_contains($url, '%')
            || \preg_match('/[\x00-\x20\x7F]/', $url)) {
            return false;
        }
        $parts = \parse_url($url);
        if (!\is_array($parts) || !isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && 443 !== $parts['port'])
            || !\preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])\.[a-z]{2,63}\z/D',
                \strtolower($parts['host']))
            || false !== \filter_var($parts['host'], \FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($template) {
            foreach (['{z}', '{x}', '{y}'] as $p) {
                if (1 !== \substr_count($url, $p)) {
                    return false;
                }
            }
            $safe = \str_replace(['{z}', '{x}', '{y}'], '', $url);
            return !\str_contains($safe, '{') && !\str_contains($safe, '}')
                && !\str_contains($safe, '..');
        }
        return !\str_contains($url, '{') && !\str_contains($url, '}')
            && !\str_contains($url, '..');
    }
}
