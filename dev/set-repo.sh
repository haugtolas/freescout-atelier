#!/bin/sh
# Replace the OWNER placeholder with your GitHub user (or organisation) name.
# Usage:  sh dev/set-repo.sh your-github-name [repo-name]
set -e
[ -n "$1" ] || { echo "Usage: sh dev/set-repo.sh your-github-name [repo-name]"; exit 1; }
OWNER="$1"; REPO="${2:-freescout-atelier}"
cd "$(dirname "$0")/.."
for f in Atelier/module.json README.md Atelier/README.md CHANGELOG.md; do
  [ -f "$f" ] && sed -i.bak "s#OWNER/freescout-atelier#$OWNER/$REPO#g; s#github.com/OWNER#github.com/$OWNER#g; s#/OWNER/#/$OWNER/#g" "$f" && rm -f "$f.bak"
done
echo "Done. module.json now points to github.com/$OWNER/$REPO"
