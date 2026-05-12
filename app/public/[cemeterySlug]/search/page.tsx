// SPDX-License-Identifier: AGPL-3.0-or-later
export default function PublicSearchPage() {
  return (
    <main className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="text-2xl font-semibold">Public Search</h1>
      <div className="mt-6 rounded-md border bg-white p-4">
        <input
          className="h-10 w-full rounded-md border px-3 text-sm"
          placeholder="Search names, plots, and public notes"
        />
      </div>
    </main>
  );
}
