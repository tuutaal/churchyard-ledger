// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";
import { getSearchData, formatValue } from "@/lib/demo-queries";

export const dynamic = "force-dynamic";

export default async function SearchPage() {
  const data = await getSearchData();

  return (
    <ResourcePage
      title="Search"
      description="Search across public and private cemetery records with visibility-aware results."
      primaryAction="New saved search"
    >
      <div className="space-y-5">
        <div className="rounded-md border bg-white p-3">
          <input
            className="h-10 w-full rounded-md border px-3 text-sm"
            placeholder="Search names, plots, and public notes"
            readOnly
          />
        </div>
        <p className="text-sm text-muted-foreground">
          Showing {data.source === "database" ? "database-backed demo data" : "built-in sample data"}. The search box is visual only until interactive filtering is added.
        </p>
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="overflow-hidden rounded-md border">
            <div className="bg-muted/55 px-4 py-2 text-xs font-medium uppercase text-muted-foreground">People</div>
            {data.people.map((person) => (
              <div key={person.id} className="border-t px-4 py-3 text-sm">
                <p className="font-medium">{person.legalName}</p>
                <p className="mt-1 text-muted-foreground">
                  {person.birthDateText || "Unknown"} - {person.deathDateText || "Unknown"} · {formatValue(person.confidence)}
                </p>
              </div>
            ))}
          </div>
          <div className="overflow-hidden rounded-md border">
            <div className="bg-muted/55 px-4 py-2 text-xs font-medium uppercase text-muted-foreground">Plots</div>
            {data.plots.map((plot) => (
              <div key={plot.id} className="border-t px-4 py-3 text-sm">
                <p className="font-medium">{plot.identifier}</p>
                <p className="mt-1 text-muted-foreground">
                  Section {plot.sectionCode ?? "None"} · {formatValue(plot.status)} · {formatValue(plot.confidence)}
                </p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </ResourcePage>
  );
}
