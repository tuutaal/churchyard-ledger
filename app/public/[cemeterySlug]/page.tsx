// SPDX-License-Identifier: AGPL-3.0-or-later
import Link from "next/link";
import { Button } from "@/components/ui/button";

export default async function PublicCemeteryLandingPage({
  params
}: {
  params: Promise<{ cemeterySlug: string }>;
}) {
  const { cemeterySlug } = await params;

  return (
    <main className="mx-auto max-w-5xl px-4 py-10">
      <section className="space-y-4">
        <p className="text-sm font-medium text-primary">Public cemetery page</p>
        <h1 className="text-3xl font-semibold">{cemeterySlug}</h1>
        <p className="max-w-2xl text-muted-foreground">
          Public pages show only records marked public by the cemetery organization.
        </p>
        <div className="flex gap-2">
          <Button asChild>
            <Link href={`/public/${cemeterySlug}/search`}>Search records</Link>
          </Button>
          <Button asChild variant="outline">
            <Link href={`/public/${cemeterySlug}/map`}>View map</Link>
          </Button>
        </div>
      </section>
    </main>
  );
}
