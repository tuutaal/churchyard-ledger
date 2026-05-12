// SPDX-License-Identifier: AGPL-3.0-or-later
import { ResourcePage } from "@/components/layout/resource-page";

export default function SectionsPage() {
  return (
    <ResourcePage
      title="Sections"
      description="Group plots by cemetery section, block, row, or other local naming conventions."
      primaryAction="Add section"
    />
  );
}
