<?php
/**
 * IMP-04: esquema SQL declarativo de 39 tablas para una instalación WordPress.
 *
 * Fuente: docs/23, docs/36, docs/38; ajustes de docs/23 §12 (R8-37/54/64).
 * Este archivo NO ejecuta SQL ni registra hooks de instalación. IMP-05 definirá
 * migraciones, checksums, orden recuperable y estados de preflight reales.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Genera DDL sin ejecutarlo. Las declaraciones son un baseline, NO prueba de
 * tablas instaladas ni de índices/FKs aceptados por el motor del comercio.
 */
final class Schema
{
    /** @var array<string, string> */
    private const DEFINITIONS = [
        'pc_sellables' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_post_id` BIGINT UNSIGNED NOT NULL,
  `variant_key` VARCHAR(191) NOT NULL,
  `kind` VARCHAR(16) NOT NULL,
  `sku` VARCHAR(100) NULL,
  `price_cents` BIGINT NOT NULL,
  `sale_price_cents` BIGINT NULL,
  `sale_active` TINYINT(1) NOT NULL,
  `track_stock` TINYINT(1) NOT NULL,
  `stock_on_hand` BIGINT NOT NULL,
  `service_duration_minutes` INT UNSIGNED NULL,
  `service_buffer_before_minutes` INT UNSIGNED NULL,
  `service_buffer_after_minutes` INT UNSIGNED NULL,
  `active` TINYINT(1) NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`product_post_id`, `variant_key`),
  UNIQUE KEY `uq_2` (`sku`),
  KEY `ix_1` (`kind`, `active`),
  KEY `ix_2` (`product_post_id`),
  CHECK (price_cents >= 0),
  CHECK (sale_price_cents IS NULL OR sale_price_cents >= 0),
  CHECK (stock_on_hand >= 0),
  CHECK (sale_active IN (0,1)),
  CHECK (track_stock IN (0,1)),
  CHECK (active IN (0,1)),
  CHECK (row_version >= 1)
SQL,

        'pc_stock_movements' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sellable_id` BIGINT UNSIGNED NOT NULL,
  `delta_units` BIGINT NOT NULL,
  `balance_after` BIGINT NOT NULL,
  `source_type` VARCHAR(32) NOT NULL,
  `source_id` BIGINT UNSIGNED NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `idempotency_id` BIGINT UNSIGNED NULL,
  `reason_code` VARCHAR(40) NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`sellable_id`, `occurred_at`, `id`),
  KEY `ix_2` (`idempotency_id`),
  CHECK (delta_units <> 0),
  CHECK (balance_after >= 0)
SQL,

        'pc_stock_commitments' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_line_id` BIGINT UNSIGNED NOT NULL,
  `sellable_id` BIGINT UNSIGNED NOT NULL,
  `committed_units` BIGINT UNSIGNED NOT NULL,
  `fulfilled_units` BIGINT UNSIGNED NOT NULL,
  `released_units` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`order_line_id`),
  KEY `ix_1` (`sellable_id`),
  KEY `ix_2` (`order_line_id`, `sellable_id`),
  CHECK (fulfilled_units <= committed_units AND released_units <= committed_units - fulfilled_units),
  CHECK (row_version >= 1)
SQL,

        'pc_orders' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(40) NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `payment_method` VARCHAR(16) NOT NULL,
  `currency` CHAR(3) NOT NULL,
  `subtotal_cents` BIGINT NOT NULL,
  `delivery_cents` BIGINT NOT NULL,
  `total_cents` BIGINT NOT NULL,
  `buyer_email` VARCHAR(254) NULL,
  `buyer_phone` VARCHAR(32) NULL,
  `customer_snapshot` LONGTEXT NOT NULL,
  `quote_fingerprint` BINARY(32) NOT NULL,
  `policy_version_id` BIGINT UNSIGNED NOT NULL,
  `privacy_version_id` BIGINT UNSIGNED NOT NULL,
  `original_delivery_snapshot` LONGTEXT NULL,
  `current_delivery_revision_id` BIGINT UNSIGNED NULL,
  `confirmed_at` DATETIME(6) NULL,
  `closed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`order_number`),
  KEY `ix_1` (`status`, `created_at`, `id`),
  KEY `ix_2` (`created_at`, `id`),
  KEY `ix_3` (`policy_version_id`),
  KEY `ix_4` (`privacy_version_id`),
  KEY `ix_5` (`id`, `current_delivery_revision_id`),
  CHECK (subtotal_cents >= 0 AND delivery_cents >= 0 AND total_cents >= 0),
  CHECK (status IN ('PENDIENTE_PAGO','CONFIRMADO','FINALIZADO','CANCELADO','VENCIDO')),
  CHECK (row_version >= 1)
