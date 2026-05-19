#!/usr/bin/env node
"use strict";

let passed = true;

function printHint(lines) {
  for (const line of lines) {
    console.error(`    hint: ${line}`);
  }
}

function check(label, fn) {
  try {
    fn();
    console.log(`  pass ${label}`);
  } catch (err) {
    console.error(`  fail ${label}`);
    console.error(`    ${err.message}`);
    if (Array.isArray(err.hints) && err.hints.length > 0) {
      printHint(err.hints);
    }
    passed = false;
  }
}

console.log("\nSmoke-testing googleapis.Auth.JWT...\n");

const googleapis = require("googleapis");

check("googleapis.Auth.JWT exists", () => {
  if (typeof googleapis.Auth?.JWT !== "function") {
    const error = new Error("Expected googleapis.Auth.JWT to be a constructor");
    error.hints = [
      "Check whether the upgraded googleapis package still exposes JWT at googleapis.Auth.JWT.",
      "If the export moved, update the reporter auth code and this smoke test to use the new entry point.",
      "Review the googleapis and google-auth-library release notes for auth API changes.",
    ];
    throw error;
  }
});

check("JWT constructor preserves email, key, and scopes", () => {
  const scopes = ["https://www.googleapis.com/auth/analytics.readonly"];
  const jwt = new googleapis.Auth.JWT({
    email: "test@example.com",
    key: "test-key",
    scopes,
  });

  if (jwt.email !== "test@example.com") {
    const error = new Error(`Expected email to be set, got ${jwt.email}`);
    error.hints = [
      "Check whether googleapis.Auth.JWT now expects the object-style constructor.",
      "If the auth library changed constructor semantics, update query_authorizer.js to pass a single options object.",
      "Confirm that the constructed JWT instance still stores email, key, and scopes after the dependency upgrade.",
    ];
    throw error;
  }
  if (jwt.key !== "test-key") {
    const error = new Error(`Expected key to be set, got ${jwt.key}`);
    error.hints = [
      "Check whether googleapis.Auth.JWT now expects the object-style constructor.",
      "If key is no longer retained on the JWT instance, inspect the new auth-library initialization contract.",
      "Update the reporter auth code before deploying, or GA4 calls may fail with 'No key or keyFile set.'",
    ];
    throw error;
  }
  if (JSON.stringify(jwt.scopes) !== JSON.stringify(scopes)) {
    const error = new Error(
      `Expected scopes to be set, got ${JSON.stringify(jwt.scopes)}`,
    );
    error.hints = [
      "Check whether scopes must be passed under a different option name in the upgraded auth client.",
      "Review the Google auth client constructor signature and update query authorizers to match it.",
    ];
    throw error;
  }
});

const googleapisPkg = require("../node_modules/googleapis/package.json");
const authPkg = require("../node_modules/google-auth-library/package.json");

console.log(`\ngoogleapis version:          ${googleapisPkg.version}`);
console.log(`google-auth-library version: ${authPkg.version}\n`);

if (passed) {
  console.log("All checks passed.\n");
  process.exit(0);
}

console.error("One or more checks failed.\n");
process.exit(1);
