// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function ImportsPage() {
  return (
    <ResourcePage
      title="Imports"
      description="Prepare for CSV imports from church spreadsheets, trustee lists, and cemetery notebooks."
      primaryAction="Upload CSV"
    />
  );
}