SQL,

        'pc_order_lines' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `sellable_id` BIGINT UNSIGNED NOT NULL,
  `kind` VARCHAR(16) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `unit_price_cents` BIGINT NOT NULL,
  `subtotal_cents` BIGINT NOT NULL,
  `fulfillment_state` VARCHAR(32) NOT NULL,
  `catalog_snapshot` LONGTEXT NOT NULL,
  `service_slot_start_utc` DATETIME(6) NULL,
  `service_slot_end_utc` DATETIME(6) NULL,
  `service_timezone` VARCHAR(64) NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`order_id`, `id`),
  KEY `ix_2` (`sellable_id`, `fulfillment_state`),
  KEY `ix_3` (`order_id`, `kind`),
  UNIQUE KEY `uq_1` (`order_id`, `id`),
  UNIQUE KEY `uq_2` (`id`, `sellable_id`),
  CHECK (quantity BETWEEN 1 AND 100000),
  CHECK (unit_price_cents >= 0 AND subtotal_cents >= 0),
  CHECK (row_version >= 1)
SQL,

        'pc_payment_attempts' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(20) NOT NULL,
  `provider_account_key` BINARY(32) NOT NULL,
  `environment` VARCHAR(12) NOT NULL,
  `provider_order_id` VARCHAR(128) NULL,
  `provider_payment_id` VARCHAR(128) NULL,
  `mp_idempotency_key` VARCHAR(128) NOT NULL,
  `amount_cents` BIGINT NOT NULL,
  `currency` CHAR(3) NOT NULL,
  `state` VARCHAR(32) NOT NULL,
  `checkout_url_reference` LONGTEXT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `last_verified_at` DATETIME(6) NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`provider`, `provider_account_key`, `environment`, `provider_order_id`),
  UNIQUE KEY `uq_2` (`provider`, `provider_account_key`, `environment`, `mp_idempotency_key`),
  KEY `ix_1` (`order_id`, `created_at`),
  UNIQUE KEY `uq_3` (`id`, `order_id`),
  CHECK (amount_cents >= 0),
  CHECK (row_version >= 1)
SQL,

        'pc_payment_events' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `payment_attempt_id` BIGINT UNSIGNED NULL,
  `kind` VARCHAR(32) NOT NULL,
  `amount_cents` BIGINT NOT NULL,
  `source_payment_event_id` BIGINT UNSIGNED NULL,
  `provider` VARCHAR(20) NULL,
  `provider_account_key` BINARY(32) NULL,
  `environment` VARCHAR(12) NULL,
  `external_reference` VARCHAR(128) NULL,
  `event_dedup_hash` BINARY(32) NOT NULL,
  `verification_reference` VARCHAR(200) NULL,
  `correction_evidence_snapshot` LONGTEXT NULL,
  `refund_evidence_snapshot` LONGTEXT NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `recorded_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`event_dedup_hash`),
  KEY `ix_1` (`order_id`, `recorded_at`, `id`),
  KEY `ix_2` (`source_payment_event_id`),
  KEY `ix_3` (`provider`, `provider_account_key`, `environment`, `external_reference`),
  UNIQUE KEY `uq_2` (`id`, `order_id`),
  KEY `ix_4` (`payment_attempt_id`, `order_id`),
  KEY `ix_5` (`source_payment_event_id`, `order_id`)
SQL,

        'pc_order_adjustments' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `order_line_id` BIGINT UNSIGNED NULL,
  `case_id` BIGINT UNSIGNED NULL,
  `reason_code` VARCHAR(40) NOT NULL,
  `delta_cents` BIGINT NOT NULL,
  `approved_by` BIGINT UNSIGNED NOT NULL,
  `original_basis_snapshot` LONGTEXT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `idempotency_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`idempotency_id`),
  KEY `ix_1` (`order_id`, `created_at`, `id`),
  KEY `ix_2` (`case_id`),
  KEY `ix_3` (`order_id`, `order_line_id`),
  KEY `ix_4` (`case_id`, `order_id`)
SQL,

        'pc_holds' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_attempt_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `order_line_id` BIGINT UNSIGNED NOT NULL,
  `resource_kind` VARCHAR(12) NOT NULL,
  `sellable_id` BIGINT UNSIGNED NULL,
  `resource_id` BIGINT UNSIGNED NULL,
  `quantity` INT UNSIGNED NULL,
  `slot_start_utc` DATETIME(6) NULL,
  `slot_end_utc` DATETIME(6) NULL,
  `state` VARCHAR(16) NOT NULL,
  `started_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `committed_at` DATETIME(6) NULL,
  `released_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`payment_attempt_id`, `order_line_id`, `resource_kind`),
  KEY `ix_1` (`order_line_id`, `state`),
  KEY `ix_2` (`sellable_id`, `state`, `expires_at`),
  KEY `ix_3` (`resource_id`, `slot_start_utc`, `slot_end_utc`),
  KEY `ix_4` (`state`, `expires_at`),
  KEY `ix_5` (`order_id`, `order_line_id`),
  KEY `ix_6` (`payment_attempt_id`, `order_id`),
  CHECK (expires_at > started_at),
  CHECK (slot_start_utc IS NULL OR slot_end_utc IS NULL OR slot_start_utc < slot_end_utc)
