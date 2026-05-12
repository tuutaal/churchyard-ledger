// SPDX-License-Identifier: AGPL-3.0-or-later
import path from "node:path";

/** @type {import('next').NextConfig} */
const nextConfig = {
  outputFileTracingRoot: path.resolve(".")
};

export default nextConfig;
