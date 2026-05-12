// SPDX-License-Identifier: AGPL-3.0-or-later
export const demoCemetery = {
  name: "Grace Rural Cemetery",
  description: "A small church cemetery maintained by volunteers.",
  publicSiteEnabled: true,
  defaultVisibility: "private"
};

export const demoPeople = [
  {
    id: "demo-john",
    legalName: "John Thomas Miller",
    birthDateText: "March 4, 1881",
    deathDateText: "June 12, 1946",
    visibility: "public",
    confidence: "confirmed"
  },
  {
    id: "demo-sarah",
    legalName: "Sarah Ann Miller",
    birthDateText: "1885",
    deathDateText: "1952",
    visibility: "public",
    confidence: "confirmed"
  },
  {
    id: "demo-mary",
    legalName: "Mary E. Holloway",
    birthDateText: "",
    deathDateText: "possibly 1918",
    visibility: "public",
    confidence: "probable"
  }
];

export const demoPlots = [
  { id: "demo-a01", identifier: "A-01", sectionCode: "A", row: "1", lot: "1", status: "occupied", visibility: "public", confidence: "confirmed" },
  { id: "demo-a02", identifier: "A-02", sectionCode: "A", row: "1", lot: "2", status: "occupied", visibility: "public", confidence: "confirmed" },
  { id: "demo-a03", identifier: "A-03", sectionCode: "A", row: "1", lot: "3", status: "needs_verification", visibility: "public", confidence: "conflicting" },
  { id: "demo-a04", identifier: "A-04", sectionCode: "A", row: "1", lot: "4", status: "reserved", visibility: "private", confidence: "confirmed" },
  { id: "demo-b01", identifier: "B-01", sectionCode: "B", row: "1", lot: "1", status: "available", visibility: "private", confidence: "confirmed" },
  { id: "demo-b02", identifier: "B-02", sectionCode: "B", row: "1", lot: "2", status: "sold", visibility: "private", confidence: "confirmed" },
  { id: "demo-b03", identifier: "B-03", sectionCode: "B", row: "1", lot: "3", status: "unusable", visibility: "private", confidence: "probable" }
];

export const demoVerificationItems = [
  {
    record: "Plot A-03",
    detail: "Conflicting owner notes",
    status: "Conflicting",
    tone: "bg-rose-50 text-rose-700"
  },
  {
    record: "Mary E. Holloway",
    detail: "Probable death date from marker photo",
    status: "Probable",
    tone: "bg-amber-50 text-amber-700"
  },
  {
    record: "Section B map",
    detail: "Uploaded sketch needs later georeferencing",
    status: "Unknown",
    tone: "bg-slate-100 text-slate-700"
  }
];
