// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function AdminSettingsPage() {
  return (
    <ResourcePage
      title="Admin Settings"
      description="Manage organizations, simple roles, exports, backups, and audit history."
      primaryAction="Invite user"
    />
  );
}
