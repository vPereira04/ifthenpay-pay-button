#!/usr/bin/env bash
# Writes a .min file next to every stylesheet and script, which the plugin loads when SCRIPT_DEBUG/WP_DEBUG is off.
# Run it after editing any asset. `./build-assets.sh --check` changes nothing and fails if a .min file is missing
# or out of date, so it can run in CI. It also writes assets/build-manifest.json (a hash of every source), which
# the unit tests compare against, so an edit that skipped the build is caught without Node.
set -euo pipefail
cd "$(dirname "$0")"

ESBUILD=(npx --yes esbuild@0.28.2)

# Vendored libraries ship as they came, with their own licence headers.
sources() {
	find assets -type f \( -name '*.css' -o -name '*.js' \) ! -name '*.min.*' ! -path 'assets/js/vendor/*' | LC_ALL=C sort
}

# One esbuild run for all of them. Nothing is bundled: each file stays its own, so the load order PHP sets still holds.
build_into() {
	local out="$1" files
	mapfile -t files < <(sources)
	"${ESBUILD[@]}" "${files[@]}" --minify --legal-comments=none --outbase=assets --outdir="$out" --entry-names='[dir]/[name].min' --log-level=warning
}

write_manifest() {
	{
		echo '{'
		local first=1 f
		while IFS= read -r f; do
			[[ $first -eq 1 ]] || echo ','
			first=0
			printf '\t"%s": "%s"' "$f" "$(sha256sum "$f" | cut -d' ' -f1)"
		done < <(sources)
		printf '\n}\n'
	} > assets/build-manifest.json
}

if [[ "${1:-}" == "--check" ]]; then
	tmp="$(mktemp -d)"
	trap 'rm -rf "$tmp"' EXIT
	build_into "$tmp"
	stale=0
	while IFS= read -r f; do
		built="$tmp/${f#assets/}"
		built="${built%.*}.min.${f##*.}"
		committed="${f%.*}.min.${f##*.}"
		if [[ ! -f "$committed" ]]; then
			echo "missing: $committed"
			stale=1
		elif ! cmp -s "$built" "$committed"; then
			echo "out of date: $committed"
			stale=1
		fi
	done < <(sources)
	[[ $stale -eq 0 ]] && echo 'All .min files are up to date.'
	exit "$stale"
fi

build_into assets
write_manifest
echo "Built $(sources | wc -l) files."
