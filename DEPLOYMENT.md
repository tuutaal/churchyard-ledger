# Beginner cPanel Deployment Guide

This guide deploys Anesti on ordinary cPanel shared hosting with PHP and MySQL.

You do not need Node.js. You do not need npm. You do not need to change WordPress for the first test.

## What You Need

In cPanel, look for:

- Domains
- File Manager
- Git Version Control
- MySQL Databases or MySQL Database Wizard
- phpMyAdmin

PHP 8.1 or newer is recommended.

## Step 1: Create A MySQL Database

1. Log in to cPanel.
2. Open MySQL Database Wizard.
3. Create a database.

Example database name:

```text
churchyard
```

cPanel will probably add your account prefix, so the real name may look like:

```text
ACCOUNTNAME_churchyard
```

4. Create a database user.
5. Generate a strong password.
6. Give the user All Privileges.
7. Save these values:

```text
Database host: localhost
Database port: 3306
Database name: ACCOUNTNAME_churchyard
Database user: ACCOUNTNAME_ledgeruser
Database password: the password you created
```

## Step 2: Create A Subdomain

1. Open Domains in cPanel.
2. Create a new domain or subdomain.

Example:

```text
records.example.org
```

3. Give it its own document root.

Example folder:

```text
records
```

Avoid putting Anesti inside your existing WordPress folder for the first test.

## Step 3: Put The Code In The Subdomain Folder

Use Git Version Control if available.

1. Open Git Version Control.
2. Choose Clone a Repository.
3. Clone URL:

```text
https://github.com/YOUR_GITHUB_USERNAME/churchyard-ledger.git
```

4. Repository Path:

```text
records
```

Use the folder that your subdomain points to.

If Git is not available, upload a zip file through File Manager and extract it into the same folder.

## Step 4: Create The `.env` File

1. Open File Manager.
2. Open the app folder.
3. Create a new file named:

```text
.env
```

4. Edit `.env`.
5. Add these values, replacing the placeholders:

```text
ANESTI_DB_HOST=localhost
ANESTI_DB_PORT=3306
ANESTI_DB_NAME=DATABASE_NAME
ANESTI_DB_USER=DATABASE_USER
ANESTI_DB_PASSWORD=DATABASE_PASSWORD
ANESTI_APP_URL=https://records.example.org
ANESTI_ENV=production
```

Do not commit real passwords to GitHub.

## Step 5: Run The Installer

Open this URL in your browser:

```text
https://records.example.org/install
```

Fill in the same database details:

```text
Database host: localhost
Database port: 3306
Database name: your full cPanel database name
Database user: your full cPanel database user
Database password: your database password
App URL: your subdomain URL
```

Click Install sample data.

The installer will:

- write or update `.env`
- create the database tables
- add sample cemetery data

## Step 6: Test The App

Open:

```text
https://records.example.org
```

Then check:

```text
https://records.example.org/people
https://records.example.org/plots
https://records.example.org/search
https://records.example.org/map
https://records.example.org/tutorial
```

The app should show Grace Rural Cemetery sample data.

## Step 7: Check In phpMyAdmin

1. Open phpMyAdmin.
2. Select the Anesti database.
3. Confirm that tables exist, including:

```text
organizations
cemeteries
sections
plots
people
interments
owners
media
documents
map_layers
audit_logs
```

## Step 8: WordPress Later

Do not change WordPress until the app works.

Later, add a normal WordPress menu link:

```text
https://records.example.org
```

Suggested link text:

```text
Cemetery Records
```

No WordPress plugin is needed for the first version.

## Troubleshooting

### The page is blank

Check the PHP error log in cPanel. Also confirm PHP is enabled for the subdomain.

### The installer cannot connect

Check:

- database name includes the cPanel account prefix
- database user includes the cPanel account prefix
- password is correct
- database host is usually `localhost`
- the database user has All Privileges

### The app downloads PHP files instead of running them

The domain is not configured to run PHP correctly. Contact the host.

### Pretty URLs do not work

Confirm `.htaccess` exists in the app folder. If needed, open:

```text
https://records.example.org/index.php/people
```

## AGPL Source Notice

Anesti is licensed under the GNU Affero General Public License v3.0 or later.

If you modify the app and let people use it over a network, the AGPL requires that users have access to the corresponding source code for that modified version. Keep the `LICENSE`, `NOTICE`, and source links available.