SQL,

        'pc_resource_admissions' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idempotency_id` BIGINT UNSIGNED NOT NULL,
  `sellable_id` BIGINT UNSIGNED NULL,
  `resource_id` BIGINT UNSIGNED NULL,
  `quantity` INT UNSIGNED NULL,
  `slot_start_utc` DATETIME(6) NULL,
  `slot_end_utc` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `released_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`sellable_id`, `expires_at`),
  KEY `ix_2` (`resource_id`, `slot_start_utc`, `slot_end_utc`),
  KEY `ix_3` (`idempotency_id`),
  CHECK (expires_at > created_at),
  CHECK (slot_start_utc IS NULL OR slot_end_utc IS NULL OR slot_start_utc < slot_end_utc)
SQL,

        'pc_idempotency' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_hash` BINARY(32) NOT NULL,
  `key_hash` BINARY(32) NOT NULL,
  `request_hash` BINARY(32) NOT NULL,
  `operation_type` VARCHAR(48) NOT NULL,
  `technical_phase` VARCHAR(24) NOT NULL,
  `entity_type` VARCHAR(24) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `response_code` SMALLINT UNSIGNED NULL,
  `response_ref` LONGTEXT NULL,
  `provider_account_key` BINARY(32) NULL,
  `provider_environment` VARCHAR(12) NULL,
  `provider_idempotency_key` VARCHAR(128) NULL,
  `provider_external_reference` VARCHAR(128) NULL,
  `checkout_context_snapshot` LONGTEXT NULL,
  `expires_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`scope_hash`, `key_hash`),
  UNIQUE KEY `uq_2` (`provider_account_key`, `provider_environment`, `provider_idempotency_key`),
  KEY `ix_1` (`technical_phase`, `updated_at`),
  KEY `ix_2` (`expires_at`)
SQL,

        'pc_webhook_inbox' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(20) NOT NULL,
  `provider_account_key` BINARY(32) NOT NULL,
  `environment` VARCHAR(12) NOT NULL,
  `event_dedup_hash` BINARY(32) NOT NULL,
  `resource_id` VARCHAR(128) NOT NULL,
  `action_code` VARCHAR(64) NOT NULL,
  `signature_verified_at` DATETIME(6) NOT NULL,
  `payload_digest` BINARY(32) NOT NULL,
  `technical_phase` VARCHAR(24) NOT NULL,
  `received_at` DATETIME(6) NOT NULL,
  `processed_at` DATETIME(6) NULL,
  `next_retry_at` DATETIME(6) NULL,
  `retry_count` INT UNSIGNED NOT NULL,
  `last_error_code` VARCHAR(40) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`provider`, `provider_account_key`, `environment`, `event_dedup_hash`),
  KEY `ix_1` (`technical_phase`, `next_retry_at`, `id`),
  KEY `ix_2` (`resource_id`)
SQL,

        'pc_outbox' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deduplication_hash` BINARY(32) NOT NULL,
  `event_code` VARCHAR(40) NOT NULL,
  `entity_type` VARCHAR(30) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `audience_code` VARCHAR(24) NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `template_version` VARCHAR(32) NULL,
  `destination_ref` LONGTEXT NULL,
  `safe_payload` LONGTEXT NULL,
  `technical_phase` VARCHAR(24) NOT NULL,
  `attempt_count` INT UNSIGNED NOT NULL,
  `next_attempt_at` DATETIME(6) NULL,
  `accepted_at` DATETIME(6) NULL,
  `completed_at` DATETIME(6) NULL,
  `last_error_code` VARCHAR(40) NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`deduplication_hash`),
  KEY `ix_1` (`technical_phase`, `next_attempt_at`, `id`),
  KEY `ix_2` (`entity_type`, `entity_id`),
  CHECK (row_version >= 1)
SQL,

        'pc_calendar_days' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `local_date` DATE NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`resource_id`, `local_date`),
  CHECK (row_version >= 1)
SQL,

        'pc_schedule_rules' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `revision_no` BIGINT UNSIGNED NOT NULL,
  `window_index` SMALLINT UNSIGNED NOT NULL,
  `weekday` TINYINT UNSIGNED NOT NULL,
  `opens_local` TIME NULL,
  `closes_local` TIME NULL,
  `closes_day_offset` TINYINT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL,
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`resource_id`, `revision_no`, `weekday`, `window_index`),
  KEY `ix_1` (`resource_id`, `weekday`, `valid_from`, `valid_to`),
  KEY `ix_2` (`resource_id`, `revision_no`),
  CHECK (weekday <= 6),
  CHECK (closes_day_offset IN (0,1)),
  CHECK (enabled IN (0,1)),
  CHECK (row_version >= 1)
SQL,

        'pc_schedule_blocks' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `starts_at_utc` DATETIME(6) NOT NULL,
  `ends_at_utc` DATETIME(6) NOT NULL,
  `reason_code` VARCHAR(40) NOT NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`resource_id`, `starts_at_utc`, `ends_at_utc`),
  CHECK (ends_at_utc > starts_at_utc),
  CHECK (active IN (0,1)),
  CHECK (row_version >= 1)
SQL,

        'pc_appointments' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `order_line_id` BIGINT UNSIGNED NOT NULL,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `starts_at_utc` DATETIME(6) NOT NULL,
  `ends_at_utc` DATETIME(6) NOT NULL,
  `local_timezone` VARCHAR(64) NOT NULL,
  `cancelled_at` DATETIME(6) NULL,
  `completed_at` DATETIME(6) NULL,
  `no_show_at` DATETIME(6) NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `outcome_evidence_ref` VARCHAR(500) NULL,
  `outcome_recorded_by` BIGINT UNSIGNED NULL,
  `outcome_recorded_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`order_id`),
  UNIQUE KEY `uq_2` (`order_line_id`),
  KEY `ix_1` (`resource_id`, `starts_at_utc`, `ends_at_utc`),
  KEY `ix_2` (`order_id`, `order_line_id`),
  CHECK (ends_at_utc > starts_at_utc),
  CHECK (row_version >= 1)
