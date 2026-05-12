# Beginner cPanel Deployment Guide

This guide assumes you can log in to cPanel and see:

- File Manager
- Git Version Control
- MySQL Databases or MySQL Database Wizard
- phpMyAdmin
- Terminal
- Application Manager
- Domains
- SSL/TLS Status

You do not need to touch WordPress for the first test.

## What We Are Deploying

Anesti is a Node.js app that uses MySQL.

That is a good fit for many shared hosts because MySQL is common in cPanel hosting. You still need Node.js support through cPanel Application Manager.

## Best First URL

Use a separate address for the app:

```text
records.example.org
```

Do not put the real app inside the WordPress folder for the first test.

After the app works, WordPress can simply link to it. No WordPress plugin is needed.

## Before You Start

Have these ready:

1. Your cPanel username and password.
2. Your domain name.
3. Your GitHub repo URL.
4. A place to write down database names, usernames, and passwords.

Use a strong database password and save it somewhere safe.

## Step 1: Create A MySQL Database

1. Log in to cPanel.
2. Find the Databases section.
3. Click MySQL Database Wizard.
4. Create a database.

Example name:

```text
churchyard
```

cPanel will probably add your account prefix. The real database name may look like:

```text
ACCOUNTNAME_churchyard
```

5. Create a database user.

Example user:

```text
ledgeruser
```

cPanel may turn it into:

```text
ACCOUNTNAME_ledgeruser
```

6. Generate a strong password.
7. On the privileges screen, choose All Privileges.
8. Finish the wizard.

Write down:

```text
Database name:
Database user:
Database password:
Database host: localhost
Database port: 3306
```

## Step 2: Create A Subdomain

1. Go back to the main cPanel page.
2. Find Domains.
3. Click Domains.
4. Click Create A New Domain.
5. Enter:

```text
records.example.org
```

6. If cPanel asks whether to share the document root with the main site, do not share it.
7. Use a separate folder, such as:

```text
records
```

8. Save.

If you do not want a subdomain yet, Application Manager may also let you use a path like:

```text
yourchurchdomain.org/churchyard-ledger
```

A subdomain is usually cleaner and avoids WordPress routing conflicts.

## Step 3: Get The Code Onto The Server

Option A is easiest if Git works in cPanel.

### Option A: Git Version Control

1. Open Git Version Control in cPanel.
2. Click Create.
3. Clone URL:

```text
https://github.com/YOUR_GITHUB_USERNAME/churchyard-ledger.git
```

4. Repository Path:

```text
churchyard-ledger
```

5. Repository Name:

```text
Anesti
```

6. Click Create.

### Option B: File Manager Upload

Use this only if Git does not work.

1. On your PC, create a zip file of the project.
2. Do not include `node_modules`.
3. Upload the zip to your home folder in cPanel File Manager.
4. Extract it into:

```text
churchyard-ledger
```

## Step 4: Set Environment Variables

There are two ways to set these values:

- A `.env` file in the app folder, which is easiest for running setup commands in Terminal.
- Environment variable fields in cPanel Application Manager, which are used when the website is running.

For now, create the `.env` file first. You will copy the same values into Application Manager in Step 9.

Your database URL uses this pattern:

```text
mysql://DATABASE_USER:DATABASE_PASSWORD@localhost:3306/DATABASE_NAME
```

If you create a database named `churchyard`, cPanel will probably add your account prefix. Your real database name may look like:

```text
ACCOUNTNAME_churchyard
```

Your database user will also have a prefix. It may look like:

```text
ACCOUNTNAME_ledgeruser
```

Use the exact database name and user shown by cPanel.

### Create The `.env` File

1. Open cPanel.
2. Open File Manager.
3. Go to your app folder. This is the Repository Path you used when cloning the project.

```text
/home/ACCOUNTNAME/APP_FOLDER/
```

4. Click New File.
5. Name the file:

```text
.env
```

6. If cPanel warns that files beginning with a dot are hidden, that is okay.
7. Right-click `.env`.
8. Click Edit.
9. Add this, changing every uppercase placeholder:

```text
DATABASE_URL=mysql://DATABASE_USER:DATABASE_PASSWORD@localhost:3306/DATABASE_NAME
NEXT_PUBLIC_APP_URL=https://APP_SUBDOMAIN.YOUR_DOMAIN.org
NODE_ENV=production
```

Example format only:

```text
DATABASE_URL=mysql://ACCOUNTNAME_ledgeruser:YOUR_DATABASE_PASSWORD@localhost:3306/ACCOUNTNAME_churchyard
NEXT_PUBLIC_APP_URL=https://records.example.org
NODE_ENV=production
```

10. Save the file.

Do not put real database passwords in GitHub. The `.env` file is already ignored by Git.

### You Will Also Use These In Application Manager Later

In Step 9, Application Manager will ask for environment variables. Add the same three values there:

```text
DATABASE_URL
NEXT_PUBLIC_APP_URL
NODE_ENV
```

## Step 5: Install Dependencies

1. Open Terminal in cPanel.
2. Go to the app folder:

```bash
cd ~/APP_FOLDER
```

3. Install packages:

```bash
npm install
```

If `npm` is not found, Node may still be installed but not added to the shell path.

Try these checks:

```bash
which node
node -v
which npm
npm -v
```

If those do not work, check common cPanel Node.js locations:

```bash
ls /opt/cpanel | grep node
ls /opt/cpanel/ea-nodejs*/bin/npm
```

If you see a path such as `/opt/cpanel/ea-nodejs20/bin/npm`, temporarily add that Node version to your path:

```bash
export PATH=/opt/cpanel/ea-nodejs20/bin:$PATH
node -v
npm -v
```

Then install packages:

```bash
npm install
```

If your server shows a different Node folder, such as `ea-nodejs18`, use that folder instead:

```bash
export PATH=/opt/cpanel/ea-nodejs18/bin:$PATH
```

If no Node folder exists, ask the host:

```text
Can Node.js and npm be enabled for my cPanel Terminal and Application Manager app?
```

## Step 6: Build The App

In Terminal:

```bash
npm run build
```

If the build fails because Node.js is too old, Application Manager may need a newer Node version. Next.js should use a modern Node version, ideally Node 20 or newer.

## Step 7: Create The Tables

In Terminal, run:

```bash
DATABASE_URL="mysql://DATABASE_USER:DATABASE_PASSWORD@localhost:3306/DATABASE_NAME" npm run db:push
```

Replace the database parts with your real cPanel values.

Example:

Use the same `DATABASE_URL` value from your `.env` file.

## Step 8: Add Sample Data

In Terminal:

```bash
DATABASE_URL="mysql://DATABASE_USER:DATABASE_PASSWORD@localhost:3306/DATABASE_NAME" npm run db:seed
```

This adds:

- Grace Rural Church
- Grace Rural Cemetery
- Sections A and B
- Sample plots
- Sample people
- Sample interments
- A few records needing verification

You can confirm tables and rows in phpMyAdmin.

## Step 9: Register The App In Application Manager

1. Go back to cPanel.
2. Open Application Manager.
3. Click Register Application.
4. Application Name:

```text
Anesti
```

5. Deployment Domain:

```text
records.example.org
```

6. Base Application URL:

```text
/
```

7. Application Path:

```text
churchyard-ledger
```

8. Deployment Environment:

```text
Production
```

9. Add environment variables:

```text
DATABASE_URL
NEXT_PUBLIC_APP_URL
NODE_ENV
```

10. Set `NODE_ENV` to:

```text
production
```

11. Save or Deploy.

12. If Application Manager asks for a startup file or command, use:

```text
npm run start
```

Some cPanel setups expect the app to listen on a port assigned by Passenger. If the app does not start, check the app logs in:

```text
churchyard-ledger/logs
```

## Step 10: Check SSL

1. Open SSL/TLS Status in cPanel.
2. Find:

```text
records.example.org
```

3. Make sure AutoSSL has issued a certificate.
4. If it has not, run AutoSSL or ask the host to enable it.

## Step 11: Test The Site

Open:

```text
https://records.example.org
```

Then check:

```text
https://records.example.org/dashboard
https://records.example.org/people
https://records.example.org/plots
https://records.example.org/search
https://records.example.org/tutorial
```

The dashboard should say it is showing database-backed demo data after `db:push` and `db:seed` have run successfully.

## Step 12: WordPress Later

Do not change WordPress until the app works.

Later, the easy WordPress step is:

1. Go to WordPress Admin.
2. Go to Appearance.
3. Go to Menus.
4. Add a Custom Link.
5. URL:

```text
https://records.example.org
```

6. Link text:

```text
Cemetery Records
```

7. Save.

That is enough for the first public connection.

## Troubleshooting

### I do not see Application Manager

Your host may not have enabled Node.js app support. MySQL alone is not enough.

Ask the host:

```text
Can my cPanel account run a Node.js app through Application Manager / Passenger?
```

### I can create MySQL databases but the app will not start

That usually means Node.js support is missing or the startup command is wrong.

Check:

- Application Manager logs
- Node.js version
- Environment variables
- Whether `npm run build` succeeded

### WordPress loads instead of the app

Use a subdomain like:

```text
records.example.org
```

Avoid deploying the app inside the WordPress `public_html` folder for the first test.

### Database connection fails

Check:

- Database name includes the cPanel prefix
- Database user includes the cPanel prefix
- Password is correct
- Host is usually `localhost`
- The database user has All Privileges

## AGPL Source Notice

Anesti is licensed under the GNU Affero General Public License v3.0 or later.

If you modify the app and let people use it over a network, the AGPL requires that users have access to the corresponding source code for that modified version. Keep the `LICENSE`, `NOTICE`, and source links available.
