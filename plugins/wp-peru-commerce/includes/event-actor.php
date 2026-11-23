<?php
/**
 * IMP-09 — identidad de auditoría. Nunca se infiere autorización a partir de
 * un ID de pedido, un nonce de WordPress ni del valor actor_kind recibido.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Event_Actor
{
    /** @var callable|null */
    private static $guestVerifier = null;
    /** @var callable|null Verificador efímero de sesión digital; sin token claro. */
    private static $digitalVerifier = null;
    /** @var callable|null Validación same-origin de la cookie formulario checkout. */
    private static $checkoutVerifier = null;
    /** Contexto público de Libro independiente del checkout comercial. */
    private static $complaintVerifier = null;
    /** @var callable|null */
    private static $systemVerifier = null;
    /** Unicamente confirmación real de una solicitud WordPress Privacy. */
    private static $privacyVerifier = null;
    private static $privacyExportVerifier = null;

    /** @param list<string> $capabilities */
    private function __construct(
        private readonly string $kind,
        private readonly ?string $userId,
        private readonly string $reference,
        private readonly string $purpose,
        private readonly array $capabilities = []
    ) {
    }

    /**
     * Una operación staff exige sesión actual y TODAS las capabilities.
     * La pertenencia al recurso/estado se valida además en IMP-11/consumidor.
     *
     * @param list<string> $capabilitiesAllOf
     */
    public static function staff(array $capabilitiesAllOf): self
    {
        if ([] === $capabilitiesAllOf || !\array_is_list($capabilitiesAllOf)
            || !\function_exists('get_current_user_id')
            || !\function_exists('current_user_can')) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
        if (!\in_array(Access_Roles::currentRole(), ['A', 'O'], true)) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
        $id = (string) \get_current_user_id();
        if ('0' === $id) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
        Domain_Validation::sqlId($id);

        foreach ($capabilitiesAllOf as $capability) {
            if (!\is_string($capability)
                || !\preg_match('/\Aperucommerce_[a-z0-9_]{1,70}\z/D', $capability)
                || !\current_user_can($capability)) {
                throw new Transaction_Failure('pc_audit_actor_denied');
            }
        }
        return new self('staff', $id, $id, 'staff', $capabilitiesAllOf);
    }

    /**
     * Referencia opaca de sesión/cookie previamente verificada. No se
     * persiste en el audit log; no es prueba de identidad por sí misma.
     */
    public static function guest(string $scopeReference, string $purpose): self
    {
        self::safeReference($scopeReference);
        self::safePurpose($purpose);
        $actor = new self('guest', null, $scopeReference, $purpose);
        $actor->assertCurrent();
        return $actor;
    }

    /**
     * Checkout público: requiere cookie propia y control CSRF/Origin verificados
     * por el controlador futuro. El ref es HMAC de 64 hex, nunca cookie cruda.
     */
    public static function checkout(string $scopeDigest, string $purpose): self
    {
        if (!\preg_match('/\A[a-f0-9]{64}\z/D', $scopeDigest)
            || !\in_array($purpose, ['checkout_cash','checkout_mp'], true)) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
        $actor = new self('form', null, $scopeDigest, $purpose);
        $actor->assertCurrent();
        return $actor;
    }

    /** El reclamante usa contexto propio, no cookie de compra ni WP login. */
    public static function complaintForm(string $digest): self
    {
        if (!\preg_match('/\A[a-f0-9]{64}\z/D',$digest)) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
        $actor=new self('form',null,$digest,'complaint_submit');
        $actor->assertCurrent();
        return $actor;
    }

    public static function registerComplaintVerifier(callable $verifier): void
    {
        if (null!==self::$complaintVerifier) {
            throw new \LogicException('pc_complaint_actor_verifier_duplicate');
        }
        self::$complaintVerifier=$verifier;
    }

    public static function registerCheckoutVerifier(callable $verifier): void
    {
        if (null !== self::$checkoutVerifier) {
            throw new \LogicException('pc_checkout_actor_verifier_duplicate');
        }
        self::$checkoutVerifier = $verifier;
    }

    /**
     * Webhook/cron/recuperación, no una cuenta falsa con ID de WP.
     * El callback futuro comprueba firma/origen y finalidad del evento.
     */
    public static function system(string $sourceReference, string $purpose): self
    {
        self::safeReference($sourceReference);
        self::safePurpose($purpose);
        $actor = new self('system', null, $sourceReference, $purpose);
        $actor->assertCurrent();
        return $actor;
    }

    /** Verificador separado del sistema MP, ligado al hook confirmado WP. */
    public static function registerPrivacySystemVerifier(callable $check):void
    {
        if(null!==self::$privacyVerifier){
            throw new \LogicException('pc_privacy_actor_duplicate');
        }
        self::$privacyVerifier=$check;
    }

    public static function registerPrivacyExportVerifier(callable $check):void
    {
        if(null!==self::$privacyExportVerifier){
            throw new \LogicException('pc_privacy_export_actor_duplicate');
        }
        self::$privacyExportVerifier=$check;
    }

    /** Actor system solo para contexto WP Privacy verificado en servidor. */
    public static function privacyConfirmed(string $digest):self
    {
        if(!\preg_match('/\A[a-f0-9]{64}\z/D',$digest)){
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
        return self::system($digest,'privacy_intake_wp');
    }

    /** Actor de descarga: separado de guest tracking y de staff. */
    public static function digital(string $tokenId): self
    {
        self::safeReference($tokenId);
        $actor=new self('digital',null,$tokenId,'digital_download');
        $actor->assertCurrent();
        return $actor;
    }

    public static function registerDigitalVerifier(callable $verifier): void
    {
        if (null!==self::$digitalVerifier) {
            throw new \LogicException('pc_digital_actor_verifier_duplicate');
        }
        self::$digitalVerifier=$verifier;
    }

    public static function registerGuestVerifier(callable $verifier): void
    {
        if (null !== self::$guestVerifier) {
            throw new \LogicException('pc_guest_actor_verifier_duplicate');
        }
        self::$guestVerifier = $verifier;
    }

    public static function registerSystemVerifier(callable $verifier): void
    {
        if (null !== self::$systemVerifier) {
            throw new \LogicException('pc_system_actor_verifier_duplicate');
        }
        self::$systemVerifier = $verifier;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function matchesCheckoutScope(string $digest, string $purpose): bool
    {
        return 'form' === $this->kind
            && \hash_equals($this->reference, $digest)
            && $this->purpose === $purpose;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    /**
     * La comprobación se repite al escribir; no aceptar un actor reutilizado
     * tras cerrar sesión/revocar la finalidad.
     */
    public function assertCurrent(): void
    {
        if ('staff' === $this->kind) {
            require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
            if (!\in_array(Access_Roles::currentRole(), ['A', 'O'], true)
                || !\function_exists('get_current_user_id')
                || (string) \get_current_user_id() !== $this->userId) {
                throw new Transaction_Failure('pc_audit_actor_denied');
            }
            foreach ($this->capabilities as $capability) {
                if (!\current_user_can($capability)) {
                    throw new Transaction_Failure('pc_audit_actor_denied');
                }
            }
            return;
        }

        $verifier = match ($this->kind) {
            'guest' => self::$guestVerifier,
            'digital' => self::$digitalVerifier,
            'form' => 'complaint_submit'===$this->purpose
                ? self::$complaintVerifier : self::$checkoutVerifier,
            'system' => 'privacy_export_worker'===$this->purpose
                ?self::$privacyExportVerifier:
                ('privacy_intake_wp'===$this->purpose
                    ?self::$privacyVerifier:self::$systemVerifier),
            default => null,
        };
        if (null === $verifier) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
        try {
            if (true !== $verifier($this->reference, $this->purpose)) {
                throw new Transaction_Failure('pc_audit_actor_denied');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
    }

    private static function safeReference(string $reference): void
    {
        // Solo un identificador interno o HMAC hexadecimal; jamás cookie,
        // IP, teléfono, nombre, email, token o segundo factor en claro.
        if (!\preg_match('/\A(?:[1-9][0-9]{0,19}|[a-f0-9]{64})\z/D', $reference)) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
    }

    private static function safePurpose(string $purpose): void
    {
        if (!\preg_match('/\A[a-z][a-z0-9_]{1,47}\z/D', $purpose)) {
            throw new Transaction_Failure('pc_audit_actor_denied');
        }
    }
}
