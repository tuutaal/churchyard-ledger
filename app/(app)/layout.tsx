// SPDX-License-Identifier: AGPL-3.0-or-later
import { AppShell } from "@/components/layout/app-shell";

export default function AuthenticatedLayout({ children }: { children: React.ReactNode }) {
  return <AppShell>{children}</AppShell>;
}
