// SPDX-License-Identifier: AGPL-3.0-or-later
import { sql } from "drizzle-orm";
import { drizzle } from "drizzle-orm/mysql2";
import {
  auditLogs,
  cemeteries,
  documents,
  interments,
  mapLayers,
  media,
  organizationMembers,
  organizations,
  owners,
  people,
  plotOwnerships,
  plots,
  sections,
  users
} from "./schema";

const connectionString = process.env.DATABASE_URL;

if (!connectionString) {
  throw new Error("DATABASE_URL is required");
}

const db = drizzle(connectionString);

const ids = {
  organization: "00000000-0000-4000-8000-000000000001",
  admin: "00000000-0000-4000-8000-000000000002",
  cemetery: "00000000-0000-4000-8000-000000000003",
  sectionA: "00000000-0000-4000-8000-000000000004",
  sectionB: "00000000-0000-4000-8000-000000000005",
  owner: "00000000-0000-4000-8000-000000000006",
  john: "00000000-0000-4000-8000-000000000007",
  sarah: "00000000-0000-4000-8000-000000000008",
  mary: "00000000-0000-4000-8000-000000000009",
  plotA01: "00000000-0000-4000-8000-000000000010",
  plotA02: "00000000-0000-4000-8000-000000000011",
  plotA03: "00000000-0000-4000-8000-000000000012",
  plotA04: "00000000-0000-4000-8000-000000000013",
  plotB01: "00000000-0000-4000-8000-000000000014",
  plotB02: "00000000-0000-4000-8000-000000000015",
  plotB03: "00000000-0000-4000-8000-000000000016",
  membership: "00000000-0000-4000-8000-000000000017",
  intermentJohn: "00000000-0000-4000-8000-000000000018",
  intermentSarah: "00000000-0000-4000-8000-000000000019",
  intermentMary: "00000000-0000-4000-8000-000000000020",
  ownership: "00000000-0000-4000-8000-000000000021",
  mapLayer: "00000000-0000-4000-8000-000000000022",
  media: "00000000-0000-4000-8000-000000000023",
  document: "00000000-0000-4000-8000-000000000024",
  auditLog: "00000000-0000-4000-8000-000000000025"
};

