<?php
/**
 * IMP-48 — contexto de presentación independiente de checkout.
 *
 * La página WordPress con shortcode entrega CSRF asociado a cookie
 * aleatoria HttpOnly (no una identidad civil). No crea ruta pública para
 * buscar un folio ni obliga a tener una compra o usuario WordPress.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Form
{
    public const SHORTCODE='perucommerce_libro_reclamaciones';
    private const COOKIE='__Host-pc_complaint_form';
    private const TTL=3600;
    private static bool $hooks=false;
    private static bool $verifiers=false;
    /** @var array<string,string>|null */
    private static ?array $issued=null;

    public static function registerHooks():void
    {
        if(self::$hooks){return;}
        self::$hooks=true;
        \add_shortcode(self::SHORTCODE,[self::class,'render']);
        \add_action('template_redirect',[self::class,'preparePage'],1);
    }

    public static function registerVerifiers():void
    {
        if(self::$verifiers){return;}
        Event_Actor::registerComplaintVerifier([self::class,'verifiesActor']);
        Idempotency::registerComplaintScopeVerifier([self::class,'verifiesScope']);
        self::$verifiers=true;
    }

    /** Emitir cookie ANTES de que WordPress imprima cabeceras. */
    public static function preparePage():void
    {
        if(!\is_ssl() || !\is_singular()
            || !Module_Gates::allows('complaints','write')
            || !Complaint_Service::readyForPage()){return;}
        $post=\get_post();
        if(!$post instanceof \WP_Post
            || !\has_shortcode($post->post_content,self::SHORTCODE)){return;}
        \wp_enqueue_style(
            'perucommerce-complaint-form',
            \plugins_url('assets/css/complaint-form.css',PERUCOMMERCE_PLUGIN_FILE),
            [],'1.0.0'
        );
        if(!\headers_sent()){
            \nocache_headers();
            \header('Cache-Control: private, no-store, max-age=0',true);
            \header('Referrer-Policy: no-referrer',true);
            \header('X-Robots-Tag: noindex, noarchive',true);
        }
        self::$issued=self::existing();
        if(null!==self::$issued){return;}
        if(\headers_sent()){return;}
        $token=\bin2hex(\random_bytes(32));
        $until=(string)(\time()+self::TTL);
        $mac=self::mac($token,$until);
        if(!\setcookie(self::COOKIE,$token.'.'.$until.'.'.$mac,[
            'expires'=>(int)$until,'path'=>'/','secure'=>true,
            'httponly'=>true,'samesite'=>'Lax',
        ])){
            return;
        }
        self::$issued=self::context($token,$until);
    }

    /** Minimal form HTML; frontend static JS is enqueued on this page ONLY. */
    public static function render():string
    {
        if(!\is_ssl() || !Module_Gates::allows('complaints','write')
            || !Complaint_Service::readyForPage()){
            return '<p>El Libro de Reclamaciones virtual no se encuentra disponible. '
                .'Solicite al establecimiento su canal de atención alternativo.</p>';
        }
        $context=self::$issued??self::existing();
        if(null===$context){
            return '<p>No fue posible preparar el formulario seguro. '
                .'Actualice la página y vuelva a intentarlo.</p>';
        }
        \wp_enqueue_script(
            'perucommerce-complaint-form',
            \plugins_url('assets/js/complaint-form.js',PERUCOMMERCE_PLUGIN_FILE),
            [],'1.0.0',true
        );
        $url=\esc_url(\rest_url('peru-commerce/v1/public/reclamaciones'));
        $csrf=\esc_attr($context['csrf']);
        $privacy=\function_exists('get_privacy_policy_url')
            ?\get_privacy_policy_url():''; 
        $privacyLink=\is_string($privacy)&&''!==$privacy
            ?'<p>Consulte la <a href="'.\esc_url($privacy)
                .'" rel="noopener noreferrer">política de privacidad</a>.</p>'
            :'';
        return '<section class="pc-libro" aria-label="Libro de Reclamaciones">'
            .'<h2>Libro de Reclamaciones</h2>'
            .'<p>Presentar un reclamo o queja no requiere comprar, registrarse ni pagar. '
            .'La constancia se genera en esta página y puede imprimirse.</p>'
            .$privacyLink
            .'<form data-pc-complaint-form data-url="'.$url.'" data-csrf="'.$csrf.'">'
            .'<fieldset><legend>1. Identificación del reclamante</legend>'
            .'<label>Nombre completo <input name="full_name" maxlength="200" required></label>'
            .'<label>Tipo de documento <select name="identification_kind" required>'
            .'<option value="DNI">DNI</option><option value="CE">Carné de extranjería</option>'
            .'<option value="PASAPORTE">Pasaporte</option></select></label>'
            .'<label>Número de documento <input name="identification_value" maxlength="32" required></label>'
            .'<label>Dirección <input name="address" maxlength="500" required></label>'
            .'<label>Teléfono <input name="phone" type="tel" maxlength="32"></label>'
            .'<label>Correo electrónico <input name="email" type="email" maxlength="254"></label>'
            .'<p>Indique por lo menos un medio de contacto válido, según la normativa aplicable.</p>'
            .'<label><input type="checkbox" name="minor"> Soy menor de edad y presentaré '
            .'los datos de mi representante.</label>'
            .'<div data-pc-representative hidden>'
            .'<label>Nombre del representante <input name="representative_full_name" maxlength="200"></label>'
            .'<label>Tipo de documento del representante <select name="representative_identification_kind">'
            .'<option value="DNI">DNI</option><option value="CE">Carné de extranjería</option>'
            .'<option value="PASAPORTE">Pasaporte</option></select></label>'
            .'<label>Documento del representante <input name="representative_identification_value" maxlength="32"></label>'
            .'<label>Dirección del representante <input name="representative_address" maxlength="500"></label>'
            .'<label>Teléfono del representante <input name="representative_phone" maxlength="32"></label>'
            .'<label>Correo del representante <input name="representative_email" type="email" maxlength="254"></label>'
            .'</div></fieldset>'
            .'<fieldset><legend>2. Identificación del bien o servicio</legend>'
            .'<label>Tipo <select name="subject"><option value="producto">Producto</option>'
            .'<option value="servicio">Servicio</option></select></label>'
            .'<label>Descripción del bien o servicio <input name="subject_description" maxlength="1000" required></label>'
            .'<label>Monto reclamado (S/, si corresponde) '
            .'<input name="amount_pen" inputmode="decimal" maxlength="22" placeholder="0.00"></label>'
            .'<label>Referencia de operación (opcional, no coloque contraseñas ni tokens)'
            .'<input name="transaction_reference" maxlength="100"></label>'
            .'</fieldset><fieldset><legend>3. Hechos y solicitud</legend>'
            .'<label>Tipo de presentación <select name="kind">'
            .'<option value="reclamo">Reclamo</option><option value="queja">Queja</option></select></label>'
            .'<label>Detalle <textarea name="description" maxlength="10000" required></textarea></label>'
            .'<label>Pedido concreto <textarea name="request" maxlength="5000" required></textarea></label>'
            .'<label><input type="checkbox" name="acknowledge" required> '
            .'Confirmo que el contenido ingresado representa mi reclamación y solicito su registro.</label>'
            .'</fieldset>'
            .'<button type="submit">Registrar reclamación</button>'
            .'<p role="status" aria-live="polite" data-pc-complaint-status></p>'
            .'</form>'
            .'<section data-pc-complaint-receipt hidden aria-label="Constancia imprimible">'
            .'<h3>Copia de la hoja de reclamación</h3><div data-pc-complaint-copy></div>'
            .'<button type="button" data-pc-complaint-print>Imprimir constancia</button>'
            .'</section></section>';
    }

    public static function verifiedDigest():string
    {
        self::requireOrigin();
        $ctx=self::existing();
        $csrf=$_SERVER['HTTP_X_PC_FORM_CSRF']??null;
        if(null===$ctx || !\is_string($csrf)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$csrf)
            || !\hash_equals($ctx['csrf'],$csrf)
            || 'yes'!==($_SERVER['HTTP_X_PC_COMPLAINT_ACCEPTED']??null)){
            throw new Transaction_Failure('pc_complaint_form_denied');
        }
        return $ctx['digest'];
    }

    public static function verifiesActor(string $digest,string $purpose):bool
    {
        if('complaint_submit'!==$purpose
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$digest)){return false;}
        try{return \hash_equals(self::verifiedDigest(),$digest);}
        catch(\Throwable $ignored){return false;}
    }

    public static function verifiesScope(array $scope):bool
    {
        if(($scope['actor_kind']??null)!=='form'
            || ($scope['resource_kind']??null)!=='complaint_form'
            || ($scope['resource_id']??null)!=='0'
            || ($scope['purpose']??null)!=='complaint_submit'
            || !\is_string($scope['actor_ref']??null)){return false;}
        try{return \hash_equals(self::verifiedDigest(),$scope['actor_ref']);}
        catch(\Throwable $ignored){return false;}
    }

    private static function requireOrigin():void
    {
        if(!\is_ssl()
            || 'POST'!==($_SERVER['REQUEST_METHOD']??null)
            || !\is_string($_SERVER['HTTP_ORIGIN']??null)
            || !\is_string($_SERVER['CONTENT_TYPE']??null)
            || !\preg_match('~\Aapplication/json(?:\s*;\s*charset=utf-8)?\z~iD',
                \trim($_SERVER['CONTENT_TYPE']))
            || (isset($_SERVER['HTTP_SEC_FETCH_SITE'])
                && 'same-origin'!==$_SERVER['HTTP_SEC_FETCH_SITE'])){
            throw new Transaction_Failure('pc_complaint_form_denied');
        }
        $origin=\wp_parse_url($_SERVER['HTTP_ORIGIN']);
        $site=\wp_parse_url(\home_url('/'));
        if(!\is_array($origin)||!\is_array($site)
            || ($origin['scheme']??null)!=='https'
            || ($site['scheme']??null)!=='https'
            || !\is_string($origin['host']??null)
            || !\is_string($site['host']??null)
            || \strtolower($origin['host'])!==\strtolower($site['host'])
            || ($origin['port']??443)!==($site['port']??443)
            || isset($origin['user']) || isset($origin['pass'])
            || isset($origin['path'])||isset($origin['query'])
            || isset($origin['fragment'])){
            throw new Transaction_Failure('pc_complaint_form_denied');
        }
    }

    /** @return ?array<string,string> */
    private static function existing():?array
    {
        $raw=$_COOKIE[self::COOKIE]??null;
        if(!\is_string($raw)||\strlen($raw)>160){return null;}
        $parts=\explode('.',$raw);
        if(\count($parts)!==3
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$parts[0])
            || !\preg_match('/\A[1-9][0-9]{9,12}\z/D',$parts[1])
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$parts[2])
            || (int)$parts[1]<=\time()
            || (int)$parts[1]>\time()+self::TTL
            || !\hash_equals(self::mac($parts[0],$parts[1]),$parts[2])){
            return null;
        }
        return self::context($parts[0],$parts[1]);
    }

    private static function context(string $token,string $expiry):array
    {
        $key=self::key();
        $site=(string)\get_current_blog_id();
        return [
            'csrf'=>\hash_hmac('sha256',
                'pc-complaint-csrf-v1:'.$site.':'.$token.':'.$expiry,$key),
            'digest'=>\hash_hmac('sha256',
                'pc-complaint-scope-v1:'.$site.':'.$token,$key),
        ];
    }

    private static function mac(string $token,string $expiry):string
    {
        return \hash_hmac('sha256','pc-complaint-cookie-v1:'
            .\get_current_blog_id().':'.$token.':'.$expiry,self::key());
    }

    private static function key():string
    {
        $key=\wp_salt('nonce');
        if(!\is_string($key)||\strlen($key)<32){
            throw new Transaction_Failure('pc_complaint_form_not_ready');
        }
        return $key;
    }
}
