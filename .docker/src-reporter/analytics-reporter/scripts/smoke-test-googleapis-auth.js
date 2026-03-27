#!/usr/bin/env node
/**
 * Smoke test: verify googleapis.Auth.JWT is still accessible after a
 * googleapis major-version upgrade.
 *
 * Run with: node scripts/smoke-test-googleapis-auth.js
 *
 * Exit 0 = pass, exit 1 = fail.
 */

"use strict";

let passed = true;

function check(label, fn) {
  try {
    fn();
    console.log(`  ✔ ${label}`);
  } catch (err) {
    console.error(`  ✗ ${label}`);
    console.error(`    ${err.message}`);
    passed = false;
  }
}

console.log("\nSmoke-testing googleapis.Auth.JWT ...\n");

const googleapis = require("googleapis");

check("googleapis is an object", () => {
  if (typeof googleapis !== "object" || googleapis === null)
    throw new Error(`Expected object, got ${typeof googleapis}`);
});

check("googleapis.Auth exists", () => {
  if (!googleapis.Auth)
    throw new Error("googleapis.Auth is falsy");
});

check("googleapis.Auth.JWT is a function (constructor)", () => {
  if (typeof googleapis.Auth.JWT !== "function")
    throw new Error(`Expected function, got ${typeof googleapis.Auth.JWT}`);
});

check("JWT instance can be constructed", () => {
  const jwt = new googleapis.Auth.JWT(
    "test@example.com",
    null,
    "-----BEGIN RSA PRIVATE KEY-----\nMII...\n-----END RSA PRIVATE KEY-----",
    ["https://www.googleapis.com/auth/analytics.readonly"]
  );
  if (typeof jwt !== "object" || jwt === null)
    throw new Error("Constructor returned non-object");
});

check("jwt.authorize is a function", () => {
  const jwt = new googleapis.Auth.JWT();
  if (typeof jwt.authorize !== "function")
    throw new Error(`Expected function, got ${typeof jwt.authorize}`);
});

const pkg = require("../node_modules/googleapis/package.json");
const authPkg = require("../node_modules/google-auth-library/package.json");

console.log(`\ngoogleapis version:          ${pkg.version}`);
console.log(`google-auth-library version: ${authPkg.version}\n`);

if (passed) {
  console.log("All checks passed.\n");
  process.exit(0);
} else {
  console.error("One or more checks failed.\n");
  process.exit(1);
}
