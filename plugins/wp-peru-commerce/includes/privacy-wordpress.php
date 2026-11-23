<?php
/**
 * IMP-50 — colaboración con WordPress Privacy sin borrar/exponer datos
 * legales por una mera coincidencia de correo.
 *
 * R8-50 (IMP-51) es la exportación protegida independiente por A
 * y sujeto legitimado; un export nativo WP solo incluye aviso genérico,
 * sin presumir cumplimiento ARCO por coincidencia de correo.
 * El eraser de core no puede eliminar ventas, Libro ni evidencias.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_WordPress
{
    private static bool $hooks=false;

    public static function registerHooks():void
    {
        if(self::$hooks){return;}
        self::$hooks=true;
        \add_filter('wp_privacy_personal_data_exporters',
            [self::class,'exporters']);
        \add_filter('wp_privacy_personal_data_erasers',
            [self::class,'erasers']);
    }

    public static function exporters(array $all):array
    {
        $all['perucommerce-legal-privacy']=[
            'exporter_friendly_name'=>'PeruCommerce — datos con revisión jurídica',
            'callback'=>[self::class,'export'],
        ];
        return $all;
    }

    public static function erasers(array $all):array
    {
        $all['perucommerce-legal-privacy']=[
            'eraser_friendly_name'=>'PeruCommerce — conservación y borrado supervisado',
            'callback'=>[self::class,'erase'],
        ];
        return $all;
    }

    /**
     * Core llama tras su autorización; aún NO demuestra que un email
     * identifica todos los pedidos, solicitudes sin email o terceros.
     */
    public static function export(string $email,int $page=1):array
    {
        if($page!==1){return ['data'=>[],'done'=>true];}
        $found=self::related($email);
        if(false===$found){return ['data'=>[],'done'=>true];}
        // Aviso genérico de alcance, no exportar PII indiscriminadamente.
        return [
            'data'=>[[
                'group_id'=>'perucommerce-legal-review',
                'group_label'=>'PeruCommerce — verificación de identidad',
                'item_id'=>'perucommerce-privacy-review',
                'data'=>[[
                    'name'=>'Estado de tratamiento',
                    'value'=>'Los datos comerciales y jurídicos protegidos no se '
                        .'incluyen en esta exportación automática. '
                        .'Solicite al comercio atención individual de '
                        .'derechos ARCO y una copia mediante un canal '
                        .'verificado de identidad.',
                ]],
            ]],
            'done'=>true,
        ];
    }

    /**
     * Nunca borrar por coincidencia de email, ni afirmar eliminación.
     * WP puede seguir borrando datos suyos que sus propios erasers validan.
     */
    public static function erase(string $email,int $page=1):array
    {
        $related=self::related($email);
        $retain=null!==$related && $related===false ?false:true;
        return [
            'items_removed'=>false,
            'items_retained'=>$retain,
            'messages'=>$retain?[
                'PeruCommerce conserva registros comerciales o jurídicos '
                    .'que requieren identificación, análisis de finalidad '
                    .'y obligaciones legales antes de cualquier supresión. '
                    .'No se borraron datos en esta operación automática.'
            ]:[],
            'done'=>true,
        ];
    }

    /** No devolver cantidad, nombre, pedido ni coincidencias al solicitante. */
    private static function related(string $email):?bool
    {
        try{$email=Domain_Validation::email($email);}
        catch(\Throwable $ignored){return null;}
        try{
            Bootstrap::loadPrivacy();
            if(!Module_Gates::allows('privacy','read')){return null;}
            return Transaction::run(static function(Transaction_Context $tx)
                use($email):bool{
                global $wpdb;
                if(!$wpdb instanceof \wpdb){return true;}
                require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
                $names=Schema::tableNames($wpdb);
                $orders=$tx->rows(
                    'SELECT id FROM %i WHERE buyer_email=%s LIMIT 1',
                    [$names['pc_orders'],$email]
                );
                if([]!==$orders){return true;}
                $claims=$tx->rows(
                    'SELECT id FROM %i WHERE JSON_UNQUOTE(JSON_EXTRACT('
                        ."complainant_snapshot,'$.claimant.email'))=%s LIMIT 1",
                    [$names['pc_complaints'],$email]
                );
                if([]!==$claims){return true;}
                $privacy=$tx->rows(
                    'SELECT id FROM %i WHERE JSON_UNQUOTE(JSON_EXTRACT('
                        ."applicant_reference,'$.contact_kind'))=%s "
                        .'AND JSON_UNQUOTE(JSON_EXTRACT('
                        ."applicant_reference,'$.contact_value'))=%s LIMIT 1",
                    [$names['pc_privacy_requests'],'email',$email]
                );
                return []!==$privacy;
            });
        }catch(\Throwable $ignored){return null;}
    }
}
