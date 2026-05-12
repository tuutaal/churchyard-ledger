// SPDX-License-Identifier: AGPL-3.0-or-later
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { CircleAlert, Database, FileCheck2, Landmark, MapPinned, Search, ShieldCheck } from "lucide-react";
import { getDashboardData } from "@/lib/demo-queries";

export const dynamic = "force-dynamic";

const recentWork = [
  "Sample cemetery seeded for Grace Rural Church",
  "Public search and map routes are scaffolded",
  "Drizzle migration generated for 14 tables"
];

export default async function DashboardPage() {
  const data = await getDashboardData();
  const metrics = [
    { label: "Cemeteries", value: String(data.counts.cemeteries), detail: data.cemetery.name, icon: Landmark },
    { label: "Plots", value: String(data.counts.plots), detail: "Includes available, reserved, occupied, and review statuses", icon: MapPinned },
    { label: "Interments", value: String(data.counts.interments), detail: "People connected to cemetery plots", icon: FileCheck2 },
    { label: "Public records", value: String(data.counts.publicRecords), detail: "Visible on public pages", icon: ShieldCheck }
  ];

  return (
    <div className="space-y-7">
      <div className="grid gap-5 xl:grid-cols-[1.45fr_0.9fr]">
        <section className="rounded-lg border bg-white p-5 shadow-sm">
          <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
              <p className="text-sm font-medium text-primary">Records workspace</p>
              <h1 className="mt-2 text-3xl font-semibold tracking-normal">{data.cemetery.name}</h1>
              <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                {data.cemetery.description ?? "A calm working view for plots, interments, ownership, public visibility, and records that need another set of eyes."}
              </p>
              <p className="mt-3 inline-flex rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                Showing {data.source === "database" ? "database-backed demo data" : "built-in sample data"}
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button>
                <Search className="h-4 w-4" aria-hidden="true" />
                Search
              </Button>
              <Button variant="outline">
                <Database className="h-4 w-4" aria-hidden="true" />
                Import CSV
              </Button>
            </div>
          </div>
          <div className="mt-5 grid gap-3 sm:grid-cols-3">
            <div className="rounded-md border bg-muted/40 p-3">
              <p className="text-xs font-medium uppercase text-muted-foreground">Public site</p>
              <p className="mt-1 text-sm font-semibold">{data.cemetery.publicSiteEnabled ? "Enabled" : "Disabled"}</p>
            </div>
            <div className="rounded-md border bg-muted/40 p-3">
              <p className="text-xs font-medium uppercase text-muted-foreground">Default visibility</p>
              <p className="mt-1 text-sm font-semibold">{data.cemetery.defaultVisibility ?? "Private"}</p>
            </div>
            <div className="rounded-md border bg-muted/40 p-3">
              <p className="text-xs font-medium uppercase text-muted-foreground">Data model</p>
              <p className="mt-1 text-sm font-semibold">Drizzle + PostgreSQL</p>
            </div>
          </div>
        </section>

        <section className="rounded-lg border bg-primary p-5 text-primary-foreground shadow-sm">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-medium text-primary-foreground/75">Next best action</p>
              <h2 className="mt-2 text-xl font-semibold">Review uncertain records</h2>
            </div>
            <CircleAlert className="h-5 w-5 text-primary-foreground/75" aria-hidden="true" />
          </div>
          <p className="mt-3 text-sm leading-6 text-primary-foreground/82">
            Start with records marked probable, conflicting, or unknown before publishing broader public search results.
          </p>
          <Button className="mt-5 bg-white text-primary hover:bg-white/90">Open review queue</Button>
        </section>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {metrics.map((metric) => (
          <Card key={metric.label} className="overflow-hidden">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">{metric.label}</CardTitle>
              <div className="rounded-md bg-muted p-2">
                <metric.icon className="h-4 w-4 text-primary" aria-hidden="true" />
              </div>
            </CardHeader>
            <CardContent>
              <p className="text-3xl font-semibold">{metric.value}</p>
              <p className="mt-1 text-sm text-muted-foreground">{metric.detail}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-5 xl:grid-cols-[1fr_0.95fr]">
        <Card>
          <CardHeader>
            <CardTitle>Verification Queue</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-hidden rounded-md border">
              {data.verificationItems.map((item) => (
                <div key={item.record} className="grid gap-3 border-b p-4 last:border-b-0 sm:grid-cols-[1fr_auto] sm:items-center">
                  <div>
                    <p className="font-medium">{item.record}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{item.detail}</p>
                  </div>
                  <span className={`w-fit rounded-full px-2.5 py-1 text-xs font-medium ${item.tone}`}>
                    {item.status}
                  </span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Map Readiness</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="relative aspect-[16/10] overflow-hidden rounded-md border bg-[#eef3ef]">
              <div className="absolute inset-0 bg-[linear-gradient(90deg,hsl(164_22%_68%_/_0.25)_1px,transparent_1px),linear-gradient(0deg,hsl(164_22%_68%_/_0.25)_1px,transparent_1px)] bg-[size:28px_28px]" />
              <div className="absolute left-[18%] top-[20%] h-20 w-28 rounded border border-primary/50 bg-white/78 p-2 text-xs shadow-sm">
                Section A
              </div>
              <div className="absolute right-[18%] top-[38%] h-24 w-32 rounded border border-amber-500/55 bg-white/78 p-2 text-xs shadow-sm">
                Section B
              </div>
              <div className="absolute bottom-3 left-3 rounded-md bg-white px-3 py-2 text-xs text-muted-foreground shadow-sm">
                GeoJSON-ready plot geometry
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent Setup Work</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 md:grid-cols-3">
            {recentWork.map((item) => (
              <div key={item} className="rounded-md border bg-muted/35 p-3 text-sm">
                {item}
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
