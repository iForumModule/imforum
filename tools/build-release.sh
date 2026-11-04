#!/bin/sh
# Builds dist/imforum-<version>.zip: the module folder `imforum` and the redirect stub `iforum`
set -e
cd "$(dirname "$0")/.."
version=$(php -r 'define("ICMS_ROOT_PATH", "x"); $modversion = []; $s = file_get_contents("src/icms_version.php"); preg_match("/\x27status_version\x27 => \x27([^\x27]+)\x27/", $s, $m); echo str_replace(" ", "-", $m[1]);')
work=$(mktemp -d)
mkdir -p "$work/modules" dist
cp -r src "$work/modules/imforum"
cp -r stub/iforum "$work/modules/iforum"
cp LICENSE README.md CHANGELOG.md "$work/modules/imforum/"
cp -r docs "$work/modules/imforum/docs"
out="$(pwd)/dist/imforum-$version.zip"
rm -f "$out"
(cd "$work" && zip -qr "$out" modules)
rm -rf "$work"
echo "$out"
