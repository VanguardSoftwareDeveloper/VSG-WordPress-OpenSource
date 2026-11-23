<?php
/**
 * IMP-50 — inventario mínimo de categorías con posible retención legal.
 * No decide automáticamente base jurídica, plazo de conservación ni borrado.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}
final class Privacy_Retention
{
    private const CATEGORIES=[
        'orders','payment_events','external_documents',
        'digital_rights','complaints','privacy_requests',
        'audit_events','appointments','delivery_history',
        'stock_events','wordpress_account','communications',
    ];

    public static function allowed(string $category):bool
    {
        return \in_array($category,self::CATEGORIES,true);
    }

    /**
     * Inventario de responsabilidades, no exportación del sujeto ni prueba
     * de que una fila concreta deba retenerse. La prueba por caso procede
     * exclusivamente del verificador P-C en Privacy_Legal.
     */
    public static function categories():array
    {
        return self::CATEGORIES;
    }
}
