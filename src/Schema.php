<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

use PDO;
use Throwable;

final class Schema
{
    public static function migrate(PDO $db): void
    {
        foreach (self::statements() as $statement) {
            $db->exec($statement);
        }

        self::addColumnIfMissing($db, 'interments', 'disposition_type', "enum('unknown','casket','cremains','other') not null default 'unknown' after person_id");
    }

    private static function statements(): array
    {
        return [
            "create table if not exists organizations (
                id varchar(36) primary key,
                name varchar(255) not null,
                slug varchar(255) not null unique,
                contact_email varchar(255),
                contact_phone varchar(50),
                public_site_enabled tinyint(1) not null default 0,
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists users (
                id varchar(36) primary key,
                email varchar(255) not null unique,
                name varchar(255),
                hashed_password varchar(255),
                is_system_admin tinyint(1) not null default 0,
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists organization_members (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                user_id varchar(36) not null,
                role enum('owner','admin','editor','viewer') not null default 'viewer',
                created_at timestamp not null default current_timestamp,
                unique key organization_members_org_user_unique (organization_id, user_id)
            )",
            "create table if not exists cemeteries (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                name varchar(255) not null,
                slug varchar(255) not null,
                description text,
                address_line_1 varchar(255),
                address_line_2 varchar(255),
                city varchar(120),
                state varchar(80),
                postal_code varchar(30),
                country varchar(2) not null default 'US',
                public_site_enabled tinyint(1) not null default 0,
                default_visibility enum('private','public') not null default 'private',
                boundary_geojson json,
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp,
                unique key cemeteries_org_slug_unique (organization_id, slug)
            )",
            "create table if not exists sections (
                id varchar(36) primary key,
                cemetery_id varchar(36) not null,
                code varchar(80) not null,
                name varchar(255) not null,
                description text,
                sort_order int not null default 0,
                geometry json,
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp,
                unique key sections_cemetery_code_unique (cemetery_id, code)
            )",
            "create table if not exists plots (
                id varchar(36) primary key,
                cemetery_id varchar(36) not null,
                section_id varchar(36),
                identifier varchar(120) not null,
                row_label varchar(80),
                lot varchar(80),
                status enum('available','reserved','occupied','sold','unknown','unusable','needs_verification') not null default 'unknown',
                geometry json,
                notes text,
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp,
                unique key plots_cemetery_identifier_unique (cemetery_id, identifier)
            )",
            "create table if not exists people (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                legal_name varchar(255) not null,
                given_name varchar(255),
                family_name varchar(255),
                alternate_names json,
                maiden_name varchar(255),
                birth_date_text varchar(120),
                death_date_text varchar(120),
                birth_date date,
                death_date date,
                notes text,
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists interments (
                id varchar(36) primary key,
                cemetery_id varchar(36) not null,
                plot_id varchar(36) not null,
                person_id varchar(36) not null,
                disposition_type enum('unknown','casket','cremains','other') not null default 'unknown',
                interment_date_text varchar(120),
                interment_date date,
                burial_permit_number varchar(120),
                marker_transcription text,
                plot_position varchar(120),
                notes text,
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists owners (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                name varchar(255) not null,
                contact_name varchar(255),
                mailing_address text,
                phone varchar(50),
                email varchar(255),
                notes text,
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists plot_ownerships (
                id varchar(36) primary key,
                plot_id varchar(36) not null,
                owner_id varchar(36) not null,
                ownership_type varchar(120),
                start_date_text varchar(120),
                end_date_text varchar(120),
                notes text,
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists media (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                cemetery_id varchar(36),
                plot_id varchar(36),
                person_id varchar(36),
                interment_id varchar(36),
                owner_id varchar(36),
                title varchar(255) not null,
                caption text,
                media_type enum('image','video','audio','other') not null default 'image',
                storage_key varchar(500),
                url varchar(1000),
                taken_date_text varchar(120),
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists documents (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                cemetery_id varchar(36),
                plot_id varchar(36),
                person_id varchar(36),
                interment_id varchar(36),
                owner_id varchar(36),
                title varchar(255) not null,
                document_type enum('deed','certificate','permit','receipt','map','note','other') not null default 'other',
                file_name varchar(255),
                storage_key varchar(500),
                url varchar(1000),
                notes text,
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists map_layers (
                id varchar(36) primary key,
                cemetery_id varchar(36) not null,
                name varchar(255) not null,
                layer_type enum('uploaded_image','geojson','vector','raster_tiles','other') not null default 'uploaded_image',
                source_url varchar(1000),
                source_metadata json,
                bounds_geojson json,
                style_json json,
                sort_order int not null default 0,
                is_visible_by_default tinyint(1) not null default 1,
                visibility enum('private','public') not null default 'private',
                confidence enum('confirmed','probable','conflicting','unknown') not null default 'unknown',
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp
            )",
            "create table if not exists custom_field_definitions (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                entity_type enum('person','plot','interment') not null,
                field_key varchar(120) not null,
                label varchar(255) not null,
                field_type enum('text','textarea','date','number','url') not null default 'text',
                help_text varchar(500),
                sort_order int not null default 0,
                is_required tinyint(1) not null default 0,
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp,
                unique key custom_fields_org_entity_key_unique (organization_id, entity_type, field_key)
            )",
            "create table if not exists custom_field_values (
                id varchar(36) primary key,
                field_definition_id varchar(36) not null,
                entity_type enum('person','plot','interment') not null,
                entity_id varchar(36) not null,
                value_text text,
                created_at timestamp not null default current_timestamp,
                updated_at timestamp not null default current_timestamp on update current_timestamp,
                unique key custom_field_values_field_entity_unique (field_definition_id, entity_type, entity_id)
            )",
            "create table if not exists audit_logs (
                id varchar(36) primary key,
                organization_id varchar(36) not null,
                user_id varchar(36),
                action enum('create','update','delete','import','export','visibility_change','setup') not null,
                entity_type varchar(120) not null,
                entity_id varchar(36),
                summary text not null,
                before_json json,
                after_json json,
                created_at timestamp not null default current_timestamp
            )",
        ];
    }

    private static function addColumnIfMissing(PDO $db, string $table, string $column, string $definition): void
    {
        try {
            $statement = $db->prepare(
                'select count(*) from information_schema.columns
                 where table_schema = database() and table_name = :table and column_name = :column'
            );
            $statement->execute(['table' => $table, 'column' => $column]);
            if ((int) $statement->fetchColumn() === 0) {
                $db->exec(sprintf('alter table %s add column %s %s', $table, $column, $definition));
            }
        } catch (Throwable) {
        }
    }
}
