CREATE TABLE `audit_logs` (
	`id` varchar(36) NOT NULL,
	`organization_id` varchar(36) NOT NULL,
	`user_id` varchar(36),
	`action` enum('create','update','delete','import','export','visibility_change','setup') NOT NULL,
	`entity_type` varchar(120) NOT NULL,
	`entity_id` varchar(36),
	`summary` text NOT NULL,
	`before` json,
	`after` json,
	`created_at` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `audit_logs_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `cemeteries` (
	`id` varchar(36) NOT NULL,
	`organization_id` varchar(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`slug` varchar(255) NOT NULL,
	`description` text,
	`address_line_1` varchar(255),
	`address_line_2` varchar(255),
	`city` varchar(120),
	`state` varchar(80),
	`postal_code` varchar(30),
	`country` varchar(2) NOT NULL DEFAULT 'US',
	`public_site_enabled` boolean NOT NULL DEFAULT false,
	`default_visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`boundary_geojson` json,
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `cemeteries_id` PRIMARY KEY(`id`),
	CONSTRAINT `cemeteries_org_slug_unique` UNIQUE(`organization_id`,`slug`)
);
--> statement-breakpoint
CREATE TABLE `documents` (
	`id` varchar(36) NOT NULL,
	`organization_id` varchar(36) NOT NULL,
	`cemetery_id` varchar(36),
	`plot_id` varchar(36),
	`person_id` varchar(36),
	`interment_id` varchar(36),
	`owner_id` varchar(36),
	`title` varchar(255) NOT NULL,
	`document_type` enum('deed','certificate','permit','receipt','map','note','other') NOT NULL DEFAULT 'other',
	`file_name` varchar(255),
	`storage_key` varchar(500),
	`url` varchar(1000),
	`notes` text,
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `documents_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `interments` (
	`id` varchar(36) NOT NULL,
	`cemetery_id` varchar(36) NOT NULL,
	`plot_id` varchar(36) NOT NULL,
	`person_id` varchar(36) NOT NULL,
	`interment_date_text` varchar(120),
	`interment_date` timestamp,
	`burial_permit_number` varchar(120),
	`marker_transcription` text,
	`plot_position` varchar(120),
	`notes` text,
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `interments_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `map_layers` (
	`id` varchar(36) NOT NULL,
	`cemetery_id` varchar(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`layer_type` enum('uploaded_image','geojson','vector','raster_tiles','other') NOT NULL DEFAULT 'uploaded_image',
	`source_url` varchar(1000),
	`source_metadata` json,
	`bounds_geojson` json,
	`style_json` json,
	`sort_order` int NOT NULL DEFAULT 0,
	`is_visible_by_default` boolean NOT NULL DEFAULT true,
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `map_layers_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `media` (
	`id` varchar(36) NOT NULL,
	`organization_id` varchar(36) NOT NULL,
	`cemetery_id` varchar(36),
	`plot_id` varchar(36),
	`person_id` varchar(36),
	`interment_id` varchar(36),
	`owner_id` varchar(36),
	`title` varchar(255) NOT NULL,
	`caption` text,
	`media_type` enum('image','video','audio','other') NOT NULL DEFAULT 'image',
	`storage_key` varchar(500),
	`url` varchar(1000),
	`taken_date_text` varchar(120),
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `media_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `organization_members` (
	`id` varchar(36) NOT NULL,
	`organization_id` varchar(36) NOT NULL,
	`user_id` varchar(36) NOT NULL,
	`role` enum('owner','admin','editor','viewer') NOT NULL DEFAULT 'viewer',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `organization_members_id` PRIMARY KEY(`id`),
	CONSTRAINT `organization_members_org_user_unique` UNIQUE(`organization_id`,`user_id`)
);
--> statement-breakpoint
CREATE TABLE `organizations` (
	`id` varchar(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`slug` varchar(255) NOT NULL,
	`contact_email` varchar(255),
	`contact_phone` varchar(50),
	`public_site_enabled` boolean NOT NULL DEFAULT false,
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `organizations_id` PRIMARY KEY(`id`),
	CONSTRAINT `organizations_slug_unique` UNIQUE(`slug`)
);
--> statement-breakpoint
CREATE TABLE `owners` (
	`id` varchar(36) NOT NULL,
	`organization_id` varchar(36) NOT NULL,
	`name` varchar(255) NOT NULL,
	`contact_name` varchar(255),
	`mailing_address` text,
	`phone` varchar(50),
	`email` varchar(255),
	`notes` text,
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `owners_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `people` (
	`id` varchar(36) NOT NULL,
	`organization_id` varchar(36) NOT NULL,
	`legal_name` varchar(255) NOT NULL,
	`given_name` varchar(255),
	`family_name` varchar(255),
	`alternate_names` json,
	`maiden_name` varchar(255),
	`birth_date_text` varchar(120),
	`death_date_text` varchar(120),
	`birth_date` timestamp,
	`death_date` timestamp,
	`notes` text,
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `people_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `plot_ownerships` (
	`id` varchar(36) NOT NULL,
	`plot_id` varchar(36) NOT NULL,
	`owner_id` varchar(36) NOT NULL,
	`ownership_type` varchar(120),
	`start_date_text` varchar(120),
	`end_date_text` varchar(120),
	`notes` text,
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `plot_ownerships_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `plots` (
	`id` varchar(36) NOT NULL,
	`cemetery_id` varchar(36) NOT NULL,
	`section_id` varchar(36),
	`identifier` varchar(120) NOT NULL,
	`row` varchar(80),
	`lot` varchar(80),
	`status` enum('available','reserved','occupied','sold','unknown','unusable','needs_verification') NOT NULL DEFAULT 'unknown',
	`geometry` json,
	`notes` text,
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `plots_id` PRIMARY KEY(`id`),
	CONSTRAINT `plots_cemetery_identifier_unique` UNIQUE(`cemetery_id`,`identifier`)
);
--> statement-breakpoint
CREATE TABLE `sections` (
	`id` varchar(36) NOT NULL,
	`cemetery_id` varchar(36) NOT NULL,
	`code` varchar(80) NOT NULL,
	`name` varchar(255) NOT NULL,
	`description` text,
	`sort_order` int NOT NULL DEFAULT 0,
	`geometry` json,
	`visibility` enum('private','public') NOT NULL DEFAULT 'private',
	`confidence` enum('confirmed','probable','conflicting','unknown') NOT NULL DEFAULT 'unknown',
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `sections_id` PRIMARY KEY(`id`),
	CONSTRAINT `sections_cemetery_code_unique` UNIQUE(`cemetery_id`,`code`)
);
--> statement-breakpoint
CREATE TABLE `users` (
	`id` varchar(36) NOT NULL,
	`email` varchar(255) NOT NULL,
	`name` varchar(255),
	`hashed_password` varchar(255),
	`is_system_admin` boolean NOT NULL DEFAULT false,
	`created_at` timestamp NOT NULL DEFAULT (now()),
	`updated_at` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `users_id` PRIMARY KEY(`id`),
	CONSTRAINT `users_email_unique` UNIQUE(`email`)
);
--> statement-breakpoint
ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_organization_id_organizations_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `cemeteries` ADD CONSTRAINT `cemeteries_organization_id_organizations_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `documents` ADD CONSTRAINT `documents_organization_id_organizations_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `documents` ADD CONSTRAINT `documents_cemetery_id_cemeteries_id_fk` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `documents` ADD CONSTRAINT `documents_plot_id_plots_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `documents` ADD CONSTRAINT `documents_person_id_people_id_fk` FOREIGN KEY (`person_id`) REFERENCES `people`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `documents` ADD CONSTRAINT `documents_interment_id_interments_id_fk` FOREIGN KEY (`interment_id`) REFERENCES `interments`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `documents` ADD CONSTRAINT `documents_owner_id_owners_id_fk` FOREIGN KEY (`owner_id`) REFERENCES `owners`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `interments` ADD CONSTRAINT `interments_cemetery_id_cemeteries_id_fk` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `interments` ADD CONSTRAINT `interments_plot_id_plots_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `interments` ADD CONSTRAINT `interments_person_id_people_id_fk` FOREIGN KEY (`person_id`) REFERENCES `people`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `map_layers` ADD CONSTRAINT `map_layers_cemetery_id_cemeteries_id_fk` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `media` ADD CONSTRAINT `media_organization_id_organizations_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `media` ADD CONSTRAINT `media_cemetery_id_cemeteries_id_fk` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `media` ADD CONSTRAINT `media_plot_id_plots_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `media` ADD CONSTRAINT `media_person_id_people_id_fk` FOREIGN KEY (`person_id`) REFERENCES `people`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `media` ADD CONSTRAINT `media_interment_id_interments_id_fk` FOREIGN KEY (`interment_id`) REFERENCES `interments`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `media` ADD CONSTRAINT `media_owner_id_owners_id_fk` FOREIGN KEY (`owner_id`) REFERENCES `owners`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `organization_members` ADD CONSTRAINT `organization_members_organization_id_organizations_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `organization_members` ADD CONSTRAINT `organization_members_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `owners` ADD CONSTRAINT `owners_organization_id_organizations_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `people` ADD CONSTRAINT `people_organization_id_organizations_id_fk` FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `plot_ownerships` ADD CONSTRAINT `plot_ownerships_plot_id_plots_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `plot_ownerships` ADD CONSTRAINT `plot_ownerships_owner_id_owners_id_fk` FOREIGN KEY (`owner_id`) REFERENCES `owners`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `plots` ADD CONSTRAINT `plots_cemetery_id_cemeteries_id_fk` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `plots` ADD CONSTRAINT `plots_section_id_sections_id_fk` FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `sections` ADD CONSTRAINT `sections_cemetery_id_cemeteries_id_fk` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `audit_logs_organization_created_idx` ON `audit_logs` (`organization_id`,`created_at`);--> statement-breakpoint
CREATE INDEX `audit_logs_entity_idx` ON `audit_logs` (`entity_type`,`entity_id`);--> statement-breakpoint
CREATE INDEX `cemeteries_organization_idx` ON `cemeteries` (`organization_id`);--> statement-breakpoint
CREATE INDEX `interments_cemetery_idx` ON `interments` (`cemetery_id`);--> statement-breakpoint
CREATE INDEX `interments_plot_idx` ON `interments` (`plot_id`);--> statement-breakpoint
CREATE INDEX `interments_person_idx` ON `interments` (`person_id`);--> statement-breakpoint
CREATE INDEX `map_layers_cemetery_idx` ON `map_layers` (`cemetery_id`);--> statement-breakpoint
CREATE INDEX `organization_members_user_idx` ON `organization_members` (`user_id`);--> statement-breakpoint
CREATE INDEX `owners_organization_name_idx` ON `owners` (`organization_id`,`name`);--> statement-breakpoint
CREATE INDEX `people_organization_name_idx` ON `people` (`organization_id`,`legal_name`);--> statement-breakpoint
CREATE INDEX `plot_ownerships_plot_idx` ON `plot_ownerships` (`plot_id`);--> statement-breakpoint
CREATE INDEX `plot_ownerships_owner_idx` ON `plot_ownerships` (`owner_id`);--> statement-breakpoint
CREATE INDEX `plots_cemetery_status_idx` ON `plots` (`cemetery_id`,`status`);--> statement-breakpoint
CREATE INDEX `plots_section_idx` ON `plots` (`section_id`);--> statement-breakpoint
CREATE INDEX `sections_cemetery_idx` ON `sections` (`cemetery_id`);