SQL,

        'pc_appointment_history' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` BIGINT UNSIGNED NOT NULL,
  `previous_start_utc` DATETIME(6) NOT NULL,
  `previous_end_utc` DATETIME(6) NOT NULL,
  `new_start_utc` DATETIME(6) NOT NULL,
  `new_end_utc` DATETIME(6) NOT NULL,
  `actor_user_id` BIGINT UNSIGNED NOT NULL,
  `reason_code` VARCHAR(40) NOT NULL,
  `communication_outbox_id` BIGINT UNSIGNED NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`appointment_id`, `occurred_at`, `id`),
  KEY `ix_2` (`communication_outbox_id`)
SQL,

        'pc_ubigeo_versions' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_name` VARCHAR(100) NOT NULL,
  `source_url` VARCHAR(500) NOT NULL,
  `dataset_version` VARCHAR(80) NOT NULL,
  `dataset_hash` BINARY(32) NOT NULL,
  `published_on` DATE NULL,
  `imported_at` DATETIME(6) NOT NULL,
  `is_current` TINYINT(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`dataset_hash`),
  KEY `ix_1` (`is_current`, `imported_at`),
  CHECK (is_current IN (0,1))
SQL,

        'pc_ubigeo' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version_id` BIGINT UNSIGNED NOT NULL,
  `code` CHAR(6) NOT NULL,
  `department_name` VARCHAR(120) NOT NULL,
  `province_name` VARCHAR(120) NOT NULL,
  `district_name` VARCHAR(120) NOT NULL,
  `valid` TINYINT(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`version_id`, `code`),
  KEY `ix_1` (`code`),
  CHECK (valid IN (0,1))
SQL,

        'pc_delivery_rates' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ubigeo_code` CHAR(6) NOT NULL,
  `enabled` TINYINT(1) NOT NULL,
  `fee_cents` BIGINT NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `updated_by` BIGINT UNSIGNED NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`ubigeo_code`),
  KEY `ix_1` (`enabled`, `ubigeo_code`),
  CHECK (fee_cents >= 0),
  CHECK (enabled IN (0,1)),
  CHECK (row_version >= 1)
SQL,

        'pc_delivery_revisions' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `previous_revision_id` BIGINT UNSIGNED NULL,
  `revision_number` INT UNSIGNED NOT NULL,
  `ubigeo_code` CHAR(6) NOT NULL,
  `address_snapshot` LONGTEXT NOT NULL,
  `fee_cents` BIGINT NOT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `case_id` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`order_id`, `revision_number`),
  KEY `ix_1` (`order_id`, `created_at`, `id`),
  UNIQUE KEY `uq_2` (`order_id`, `id`),
  KEY `ix_2` (`order_id`, `previous_revision_id`),
  KEY `ix_3` (`case_id`, `order_id`),
  CHECK (fee_cents >= 0)
SQL,

        'pc_guest_sessions' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `credential_kind` VARCHAR(12) NOT NULL,
  `token_hash` BINARY(32) NOT NULL,
  `source_credential_id` BIGINT UNSIGNED NULL,
  `issued_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `revoked_at` DATETIME(6) NULL,
  `last_used_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`token_hash`),
  KEY `ix_1` (`order_id`, `credential_kind`, `expires_at`),
  UNIQUE KEY `uq_2` (`id`, `order_id`),
  KEY `ix_2` (`source_credential_id`, `order_id`),
  CHECK (expires_at > issued_at)
SQL,

        'pc_otp_challenges' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `guest_session_id` BIGINT UNSIGNED NOT NULL,
  `purpose` VARCHAR(40) NOT NULL,
  `recipient_hash` BINARY(32) NOT NULL,
  `code_hash` BINARY(32) NOT NULL,
  `issued_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `superseded_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`guest_session_id`, `purpose`, `issued_at`),
  KEY `ix_2` (`order_id`, `purpose`, `issued_at`),
  KEY `ix_3` (`recipient_hash`, `expires_at`),
  UNIQUE KEY `uq_1` (`id`, `guest_session_id`, `order_id`),
  KEY `ix_4` (`guest_session_id`, `order_id`),
  CHECK (attempts <= 5),
  CHECK (expires_at > issued_at)
