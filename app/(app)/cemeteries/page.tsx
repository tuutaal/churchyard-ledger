// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function CemeteriesPage() {
  return (
    <ResourcePage
      title="Cemeteries"
      description="Manage one or more cemeteries under each organization without enterprise overhead."
      primaryAction="Add cemetery"
    />
  );
}
