// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function IntermentsPage() {
  return (
    <ResourcePage
      title="Interments"
      description="Connect people to plots, allowing zero, one, or multiple interments per plot."
      primaryAction="Add interment"
    />
  );
}
