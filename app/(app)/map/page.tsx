// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function MapPage() {
  return (
    <ResourcePage
      title="Map"
      description="Start with uploaded image maps and GeoJSON-style plot geometry, leaving room for PostGIS later."
      primaryAction="Add map layer"
    >
      <div className="aspect-[16/9] rounded-md border bg-muted p-4 text-sm text-muted-foreground">
        Leaflet or MapLibre will render cemetery sections, plots, and uploaded image layers here.
      </div>
    </ResourcePage>
  );
}
