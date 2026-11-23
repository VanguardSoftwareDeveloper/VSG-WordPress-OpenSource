<?php
/**
 * IMP-48 — verificador condicionado por instalación/modalidad (J-01).
 *
 * El software conoce los campos comunes del Anexo I, pero NO certifica
 * automáticamente la normativa ni sustituye la validación legal P-C.
 * Sin verificador explícito real R8-15 permanece fail-closed.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Legal
{
    /** @var callable|null Política jurídica ratificada por instalación. */
    private static $verifiedForm = null;

    /**
     * P-C registra un callable SIN E/S dentro de SQL:
     * bool(merchantSnapshot, complaintBody). Debe evaluar vigencia, menor/
     * representación, contacto y monto aplicables. No basta la existencia
     * de datos de settings o un booleano enviado desde el navegador.
     */
    public static function registerApplicableFormVerifier(callable $verifier):void
    {
        if(null!==self::$verifiedForm){
            throw new \LogicException('pc_complaint_legal_duplicate');
        }
        self::$verifiedForm=$verifier;
    }

    public static function ready():bool
    {
        return null!==self::$verifiedForm;
    }

    public static function requireWithin(array $merchant,array $body):void
    {
        if(null===self::$verifiedForm){
            throw new Transaction_Failure('pc_complaint_legal_not_ready');
        }
        try {
            if(true!==(self::$verifiedForm)($merchant,$body)){
                throw new Transaction_Failure('pc_complaint_legal_not_ready');
            }
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_legal_not_ready');
        }
    }
    /** P-C: comprueba materialmente origen/canal/entrega, SIN IO dentro de SQL. */
    private static $verifiedDelivery=null;
    private static ?string $deliveryVerifierVersion=null;

    public static function registerDeliveryEvidenceVerifier(
        string $version,callable $verifier
    ):void {
        if(null!==self::$verifiedDelivery || !\preg_match(
            '/\A[a-zA-Z0-9_.-]{6,80}\z/D',$version)){
            throw new \LogicException('pc_complaint_delivery_verifier_invalid');
        }
        self::$deliveryVerifierVersion=$version;
        self::$verifiedDelivery=$verifier;
    }

    public static function requireDeliveryEvidenceWithin(
        Transaction_Context $tx,string $caseId,array $entry,
        string $response,array $merchant,string $submittedUtc
    ):array {
        if(null===self::$verifiedDelivery
            || null===self::$deliveryVerifierVersion
            || 'response_delivery'!==($entry['purpose']??null)
            || !\in_array($entry['channel']??null,['email','letter'],true)
            || !\hash_equals((string)$entry['response_sha256'],
                \hash('sha256',$response))){
            throw new Transaction_Failure('pc_complaint_delivery_unverified');
        }
        try {
            $delivered=Business_Time::fromApi(
                (string)$entry['delivered_at_utc']
            );
            $submitted=Business_Time::fromDatabase($submittedUtc);
            $clock=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
            if(1!==\count($clock)
                || $delivered<$submitted
                || $delivered>Business_Time::fromDatabase(
                    (string)$clock[0]['now_utc']
                ) || true!==(self::$verifiedDelivery)(
                    $caseId,$entry,\hash('sha256',$response),$merchant
                )){
                throw new \UnexpectedValueException();
            }
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_delivery_unverified');
        }
        return [
            'ref'=>$entry['ref'],
            'channel'=>$entry['channel'],
            'delivered_at_utc'=>$entry['delivered_at_utc'],
            'verifier_version'=>self::$deliveryVerifierVersion,
        ];
    }

}
