// SPDX-License-Identifier: AGPL-3.0-or-later
import Link from "next/link";
import { Bell, LifeBuoy, Search } from "lucide-react";
import { navigationItems } from "@/lib/navigation";
import { Button } from "@/components/ui/button";

export function AppShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-[radial-gradient(circle_at_top_left,hsl(164_42%_25%_/_0.08),transparent_32rem),hsl(var(--background))]">
      <aside className="fixed inset-y-0 left-0 hidden w-72 border-r bg-white/92 shadow-sm backdrop-blur lg:block">
        <div className="border-b px-5 py-5">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-sm font-semibold text-primary-foreground">
              CL
            </div>
            <div>
              <p className="text-base font-semibold leading-5">Anesti</p>
              <p className="text-xs text-muted-foreground">Grace Rural Church</p>
            </div>
          </div>
        </div>
        <div className="border-b px-4 py-3">
          <div className="flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm text-muted-foreground">
            <Search className="h-4 w-4" aria-hidden="true" />
            Search records
          </div>
        </div>
        <nav className="grid gap-1 p-3">
          {navigationItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-foreground/82 transition-colors hover:bg-muted hover:text-foreground"
            >
              <item.icon className="h-4 w-4" aria-hidden="true" />
              {item.label}
            </Link>
          ))}
        </nav>
        <div className="absolute bottom-0 left-0 right-0 border-t bg-muted/45 p-4">
          <div className="rounded-lg border bg-white p-3">
            <div className="flex items-start gap-3">
              <LifeBuoy className="mt-0.5 h-4 w-4 text-primary" aria-hidden="true" />
              <div>
                <p className="text-sm font-medium">Volunteer-friendly setup</p>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                  Keep public pages simple and exports easy to find.
                </p>
              </div>
            </div>
          </div>
        </div>
      </aside>
      <div className="lg:pl-72">
        <header className="sticky top-0 z-10 border-b bg-background/88 px-4 py-3 backdrop-blur">
          <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-3">
            <div>
              <p className="text-sm font-semibold lg:hidden">Anesti</p>
              <p className="hidden text-sm text-muted-foreground lg:block">
                Cemetery records, maps, public pages, and verification work
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm">
                Export
              </Button>
              <Button size="icon" variant="ghost" aria-label="Notifications">
                <Bell className="h-4 w-4" aria-hidden="true" />
              </Button>
            </div>
          </div>
        </header>
        <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{children}</main>
      </div>
    </div>
  );
}
