# Local Development With Docker

This is the recommended way to test Anesti locally before deploying to cPanel shared hosting.

## Requirements

- Docker Desktop
- Git

## Start The Local App

Open a terminal in the Anesti project folder and run:

```bash
docker compose up --build
```

Then open:

```text
http://localhost:8080/install
```

The Docker setup provides these installer values automatically:

```text
Database host: db
Database port: 3306
Database name: anesti
Database user: anesti
Database password: anesti
App URL: http://localhost:8080
```

Click the install button once to create the tables and sample data.

After installation, open:

```text
http://localhost:8080
```

## What To Test Before Deploying

- Add and edit a person.
- Add and edit a plot.
- Add an interment connecting a person to a plot.
- Add a second interment to the same plot for a double burial or cremains.
- Upload a grave photo on an interment.
- Check the public page at `/public`.
- Download CSV files from `/exports`.
- Import a small people, plots, or interments CSV from `/imports`.

## Database Access

The MySQL container is exposed to your computer on port `3307`.

Use these values in a desktop database tool:

```text
Host: localhost
Port: 3307
Database: anesti
User: anesti
Password: anesti
```

## Stop The Local App

Press `Ctrl+C` in the terminal running Docker, then run:

```bash
docker compose down
```

## Reset The Local Database

This deletes the local Docker database and starts fresh:

```bash
docker compose down -v
docker compose up --build
```

Then visit `/install` again.
