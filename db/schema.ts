// SPDX-License-Identifier: AGPL-3.0-or-later
import {
  boolean,
  index,
  int,
  json,
  mysqlEnum,
  mysqlTable,
  text,
  timestamp,
  uniqueIndex,
  varchar
} from "drizzle-orm/mysql-core";

const organizationRole = (name: string) => mysqlEnum(name, ["owner", "admin", "editor", "viewer"]);
const visibilityEnum = (name: string) => mysqlEnum(name, ["private", "public"]);
const recordConfidence = (name: string) => mysqlEnum(name, [
  "confirmed",
  "probable",
  "conflicting",
  "unknown"
]);
const plotStatus = (name: string) => mysqlEnum(name, [
  "available",
  "reserved",
  "occupied",
  "sold",
  "unknown",
  "unusable",
  "needs_verification"
]);
const mediaType = (name: string) => mysqlEnum(name, ["image", "video", "audio", "other"]);
const documentType = (name: string) => mysqlEnum(name, [
  "deed",
  "certificate",
  "permit",
  "receipt",
  "map",
  "note",
  "other"
]);
const mapLayerType = (name: string) => mysqlEnum(name, [
  "uploaded_image",
  "geojson",
  "vector",
  "raster_tiles",
  "other"
]);
const auditAction = (name: string) => mysqlEnum(name, [
  "create",
  "update",
  "delete",
  "import",
  "export",
  "visibility_change",
  "setup"
]);

const id = (name = "id") => varchar(name, { length: 36 });

const timestamps = {
  createdAt: timestamp("created_at").notNull().defaultNow(),
  updatedAt: timestamp("updated_at").notNull().defaultNow().onUpdateNow()
};

export const organizations = mysqlTable("organizations", {
  id: id().primaryKey(),
  name: varchar("name", { length: 255 }).notNull(),
  slug: varchar("slug", { length: 255 }).notNull().unique(),
  contactEmail: varchar("contact_email", { length: 255 }),
  contactPhone: varchar("contact_phone", { length: 50 }),
  publicSiteEnabled: boolean("public_site_enabled").notNull().default(false),
  ...timestamps
});

export const users = mysqlTable("users", {
  id: id().primaryKey(),
  email: varchar("email", { length: 255 }).notNull().unique(),
  name: varchar("name", { length: 255 }),
  hashedPassword: varchar("hashed_password", { length: 255 }),
  isSystemAdmin: boolean("is_system_admin").notNull().default(false),
  ...timestamps
});

