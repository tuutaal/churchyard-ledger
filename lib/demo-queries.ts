// SPDX-License-Identifier: AGPL-3.0-or-later
import { and, eq, ne, or, sql } from "drizzle-orm";
import { getDb } from "@/lib/db";
import {
  cemeteries,
  interments,
  people,
  plots,
  sections
} from "@/db/schema";
import { demoCemetery, demoPeople, demoPlots, demoVerificationItems } from "@/lib/demo-data";

type CountRow = { count: number };

export async function getDashboardData() {
  const db = getDb();

  if (!db) {
    return {
      source: "sample" as const,
      cemetery: demoCemetery,
      counts: {
        cemeteries: 1,
        plots: demoPlots.length,
        interments: 3,
        publicRecords: demoPeople.length + demoPlots.filter((plot) => plot.visibility === "public").length
      },
      verificationItems: demoVerificationItems
    };
  }

  try {
    const [cemetery] = await db.select().from(cemeteries).limit(1);
    const [cemeteryCount] = await db.select({ count: sql<number>`count(*)::int` }).from(cemeteries);
    const [plotCount] = await db.select({ count: sql<number>`count(*)::int` }).from(plots);
    const [intermentCount] = await db.select({ count: sql<number>`count(*)::int` }).from(interments);
    const [publicPeople] = await db
      .select({ count: sql<number>`count(*)::int` })
      .from(people)
      .where(eq(people.visibility, "public"));
    const [publicPlots] = await db
      .select({ count: sql<number>`count(*)::int` })
      .from(plots)
      .where(eq(plots.visibility, "public"));

    const uncertainPlots = await db
      .select({
        identifier: plots.identifier,
        status: plots.status,
        confidence: plots.confidence
      })
      .from(plots)
      .where(or(ne(plots.confidence, "confirmed"), eq(plots.status, "needs_verification")))
      .limit(3);

    const verificationItems = uncertainPlots.length
      ? uncertainPlots.map((plot) => ({
          record: `Plot ${plot.identifier}`,
          detail: `Status ${formatValue(plot.status)} with ${plot.confidence} confidence`,
          status: formatValue(plot.confidence),
          tone: plot.confidence === "conflicting" ? "bg-rose-50 text-rose-700" : "bg-amber-50 text-amber-700"
        }))
      : demoVerificationItems;

    return {
      source: "database" as const,
      cemetery: cemetery ?? demoCemetery,
      counts: {
        cemeteries: countValue(cemeteryCount),
        plots: countValue(plotCount),
        interments: countValue(intermentCount),
        publicRecords: countValue(publicPeople) + countValue(publicPlots)
      },
      verificationItems
    };
  } catch {
    return {
      source: "sample" as const,
      cemetery: demoCemetery,
      counts: {
        cemeteries: 1,
        plots: demoPlots.length,
        interments: 3,
        publicRecords: demoPeople.length + demoPlots.filter((plot) => plot.visibility === "public").length
      },
      verificationItems: demoVerificationItems
    };
  }
}

export async function getPeopleData() {
  const db = getDb();

  if (!db) {
    return { source: "sample" as const, people: demoPeople };
  }

  try {
    const rows = await db
      .select({
        id: people.id,
        legalName: people.legalName,
        birthDateText: people.birthDateText,
        deathDateText: people.deathDateText,
        visibility: people.visibility,
        confidence: people.confidence
      })
      .from(people)
      .orderBy(people.legalName);

    return { source: "database" as const, people: rows.length ? rows : demoPeople };
  } catch {
    return { source: "sample" as const, people: demoPeople };
  }
}

export async function getPlotsData() {
  const db = getDb();

  if (!db) {
    return { source: "sample" as const, plots: demoPlots };
  }

  try {
    const rows = await db
      .select({
        id: plots.id,
        identifier: plots.identifier,
        sectionCode: sections.code,
        row: plots.row,
        lot: plots.lot,
        status: plots.status,
        visibility: plots.visibility,
        confidence: plots.confidence
      })
      .from(plots)
      .leftJoin(sections, eq(plots.sectionId, sections.id))
      .orderBy(plots.identifier);

    return { source: "database" as const, plots: rows.length ? rows : demoPlots };
  } catch {
    return { source: "sample" as const, plots: demoPlots };
  }
}

export async function getSearchData() {
  const peopleData = await getPeopleData();
  const plotsData = await getPlotsData();

  return {
    source: peopleData.source === "database" || plotsData.source === "database" ? "database" as const : "sample" as const,
    people: peopleData.people,
    plots: plotsData.plots
  };
}

export function formatValue(value: string | null | undefined) {
  if (!value) {
    return "Unknown";
  }

  return value
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function countValue(row: CountRow | undefined) {
  return Number(row?.count ?? 0);
}
