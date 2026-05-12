// SPDX-License-Identifier: AGPL-3.0-or-later
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";

type ResourcePageProps = {
  title: string;
  description: string;
  primaryAction?: string;
  children?: React.ReactNode;
};

export function ResourcePage({
  title,
  description,
  primaryAction = "Add record",
  children
}: ResourcePageProps) {
  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{title}</h1>
          <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{description}</p>
        </div>
        <Button>{primaryAction}</Button>
      </div>
      <Card>
        <CardHeader>
          <CardTitle>Working table</CardTitle>
        </CardHeader>
        <CardContent>
          {children ?? (
            <div className="overflow-hidden rounded-md border">
              <div className="grid grid-cols-[1.2fr_0.8fr_0.8fr] bg-muted/55 px-4 py-2 text-xs font-medium uppercase text-muted-foreground">
                <span>Record</span>
                <span>Status</span>
                <span>Visibility</span>
              </div>
              {["Sample row", "Needs verification", "Public-ready record"].map((row, index) => (
                <div
                  key={row}
                  className="grid grid-cols-[1.2fr_0.8fr_0.8fr] border-t px-4 py-3 text-sm"
                >
                  <span className="font-medium">{row}</span>
                  <span className="text-muted-foreground">
                    {index === 1 ? "Probable" : "Confirmed"}
                  </span>
                  <span className="text-muted-foreground">
                    {index === 0 ? "Private" : "Public"}
                  </span>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
