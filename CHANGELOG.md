# Changelog

All notable changes to Anesti should be recorded here.

This project follows a simple changelog format while the app is early in development. Use the `Unreleased` section for work that has not been tagged as a release yet.

## Unreleased

- Keep this section updated as features, fixes, schema changes, and deployment changes are added.

### Added

- Added `scripts/import_public_directory.php`, a re-runnable CLI importer that pulls the public cemetery directory (5 WordPress pages) into people/plots/interments/media. Normalizes both Location formats (`Sec/Row/Blk` and `Sec/Lot`), treats "No Marker"/blank as an unlinked interment, groups multiple people sharing a location into one plot, and downloads headstone photos into `uploads/grave-photos/` using the existing convention. Idempotent: re-running matches existing records via a local state file (`scripts/state/`, gitignored) and updates them in place instead of duplicating, while preserving admin-edited fields (visibility, confidence, notes, status, disposition). Rows that fail to parse cleanly are written to a report instead of guessed. Added `scripts/.htaccess` to deny web access to the script and its state directory.
- Added a "Block" custom plot field, auto-created by the importer, to carry a hand-drawn map's block number alongside the existing row/lot fields.
- Added per-interment map pinning: an interment can carry a `map_point` (x/y in the map's coordinate space) and is drawn as a clickable grave marker on both the public `/map` and the admin boundary editor, so individual graves can be located within a plot's boundary. Added `Repository::saveIntermentMapPoint()` to set a point and `Repository::mapMarkers()` to fetch them for rendering. (Install-specific map data — the traced grid, transcribed names, and generated SVGs for a particular cemetery — is kept out of the app tree in a gitignored working area, not committed here.)

### Changed

- `interments.plot_id` is now nullable (guarded migration), so a person with no physical marker can still have an interment record for their notes/photo without a plot link. `Repository::saveInterment()`/`importIntermentRow()` and the interment edit form no longer require a plot. `publicInterments()` now left-joins plots so these records can still appear in public search.
- Added `Repository::upsertPlot()`, `upsertPerson()`, `upsertInterment()`, and `upsertSection()` as find-or-create helpers that preserve admin-curated fields on update, for use by CLI importers.
- Added `Repository::attachPhotoFromPath()`, refactored out of the existing upload handler, so both the web upload form and CLI importers store grave photos the same way.
- Section labels on the plot map now position from each section's actual rendered geometry instead of a fixed per-section grid slot, so sections stay correctly labeled when image-aligned plot geometry is mixed with auto-laid-out plots.
- The `/map` viewer now renders plots whose geometry is in a shared world coordinate system (`{"space":"world_feet","points":[...]}`) as one unified, real-world-proportioned map (fitted to the view, north up), with each section shown as a toggleable layer (frames, labels, and per-area checkboxes) and per-plot labels revealed only when zoomed in. This is the coordinate substrate for later GPS/drone-surveyed geometry, which can replace the feet coordinates without changing records. Installs without world geometry keep the previous per-section auto-layout.
- Fixed the admin map-boundary editor and the internal `/map` viewer to render the uploaded background image with `preserveAspectRatio="xMidYMid meet"` instead of `slice`, so the full image is shown without cropping — needed for pixel-accurate tracing/grid generation against a scanned map.

### Schema

- Added `interments.map_point` (nullable JSON) for per-interment marker coordinates.

## 2026-05-13

### Added

- Added a Docker-based local development stack with PHP 8.3, Apache, and MySQL.
- Added local development documentation in `LOCAL_DEVELOPMENT.md`.
- Added public interment search on `/public`.
- Added internal admin/editor search on `/search`.
- Added CSV imports for people, plots, and interments on `/imports`.
- Added custom fields for people, plots, and interments, including import-aware custom columns.
- Added a functional plot map on `/map` grouped by section with clickable, status-colored plot tiles.

### Changed

- Public search now shows only public interment records.
- Public search no longer exposes casket/cremains disposition type.
- Public search no longer lists or searches public plots separately.
- Removed old Next.js, Node.js, TypeScript, and Drizzle files from the active repository tree after archiving them locally.
- Reworked the plot map into a GIS-style pan-and-zoom viewer with plot boundaries, search, filters, and a selected-plot details panel.
- Added an Admin settings hub with map setup, user setup, and a plot-boundary editor for tracing plots over an uploaded cemetery map image.
- Improved map setup so admins can import a map background, redraw existing plot geography, or create a new blank plot from a drawn polygon.
- Added cemetery GPS coordinate fields, a USGS NAIP image URL helper, and zoom/pan controls for the admin boundary editor.
- Simplified aerial photo setup so admins can find a USGS image from a cemetery street address, with GPS and image URL options tucked under advanced controls.

## 2026-05-12

### Added

- Added the initial PHP/MySQL shared-hosting app for cPanel hosts.
- Added HostGator/cPanel deployment documentation.
- Added installer flow for entering database settings and seeding sample data.
- Added sample cemetery data for a small church cemetery.
- Added people add/edit screens.
- Added plot add/edit screens.
- Added interment add/edit screens connecting people to plots.
- Added support for multiple interments per plot.
- Added casket/cremains/other/unknown disposition tracking for interments.
- Added grave photo URL support.
- Added uploaded grave photo support under `uploads/grave-photos/`.
- Added public-only cemetery page.
- Added CSV exports for people, plots, interments, and media.
- Added AGPL notice and licensing files.

### Changed

- Renamed the forward-facing product to Anesti.
- Switched deployment direction from Next.js/Node to PHP/MySQL for shared-hosting compatibility.
- Updated installer behavior to redirect after install and hide setup once the database is installed.

### Security

- Blocked direct web access to `.env`.
- Added upload-directory protection to deny PHP execution in `uploads/`.