SQL,

        'pc_action_grants' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `guest_session_id` BIGINT UNSIGNED NOT NULL,
  `purpose` VARCHAR(40) NOT NULL,
  `token_hash` BINARY(32) NOT NULL,
  `verified_challenge_id` BIGINT UNSIGNED NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`token_hash`),
  KEY `ix_1` (`guest_session_id`, `purpose`, `expires_at`),
  KEY `ix_2` (`order_id`, `purpose`, `expires_at`),
  KEY `ix_3` (`guest_session_id`, `order_id`),
  KEY `ix_4` (`verified_challenge_id`, `guest_session_id`, `order_id`),
  CHECK (expires_at > created_at)
SQL,

        'pc_digital_files' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_product_post_id` BIGINT UNSIGNED NOT NULL,
  `sellable_id` BIGINT UNSIGNED NOT NULL,
  `replaces_file_id` BIGINT UNSIGNED NULL,
  `private_storage_key` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `byte_size` BIGINT UNSIGNED NOT NULL,
  `content_hash` BINARY(32) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `archived_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`private_storage_key`),
  KEY `ix_1` (`sellable_id`, `created_at`, `id`),
  KEY `ix_2` (`replaces_file_id`),
  KEY `ix_3` (`owner_product_post_id`, `created_at`),
  UNIQUE KEY `uq_2` (`id`, `sellable_id`),
  KEY `ix_4` (`replaces_file_id`, `sellable_id`),
  CHECK (byte_size > 0)
SQL,

        'pc_download_rights' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_line_id` BIGINT UNSIGNED NOT NULL,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `granted_at` DATETIME(6) NOT NULL,
  `revoked_at` DATETIME(6) NULL,
  `optional_limit` INT UNSIGNED NULL,
  `download_count` INT UNSIGNED NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`order_line_id`, `file_id`),
  KEY `ix_1` (`file_id`),
  UNIQUE KEY `uq_2` (`id`, `file_id`),
  CHECK (optional_limit IS NULL OR optional_limit > 0),
  CHECK (row_version >= 1)
SQL,

        'pc_download_tokens' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `right_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` BINARY(32) NOT NULL,
  `issued_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `revoked_at` DATETIME(6) NULL,
  `last_used_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`token_hash`),
  KEY `ix_1` (`right_id`, `expires_at`),
  CHECK (expires_at > issued_at)
SQL,

        'pc_download_events' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `right_id` BIGINT UNSIGNED NOT NULL,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `result_code` VARCHAR(32) NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `opaque_request_id` BINARY(16) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`right_id`, `occurred_at`, `id`),
  KEY `ix_2` (`right_id`, `file_id`),
  KEY `ix_3` (`file_id`)
SQL,

        'pc_complaints' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sequence_scope` VARCHAR(40) NOT NULL,
  `sequence_number` BIGINT UNSIGNED NOT NULL,
  `submitted_at` DATETIME(6) NOT NULL,
  `due_at` DATETIME(6) NULL,
  `merchant_snapshot` LONGTEXT NOT NULL,
  `complainant_snapshot` LONGTEXT NOT NULL,
  `complaint_snapshot` LONGTEXT NOT NULL,
  `evidence_reference` LONGTEXT NULL,
  `evidence_manifest` LONGTEXT NULL,
  `responded_at` DATETIME(6) NULL,
  `response_channel` VARCHAR(32) NULL,
  `response_delivered_at` DATETIME(6) NULL,
  `response_snapshot` LONGTEXT NULL,
  `response_evidence` LONGTEXT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`sequence_scope`, `sequence_number`),
  KEY `ix_1` (`submitted_at`, `id`),
  KEY `ix_2` (`responded_at`),
  CHECK (row_version >= 1)
SQL,

        'pc_cases' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NULL,
  `order_line_id` BIGINT UNSIGNED NULL,
  `case_type` VARCHAR(40) NOT NULL,
  `source_operation_id` BIGINT UNSIGNED NULL,
  `case_phase` VARCHAR(32) NOT NULL,
  `reason_code` VARCHAR(40) NOT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `evidence_snapshot` LONGTEXT NULL,
  `opened_at` DATETIME(6) NOT NULL,
  `resolved_at` DATETIME(6) NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`order_id`, `opened_at`, `id`),
  KEY `ix_2` (`case_type`, `case_phase`, `opened_at`),
  KEY `ix_3` (`order_line_id`),
  KEY `ix_4` (`source_operation_id`, `opened_at`),
  UNIQUE KEY `uq_1` (`case_type`, `source_operation_id`),
  UNIQUE KEY `uq_2` (`id`, `order_id`),
  KEY `ix_5` (`order_id`, `order_line_id`),
  CHECK (row_version >= 1)
