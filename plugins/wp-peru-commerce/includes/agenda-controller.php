<?php
/**
 * IMP-18 — rutas R8-04/36/37/53 sobre recurso único.
 *
 * Los guards nunca reciben pruebas de permisos del navegador; el router
 * valida DTO/OpenAPI, sesión staff+nonce y capacidades por operación.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Agenda_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    public static function registerGates(): void
    {
        if (self::$gates) {
            return;
        }
        self::$gates = true;
        Module_Gates::registerOperation(
            'appointments', 'read', static fn (): bool =>
                \post_type_exists(Catalog_Controller::POST_TYPE)
        );
        Module_Gates::registerOperation(
            'appointments', 'write', static fn (): bool =>
                \post_type_exists(Catalog_Controller::POST_TYPE)
                && self::siteValid()
        );
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadAgenda();

        Rest_Router::bind(
            'R8-04',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Agenda_Service::slots($ctx['path']['id'], $ctx['query']['date']),
            ],
            [self::class, 'publicGuard']
        );
        Rest_Router::bind(
            'R8-36',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Agenda_Service::adminView($ctx['query']),
            ],
            [self::class, 'staffGuard']
        );
        Rest_Router::bind(
            'R8-37',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Agenda_Mutations::closure($ctx['body']),
            ],
            [self::class, 'staffGuard']
        );
        Rest_Router::bind(
            'R8-53',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Agenda_Mutations::schedule($ctx['body']),
            ],
            [self::class, 'staffGuard']
        );
    }

    public static function publicGuard(array $_ctx, ?string $role): array
    {
        if (null !== $role || !self::siteValid()) {
            return [];
        }
        return ['authorized' => true];
    }

    /** @return array<string,bool> */
    public static function staffGuard(array $ctx, ?string $role): array
    {
        if (!\in_array($role, ['A','O'], true)
            || Access_Roles::currentRole() !== $role
            || !\current_user_can('perucommerce_appointments_manage')
            || !self::siteValid()) {
            return [];
        }
        $id = $ctx['body']['resource_id'] ?? $ctx['query']['resource_id']
            ?? Agenda_Time::RESOURCE;
        if (!\is_string($id) || Agenda_Time::RESOURCE !== $id) {
            return [];
        }
        try {
            $exists = Transaction::run(static function (Transaction_Context $tx): bool {
                $row = $tx->rows(
                    'SELECT resource_id FROM %i WHERE resource_id = %s',
                    [Agenda_Repository::table('pc_schedule_revisions'), Agenda_Time::RESOURCE]
                );
                return 1 === \count($row);
            });
            if (!$exists) {
                return [];
            }
        } catch (\Throwable $ignored) {
            return [];
        }
        $proof = [
            'authorized' => true,
            'same_site' => true,
            'resource_checked' => true,
            'state_checked' => true,
            'field_checked' => true,
        ];
        if (isset($ctx['body']['weekly_rules'])) {
            $proof['schedule_only'] = true;
        }
        if (isset($ctx['body']['action'])) {
            $proof['schedule_only'] = false;
        }
        return $proof;
    }

    private static function siteValid(): bool
    {
        return \function_exists('get_current_blog_id')
            && (int) \get_current_blog_id() > 0
            && \post_type_exists(Catalog_Controller::POST_TYPE);
    }
}
