// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function PublicSiteSettingsPage() {
  return (
    <ResourcePage
      title="Public Site Settings"
      description="Configure public landing, search, map, and iframe embed behavior for church websites."
      primaryAction="Update settings"
    />
  );
}
