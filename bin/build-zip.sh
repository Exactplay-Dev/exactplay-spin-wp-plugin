#!/usr/bin/env bash
# Builds dist/exactplay-spin.zip, ready to upload to a site or copy into SVN trunk.
set -euo pipefail

cd "$(dirname "$0")/.."
slug="exactplay-spin"
version=$(sed -n 's/^ \* Version: *//p' "$slug.php")
stable=$(sed -n 's/^Stable tag: *//p' readme.txt)

if [ "$version" != "$stable" ]; then
	echo "Version mismatch: $slug.php says $version, readme.txt Stable tag says $stable" >&2
	exit 1
fi

rm -rf dist
mkdir -p "dist/$slug"
rsync -a --exclude-from=.distignore ./ "dist/$slug/"
(cd dist && zip -qr "$slug.zip" "$slug")

echo "Built dist/$slug.zip (version $version)"
