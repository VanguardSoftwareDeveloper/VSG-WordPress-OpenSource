<?php
/**
 * IMP-16 — contenido público accesible y rastreable con PHP/WordPress.
 *
 * [perucommerce_catalog] y bloque perucommerce/catalog: listado paginado.
 * [perucommerce_product id="123"] y bloque perucommerce/product: ficha.
 * /?pc_product=123: ficha pública canónica, sin rewrites ni Angular.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Catalog_Public_View
{
    private static bool $hooks = false;
    private static bool $cssSent = false;
    /** Solo el producto de la petición pública /?pc_product=ID. */
    private static ?array $routeProduct = null;
    private static int $routeStatus = 200;

    public static function registerHooks(): void
    {
        if (self::$hooks) {
            return;
        }
        self::$hooks = true;
        \add_action('init', [self::class, 'registerContent'], 8);
        \add_filter('query_vars', [self::class, 'queryVars']);
        \add_filter('get_canonical_url', [self::class, 'catalogCanonical'], 10, 2);
        \add_filter('redirect_canonical', [self::class, 'keepCatalogPagination'], 10, 2);
        \add_action('template_redirect', [self::class, 'renderProductRoute'], 1);
    }

    /** Bloques PHP-only, insertables sin bundle de editor ni frontend. */
    public static function registerContent(): void
    {
        \add_shortcode('perucommerce_catalog', [self::class, 'catalogShortcode']);
        \add_shortcode('perucommerce_product', [self::class, 'productShortcode']);

        \register_block_type('perucommerce/catalog', [
            'api_version' => 3,
            'title' => 'Catálogo PeruCommerce',
            'description' => 'Lista pública de productos con enlaces a sus fichas.',
            'category' => 'widgets',
            'supports' => ['html' => false, 'autoRegister' => true],
            'attributes' => [
                'perPage' => ['type' => 'integer', 'default' => 12],
            ],
            'render_callback' => static fn (array $attrs): string =>
                self::catalog(['per_page' => $attrs['perPage'] ?? 12]),
        ]);
        \register_block_type('perucommerce/product', [
            'api_version' => 3,
            'title' => 'Ficha PeruCommerce',
            'description' => 'Muestra el contenido público de un producto por ID.',
            'category' => 'widgets',
            'supports' => ['html' => false, 'autoRegister' => true],
            'attributes' => [
                'productId' => ['type' => 'string', 'default' => ''],
            ],
            'render_callback' => static fn (array $attrs): string =>
                self::product(['id' => $attrs['productId'] ?? '']),
        ]);
    }

    /** @param list<string> $vars
     * @return list<string>
     */
    public static function queryVars(array $vars): array
    {
        foreach (['pc_product', 'pc_variant', 'pc_page'] as $name) {
            if (!\in_array($name, $vars, true)) {
                $vars[] = $name;
            }
        }
        return $vars;
    }

    /** Shortcode devuelve HTML, jamás imprime ni consulta archivos privados. */
    public static function catalogShortcode(mixed $atts = []): string
    {
        $atts = \is_array($atts) ? $atts : [];
        return self::catalog([
            'per_page' => $atts['per_page'] ?? 12,
        ]);
    }

    public static function productShortcode(mixed $atts = []): string
    {
        $atts = \is_array($atts) ? $atts : [];
        return self::product(['id' => $atts['id'] ?? '']);
    }

    public static function catalog(array $attrs = []): string
    {
        if (\is_admin() && !\wp_doing_ajax()) {
            return self::notice('El catálogo se muestra en la página publicada.');
        }
        Bootstrap::loadPublicCatalog();
        try {
            $perPage = self::integer($attrs['per_page'] ?? 12, 1, 100);
            $page = self::integer(\get_query_var('pc_page', '1'), 1, \PHP_INT_MAX);
            $result = Catalog_Public_Service::listing([
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (\Throwable $ignored) {
            return self::notice('El catálogo no está disponible en este momento.');
        }

        $html = '<section class="pc-catalog" aria-label="Catálogo de productos">'
            . '<h2>Productos</h2>';
        if ([] === $result['items']) {
            $html .= '<p>No hay productos disponibles en esta página.</p>';
        } else {
            $html .= '<ul class="pc-catalog__list">';
            foreach ($result['items'] as $product) {
                $html .= '<li class="pc-catalog__card"><article>'
                    . '<h3><a href="' . \esc_url(self::productUrl($product['id'])) . '">'
                    . \esc_html($product['title']) . '</a></h3>'
                    . '<p class="pc-catalog__kind">' . \esc_html(self::kindLabel($product['kind'])) . '</p>'
                    . '<p class="pc-catalog__price">' . self::price($product) . '</p>';
                if (isset($product['description'])) {
                    $excerpt = \mb_substr(
                        \trim(\wp_strip_all_tags($product['description'])), 0, 180, 'UTF-8'
                    );
                    if ('' !== $excerpt) {
                        $html .= '<p>' . \esc_html($excerpt) . '</p>';
                    }
                }
                $html .= '</article></li>';
            }
            $html .= '</ul>';
        }

        $base = self::catalogPageUrl();
        if (null !== $base) {
            $html .= '<nav class="pc-catalog__pages" aria-label="Paginación del catálogo">';
            if ($page > 1) {
                $prev = $page - 1;
                $html .= '<a rel="prev" href="' . \esc_url(
                    1 === $prev ? $base : \add_query_arg('pc_page', $prev, $base)
                ) . '">Anterior</a>';
            }
            $html .= '<span>Página ' . \esc_html((string) $page) . '</span>';
            if ($result['has_more'] && $page < \PHP_INT_MAX) {
                $html .= '<a rel="next" href="' . \esc_url(
                    \add_query_arg('pc_page', $page + 1, $base)
                ) . '">Siguiente</a>';
            }
            $html .= '</nav>';
        }
        return self::withStyles($html . '</section>');
    }

    public static function product(array $attrs = []): string
    {
        Bootstrap::loadPublicCatalog();
        try {
            $id = self::sqlId($attrs['id'] ?? null);
            $product = Catalog_Public_Service::detail($id);
            $chosenId = self::queryVariant();
            return self::productHtml($product, $chosenId);
        } catch (\Throwable $ignored) {
            return self::notice('Producto no disponible.');
        }
    }

    /**
     * URL WordPress ordinaria sin rewrites, válida con enlaces permanentes
     * simples o bonitos. No se inventa un endpoint REST adicional.
     */
    public static function productUrl(string $productId, ?string $variantId = null): string
    {
        $params = ['pc_product' => Domain_Validation::sqlId($productId)];
        if (null !== $variantId) {
            $params['pc_variant'] = Domain_Validation::sqlId($variantId);
        }
        return \add_query_arg($params, \home_url('/'));
    }

    /**
     * Resolver únicamente en la raíz pública. Un pc_product adjunto a otra
     * página, checkout, búsqueda o REST no toma el control de esa URL.
     * No necesita una regla de rewrite ni flush_rewrite_rules().
     */
    public static function renderProductRoute(): void
    {
        if (\is_admin() || \is_feed() || \wp_doing_ajax()
            || !self::isProductRootRequest()) {
            return;
        }

        Bootstrap::loadPublicCatalog();
        $product = null;
        $selectedId = null;
        $status = 200;
        try {
            $id = self::sqlId(\get_query_var('pc_product'));
            $selectedId = self::queryVariant();
            $product = Catalog_Public_Service::detail($id);
            if (null !== $selectedId && !self::containsVariant($product, $selectedId)) {
                $status = 404;
                $product = null;
            }
        } catch (\InvalidArgumentException $ignored) {
            $status = 404;
        } catch (\Rest_Failure $failure) {
            $status = 404 === $failure->status ? 404 : 503;
        } catch (\Transaction_Failure $failure) {
            $status = 'pc_catalog_not_found' === $failure->getMessage() ? 404 : 503;
        } catch (\Throwable $ignored) {
            $status = 503;
        }

        self::$routeProduct = $product;
        self::$routeStatus = $status;
        \status_header($status);
        if (200 !== $status) {
            \nocache_headers();
        }
        // La home de WordPress puede ser un post singular con su canonical.
        // Solo se reemplaza para esta URL de detalle del plugin.
        \remove_action('wp_head', 'rel_canonical');
        \add_filter('pre_get_document_title', [self::class, 'documentTitle'], 20);
        \add_action('wp_head', [self::class, 'headMetadata'], 1);
        \get_header();
        echo '<main id="pc-product" class="pc-catalog pc-catalog__route">';
        if (null === $product) {
            echo self::notice(404 === $status
                ? 'Producto no disponible.' : 'Catálogo temporalmente no disponible.');
        } else {
            echo self::productHtml($product, $selectedId, true);
        }
        echo '</main>';
        \get_footer();
        exit;
    }

    public static function documentTitle(string $original): string
    {
        $title = null === self::$routeProduct
            ? (404 === self::$routeStatus ? 'Producto no disponible' : 'Catálogo no disponible')
            : self::$routeProduct['title'];
        return $title . ' — ' . \get_bloginfo('name');
    }

    /** Metadatos sin imágenes/identidad inventadas y sin precio de checkout. */
    public static function headMetadata(): void
    {
        if (null === self::$routeProduct) {
            echo '<meta name="robots" content="noindex,follow">' . "\n";
            return;
        }
        $product = self::$routeProduct;
        $description = \mb_substr(
            \trim(\wp_strip_all_tags($product['description'] ?? '')), 0, 160, 'UTF-8'
        );
        if ('' !== $description) {
            echo '<meta name="description" content="' . \esc_attr($description) . '">' . "\n";
        }
        echo '<link rel="canonical" href="' . \esc_url(self::productUrl($product['id'])) . '">' . "\n";
    }

    private static function isProductRootRequest(): bool
    {
        if (!\in_array($_SERVER['REQUEST_METHOD'] ?? null, ['GET', 'HEAD'], true)
            || '' === \get_query_var('pc_product', '')) {
            return false;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        if (!\is_string($uri) || \strlen($uri) > 2048) {
            return false;
        }
        $actual = \wp_parse_url($uri, \PHP_URL_PATH);
        $home = \wp_parse_url(\home_url('/'), \PHP_URL_PATH);
        if (!\is_string($actual) || !\is_string($home)
            || \rtrim(\rawurldecode($actual), '/') !== \rtrim($home, '/')) {
            return false;
        }
        foreach (\array_keys($_GET) as $key) {
            if (!\is_string($key) || !\in_array($key, ['pc_product', 'pc_variant'], true)) {
                return false;
            }
        }
        return true;
    }

    /** Sólo las páginas que realmente contienen el catálogo versionan su canonical. */
    public static function catalogCanonical(string $url, \WP_Post $post): string
    {
        if ((int) $post->ID !== (int) \get_queried_object_id()
            || !self::hostsCatalog($post)) {
            return $url;
        }
        $page = \get_query_var('pc_page', '');
        try {
            $number = self::integer($page, 1, \PHP_INT_MAX);
            return $number > 1 ? \add_query_arg('pc_page', $number, $url) : $url;
        } catch (\Throwable $ignored) {
            return $url;
        }
    }

    /** Evitar que la canonicalización de WP borre la página 2+ del catálogo. */
    public static function keepCatalogPagination(mixed $redirect, string $_requested): mixed
    {
        if (!\is_singular() || '' === \get_query_var('pc_page', '')) {
            return $redirect;
        }
        $post = \get_queried_object();
        return $post instanceof \WP_Post && self::hostsCatalog($post)
            ? false : $redirect;
    }

    private static function hostsCatalog(\WP_Post $post): bool
    {
        return \has_shortcode($post->post_content, 'perucommerce_catalog')
            || \has_block('perucommerce/catalog', $post);
    }

    /** @return string|null El contexto no siempre es una página WP publicada. */
    private static function catalogPageUrl(): ?string
    {
        if (!\is_singular() || \get_post_status(\get_queried_object_id()) !== 'publish') {
            return null;
        }
        $url = \get_permalink(\get_queried_object_id());
        return \is_string($url) && '' !== $url ? $url : null;
    }

    private static function productHtml(
        array $product, ?string $variantId, bool $standalone = false
    ): string
    {
        $variants = $product['variants'];
        $selected = self::chooseVariant($product, $variantId);
        if (null === $selected) {
            return self::notice('Presentación no disponible.');
        }
        $heading = $standalone ? 'h1' : 'h2';
        $html = '<article class="pc-catalog__product" data-pc-product-id="'
            . \esc_attr($product['id']) . '" data-pc-selected-sellable="'
            . \esc_attr($selected['id']) . '">'
            . '<' . $heading . '>' . \esc_html($product['title']) . '</' . $heading . '>'
            . '<p class="pc-catalog__kind">' . \esc_html(self::kindLabel($product['kind'])) . '</p>';
        if (isset($product['description'])) {
            $html .= '<div class="pc-catalog__description">'
                . \wp_kses_post($product['description']) . '</div>';
        }

        if (\count($variants) > 1) {
            $html .= '<nav aria-label="Seleccionar presentación"><ul class="pc-catalog__variants">';
            foreach ($variants as $v) {
                $active = $selected['id'] === $v['id'];
                $href = self::productUrl($product['id'], $v['id']);
                $html .= '<li><a href="' . \esc_url($href) . '"'
                    . ($active ? ' aria-current="true"' : '') . '>'
                    . \esc_html($v['label']) . ' — ' . self::price($v) . '</a></li>';
            }
            $html .= '</ul></nav>';
        }

        $html .= '<p class="pc-catalog__price">' . self::price($selected) . '</p>';
        if (!$selected['can_order']) {
            $html .= '<p>La compra de esta presentación no está disponible por el momento.</p>';
        } else {
            // Catálogo sigue siendo HTML PHP indexable; el CTA solo apunta
            // a una página WordPress REAL cuyo bundle Angular existe.
            $cartUrl = \class_exists(Angular_Surfaces::class)
                ? Angular_Surfaces::publicCartUrl() : null;
            if (null !== $cartUrl) {
                $url=\add_query_arg(
                    ['product'=>$product['id'],'variant'=>$selected['id']],$cartUrl
                );
                $html .= '<p><a class="pc-catalog__buy-link" href="'
                    . \esc_url($url) . '">Añadir al carrito</a></p>';
            } else {
                $html .= '<p>El carrito aún no está disponible en esta instalación.</p>';
            }
        }
        $html .= '<div class="pc-catalog__cart-slot" data-pc-cart-product="'
            . \esc_attr($product['id']) . '" data-pc-cart-sellable="'
            . \esc_attr($selected['id']) . '" data-pc-order-allowed="'
            . ($selected['can_order'] ? '1' : '0') . '"></div></article>';
        return self::withStyles($html);
    }

    private static function chooseVariant(array $product, ?string $id): ?array
    {
        if (null !== $id) {
            foreach ($product['variants'] as $variant) {
                if ($variant['id'] === $id) {
                    return $variant;
                }
            }
            return null;
        }
        foreach ($product['variants'] as $variant) {
            if ($variant['price_cents'] === $product['price_cents']
                && $variant['sale_active'] === $product['sale_active']
                && ($variant['sale_price_cents'] ?? null)
                    === ($product['sale_price_cents'] ?? null)) {
                return $variant;
            }
        }
        return null;
    }

    private static function containsVariant(array $product, string $id): bool
    {
        foreach ($product['variants'] as $variant) {
            if ($variant['id'] === $id) {
                return true;
            }
        }
        return false;
    }

    private static function queryVariant(): ?string
    {
        $value = \get_query_var('pc_variant', '');
        return '' === $value ? null : self::sqlId($value);
    }

    private static function sqlId(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value)) {
            throw new \InvalidArgumentException('pc_public_id_invalid');
        }
        return Domain_Validation::sqlId((string) $value);
    }

    private static function integer(mixed $value, int $min, int $max): int
    {
        if (\is_int($value)) {
            $number = $value;
        } elseif (\is_string($value)
            && \preg_match('/\A[1-9][0-9]*\z/D', $value)
            && \strlen($value) <= \strlen((string) \PHP_INT_MAX)
            && (\strlen($value) < \strlen((string) \PHP_INT_MAX)
                || \strcmp($value, (string) \PHP_INT_MAX) <= 0)) {
            $number = (int) $value;
        } else {
            throw new \InvalidArgumentException('pc_public_page_invalid');
        }
        if ($number < $min || $number > $max) {
            throw new \InvalidArgumentException('pc_public_page_invalid');
        }
        return $number;
    }

    private static function price(array $item): string
    {
        $regular = Money::fromApi($item['price_cents']);
        if (0 === $regular) {
            return 'Precio por definir';
        }
        if (true === $item['sale_active'] && isset($item['sale_price_cents'])) {
            $offer = Money::fromApi($item['sale_price_cents']);
            return '<del>' . \esc_html(self::money($regular)) . '</del> '
                . '<ins>' . \esc_html(self::money($offer)) . '</ins>';
        }
        return \esc_html(self::money($regular));
    }

    private static function money(int $cents): string
    {
        // Evitar number_format(): pasa por float y puede perder céntimos
        // de importes BIGINT altos. Separadores solo sobre texto decimal.
        $whole = (string) \intdiv($cents, 100);
        $grouped = \preg_replace('/\B(?=(?:\d{3})+(?!\d))/', ',', $whole);
        return 'S/ ' . $grouped
            . '.' . \str_pad((string) ($cents % 100), 2, '0', \STR_PAD_LEFT);
    }

    private static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'physical' => 'Producto físico',
            'digital' => 'Producto digital',
            'service' => 'Servicio presencial',
            default => 'Producto',
        };
    }

    private static function notice(string $message): string
    {
        return self::withStyles(
            '<section class="pc-catalog" role="status"><p>' . \esc_html($message)
                . '</p></section>'
        );
    }

    /** Solo se incluye donde existe superficie pública; ningún bundle Angular. */
    private static function withStyles(string $html): string
    {
        if (self::$cssSent) {
            return $html;
        }
        self::$cssSent = true;
        $style = '.pc-catalog{max-width:72rem;margin:1.5rem auto;padding:1rem;line-height:1.5}'
            . '.pc-catalog__list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,16rem),1fr));gap:1rem;list-style:none;padding:0;margin:0}'
            . '.pc-catalog__card,.pc-catalog__product{border:1px solid currentColor;border-radius:.5rem;padding:1rem}'
            . '.pc-catalog__card h3 a{display:inline-flex;align-items:center;min-height:44px}'
            . '.pc-catalog__kind{opacity:.8}.pc-catalog__price{font-weight:700}'
            . '.pc-catalog__variants{display:flex;flex-wrap:wrap;gap:.5rem;list-style:none;padding:0}'
            . '.pc-catalog__variants a,.pc-catalog__pages a{display:inline-flex;align-items:center;min-height:44px;min-width:44px;padding:.5rem .75rem;border:1px solid currentColor;border-radius:.4rem}'
            . '.pc-catalog__pages{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;margin-block:1.5rem}'
            . '.pc-catalog a:focus-visible{outline:3px solid currentColor;outline-offset:3px}'
            . '.pc-catalog__description{overflow-wrap:anywhere}'
            . '.pc-catalog__product{max-width:52rem;margin:auto}';
        return '<style id="pc-catalog-css">' . $style . '</style>' . $html;
    }
}
