#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build

ARTIFACT="build/chubes-docs.tar.gz"

# Build a deployable artifact (no top-level folder) for Homeboy deploy.
# We ship vendor/ as committed; no composer step here.

# Exclude common dev junk and build output.
tar \
  --exclude='./.git' \
  --exclude='./build' \
  --exclude='./node_modules' \
  --exclude='./tests' \
  --exclude='./.github' \
  --exclude='./docs' \
  --exclude='./CLAUDE.md' \
  --exclude='./.DS_Store' \
  -czf "$ARTIFACT" \
  .

echo "$ARTIFACT"