export const organizationMembers = mysqlTable(
  "organization_members",
  {
    id: id().primaryKey(),
    organizationId: id("organization_id")
      .notNull()
      .references(() => organizations.id, { onDelete: "cascade" }),
    userId: id("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    role: organizationRole("role").notNull().default("viewer"),
    createdAt: timestamp("created_at").notNull().defaultNow()
  },
  (table) => ({
    organizationUserUnique: uniqueIndex("organization_members_org_user_unique").on(
      table.organizationId,
      table.userId
    ),
    userIndex: index("organization_members_user_idx").on(table.userId)
  })
);

export const cemeteries = mysqlTable(
  "cemeteries",
  {
    id: id().primaryKey(),
    organizationId: id("organization_id")
      .notNull()
      .references(() => organizations.id, { onDelete: "cascade" }),
    name: varchar("name", { length: 255 }).notNull(),
    slug: varchar("slug", { length: 255 }).notNull(),
    description: text("description"),
    addressLine1: varchar("address_line_1", { length: 255 }),
    addressLine2: varchar("address_line_2", { length: 255 }),
    city: varchar("city", { length: 120 }),
    state: varchar("state", { length: 80 }),
    postalCode: varchar("postal_code", { length: 30 }),
    country: varchar("country", { length: 2 }).notNull().default("US"),
    publicSiteEnabled: boolean("public_site_enabled").notNull().default(false),
    defaultVisibility: visibilityEnum("default_visibility").notNull().default("private"),
    boundaryGeoJson: json("boundary_geojson"),
    ...timestamps
  },
  (table) => ({
    organizationSlugUnique: uniqueIndex("cemeteries_org_slug_unique").on(
      table.organizationId,
      table.slug
    ),
    organizationIndex: index("cemeteries_organization_idx").on(table.organizationId)
  })
);

export const sections = mysqlTable(
  "sections",
  {
    id: id().primaryKey(),
    cemeteryId: id("cemetery_id")
      .notNull()
      .references(() => cemeteries.id, { onDelete: "cascade" }),
    code: varchar("code", { length: 80 }).notNull(),
    name: varchar("name", { length: 255 }).notNull(),
    description: text("description"),
    sortOrder: int("sort_order").notNull().default(0),
    geometry: json("geometry"),
    visibility: visibilityEnum("visibility").notNull().default("private"),
    confidence: recordConfidence("confidence").notNull().default("unknown"),
    ...timestamps
  },
  (table) => ({
    cemeteryCodeUnique: uniqueIndex("sections_cemetery_code_unique").on(
      table.cemeteryId,
      table.code
    ),
    cemeteryIndex: index("sections_cemetery_idx").on(table.cemeteryId)
  })
);

export const plots = mysqlTable(
  "plots",
  {
    id: id().primaryKey(),
    cemeteryId: id("cemetery_id")
      .notNull()
      .references(() => cemeteries.id, { onDelete: "cascade" }),
    sectionId: id("section_id").references(() => sections.id, { onDelete: "set null" }),
    identifier: varchar("identifier", { length: 120 }).notNull(),
    row: varchar("row", { length: 80 }),
    lot: varchar("lot", { length: 80 }),
    status: plotStatus("status").notNull().default("unknown"),
    geometry: json("geometry"),
    notes: text("notes"),
    visibility: visibilityEnum("visibility").notNull().default("private"),
    confidence: recordConfidence("confidence").notNull().default("unknown"),
    ...timestamps
  },
  (table) => ({
    cemeteryIdentifierUnique: uniqueIndex("plots_cemetery_identifier_unique").on(
      table.cemeteryId,
      table.identifier
    ),
    cemeteryStatusIndex: index("plots_cemetery_status_idx").on(table.cemeteryId, table.status),
    sectionIndex: index("plots_section_idx").on(table.sectionId)
  })
);

export const people = mysqlTable(
  "people",
  {
    id: id().primaryKey(),
    organizationId: id("organization_id")
      .notNull()
      .references(() => organizations.id, { onDelete: "cascade" }),
    legalName: varchar("legal_name", { length: 255 }).notNull(),
    givenName: varchar("given_name", { length: 255 }),
    familyName: varchar("family_name", { length: 255 }),
    alternateNames: json("alternate_names"),
    maidenName: varchar("maiden_name", { length: 255 }),
    birthDateText: varchar("birth_date_text", { length: 120 }),
    deathDateText: varchar("death_date_text", { length: 120 }),
    birthDate: timestamp("birth_date"),
    deathDate: timestamp("death_date"),
    notes: text("notes"),
    visibility: visibilityEnum("visibility").notNull().default("private"),
    confidence: recordConfidence("confidence").notNull().default("unknown"),
    ...timestamps
  },
  (table) => ({
    organizationNameIndex: index("people_organization_name_idx").on(
      table.organizationId,
      table.legalName
    )
  })
);

export const interments = mysqlTable(
  "interments",
  {
    id: id().primaryKey(),
    cemeteryId: id("cemetery_id")
      .notNull()
      .references(() => cemeteries.id, { onDelete: "cascade" }),
    plotId: id("plot_id")
      .notNull()
      .references(() => plots.id, { onDelete: "cascade" }),
    personId: id("person_id")
      .notNull()
      .references(() => people.id, { onDelete: "cascade" }),
    intermentDateText: varchar("interment_date_text", { length: 120 }),
    intermentDate: timestamp("interment_date"),
    burialPermitNumber: varchar("burial_permit_number", { length: 120 }),
    markerTranscription: text("marker_transcription"),
    plotPosition: varchar("plot_position", { length: 120 }),
    notes: text("notes"),
    visibility: visibilityEnum("visibility").notNull().default("private"),
    confidence: recordConfidence("confidence").notNull().default("unknown"),
    ...timestamps
  },
  (table) => ({
    cemeteryIndex: index("interments_cemetery_idx").on(table.cemeteryId),
    plotIndex: index("interments_plot_idx").on(table.plotId),
    personIndex: index("interments_person_idx").on(table.personId)
  })
);

export const owners = mysqlTable(
  "owners",
  {
    id: id().primaryKey(),
    organizationId: id("organization_id")
      .notNull()
      .references(() => organizations.id, { onDelete: "cascade" }),
    name: varchar("name", { length: 255 }).notNull(),
    contactName: varchar("contact_name", { length: 255 }),
    mailingAddress: text("mailing_address"),
    phone: varchar("phone", { length: 50 }),
    email: varchar("email", { length: 255 }),
    notes: text("notes"),
    visibility: visibilityEnum("visibility").notNull().default("private"),
    confidence: recordConfidence("confidence").notNull().default("unknown"),
    ...timestamps
  },
  (table) => ({
    organizationNameIndex: index("owners_organization_name_idx").on(
      table.organizationId,
      table.name
    )
  })
);

export const plotOwnerships = mysqlTable(
  "plot_ownerships",
  {
    id: id().primaryKey(),
    plotId: id("plot_id")
      .notNull()
      .references(() => plots.id, { onDelete: "cascade" }),
    ownerId: id("owner_id")
      .notNull()
      .references(() => owners.id, { onDelete: "cascade" }),
    ownershipType: varchar("ownership_type", { length: 120 }),
    startDateText: varchar("start_date_text", { length: 120 }),
    endDateText: varchar("end_date_text", { length: 120 }),
    notes: text("notes"),
    confidence: recordConfidence("confidence").notNull().default("unknown"),
    ...timestamps
  },
  (table) => ({
    plotIndex: index("plot_ownerships_plot_idx").on(table.plotId),
    ownerIndex: index("plot_ownerships_owner_idx").on(table.ownerId)
  })
);

export const media = mysqlTable("media", {
  id: id().primaryKey(),
  organizationId: id("organization_id")
    .notNull()
    .references(() => organizations.id, { onDelete: "cascade" }),
  cemeteryId: id("cemetery_id").references(() => cemeteries.id, { onDelete: "cascade" }),
  plotId: id("plot_id").references(() => plots.id, { onDelete: "cascade" }),
  personId: id("person_id").references(() => people.id, { onDelete: "cascade" }),
  intermentId: id("interment_id").references(() => interments.id, { onDelete: "cascade" }),
  ownerId: id("owner_id").references(() => owners.id, { onDelete: "cascade" }),
  title: varchar("title", { length: 255 }).notNull(),
  caption: text("caption"),
  mediaType: mediaType("media_type").notNull().default("image"),
  storageKey: varchar("storage_key", { length: 500 }),
  url: varchar("url", { length: 1000 }),
  takenDateText: varchar("taken_date_text", { length: 120 }),
  visibility: visibilityEnum("visibility").notNull().default("private"),
  confidence: recordConfidence("confidence").notNull().default("unknown"),
  ...timestamps
});

export const documents = mysqlTable("documents", {
  id: id().primaryKey(),
  organizationId: id("organization_id")
    .notNull()
    .references(() => organizations.id, { onDelete: "cascade" }),
  cemeteryId: id("cemetery_id").references(() => cemeteries.id, { onDelete: "cascade" }),
  plotId: id("plot_id").references(() => plots.id, { onDelete: "cascade" }),
  personId: id("person_id").references(() => people.id, { onDelete: "cascade" }),
  intermentId: id("interment_id").references(() => interments.id, { onDelete: "cascade" }),
  ownerId: id("owner_id").references(() => owners.id, { onDelete: "cascade" }),
  title: varchar("title", { length: 255 }).notNull(),
  documentType: documentType("document_type").notNull().default("other"),
  fileName: varchar("file_name", { length: 255 }),
  storageKey: varchar("storage_key", { length: 500 }),
  url: varchar("url", { length: 1000 }),
  notes: text("notes"),
  visibility: visibilityEnum("visibility").notNull().default("private"),
  confidence: recordConfidence("confidence").notNull().default("unknown"),
  ...timestamps
});

export const mapLayers = mysqlTable(
  "map_layers",
  {
    id: id().primaryKey(),
    cemeteryId: id("cemetery_id")
      .notNull()
      .references(() => cemeteries.id, { onDelete: "cascade" }),
    name: varchar("name", { length: 255 }).notNull(),
    layerType: mapLayerType("layer_type").notNull().default("uploaded_image"),
    sourceUrl: varchar("source_url", { length: 1000 }),
    sourceMetadata: json("source_metadata"),
    boundsGeoJson: json("bounds_geojson"),
    styleJson: json("style_json"),
    sortOrder: int("sort_order").notNull().default(0),
    isVisibleByDefault: boolean("is_visible_by_default").notNull().default(true),
    visibility: visibilityEnum("visibility").notNull().default("private"),
    confidence: recordConfidence("confidence").notNull().default("unknown"),
    ...timestamps
  },
  (table) => ({
    cemeteryIndex: index("map_layers_cemetery_idx").on(table.cemeteryId)
  })
);

export const auditLogs = mysqlTable(
  "audit_logs",
  {
    id: id().primaryKey(),
    organizationId: id("organization_id")
      .notNull()
      .references(() => organizations.id, { onDelete: "cascade" }),
    userId: id("user_id").references(() => users.id, { onDelete: "set null" }),
    action: auditAction("action").notNull(),
    entityType: varchar("entity_type", { length: 120 }).notNull(),
    entityId: varchar("entity_id", { length: 36 }),
    summary: text("summary").notNull(),
    before: json("before"),
    after: json("after"),
    createdAt: timestamp("created_at").notNull().defaultNow()
  },
  (table) => ({
    organizationCreatedIndex: index("audit_logs_organization_created_idx").on(
      table.organizationId,
      table.createdAt
    ),
    entityIndex: index("audit_logs_entity_idx").on(table.entityType, table.entityId)
  })
);

export type Organization = typeof organizations.$inferSelect;
export type Cemetery = typeof cemeteries.$inferSelect;
export type Plot = typeof plots.$inferSelect;
export type Person = typeof people.$inferSelect;
