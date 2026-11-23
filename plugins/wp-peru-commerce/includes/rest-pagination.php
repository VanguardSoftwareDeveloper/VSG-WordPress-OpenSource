<?php
/**
 * IMP-12 — paginación contractualmente acotada (page/per_page).
 *
 * Query validada por Rest_Validator antes de usarse. No hacer CAST a SQL
 * con un overflow ni retornar más filas de las previstas por OpenAPI.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Rest_Pagination
{
    /**
     * @param array<string,mixed> $query
     * @return array{page:int,per_page:int,offset:int,fetch:int}
     */
    public static function window(array $query): array
    {
        $page = $query['page'] ?? 1;
        $perPage = $query['per_page'] ?? 20;
        if (!\is_int($page) || !\is_int($perPage)
            || $page < 1 || $perPage < 1 || $perPage > 100
            || $page - 1 > \intdiv(\PHP_INT_MAX, $perPage)
            || $perPage >= \PHP_INT_MAX) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'page');
        }
        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'fetch' => $perPage + 1,
        ];
    }

    /**
     * La consulta del módulo devuelve como máximo per_page+1 filas bajo
     * sus filtros por instalación/actor; la fila adicional solo indica
     * has_more, no se serializa ni da acceso a información ajena.
     *
     * @param list<array<string,mixed>> $rows
     * @param array{page:int,per_page:int,offset:int,fetch:int} $window
     * @return array{items:list<array<string,mixed>>,page:int,per_page:int,has_more:bool}
     */
    public static function envelope(array $rows, array $window): array
    {
        if (!\array_is_list($rows) || \count($rows) > $window['fetch']
            || $window['per_page'] < 1 || $window['per_page'] > 100) {
            throw new Rest_Failure('pc_result_invalid', 500);
        }
        $hasMore = \count($rows) > $window['per_page'];
        if ($hasMore) {
            \array_pop($rows);
        }
        return [
            'items' => $rows,
            'page' => $window['page'],
            'per_page' => $window['per_page'],
            'has_more' => $hasMore,
        ];
    }
}
