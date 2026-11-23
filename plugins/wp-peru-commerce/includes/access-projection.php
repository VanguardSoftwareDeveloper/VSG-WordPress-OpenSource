<?php
/**
 * IMP-11 — serialización server-side con allowlist OpenAPI anidada.
 *
 * No proyectar un SELECT * ni enviar DTO completo a Angular para ocultarlo:
 * cada campo, lista y subobjeto se reconstruye desde contrato declarativo.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Access_Projection
{
    /**
     * Solo respuestas JSON de staff con esquema 2xx conocido. Binarios y
     * secretos de entrega única requieren el emisor protegido de su módulo.
     *
     * @param array<string,mixed> $body
     * @param array<string,mixed> $requestContext
     * @return array<string,mixed>
     */
    public static function staff(
        string $operationId,
        array $body,
        array $requestContext = [],
        ?string $wpRestNonce = null
    ): array {
        $rule = Access_Contract::operation($operationId);
        if (null === $rule
            || !\in_array($rule['actor'], ['staff', 'admin_only'], true)) {
            throw new \RuntimeException('pc_projection_denied');
        }
        $proof = Access_Control::require($operationId, $requestContext, $wpRestNonce);
        $role = Access_Roles::currentRole();
        $root = Access_Output_Contract::root($operationId);
        if (null === $role || null === $root) {
            // R8-58/59 primera entrega de enlace y GET privados binarios no
            // pueden emitirse a través de un mapper JSON genérico.
            throw new \RuntimeException('pc_projection_requires_special_handler');
        }
        if ('O' === $role && 'R8-39' === $operationId
            && !\is_array($proof['allowed_notification_ids'] ?? null)) {
            throw new \RuntimeException('pc_projection_scope_unverified');
        }

        $responseAllow = \array_fill_keys($rule['outputs'], true);
        $never = \array_fill_keys($rule['never'], true);
        $definition = Access_Output_Contract::schema($root);
        if (null === $definition || 'object' !== $definition[0]) {
            throw new \RuntimeException('pc_projection_contract_invalid');
        }
        $projected = self::shape(
            \array_intersect_key($body, $responseAllow),
            ['ref', $root],
            $never,
            $role,
            $proof,
            0,
            $root
        );
        if (!\is_array($projected)) {
            throw new \RuntimeException('pc_projection_contract_invalid');
        }

        if ('R8-19' === $operationId) {
            $allowed = 'A' === $role
                ? Access_Roles::adminCapabilities() : Access_Roles::operatorCapabilities();
            if (isset($projected['ui_capabilities'])
                && \is_array($projected['ui_capabilities'])) {
                $projected['ui_capabilities'] = \array_values(\array_intersect(
                    $projected['ui_capabilities'], $allowed
                ));
            }
            $projected['role_label'] = $role;
        }
        return $projected;
    }

    /**
     * @param array<mixed> $descriptor
     * @param array<string,bool> $never
     * @param array<string,mixed> $proof
     */
    private static function shape(
        mixed $value,
        array $descriptor,
        array $never,
        string $role,
        array $proof,
        int $depth,
        string $schema
    ): mixed {
        if ($depth > 16) {
            throw new \RuntimeException('pc_projection_depth_exceeded');
        }
        $type = $descriptor[0] ?? 'deny';
        if ('ref' === $type) {
            $name = $descriptor[1] ?? '';
            $nested = Access_Output_Contract::schema($name);
            if (null === $nested) {
                throw new \RuntimeException('pc_projection_contract_invalid');
            }
            return self::shape($value, $nested, $never, $role, $proof, $depth + 1, $name);
        }
        if ('nullable' === $type) {
            return null === $value ? null : self::shape(
                $value, ['scalar', $descriptor[1] ?? 'deny'],
                $never, $role, $proof, $depth + 1, $schema
            );
        }
        if ('scalar' === $type) {
            $scalar = $descriptor[1] ?? null;
            return match ($scalar) {
                'string' => \is_string($value) ? $value : throw new \RuntimeException('pc_projection_value_invalid'),
                'integer' => \is_int($value) ? $value : throw new \RuntimeException('pc_projection_value_invalid'),
                'boolean' => \is_bool($value) ? $value : throw new \RuntimeException('pc_projection_value_invalid'),
                'number' => (\is_int($value) || \is_float($value)) && \is_finite((float) $value)
                    ? $value : throw new \RuntimeException('pc_projection_value_invalid'),
                'null' => null === $value ? null : throw new \RuntimeException('pc_projection_value_invalid'),
                default => throw new \RuntimeException('pc_projection_contract_invalid'),
            };
        }
        if ('list' === $type) {
            // IMP-19: CoverageView puede listar hasta 2500 distritos
            // autorizados; conservar 200 como límite defensivo de todos
            // los otros DTO staff y sus listas de objetos sensibles.
            $max = 'CoverageView' === $schema ? 2500 : 200;
            if (!\is_array($value) || !\array_is_list($value) || \count($value) > $max) {
                throw new \RuntimeException('pc_projection_value_invalid');
            }
            $out = [];
            foreach ($value as $item) {
                if ($schema === 'NotificationPage' && 'O' === $role) {
                    if (!\is_array($item)
                        || !\is_string($item['id'] ?? null)
                        || !\in_array($item['id'], $proof['allowed_notification_ids'], true)
                        || !\in_array((string) ($item['resource_type'] ?? ''),
                            ['order', 'order_line', 'appointment', 'sellable', 'stock', 'delivery'], true)) {
                        continue;
                    }
                }
                $out[] = self::shape($item, $descriptor[1], $never, $role, $proof,
                    $depth + 1, $schema);
            }
            return $out;
        }
        if ('object' === $type) {
            if (!\is_array($value) || (\array_is_list($value) && [] !== $value)) {
                throw new \RuntimeException('pc_projection_value_invalid');
            }
            $allowed = $descriptor[1] ?? null;
            if (!\is_array($allowed)) {
                throw new \RuntimeException('pc_projection_contract_invalid');
            }
            $result = [];
            foreach ($allowed as $field => $child) {
                if (!\array_key_exists($field, $value) || isset($never[$field])
                    || 'deny' === ($child[0] ?? 'deny')
                    || !self::fieldAllowed($schema, $field, $role, $proof, $value)) {
                    continue;
                }
                if (null === $value[$field]) {
                    if ('nullable' === ($child[0] ?? 'deny')) {
                        $result[$field] = null;
                    }
                    // Campos opcionales no nulables se omiten; solo un
                    // descriptor nullable del OpenAPI admite JSON null.
                    continue;
                }
                $result[$field] = self::shape(
                    $value[$field], $child, $never, $role, $proof, $depth + 1,
                    'contact_for_task' === $field ? 'contact_for_task' : $schema
                );
            }
            return $result;
        }
        throw new \RuntimeException('pc_projection_contract_invalid');
    }

    /**
     * Todas las excepciones sensibles se aplican ANTES de copiar el campo.
     * $proof lo produce el verificador del recurso desde el servidor.
     *
     * @param array<string,mixed> $proof
     * @param array<string,mixed> $row
     */
    private static function fieldAllowed(
        string $schema,
        string $field,
        string $role,
        array $proof,
        array $row
    ): bool {
        if ('O' === $role) {
            if (\in_array($field, [
                'cases', 'case_detail', 'digital_rights', 'digital_assignments',
                'financial_movements', 'external_documents', 'claimant',
                'representative', 'fiscal', 'tax_id_ruc', 'issuer_identity',
                'raw_payment_ref', 'identity_evidence', 'evidence_manifest',
            ], true)) {
                return false;
            }
        }

        if ('AdminOrder' === $schema && 'A' === $role) {
            if ('financial_movements' === $field) {
                return (\current_user_can('perucommerce_orders_financial_adjust')
                        || \current_user_can('perucommerce_refunds_record_external'))
                    && true === ($proof['financial_purpose_verified'] ?? false);
            }
            if ('external_documents' === $field) {
                return \current_user_can('perucommerce_settings_manage')
                    && \current_user_can('perucommerce_orders_read')
                    && true === ($proof['fiscal_purpose_verified'] ?? false);
            }
            if ('digital_rights' === $field) {
                return \current_user_can('perucommerce_digital_files_manage')
                    && true === ($proof['digital_purpose_verified'] ?? false);
            }
            if ('cases' === $field || 'case_detail' === $field) {
                return \current_user_can('perucommerce_orders_read')
                    && true===($proof['case_scope_verified']??false)
                    && ('cases'===$field
                        || true===($proof['case_id_verified']??false));
            }
        }
        if (\in_array($schema, ['AdminProduct', 'AdminVariant'], true)
            && 'digital_assignments' === $field) {
            return 'A' === $role
                && \current_user_can('perucommerce_catalog_edit')
                && \current_user_can('perucommerce_digital_files_manage')
                && true === ($proof['digital_purpose_verified'] ?? false);
        }
        if ('contact_for_task' === $field) {
            return true === ($proof['contact_scope_verified'] ?? false);
        }
        if ('contact_for_task' === $schema && 'O' === $role) {
            $task = $proof['task'] ?? null;
            return match ($field) {
                'recipient', 'phone' => \in_array($task, ['contact', 'delivery', 'service'], true),
                'email' => \in_array($task, ['contact', 'service'], true),
                'address' => 'delivery' === $task
                    && \current_user_can('perucommerce_orders_fulfill'),
                default => false,
            };
        }
        if ('NotificationEntry' === $schema && 'O' === $role) {
            return !\in_array($field, ['resource_id', 'resource_type'], true)
                || true === ($proof['notification_resource_refs_verified'] ?? false);
        }
        return true;
    }
}
