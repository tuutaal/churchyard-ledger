// SPDX-License-Identifier: AGPL-3.0-or-later
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

const steps = [
  "Create the first admin user",
  "Create the organization",
  "Create the first cemetery",
  "Choose public search and map defaults"
];

export default function SetupPage() {
  return (
    <main className="mx-auto grid min-h-screen w-full max-w-3xl content-center gap-6 px-4 py-10">
      <div>
        <h1 className="text-3xl font-semibold">Set up Anesti</h1>
        <p className="mt-2 text-muted-foreground">
          First run should be calm: one admin user, one organization, and one cemetery.
        </p>
      </div>
      <Card>
        <CardHeader>
          <CardTitle>Setup checklist</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3">
          {steps.map((step, index) => (
            <div key={step} className="flex items-center gap-3 rounded-md border p-3 text-sm">
              <span className="flex h-7 w-7 items-center justify-center rounded-full bg-muted font-medium">
                {index + 1}
              </span>
              {step}
            </div>
          ))}
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>Optional support</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4 text-sm text-muted-foreground">
          <p>
            Anesti is free and open-source. Donations can help support development,
            hosting docs, accessibility work, and import tools. This step is always optional and
            never unlocks or restricts features.
          </p>
          <div className="flex flex-wrap gap-2">
            <Button>Support development</Button>
            <Button variant="outline">Skip</Button>
          </div>
        </CardContent>
      </Card>
    </main>
  );
}