SQL,

        'pc_privacy_requests' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicant_reference` LONGTEXT NOT NULL,
  `request_snapshot` LONGTEXT NOT NULL,
  `request_type` VARCHAR(24) NOT NULL,
  `received_channel` VARCHAR(32) NOT NULL,
  `received_at` DATETIME(6) NOT NULL,
  `identity_evidence_ref` VARCHAR(500) NULL,
  `evidence_manifest` LONGTEXT NULL,
  `verified_at` DATETIME(6) NULL,
  `due_at` DATETIME(6) NULL,
  `resolved_at` DATETIME(6) NULL,
  `response_channel` VARCHAR(32) NULL,
  `response_evidence_ref` VARCHAR(500) NULL,
  `result_reference` LONGTEXT NULL,
  `legal_basis_code` VARCHAR(60) NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`request_type`, `received_at`, `id`),
  KEY `ix_2` (`due_at`, `resolved_at`),
  CHECK (row_version >= 1)
SQL,

        'pc_external_documents' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `case_id` BIGINT UNSIGNED NULL,
  `source_document_id` BIGINT UNSIGNED NULL,
  `idempotency_id` BIGINT UNSIGNED NOT NULL,
  `issuer_tax_regime` VARCHAR(32) NULL,
  `document_kind` VARCHAR(32) NOT NULL,
  `series` VARCHAR(30) NULL,
  `number` VARCHAR(64) NULL,
  `issued_at` DATETIME(6) NULL,
  `external_reference` VARCHAR(255) NULL,
  `event_dedup_hash` BINARY(32) NOT NULL,
  `status_note` VARCHAR(32) NULL,
  `amount_cents` BIGINT NULL,
  `recorded_by` BIGINT UNSIGNED NOT NULL,
  `recorded_at` DATETIME(6) NOT NULL,
  `issuer_identity_hash` BINARY(32) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`idempotency_id`),
  UNIQUE KEY `uq_2` (`event_dedup_hash`),
  KEY `ix_1` (`order_id`, `recorded_at`, `id`),
  KEY `ix_2` (`document_kind`, `series`, `number`),
  UNIQUE KEY `uq_3` (`id`, `order_id`),
  KEY `ix_3` (`case_id`, `order_id`),
  KEY `ix_4` (`source_document_id`, `order_id`)
SQL,

        'pc_policy_versions' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_kind` VARCHAR(24) NOT NULL,
  `version_code` VARCHAR(40) NOT NULL,
  `body_snapshot` LONGTEXT NOT NULL,
  `content_hash` BINARY(32) NOT NULL,
  `effective_from` DATETIME(6) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`policy_kind`, `version_code`),
  KEY `ix_1` (`policy_kind`, `effective_from`)
SQL,

        'pc_audit_log' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(32) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `operation_code` VARCHAR(48) NOT NULL,
  `actor_kind` VARCHAR(20) NOT NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `request_id` BINARY(16) NULL,
  `reason_code` VARCHAR(40) NULL,
  `safe_before_hash` BINARY(32) NULL,
  `safe_after_hash` BINARY(32) NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_1` (`entity_type`, `entity_id`, `occurred_at`, `id`),
  KEY `ix_2` (`actor_user_id`, `occurred_at`)
SQL,

        'pc_migrations' => <<<'SQL'
  `version` VARCHAR(50) NOT NULL,
  `checksum` BINARY(32) NOT NULL,
  `applied_at` DATETIME(6) NOT NULL,
  `plugin_version` VARCHAR(40) NOT NULL,
  `migration_result` VARCHAR(24) NOT NULL,
  `diagnostic_code` VARCHAR(50) NULL,
  PRIMARY KEY (`version`),
  KEY `ix_1` (`applied_at`)
SQL,

        'pc_schedule_revisions' => <<<'SQL'
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `active_revision_no` BIGINT UNSIGNED NOT NULL,
  `timezone` VARCHAR(64) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `updated_by` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`resource_id`),
  CHECK (row_version >= 1)
SQL,

        'pc_case_events' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` BIGINT UNSIGNED NOT NULL,
  `idempotency_id` BIGINT UNSIGNED NOT NULL,
  `event_code` VARCHAR(48) NOT NULL,
  `decision_code` VARCHAR(64) NULL,
  `actor_kind` VARCHAR(20) NOT NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `evidence_ref` VARCHAR(500) NULL,
  `safe_payload_snapshot` LONGTEXT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`idempotency_id`),
  KEY `ix_1` (`case_id`, `occurred_at`, `id`),
  KEY `ix_2` (`actor_user_id`, `occurred_at`)
SQL,

        'pc_digital_assignments' => <<<'SQL'
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sellable_id` BIGINT UNSIGNED NOT NULL,
  `slot_key` VARCHAR(100) NOT NULL,
  `active_file_id` BIGINT UNSIGNED NOT NULL,
  `row_version` BIGINT UNSIGNED NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `updated_by` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_1` (`sellable_id`, `slot_key`),
  KEY `ix_1` (`active_file_id`),
  KEY `ix_2` (`active_file_id`, `sellable_id`),
  CHECK (row_version >= 1)
