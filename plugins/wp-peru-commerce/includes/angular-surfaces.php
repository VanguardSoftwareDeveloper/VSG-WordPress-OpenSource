<?php
/**
 * IMP-54: un punto de montaje Angular por documento, sin rutas SPA globales.
 * El manifest proviene exclusivamente de una build REAL, nunca se inventa.
 * No se carga JS Angular en catálogo PHP ni en otras páginas WordPress.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Angular_Surfaces
{
    private static bool $registered=false;
    private static bool $root=false;
    private static ?string $adminHook=null;
    private static ?array $manifest=null;
    private static bool $manifestRead=false;

    public static function registerHooks(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        \add_action('init',[self::class,'registerShortcode'],12);
        \add_action('wp_enqueue_scripts',[self::class,'preparePublic'],9);
        \add_action('admin_menu',[self::class,'registerMenu'],20);
        \add_action('admin_enqueue_scripts',[self::class,'prepareAdmin'],9);
    }

    public static function registerShortcode(): void
    {
        \add_shortcode('perucommerce_app',[self::class,'shortcode']);
    }

    public static function preparePublic(): void
    {
        if (!\is_singular('page')) { return; }
        $post=\get_queried_object();
        if ($post instanceof \WP_Post && \has_shortcode($post->post_content,'perucommerce_app')) {
            self::enqueue();
        }
    }

    public static function shortcode(mixed $raw=[]): string
    {
        if (!\is_array($raw)) { $raw=[]; }
        $atts=\shortcode_atts(['scene'=>'cart'],$raw,'perucommerce_app');
        $scene=$atts['scene'];
        if (!\in_array($scene,['cart','checkout','return'],true)
            || !\is_singular('page') || self::$root) { return ''; }
        self::$root=true;
        // Escena de retorno solo en página independiente de WordPress:
        // ningún query arg de MP es una confirmación de pago.
        return self::root($scene);
    }

    public static function registerMenu(): void
    {
        if (!self::staffAllowed()) { return; }
        self::$adminHook=\add_menu_page(
            'PeruCommerce','PeruCommerce','read','perucommerce',
            [self::class,'adminPage'],'dashicons-store',32
        );
    }

    public static function prepareAdmin(string $hook): void
    {
        if (self::staffAllowed() && self::$adminHook!==null
            && $hook===self::$adminHook) {
            self::enqueue();
        }
    }

    public static function adminPage(): void
    {
        if (!self::staffAllowed()) {
            \wp_die(\esc_html__('No autorizado','peru-commerce'));
        }
        echo '<div class="wrap"><h1>PeruCommerce</h1>'
            .self::root('admin').'</div>';
    }

    private static function staffAllowed(): bool
    {
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/access-roles.php';
        return \in_array(Access_Roles::currentRole(),['A','O'],true)
            && \current_user_can('read')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin') && \is_network_admin());
    }

    private static function root(string $scene): string
    {
        if (!\is_ssl()) {
            return '<p role="status">Se requiere HTTPS para esta operación.</p>';
        }
        if (!self::enqueue()) {
            return '<p role="status">Esta interfaz aún no está disponible en la instalación.</p>';
        }
        $base=\rest_url('peru-commerce/v1/');
        $nonce='admin'===$scene ? \wp_create_nonce('wp_rest') : '';
        $digitalOpen='admin'===$scene
            && \current_user_can('perucommerce_digital_files_manage')
            && \current_user_can('perucommerce_orders_read')
            && 'A'===Access_Roles::currentRole();
        $digitalOpenUrl=$digitalOpen ? \admin_url('admin-post.php') : '';
        $digitalOpenNonce=$digitalOpen
            ? \wp_create_nonce('perucommerce_digital_case_open') : '';
        $checkout=self::pageUrl('checkout');
        return '<pc-root class="pc-app-root" data-scene="'.\esc_attr($scene).'"'
            .' data-api="'.\esc_url($base).'"'
            .' data-checkout="'.\esc_url($checkout??'').'"'
            .' data-nonce="'.\esc_attr($nonce).'"'
            .' data-digital-open-url="'.\esc_url($digitalOpenUrl).'"'
            .' data-digital-open-nonce="'.\esc_attr($digitalOpenNonce).'"'
            .' data-locale="es-PE" data-currency="PEN">'
            .'<p role="status">Cargando PeruCommerce…</p></pc-root>';
    }

    private static function enqueue(): bool
    {
        $manifest=self::manifest();
        if (null===$manifest) { return false; }
        if (!\function_exists('wp_enqueue_script_module')) { return false; }
        foreach ($manifest['stylesheets'] as $i=>$file) {
            \wp_enqueue_style(
                'peru-commerce-app-'.(string)$i,
                \plugins_url('assets/app/'.$file,PERUCOMMERCE_PLUGIN_FILE),
                [],PERUCOMMERCE_VERSION
            );
        }
        \wp_enqueue_script_module(
            'peru-commerce/app',
            \plugins_url('assets/app/'.$manifest['entry_module'],PERUCOMMERCE_PLUGIN_FILE),
            [],PERUCOMMERCE_VERSION
        );
        return true;
    }


    /** URL solo si existe página WP publicada y activa para esta escena. */
    public static function publicCartUrl(): ?string
    {
        return null===self::manifest()?null:self::pageUrl('carrito');
    }

    private static function pageUrl(string $slug): ?string
    {
        $page=\get_page_by_path($slug,OBJECT,'page');
        if (!$page instanceof \WP_Post || $page->post_status!=='publish'
            || !\has_shortcode($page->post_content,'perucommerce_app')) {
            return null;
        }
        $url=\get_permalink($page);
        $home=\wp_parse_url(\home_url('/'));
        $target=\is_string($url)?\wp_parse_url($url):false;
        if (!\is_string($url) || !\is_array($home) || !\is_array($target)
            || ($target['scheme']??null)!=='https'
            || \strtolower((string)($target['host']??''))!==\strtolower((string)($home['host']??''))
            || ($target['port']??443)!==($home['port']??443)
            || isset($target['user']) || isset($target['pass'])
            || isset($target['fragment'])) {
            return null;
        }
        return $url;
    }

    /**
     * IMP-67: consume exclusivamente el inventario de un build de navegador
     * REAL, con referencia a fuente y licencia de terceros. No reconstruir
     * valores a partir del nombre de un archivo o un JSON inventado.
     *
     * Verifica bytes físicos en cada carga por proceso. No valida P-C,
     * compatibilidad operativa ni licencia: esos son gates separados.
     */
    private static function manifest(): ?array
    {
        if (self::$manifestRead) { return self::$manifest; }
        self::$manifestRead=true;
        $dir=PERUCOMMERCE_PLUGIN_DIR.'assets/app/';
        $path=$dir.'pc-assets.json';
        if (!\is_file($path) || \is_link($path)
            || \filesize($path)<200 || \filesize($path)>200000) {
            return null;
        }
        try{
            $decoded=\json_decode((string)\file_get_contents($path),true,16,\JSON_THROW_ON_ERROR);
        }catch(\Throwable $ignored){return null;}
        if(!\is_array($decoded)
            || \array_keys($decoded)!==[
                'schema','release','schema_version','source_release',
                'frontend_api','entry_module','stylesheets','media_root',
                'files','third_party_notices','build_id',
            ]
            || ($decoded['schema']??null)!=='perucommerce-assets-v1'
            || ($decoded['release']??null)!==PERUCOMMERCE_VERSION
            || ($decoded['schema_version']??null)!==PERUCOMMERCE_SCHEMA_VERSION
            || ($decoded['frontend_api']??null)!=='peru-commerce/v1'
            || ($decoded['media_root']??null)!=='media'
            || !\is_string($decoded['source_release'])
            || !\preg_match('/\A[a-f0-9]{40}\z/D',$decoded['source_release'])
            || !\is_string($decoded['build_id'])
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$decoded['build_id'])
            || !\is_string($decoded['entry_module']??null)
            || !\preg_match('/-[A-Za-z0-9_-]{8,}\.js\z/D',$decoded['entry_module'])
            || !\is_array($decoded['stylesheets'])
            || !\array_is_list($decoded['stylesheets'])
            || \count($decoded['stylesheets'])<1
            || \count($decoded['stylesheets'])>20
            || !\is_array($decoded['files'])
            || !\array_is_list($decoded['files'])
            || \count($decoded['files'])<2
            || \count($decoded['files'])>500
            || !\is_array($decoded['third_party_notices'])
            || \array_keys($decoded['third_party_notices'])!==['path','sha256','bytes']){
            return null;
        }
        $root=\realpath($dir);
        if(!\is_string($root)||!\is_dir($root)){return null;}
        $names=[];$last='';
        $pieces=[
            'schema='.$decoded['schema'],
            'release='.$decoded['release'],
            'schema_version='.$decoded['schema_version'],
            'source_release='.$decoded['source_release'],
            'entry_module='.$decoded['entry_module'],
        ];
        foreach($decoded['stylesheets'] as $style){
            if(!\is_string($style)
                || !\preg_match('/-[A-Za-z0-9_-]{8,}\.css\z/D',$style)
                || isset($names['style:'.$style])){
                return null;
            }
            $names['style:'.$style]=true;
            $pieces[]='style='.$style;
        }
        foreach($decoded['files'] as $file){
            if(!\is_array($file)||\array_keys($file)!==['path','sha256','bytes']
                || !\is_string($file['path'])||!\is_string($file['sha256'])
                || !\is_int($file['bytes'])){
                return null;
            }
            $name=$file['path'];
            if(!self::safeAssetPath($name)
                || !\preg_match('/\.(?:js|css|woff2?|png|jpe?g|svg|webp|ico)\z/iD',$name)
                || !\preg_match('/\A[a-f0-9]{64}\z/D',$file['sha256'])
                || $file['bytes']<1||$file['bytes']>20000000
                || ($last!=='' && \strcmp($last,$name)>=0)
                || isset($names['asset:'.$name])
                || !self::assetMatches($root,$name,$file['bytes'],$file['sha256'])){
                return null;
            }
            $last=$name;$names['asset:'.$name]=true;
            $pieces[]='file='.$name."\t".$file['bytes']."\t".$file['sha256'];
        }
        foreach(\array_merge([$decoded['entry_module']],$decoded['stylesheets']) as $entry){
            if(!isset($names['asset:'.$entry])){return null;}
        }
        $license=$decoded['third_party_notices'];
        if($license['path']!=='3rdpartylicenses.txt'
            || !\is_string($license['sha256'])
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$license['sha256'])
            || !\is_int($license['bytes'])
            || $license['bytes']<100||$license['bytes']>2000000
            || !self::assetMatches($root,'3rdpartylicenses.txt',
                $license['bytes'],$license['sha256'])){
            return null;
        }
        $pieces[]='notices='.$license['bytes']."\t".$license['sha256'];
        if(!\hash_equals($decoded['build_id'],
            \hash('sha256',\implode("\n",$pieces)."\n"))){
            return null;
        }
        self::$manifest=[
            'entry_module'=>$decoded['entry_module'],
            'stylesheets'=>$decoded['stylesheets'],
        ];
        return self::$manifest;
    }

    private static function safeAssetPath(string $name):bool
    {
        return !\str_contains($name,'..')
            && !\str_contains($name,'\\')
            && !\str_contains($name,':')
            && 1===\preg_match(
                '/\A[A-Za-z0-9_-][A-Za-z0-9._-]*(?:\/[A-Za-z0-9_-][A-Za-z0-9._-]*)*\z/D',
                $name
            );
    }

    /** Refuse traversal and symlinked directories, not just final file. */
    private static function assetMatches(
        string $root,string $name,int $bytes,string $sha
    ):bool {
        if(!self::safeAssetPath($name)){return false;}
        $parts=\explode('/',$name);
        $current=$root;
        foreach($parts as $part){
            $current.='/'.$part;
            if(\is_link($current)){return false;}
        }
        $full=\realpath($current);
        if(!\is_string($full)||!\str_starts_with($full,$root.'/')
            || !\is_file($full)||\filesize($full)!==$bytes){
            return false;
        }
        $digest=\hash_file('sha256',$full);
        return \is_string($digest)&&\hash_equals($sha,$digest);
    }
}
