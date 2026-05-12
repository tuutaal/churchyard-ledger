// SPDX-License-Identifier: AGPL-3.0-or-later
import { drizzle } from "drizzle-orm/mysql2";
import * as schema from "@/db/schema";

const connectionString = process.env.DATABASE_URL;

export function getDb() {
  if (!connectionString) {
    return null;
  }

  return drizzle(connectionString, { schema, mode: "default" });
}
