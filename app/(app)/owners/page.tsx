// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function OwnersPage() {
  return (
    <ResourcePage
      title="Owners"
      description="Manage plot ownership and contact notes with careful public/private defaults."
      primaryAction="Add owner"
    />
  );
}
