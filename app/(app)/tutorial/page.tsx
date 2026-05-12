// SPDX-License-Identifier: AGPL-3.0-or-later
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

const steps = [
  {
    title: "Check the dashboard",
    body: "Start with the dashboard to see cemetery totals, public record counts, and records that need verification."
  },
  {
    title: "Review plots",
    body: "Open Plots to see section, row, lot, status, confidence, and visibility. A plot may have zero, one, or multiple interments."
  },
  {
    title: "Review people",
    body: "Open People to see names, partial dates, visibility, and confidence. This is where alternate names and uncertain dates belong."
  },
  {
    title: "Use search",
    body: "Search shows how public and private record results will be presented. Interactive filtering comes after deployment basics are stable."
  },
  {
    title: "Publish public pages later",
    body: "Public search and map pages should show only records marked public. WordPress can simply link to these pages; no plugin is needed."
  }
];

export default function TutorialPage() {
  return (
    <div className="space-y-6">
      <div>
        <p className="text-sm font-medium text-primary">First demo walkthrough</p>
        <h1 className="mt-2 text-2xl font-semibold">Tutorial</h1>
        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
          This walkthrough is for a church secretary, pastor, trustee, or volunteer opening the demo for the first time.
        </p>
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        {steps.map((step, index) => (
          <Card key={step.title}>
            <CardHeader>
              <CardTitle className="flex items-center gap-3 text-base">
                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-sm text-primary-foreground">
                  {index + 1}
                </span>
                {step.title}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm leading-6 text-muted-foreground">{step.body}</p>
            </CardContent>
          </Card>
        ))}
      </div>
      <Card>
        <CardHeader>
          <CardTitle>Deployment Goal</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm leading-6 text-muted-foreground">
            The first deployment goal is a real app with sample data on its own address, such as records.example.org.
            WordPress can stay untouched until you are ready to add a simple menu link.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
