#!/usr/bin/env bash
# Baut dist/clesk-termine.zip zum Hochladen unter Plugins > Plugin hinzufügen > Plugin hochladen.
set -euo pipefail

cd "$(dirname "$0")/.."

main=clesk-termine/clesk-termine.php
version_header=$(sed -n 's/^ \* Version: *//p' "$main")
version_const=$(sed -n "s/^define( 'CLESK_TERMINE_VERSION', '\(.*\)' );$/\1/p" "$main")
version_block=$(sed -n 's/^[[:space:]]*"version": "\(.*\)",$/\1/p' clesk-termine/blocks/termine/block.json)

if [ -z "$version_header" ] || [ "$version_header" != "$version_const" ] || [ "$version_header" != "$version_block" ]; then
	echo "Versionen passen nicht: Header '$version_header', Konstante '$version_const', block.json '$version_block'" >&2
	exit 1
fi

if command -v php >/dev/null 2>&1; then
	while IFS= read -r file; do
		php -l "$file" >/dev/null
	done < <(find clesk-termine -name '*.php')
fi

mkdir -p dist
rm -f dist/clesk-termine.zip
zip -rqX dist/clesk-termine.zip clesk-termine -x '*.DS_Store'
echo "dist/clesk-termine.zip gebaut (Version $version_header)"
