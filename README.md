# Anesti

Anesti is a free and open-source cemetery records and mapping web app for small churches, rural cemeteries, and volunteer cemetery boards.

The goal is to help a church secretary, pastor, trustee, or cemetery volunteer keep accurate cemetery records without buying enterprise cemetery software. The project prioritizes careful records, public/private controls, exportable data, self-hosting, and public cemetery search and map pages that churches can embed on existing websites.

This project is intentionally not aimed at large enterprise cemeteries.

## Name

Anesti comes from the Easter greeting:

```text
Christos Anesti! Alethos Anesti!
Christ is risen! He is truly risen!
```

The name points to the Christian hope confessed in the resurrection of the body and the life of the world to come. Cemetery records are practical, local, and often humble work, but for churches they are also tied to memory, care, and hope.

## Product Principles

- Free and open-source
- Self-hostable
- Church-friendly and affordable
- Accurate records over flashy features
- Public/private record controls
- Exportable data with no vendor lock-in
- Public cemetery search and map pages
- GIS-friendly structure, starting simple

## Initial Stack

- PHP 8.1+
- MySQL
- PDO
- cPanel-compatible shared hosting
- No Composer dependency for the first release
- Leaflet or MapLibre for maps later

## Architecture Notes

One installation can manage one or more organizations. Each organization can manage one or more cemeteries.

The initial schema includes:

- Organizations and users with simple roles
- Cemeteries, sections, plots, people, interments, owners, and plot ownership
- Plot statuses including available, reserved, occupied, sold, unknown, unusable, and needs verification
- Confidence fields for confirmed, probable, conflicting, and unknown records
- Public/private visibility for records and attachments
- Media and documents attached to cemeteries, plots, people, interments, or owners
- GeoJSON-style JSON fields for plot and cemetery geometry
- Map layers for uploaded image maps now and GIS layers later
- Audit logs for important changes

## Local Development

The easiest local setup is Docker Desktop. It runs the same basic services Anesti needs on shared hosting:

- PHP with Apache
- MySQL

From the project folder:

```bash
docker compose up --build
```

Then open:

```text
http://localhost:8080/install
```

Use the prefilled installer values and install the sample data. After that, open:

```text
http://localhost:8080
```

The local MySQL database is exposed on port `3307` if you want to inspect it with a desktop database tool.

If you prefer XAMPP, MAMP, Laragon, or another local PHP/MySQL setup, create a MySQL database, copy `.env.example` to `.env`, update the database values, and open:

```text
/install
```

The installer creates the tables and sample data.

## Deployment

For beginner deployment steps, including HostGator/cPanel notes and a no-WordPress-required path, read:

[DEPLOYMENT.md](./DEPLOYMENT.md)

For the first real setup flow, the app should guide an administrator through:

1. Creating the first admin user
2. Creating the first organization
3. Creating the first cemetery
4. Reviewing an optional, skippable support/donation screen

The donation screen must never restrict features.

## Public And Embeddable Pages

Public cemetery pages are planned at:

- `/public/[cemeterySlug]`
- `/public/[cemeterySlug]/search`
- `/public/[cemeterySlug]/map`

Embeddable iframe pages are planned at:

- `/embed/[cemeterySlug]/search`
- `/embed/[cemeterySlug]/map`

These pages should show only records that the organization has marked public.

## Backup And Export Goals

Anesti should make it easy to leave, back up, or self-host:

- CSV export for core tables
- JSON export for full-fidelity data
- Media and document export bundles
- MySQL backup documentation
- No vendor lock-in

## Repository Setup

To work on Anesti locally, clone the repository:

```bash
git clone https://github.com/YOUR_GITHUB_USERNAME/churchyard-ledger.git
cd churchyard-ledger
```

For a first commit in a new repository:

```bash
git add .
git commit -m "Initial Anesti scaffold"
git branch -M main
git remote add origin https://github.com/YOUR_GITHUB_USERNAME/churchyard-ledger.git
git push -u origin main
```

If the repo was cloned first, skip `git remote add origin` because it already has the remote configured.

## Not In Scope Yet

- Billing or subscription logic
- AI features
- Advanced GIS
- Funeral home integrations
- Accounting
- Work orders
- WordPress plugin

## License

Anesti is licensed under the GNU Affero General Public License v3.0 or later.

See [LICENSE](./LICENSE) and [NOTICE](./NOTICE).
