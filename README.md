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

- Next.js
- TypeScript
- Tailwind CSS
- shadcn/ui conventions
- MySQL
- Drizzle ORM
- Docker Compose
- Leaflet or MapLibre for maps

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

Copy the environment file:

```bash
cp .env.example .env
```

Install dependencies:

```bash
npm install
```

Start MySQL:

```bash
docker compose up db
```

Create the database schema and seed sample data:

```bash
npm run db:push
npm run db:seed
```

Start the app:

```bash
npm run dev
```

Open `http://localhost:3000`.

## Docker Compose Development

You can also start the app and database together:

```bash
docker compose up
```

The app runs on `http://localhost:3000`, and MySQL is exposed on local port `3306`.

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

To work on Anesti locally, clone the repository and install dependencies:

```bash
git clone https://github.com/YOUR_GITHUB_USERNAME/churchyard-ledger.git
cd churchyard-ledger
npm install
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