async function main() {
  await db.insert(organizations).values({
    id: ids.organization,
    name: "Grace Rural Church",
    slug: "grace-rural-church",
    contactEmail: "office@example.org",
    publicSiteEnabled: true
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(users).values({
    id: ids.admin,
    email: "admin@example.org",
    name: "Church Secretary"
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(organizationMembers).values({
    id: ids.membership,
    organizationId: ids.organization,
    userId: ids.admin,
    role: "owner"
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(cemeteries).values({
    id: ids.cemetery,
    organizationId: ids.organization,
    name: "Grace Rural Cemetery",
    slug: "grace-rural-cemetery",
    description: "A small church cemetery maintained by volunteers.",
    city: "Fairview",
    state: "IA",
    publicSiteEnabled: true,
    boundaryGeoJson: {
      type: "Polygon",
      coordinates: [[[-93.111, 41.991], [-93.108, 41.991], [-93.108, 41.993], [-93.111, 41.993], [-93.111, 41.991]]]
    }
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(sections).values([
    {
      id: ids.sectionA,
      cemeteryId: ids.cemetery,
      code: "A",
      name: "Section A",
      description: "Oldest section near the church lane.",
      sortOrder: 1,
      visibility: "public",
      confidence: "confirmed"
    },
    {
      id: ids.sectionB,
      cemeteryId: ids.cemetery,
      code: "B",
      name: "Section B",
      description: "Newer section with several records needing verification.",
      sortOrder: 2,
      visibility: "public",
      confidence: "probable"
    }
  ]).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(plots).values([
    plot(ids.plotA01, ids.sectionA, "A-01", "occupied", "confirmed", "public", 0),
    plot(ids.plotA02, ids.sectionA, "A-02", "occupied", "confirmed", "public", 1),
    plot(ids.plotA03, ids.sectionA, "A-03", "needs_verification", "conflicting", "public", 2),
    plot(ids.plotA04, ids.sectionA, "A-04", "reserved", "confirmed", "private", 3),
    plot(ids.plotB01, ids.sectionB, "B-01", "available", "confirmed", "private", 4),
    plot(ids.plotB02, ids.sectionB, "B-02", "sold", "confirmed", "private", 5),
    plot(ids.plotB03, ids.sectionB, "B-03", "unusable", "probable", "private", 6)
  ]).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(people).values([
    {
      id: ids.john,
      organizationId: ids.organization,
      legalName: "John Thomas Miller",
      givenName: "John Thomas",
      familyName: "Miller",
      birthDateText: "March 4, 1881",
      deathDateText: "June 12, 1946",
      visibility: "public",
      confidence: "confirmed"
    },
    {
      id: ids.sarah,
      organizationId: ids.organization,
      legalName: "Sarah Ann Miller",
      givenName: "Sarah Ann",
      familyName: "Miller",
      maidenName: "Brooks",
      alternateNames: ["S. A. Miller", "Sarah Brooks Miller"],
      birthDateText: "1885",
      deathDateText: "1952",
      visibility: "public",
      confidence: "confirmed"
    },
    {
      id: ids.mary,
      organizationId: ids.organization,
      legalName: "Mary E. Holloway",
      givenName: "Mary E.",
      familyName: "Holloway",
      deathDateText: "possibly 1918",
      notes: "Date comes from a faded marker photo and needs confirmation.",
      visibility: "public",
      confidence: "probable"
    }
  ]).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(interments).values([
    {
      id: ids.intermentJohn,
      cemeteryId: ids.cemetery,
      plotId: ids.plotA01,
      personId: ids.john,
      intermentDateText: "June 1946",
      markerTranscription: "John T. Miller 1881-1946",
      visibility: "public",
      confidence: "confirmed"
    },
    {
      id: ids.intermentSarah,
      cemeteryId: ids.cemetery,
      plotId: ids.plotA02,
      personId: ids.sarah,
      intermentDateText: "1952",
      markerTranscription: "Sarah A. Miller 1885-1952",
      visibility: "public",
      confidence: "confirmed"
    },
    {
      id: ids.intermentMary,
      cemeteryId: ids.cemetery,
      plotId: ids.plotA03,
      personId: ids.mary,
      markerTranscription: "Mary E. Holloway",
      notes: "Plot location copied from handwritten trustee map.",
      visibility: "public",
      confidence: "probable"
    }
  ]).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(owners).values({
    id: ids.owner,
    organizationId: ids.organization,
    name: "Miller Family",
    contactName: "Private family contact",
    notes: "Contact information is private; ownership record can be reviewed by trustees.",
    visibility: "private",
    confidence: "confirmed"
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(plotOwnerships).values({
    id: ids.ownership,
    plotId: ids.plotA04,
    ownerId: ids.owner,
    ownershipType: "family reservation",
    startDateText: "1953",
    confidence: "confirmed"
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(mapLayers).values({
    id: ids.mapLayer,
    cemeteryId: ids.cemetery,
    name: "Trustee sketch map",
    layerType: "uploaded_image",
    sourceMetadata: {
      originalFileName: "trustee-sketch-map.jpg",
      note: "Image upload placeholder; georeferencing can be added later."
    },
    visibility: "private",
    confidence: "probable"
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(media).values({
    id: ids.media,
    organizationId: ids.organization,
    cemeteryId: ids.cemetery,
    personId: ids.mary,
    title: "Faded marker photo",
    caption: "Needs a second reading before dates are confirmed.",
    mediaType: "image",
    visibility: "private",
    confidence: "probable"
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(documents).values({
    id: ids.document,
    organizationId: ids.organization,
    cemeteryId: ids.cemetery,
    plotId: ids.plotA03,
    title: "Handwritten trustee map note",
    documentType: "note",
    notes: "Lists A-03 as Holloway, but row number is hard to read.",
    visibility: "private",
    confidence: "conflicting"
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });

  await db.insert(auditLogs).values({
    id: ids.auditLog,
    organizationId: ids.organization,
    userId: ids.admin,
    action: "setup",
    entityType: "Organization",
    entityId: ids.organization,
    summary: "Seeded sample church cemetery data."
  }).onDuplicateKeyUpdate({ set: { id: sql`id` } });
}

function plot(
  id: string,
  sectionId: string,
  identifier: string,
  status: "available" | "reserved" | "occupied" | "sold" | "unknown" | "unusable" | "needs_verification",
  confidence: "confirmed" | "probable" | "conflicting" | "unknown",
  visibility: "private" | "public",
  index: number
) {
  return {
    id,
    cemeteryId: ids.cemetery,
    sectionId,
    identifier,
    row: "1",
    lot: String(index + 1),
    status,
    geometry: {
      type: "Polygon",
      coordinates: [[
        [-93.1108 + index * 0.00015, 41.9912],
        [-93.1107 + index * 0.00015, 41.9912],
        [-93.1107 + index * 0.00015, 41.9913],
        [-93.1108 + index * 0.00015, 41.9913],
        [-93.1108 + index * 0.00015, 41.9912]
      ]]
    },
    visibility,
    confidence
  };
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
