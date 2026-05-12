// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";
import { getPlotsData, formatValue } from "@/lib/demo-queries";

export const dynamic = "force-dynamic";

export default async function PlotsPage() {
  const data = await getPlotsData();

  return (
    <ResourcePage
      title="Plots"
      description="Track plot identifiers, status, verification confidence, geometry, and public visibility."
      primaryAction="Add plot"
    >
      <div className="space-y-3">
        <p className="text-sm text-muted-foreground">
          Showing {data.source === "database" ? "database-backed demo data" : "built-in sample data"}.
        </p>
        <div className="overflow-hidden rounded-md border">
          <div className="grid grid-cols-[0.8fr_0.6fr_0.5fr_0.5fr_0.9fr_0.8fr_0.7fr] bg-muted/55 px-4 py-2 text-xs font-medium uppercase text-muted-foreground">
            <span>Plot</span>
            <span>Section</span>
            <span>Row</span>
            <span>Lot</span>
            <span>Status</span>
            <span>Confidence</span>
            <span>Visibility</span>
          </div>
          {data.plots.map((plot) => (
            <div
              key={plot.id}
              className="grid grid-cols-[0.8fr_0.6fr_0.5fr_0.5fr_0.9fr_0.8fr_0.7fr] border-t px-4 py-3 text-sm"
            >
              <span className="font-medium">{plot.identifier}</span>
              <span className="text-muted-foreground">{plot.sectionCode ?? "None"}</span>
              <span className="text-muted-foreground">{plot.row ?? "Unknown"}</span>
              <span className="text-muted-foreground">{plot.lot ?? "Unknown"}</span>
              <span className="text-muted-foreground">{formatValue(plot.status)}</span>
              <span className="text-muted-foreground">{formatValue(plot.confidence)}</span>
              <span className="text-muted-foreground">{formatValue(plot.visibility)}</span>
            </div>
          ))}
        </div>
      </div>
    </ResourcePage>
  );
}
