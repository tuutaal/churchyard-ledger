// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";
import { getPeopleData, formatValue } from "@/lib/demo-queries";

export const dynamic = "force-dynamic";

export default async function PeoplePage() {
  const data = await getPeopleData();

  return (
    <ResourcePage
      title="People"
      description="Record names, alternate names, maiden names, partial dates, notes, and visibility controls."
      primaryAction="Add person"
    >
      <div className="space-y-3">
        <p className="text-sm text-muted-foreground">
          Showing {data.source === "database" ? "database-backed demo data" : "built-in sample data"}.
        </p>
        <div className="overflow-hidden rounded-md border">
          <div className="grid grid-cols-[1.1fr_0.8fr_0.8fr_0.7fr_0.7fr] bg-muted/55 px-4 py-2 text-xs font-medium uppercase text-muted-foreground">
            <span>Name</span>
            <span>Born</span>
            <span>Died</span>
            <span>Confidence</span>
            <span>Visibility</span>
          </div>
          {data.people.map((person) => (
            <div
              key={person.id}
              className="grid grid-cols-[1.1fr_0.8fr_0.8fr_0.7fr_0.7fr] border-t px-4 py-3 text-sm"
            >
              <span className="font-medium">{person.legalName}</span>
              <span className="text-muted-foreground">{person.birthDateText || "Unknown"}</span>
              <span className="text-muted-foreground">{person.deathDateText || "Unknown"}</span>
              <span className="text-muted-foreground">{formatValue(person.confidence)}</span>
              <span className="text-muted-foreground">{formatValue(person.visibility)}</span>
            </div>
          ))}
        </div>
      </div>
    </ResourcePage>
  );
}
