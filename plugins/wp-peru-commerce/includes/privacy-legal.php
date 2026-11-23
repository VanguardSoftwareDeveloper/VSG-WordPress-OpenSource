<?php
/**
 * IMP-50 — evidencia de identidad, fuente, resolución y retención ARCO.
 * Las verificaciones P-C son independientes de un correo confirmado,
 * casilla, nonce WordPress o declaración del administrador.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Legal
{
    private static $sourceVerifier=null;
    private static $identityVerifier=null;
    private static $resolutionVerifier=null;
    private static $deliveryVerifier=null;
    private static $retentionVerifier=null;

    public static function registerVerifiedSource(callable $check):void
    {self::one(self::$sourceVerifier,$check,'source');}
    public static function registerIdentityVerifier(callable $check):void
    {self::one(self::$identityVerifier,$check,'identity');}
    public static function registerResolutionVerifier(callable $check):void
    {self::one(self::$resolutionVerifier,$check,'resolution');}
    public static function registerDeliveryVerifier(callable $check):void
    {self::one(self::$deliveryVerifier,$check,'delivery');}
    public static function registerRetentionVerifier(callable $check):void
    {self::one(self::$retentionVerifier,$check,'retention');}

    private static function one(mixed &$slot,callable $check,string $kind):void
    {
        if(null!==$slot){throw new \LogicException('pc_privacy_verifier_duplicate_'.$kind);}
        $slot=$check;
    }

    public static function intakeReady():bool
    {return null!==self::$sourceVerifier;}
    public static function identityReady():bool
    {return null!==self::$identityVerifier;}
    public static function resolutionReady():bool
    {
        return null!==self::$resolutionVerifier
            && null!==self::$retentionVerifier
            && null!==self::$deliveryVerifier;
    }

    public static function requireSource(array $entry):void
    {self::require(self::$sourceVerifier,$entry,'pc_privacy_source_unverified');}
    public static function requireIdentity(array $source,array $proof):void
    {
        self::require(self::$identityVerifier,
            ['source'=>$source,'proof'=>$proof],
            'pc_privacy_identity_unverified');
    }
    public static function requireResolution(array $case,array $decision):void
    {
        self::require(self::$resolutionVerifier,
            ['case'=>$case,'decision'=>$decision],
            'pc_privacy_resolution_unverified');
    }
    public static function requireDelivery(array $case,array $evidence):void
    {
        self::require(self::$deliveryVerifier,
            ['case'=>$case,'evidence'=>$evidence],
            'pc_privacy_delivery_unverified');
    }
    public static function retentionReview(array $case,array $decision):array
    {
        if(null===self::$retentionVerifier){
            throw new Transaction_Failure('pc_privacy_retention_unverified');
        }
        try{$result=(self::$retentionVerifier)(
            ['case'=>$case,'decision'=>$decision]
        );}catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_retention_unverified');
        }
        if(!\is_array($result)
            || !\is_string($result['basis_code']??null)
            || !\preg_match('/\A[a-z][a-z0-9_]{2,59}\z/D',
                $result['basis_code'])
            || !\is_string($result['inventory_version']??null)
            || !\preg_match('/\A[a-zA-Z0-9_.-]{6,80}\z/D',
                $result['inventory_version'])
            || !\is_array($result['retain_categories']??null)
            || !\array_is_list($result['retain_categories'])
            || \count($result['retain_categories'])>20){
            throw new Transaction_Failure('pc_privacy_retention_unverified');
        }
        if(($decision['resolution']??null)==='retained_due_to_legal_obligation'
            && []===$result['retain_categories']){
            throw new Transaction_Failure('pc_privacy_retention_unverified');
        }
        foreach($result['retain_categories'] as $category){
            if(!\is_string($category)
                || !Privacy_Retention::allowed($category)
                || !\preg_match('/\A[a-z][a-z0-9_]{2,59}\z/D',$category)){
                throw new Transaction_Failure('pc_privacy_retention_unverified');
            }
        }
        return $result;
    }

    private static function require(
        mixed $verifier,array $context,string $failure
    ):void {
        if(null===$verifier){throw new Transaction_Failure($failure);}
        try{
            if(true!==$verifier($context)){throw new \UnexpectedValueException();}
        }catch(\Throwable $ignored){throw new Transaction_Failure($failure);}
    }
}
