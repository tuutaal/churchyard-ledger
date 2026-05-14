# Changelog

All notable changes to Anesti should be recorded here.

This project follows a simple changelog format while the app is early in development. Use the `Unreleased` section for work that has not been tagged as a release yet.

## Unreleased

- Keep this section updated as features, fixes, schema changes, and deployment changes are added.

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
