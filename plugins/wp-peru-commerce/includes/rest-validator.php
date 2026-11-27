<?php
/**
 * IMP-12 — validación estructural del OpenAPI 0.13.0-draft.
 *
 * Conserva tipos JSON reales: stdClass es objeto (incluso vacío),
 * array es lista. No coercionar importes/cantidades ni hacer mass-assignment.
 * La validez DTO no demuestra elegibilidad, identidad ni precio.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Rest_Validator
{
    private const MAX_JSON_BYTES = 131072;
    private const MAX_DEPTH = 24;
    private const MAX_PROPERTIES = 150;
    private const MAX_ARRAY = 500;

    /**
     * @return array<string,mixed> Datos temporales del controlador; solo
     * provider_auth contiene bytes HTTP crudos para comprobar firma real.
     */
    public static function request(\WP_REST_Request $request, string $operationId): array
    {
        $contract = Rest_Contract::operation($operationId);
        if (null === $contract) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
        $method = $request->get_method();
        $raw = $request->get_body();
        // IMP-19: CoveragePatch admite hasta 2500 distritos; el límite
        // general de 128 KiB bloquearía un DTO válido. Solo R8-52 tiene
        // margen adicional acotado (sin ampliar webhooks ni otros POST).
        $limit = 'R8-52' === $operationId ? 262144 : self::MAX_JSON_BYTES;
        if (!\is_string($raw) || \strlen($raw) > $limit) {
            throw new Rest_Failure('pc_request_too_large', 400);
        }

        $params = ['body' => [], 'path' => [], 'query' => [], 'headers' => []];
        foreach ($contract['params'] as $argument) {
            $where = $argument['in'];
            $name = $argument['name'];
            if ('path' === $where) {
                $candidate = $request->get_url_params()[$name] ?? null;
            } elseif ('query' === $where) {
                $candidate = $request->get_query_params()[$name] ?? null;
            } elseif ('header' === $where) {
                $candidate = $request->get_header($name);
            } else {
                throw new Rest_Failure('pc_field_invalid', 422);
            }
            if (null === $candidate) {
                if ($argument['required']) {
                    throw new Rest_Failure('pc_field_invalid', 422, false, $name);
                }
                if ('query' === $where && \array_key_exists('default', $argument['schema'])) {
                    $candidate = $argument['schema']['default'];
                } else {
                    continue;
                }
            }
            if ('query' === $where && \is_string($candidate)
                && 'integer' === ($argument['schema']['type'] ?? null)) {
                if (!\preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $candidate)
                    || \strlen($candidate) > \strlen((string) \PHP_INT_MAX)
                    || (\strlen($candidate) === \strlen((string) \PHP_INT_MAX)
                        && \strcmp($candidate, (string) \PHP_INT_MAX) > 0)) {
                    throw new Rest_Failure('pc_field_invalid', 422, false, $name);
                }
                $candidate = (int) $candidate;
            }
            self::check($candidate, $argument['schema'], $name, 0, false);
            $params[$where === 'header' ? 'headers' : $where][$name] = $candidate;
        }

        $query = $request->get_query_params();
        if (\count($query) > 16) {
            throw new Rest_Failure('pc_request_invalid', 400);
        }
        foreach ($query as $queryName => $queryValue) {
            if ('rest_route' === $queryName) {
                // WordPress sin enlaces permanentes usa ?rest_route=....
                continue;
            }
            if (!\array_key_exists($queryName, $params['query'])
                && !self::isKnownQueryName($contract['params'], $queryName)) {
                // Mercado Pago puede enviar data.id, type o topic en query
                // fuera del OpenAPI. No usar estos valores antes de verificar
                // x-signature y la cuenta, y limitar su volumen para evitar DoS.
                if ('R8-17' !== $operationId
                    || !\is_string($queryName)
                    || !\preg_match('/\A[a-zA-Z0-9_.-]{1,48}\z/D', $queryName)
                    || !\is_string($queryValue) || \strlen($queryValue) > 256) {
                    throw new Rest_Failure('pc_field_invalid', 422, false, (string) $queryName);
                }
            }
        }
        if ([] !== $contract['bodyMedia'] && '' === $raw
            && true === $contract['bodyRequired']) {
            throw new Rest_Failure('pc_json_invalid', 400);
        }
        if ([] === $contract['bodyMedia']) {
            if ('' !== $raw) {
                throw new Rest_Failure('pc_request_invalid', 400);
            }
            return $params;
        }
        if (['multipart/form-data'] === $contract['bodyMedia']) {
            // El controlador binario de IMP-25/40 debe validar MIME, tamaño,
            // contrato y storage, no descifrar archivos con parser JSON.
            throw new Rest_Failure('pc_module_not_ready', 423);
        }
        if (!\in_array('application/json', $contract['bodyMedia'], true)
            || !\preg_match('/\Aapplication\/json(?:\s*;[^\r\n]*)?\z/i',
                \trim($request->get_header('content-type') ?? ''))) {
            throw new Rest_Failure('pc_media_unsupported', 400);
        }
        try {
            $value = \json_decode($raw, false, self::MAX_DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $ignored) {
            throw new Rest_Failure('pc_json_invalid', 400);
        }
        if (!$value instanceof \stdClass) {
            throw new Rest_Failure('pc_json_invalid', 400);
        }
        self::check($value, $contract['body'], 'body', 0, false);
        try {
            $decoded = \json_decode($raw, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $ignored) {
            throw new Rest_Failure('pc_json_invalid', 400);
        }
        if (!\is_array($decoded)) {
            throw new Rest_Failure('pc_json_invalid', 400);
        }
        $params['body'] = $decoded;
        if ('R8-17' === $operationId) {
            if (\strlen($raw) > 65536) {
                throw new Rest_Failure('pc_request_too_large', 400);
            }
            $signature = $request->get_header('x-signature');
            $requestId = $request->get_header('x-request-id');
            if (!\is_string($signature) || '' === $signature
                || \strlen($signature) > 512
                || !\is_string($requestId) || '' === $requestId
                || \strlen($requestId) > 128) {
                throw new Rest_Failure('pc_access_denied', 403);
            }
            // Material efímero: SOLO autenticador de firma, no logs/cola.
            // La validación HMAC REAL se implementa en IMP-27+.
            $params['provider_auth'] = [
                'raw_body' => $raw,
                'x_signature' => $signature,
                'x_request_id' => $requestId,
                'query' => $query,
            ];
        }
        return $params;
    }

    /**
     * Para servicios internos que no reciben directamente WP_REST_Request.
     * El llamante construye stdClass recursivo y no pierde el tipo objeto
     * del JSON original (en particular {} frente a []).
     */
    public static function namedObject(string $schemaName, \stdClass $document): void
    {
        $schema = Rest_Contract::schema($schemaName);
        if (null === $schema) {
            throw new Rest_Failure('pc_field_invalid', 422);
        }
        self::check($document, $schema, 'body', 0, false);
    }

    /** Valida el resultado ANTES de proyectar/emitir al cliente. */
    public static function response(string $operationId, int $status, array $body): void
    {
        $operation = Rest_Contract::operation($operationId);
        $byStatus = $operation['responses'][(string) $status] ?? null;
        if (null === $operation || !\is_array($byStatus) || !isset($byStatus['application/json'])) {
            throw new Rest_Failure('pc_result_invalid', 500);
        }
        // Reconstruir objeto con propiedades de PHP; permitir objeto vacío.
        try {
            $json = \json_encode((object) $body, \JSON_THROW_ON_ERROR);
            $value = \json_decode($json, false, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $ignored) {
            throw new Rest_Failure('pc_result_invalid', 500);
        }
        self::check($value, $byStatus['application/json'], 'result', 0, true);
    }

    /** @param list<array<string,mixed>> $params */
    private static function isKnownQueryName(array $params, string $name): bool
    {
        foreach ($params as $param) {
            if ('query' === $param['in'] && $param['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $schema */
    private static function check(
        mixed $value, array $schema, string $path, int $depth, bool $output
    ): void {
        if ($depth > self::MAX_DEPTH) {
            self::invalid($path);
        }
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            if (!\is_string($ref)
                || !\str_starts_with($ref, '#/components/schemas/')) {
                self::invalid($path);
            }
            $name = \substr($ref, \strlen('#/components/schemas/'));
            $target = Rest_Contract::schema($name);
            if (null === $target) {
                self::invalid($path);
            }
            // Extraer rangos SQL precisos, no flotantes de JSON.
            if ('SqlId' === $name && \is_string($value)) {
                try {
                    Domain_Validation::sqlId($value);
                } catch (\Throwable $ignored) {
                    self::invalid($path);
                }
            }
            if ('MoneyCents' === $name && \is_string($value)) {
                try {
                    Money::fromApi($value);
                } catch (\Throwable $ignored) {
                    self::invalid($path);
                }
            }
            if ('SignedCents' === $name && \is_string($value)) {
                try {
                    Money::fromSignedLedger($value);
                } catch (\Throwable $ignored) {
                    self::invalid($path);
                }
            }
            if ('Ubigeo' === $name && \is_string($value)) {
                try {
                    Domain_Validation::ubigeo($value);
                } catch (\Throwable $ignored) {
                    self::invalid($path);
                }
            }
            self::check($value, $target, $path, $depth + 1, $output);
        }

        if ($output && true === ($schema['writeOnly'] ?? false)
            || !$output && true === ($schema['readOnly'] ?? false)) {
            self::invalid($path);
        }
        if (isset($schema['type'])) {
            $types = \is_array($schema['type']) ? $schema['type'] : [$schema['type']];
            $valid = false;
            foreach ($types as $t) {
                $valid = $valid || match ($t) {
                    'object' => $value instanceof \stdClass,
                    'array' => \is_array($value),
                    'string' => \is_string($value),
                    'integer' => \is_int($value),
                    'number' => \is_int($value) || (\is_float($value) && \is_finite($value)),
                    'boolean' => \is_bool($value),
                    'null' => null === $value,
                    default => false,
                };
            }
            if (!$valid) {
                self::invalid($path);
            }
        }
        if (\array_key_exists('const', $schema) && $value !== $schema['const']
            || isset($schema['enum']) && !\in_array($value, $schema['enum'], true)) {
            self::invalid($path);
        }

        if (\is_string($value)) {
            if (!\mb_check_encoding($value, 'UTF-8')) {
                self::invalid($path);
            }
            $length = \mb_strlen($value, 'UTF-8');
            if (isset($schema['minLength']) && $length < $schema['minLength']
                || isset($schema['maxLength']) && $length > $schema['maxLength']) {
                self::invalid($path);
            }
            if (isset($schema['pattern'])) {
                $pattern = '~' . \str_replace('~', '\~', $schema['pattern']) . '~D';
                if (1 !== @\preg_match($pattern, $value)) {
                    self::invalid($path);
                }
            }
            if (isset($schema['format'])) {
                self::format($value, $schema['format'], $path);
            }
        }
        if (\is_int($value) || \is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']
                || isset($schema['maximum']) && $value > $schema['maximum']) {
                self::invalid($path);
            }
        }
        if (\is_array($value)) {
            $length = \count($value);
            // R8-52/CoverageView admiten 2500 entradas; los demás
            // arrays mantienen el límite defensivo global de 500.
            $allowedArray = (\in_array($path, ['body.entries', 'result.items'], true)
                && 2500 === ($schema['maxItems'] ?? null))
                    ? 2500 : self::MAX_ARRAY;
            if ($length > $allowedArray
                || isset($schema['minItems']) && $length < $schema['minItems']
                || isset($schema['maxItems']) && $length > $schema['maxItems']) {
                self::invalid($path);
            }
            if (isset($schema['items'])) {
                foreach ($value as $i => $item) {
                    self::check($item, $schema['items'], $path . '[' . $i . ']', $depth + 1, $output);
                }
            }
        }
        if ($value instanceof \stdClass) {
            $members = \get_object_vars($value);
            if (\count($members) > self::MAX_PROPERTIES) {
                self::invalid($path);
            }
            foreach ($schema['required'] ?? [] as $required) {
                if (!\array_key_exists($required, $members)) {
                    self::invalid($path . '.' . $required);
                }
            }
            foreach ($members as $key => $member) {
                $child = $schema['properties'][$key] ?? null;
                if (null === $child) {
                    if (true !== ($schema['additionalProperties'] ?? false)) {
                        self::invalid($path . '.' . $key);
                    }
                    continue;
                }
                self::check($member, $child, $path . '.' . $key, $depth + 1, $output);
            }
            foreach ($schema['dependentRequired'] ?? [] as $field => $dependencies) {
                if (!\array_key_exists($field, $members)) {
                    continue;
                }
                foreach ($dependencies as $dependency) {
                    if (!\array_key_exists($dependency, $members)) {
                        self::invalid($path . '.' . $dependency);
                    }
                }
            }
        }

        foreach ($schema['allOf'] ?? [] as $part) {
            self::check($value, $part, $path, $depth + 1, $output);
        }
        if (isset($schema['oneOf'])) {
            $count = 0;
            foreach ($schema['oneOf'] as $part) {
                if (self::matches($value, $part, $path, $depth + 1, $output)) {
                    ++$count;
                }
            }
            if (1 !== $count) {
                self::invalid($path);
            }
        }
        if (isset($schema['anyOf'])) {
            $found = false;
            foreach ($schema['anyOf'] as $part) {
                $found = $found || self::matches($value, $part, $path, $depth + 1, $output);
            }
            if (!$found) {
                self::invalid($path);
            }
        }
        if (isset($schema['not']) && self::matches($value, $schema['not'], $path, $depth + 1, $output)) {
            self::invalid($path);
        }
        if (isset($schema['if'])) {
            $branch = self::matches($value, $schema['if'], $path, $depth + 1, $output)
                ? ($schema['then'] ?? null) : ($schema['else'] ?? null);
            if (\is_array($branch)) {
                self::check($value, $branch, $path, $depth + 1, $output);
            }
        }
    }

    /** @param array<string,mixed> $schema */
    private static function matches(
        mixed $value, array $schema, string $path, int $depth, bool $output
    ): bool {
        try {
            self::check($value, $schema, $path, $depth, $output);
            return true;
        } catch (Rest_Failure $ignored) {
            return false;
        }
    }

    private static function format(string $value, string $format, string $path): void
    {
        try {
            switch ($format) {
                case 'date-time':
                    Business_Time::fromApi($value);
                    return;
                case 'date':
                    Business_Time::localDate($value);
                    return;
                case 'email':
                    Domain_Validation::email($value);
                    return;
                case 'uri':
                    if (false === \filter_var($value, \FILTER_VALIDATE_URL)
                        || !\in_array(\strtolower((string) \parse_url($value, \PHP_URL_SCHEME)),
                            ['http','https'], true)) {
                        self::invalid($path);
                    }
                    return;
                case 'uri-reference':
                    if (!\preg_match('/\A(?:https?:\/\/[^\s]+|\/[^\s]*)\z/D', $value)) {
                        self::invalid($path);
                    }
                    return;
                case 'binary':
                    // No tratar base64/archivo arbitrario como campo JSON.
                    self::invalid($path);
            }
        } catch (\Throwable $e) {
            self::invalid($path);
        }
        self::invalid($path);
    }

    private static function invalid(string $field): never
    {
        // Nunca incluir valor ingresado, email, body o ruta real.
        $safe = \preg_match('/\A[a-zA-Z0-9_.\[\]]{1,120}\z/D', $field)
            ? $field : null;
        throw new Rest_Failure('pc_field_invalid', 422, false, $safe);
    }
}
