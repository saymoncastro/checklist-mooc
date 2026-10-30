#!/usr/bin/env bash
# Builds the installable ZIP of local_mooc_audit from a committed revision of this repository and checks it.
#
#   tools/package-plugin.sh [ref] [outdir]      # defaults: HEAD, dist/
#
# The ZIP has a single root directory, mooc_audit/, as Moodle expects (Site administration > Plugins > Install
# plugins, or unzip into <moodle>/local/). Only committed files are packaged (git archive), so the same commit always
# gives the same ZIP. Laboratory-only scripts that create test courses are left out of the package.
set -euo pipefail

ref="${1:-HEAD}"
outdir="${2:-dist}"
src=plugin/local_mooc_audit
root=$(git rev-parse --show-toplevel)

# Files kept in the repository for development but never shipped.
excludes=(
    'cli/make_fixtures.php'
    'cli/make_import.php'
    'cli/make_scenarios.php'
)

versionphp=$(git -C "$root" show "$ref:$src/version.php")
release=$(sed -n "s/^\$plugin->release *= *'\([^']*\)'.*/\1/p" <<<"$versionphp")
build=$(sed -n 's/^\$plugin->version *= *\([0-9]*\).*/\1/p' <<<"$versionphp")
commit=$(git -C "$root" rev-parse --short=12 "$ref^{commit}")
committime=$(git -C "$root" log -1 --format=%cI "$ref")
[ -n "$release" ] && [ -n "$build" ] || { echo "version.php: release or version not found" >&2; exit 1; }

# --mtime (below) needs git 2.42 or later.
gitversion=$(git --version | sed 's/[^0-9]*\([0-9]*\)\.\([0-9]*\).*/\1 \2/')
read -r gitmajor gitminor <<<"$gitversion"
if [ "$gitmajor" -lt 2 ] || { [ "$gitmajor" -eq 2 ] && [ "$gitminor" -lt 42 ]; }; then
    echo "git 2.42 or later is required (found $(git --version))" >&2
    exit 1
fi

mkdir -p "$outdir"
zip="$(cd "$outdir" && pwd)/local_mooc_audit-$release-$build.zip"
pathspec=(.)
for e in "${excludes[@]}"; do pathspec+=(":(exclude)$e"); done
# A subtree has no commit time of its own; --mtime keeps the archive identical between builds (git 2.42 or later).
git -C "$root" archive --format=zip --prefix=mooc_audit/ --mtime="$committime" -o "$zip" "$ref:$src" -- "${pathspec[@]}"

# Checks on the produced package.
fail() { echo "FAIL: $*" >&2; exit 1; }
entries=$(unzip -Z1 "$zip")
grep -qv '^mooc_audit/' <<<"$entries" && fail "entries outside the mooc_audit/ root directory"
for f in version.php lib.php db/access.php lang/en/local_mooc_audit.php classes/privacy/provider.php; do
    grep -qx "mooc_audit/$f" <<<"$entries" || fail "missing $f"
done
grep -Eq '(^|/)(\.git|\.github|\.DS_Store|node_modules|vendor)(/|$)|\.(mbz|xlsx|zip)$' <<<"$entries" \
    && fail "development or institutional files in the package"
for e in "${excludes[@]}"; do grep -qx "mooc_audit/$e" <<<"$entries" && fail "excluded file packaged: $e"; done
unzip -p "$zip" mooc_audit/version.php | grep -q "^\$plugin->component *= *'local_mooc_audit';" \
    || fail "version.php does not declare local_mooc_audit"

sha=$(shasum -a 256 "$zip" | cut -d' ' -f1)
echo "$sha  $(basename "$zip")" > "$zip.sha256"
echo "Package:  $zip"
echo "Release:  $release (version $build), commit $commit"
echo "Files:    $(grep -cv '/$' <<<"$entries")"
echo "SHA-256:  $sha"