SQL,
    ];

    /**
     * Relaciones entre tablas propias. No crear FK a wp_posts/wp_users ni a
     * entity_type/entity_id polimórficos. Historial: RESTRICT, nunca CASCADE.
     *
     * Cada elemento: tabla fuente, columnas, tabla destino, columnas destino.
     */
    private const RELATIONS = [
        ['pc_stock_movements', ['sellable_id'], 'pc_sellables', ['id']],
        ['pc_stock_movements', ['idempotency_id'], 'pc_idempotency', ['id']],
        ['pc_stock_commitments', ['order_line_id', 'sellable_id'], 'pc_order_lines', ['id', 'sellable_id']],
        ['pc_orders', ['policy_version_id'], 'pc_policy_versions', ['id']],
        ['pc_orders', ['privacy_version_id'], 'pc_policy_versions', ['id']],
        ['pc_orders', ['id', 'current_delivery_revision_id'], 'pc_delivery_revisions', ['order_id', 'id']],
        ['pc_order_lines', ['order_id'], 'pc_orders', ['id']],
        ['pc_order_lines', ['sellable_id'], 'pc_sellables', ['id']],
        ['pc_payment_attempts', ['order_id'], 'pc_orders', ['id']],
        ['pc_payment_events', ['order_id'], 'pc_orders', ['id']],
        ['pc_payment_events', ['payment_attempt_id', 'order_id'], 'pc_payment_attempts', ['id', 'order_id']],
        ['pc_payment_events', ['source_payment_event_id', 'order_id'], 'pc_payment_events', ['id', 'order_id']],
        ['pc_order_adjustments', ['order_id'], 'pc_orders', ['id']],
        ['pc_order_adjustments', ['order_id', 'order_line_id'], 'pc_order_lines', ['order_id', 'id']],
        ['pc_order_adjustments', ['case_id', 'order_id'], 'pc_cases', ['id', 'order_id']],
        ['pc_order_adjustments', ['idempotency_id'], 'pc_idempotency', ['id']],
        ['pc_holds', ['order_id', 'order_line_id'], 'pc_order_lines', ['order_id', 'id']],
        ['pc_holds', ['payment_attempt_id', 'order_id'], 'pc_payment_attempts', ['id', 'order_id']],
        ['pc_holds', ['sellable_id'], 'pc_sellables', ['id']],
        ['pc_holds', ['resource_id'], 'pc_schedule_revisions', ['resource_id']],
        ['pc_resource_admissions', ['idempotency_id'], 'pc_idempotency', ['id']],
        ['pc_resource_admissions', ['sellable_id'], 'pc_sellables', ['id']],
        ['pc_resource_admissions', ['resource_id'], 'pc_schedule_revisions', ['resource_id']],
        ['pc_calendar_days', ['resource_id'], 'pc_schedule_revisions', ['resource_id']],
        ['pc_schedule_rules', ['resource_id'], 'pc_schedule_revisions', ['resource_id']],
        ['pc_schedule_blocks', ['resource_id'], 'pc_schedule_revisions', ['resource_id']],
        ['pc_appointments', ['order_id', 'order_line_id'], 'pc_order_lines', ['order_id', 'id']],
        ['pc_appointments', ['resource_id'], 'pc_schedule_revisions', ['resource_id']],
        ['pc_appointment_history', ['appointment_id'], 'pc_appointments', ['id']],
        ['pc_appointment_history', ['communication_outbox_id'], 'pc_outbox', ['id']],
        ['pc_ubigeo', ['version_id'], 'pc_ubigeo_versions', ['id']],
        ['pc_delivery_revisions', ['order_id'], 'pc_orders', ['id']],
        ['pc_delivery_revisions', ['order_id', 'previous_revision_id'], 'pc_delivery_revisions', ['order_id', 'id']],
        ['pc_delivery_revisions', ['case_id', 'order_id'], 'pc_cases', ['id', 'order_id']],
        ['pc_guest_sessions', ['order_id'], 'pc_orders', ['id']],
        ['pc_guest_sessions', ['source_credential_id', 'order_id'], 'pc_guest_sessions', ['id', 'order_id']],
        ['pc_otp_challenges', ['guest_session_id', 'order_id'], 'pc_guest_sessions', ['id', 'order_id']],
        ['pc_action_grants', ['guest_session_id', 'order_id'], 'pc_guest_sessions', ['id', 'order_id']],
        ['pc_action_grants', ['verified_challenge_id', 'guest_session_id', 'order_id'], 'pc_otp_challenges', ['id', 'guest_session_id', 'order_id']],
        ['pc_digital_files', ['sellable_id'], 'pc_sellables', ['id']],
        ['pc_digital_files', ['replaces_file_id', 'sellable_id'], 'pc_digital_files', ['id', 'sellable_id']],
        ['pc_download_rights', ['order_line_id'], 'pc_order_lines', ['id']],
        ['pc_download_rights', ['file_id'], 'pc_digital_files', ['id']],
        ['pc_download_tokens', ['right_id'], 'pc_download_rights', ['id']],
        ['pc_download_events', ['right_id', 'file_id'], 'pc_download_rights', ['id', 'file_id']],
        ['pc_download_events', ['file_id'], 'pc_digital_files', ['id']],
        ['pc_cases', ['order_id', 'order_line_id'], 'pc_order_lines', ['order_id', 'id']],
        ['pc_cases', ['source_operation_id'], 'pc_idempotency', ['id']],
        ['pc_external_documents', ['order_id'], 'pc_orders', ['id']],
        ['pc_external_documents', ['case_id', 'order_id'], 'pc_cases', ['id', 'order_id']],
        ['pc_external_documents', ['source_document_id', 'order_id'], 'pc_external_documents', ['id', 'order_id']],
        ['pc_external_documents', ['idempotency_id'], 'pc_idempotency', ['id']],
        ['pc_case_events', ['case_id'], 'pc_cases', ['id']],
        ['pc_case_events', ['idempotency_id'], 'pc_idempotency', ['id']],
        ['pc_digital_assignments', ['sellable_id'], 'pc_sellables', ['id']],
        ['pc_digital_assignments', ['active_file_id', 'sellable_id'], 'pc_digital_files', ['id', 'sellable_id']],
    ];

    /** @return list<string> */
    public static function suffixes(): array
    {
        return \array_keys(self::DEFINITIONS);
    }

    /**
     * @return array<string, string> CREATE TABLE por sufijo lógico.
     */
    public static function tableStatements(\wpdb $wpdb): array
    {
        $charset = \strtolower((string) $wpdb->charset);
        $collation = \strtolower((string) $wpdb->collate);
        if ('utf8mb4' !== $charset || ('' !== $collation &&
            !\preg_match('/^utf8mb4_[a-z0-9_]+$/D', $collation))) {
            throw new \RuntimeException('PeruCommerce: charset/collation no compatible.');
        }

        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            . ('' === $collation ? '' : ' COLLATE=' . $collation);

        $sql = [];
        foreach (self::DEFINITIONS as $suffix => $definition) {
            $sql[$suffix] = 'CREATE TABLE ' . self::tableIdentifier($wpdb, $suffix)
                . " (\n" . $definition . "\n)" . $options;
        }
        return $sql;
    }

    /**
     * @return list<string> ALTER TABLE para aplicar DESPUÉS de crear y verificar
     *                      las 39 tablas, con journal separado en IMP-05.
     */
    public static function foreignKeyStatements(\wpdb $wpdb): array
    {
        $statements = [];
        foreach (self::RELATIONS as [$child, $columns, $parent, $referenced]) {
            $name = 'pcfk_' . \substr(\hash(
                'sha256',
                self::physicalName($wpdb, $child) . ':' . \implode(',', $columns)
                    . '>' . self::physicalName($wpdb, $parent) . ':' . \implode(',', $referenced)
            ), 0, 24);

            $statements[] = 'ALTER TABLE ' . self::tableIdentifier($wpdb, $child)
                . ' ADD CONSTRAINT `' . $name . '` FOREIGN KEY (' . self::identifiers($columns)
                . ') REFERENCES ' . self::tableIdentifier($wpdb, $parent)
                . ' (' . self::identifiers($referenced)
                . ') ON DELETE RESTRICT ON UPDATE RESTRICT';
        }
        return $statements;
    }

    /** @return array<string, string> sufijo => nombre físico; solo metadatos. */
    public static function tableNames(\wpdb $wpdb): array
    {
        $names = [];
        foreach (self::suffixes() as $suffix) {
            $names[$suffix] = self::physicalName($wpdb, $suffix);
        }
        return $names;
    }

    private static function physicalName(\wpdb $wpdb, string $suffix): string
    {
        if (!\array_key_exists($suffix, self::DEFINITIONS)
            || !\is_string($wpdb->prefix)
            || !\preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->prefix)) {
            throw new \InvalidArgumentException('PeruCommerce: prefijo/tabla no válido.');
        }

        $physical = $wpdb->prefix . $suffix;
        if (\strlen($physical) > 64) {
            throw new \RuntimeException('PeruCommerce: identificador SQL demasiado largo.');
        }
        return $physical;
    }

    private static function tableIdentifier(\wpdb $wpdb, string $suffix): string
    {
        return '`' . self::physicalName($wpdb, $suffix) . '`';
    }

    /** @param list<string> $columns */
    private static function identifiers(array $columns): string
    {
        foreach ($columns as $column) {
            if (!\preg_match('/^[a-z][a-z0-9_]*$/D', $column)) {
                throw new \LogicException('PeruCommerce: columna SQL no válida.');
            }
        }
        return '`' . \implode('`, `', $columns) . '`';
    }
}
