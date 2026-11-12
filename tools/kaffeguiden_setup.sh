#!/usr/bin/env bash
# kaffeguiden_setup.sh - saetter Kaffeguidens nye WordPress op: tema, plugins, indstillinger og alle udkast som kladder
#
# Brug:
#   bash tools/kaffeguiden_setup.sh --path ~/www/brygguiden.dk            # toerkoersel: viser hvad der sker, aendrer intet
#   bash tools/kaffeguiden_setup.sh --path ~/www/brygguiden.dk --apply    # goer det
# Valg:
#   --url <adresse>       sendes videre til wp-cli (multisite eller flere sites paa samme sti)
#   --cache litespeed     installerer LiteSpeed Cache (kun paa en LiteSpeed-server; standard: intet cache-plugin)
#   --app-user <bruger>   laver en applikationsadgangskode "Command Center" til brugeren og viser den EN gang
#   --skip-content        kun tema, plugins og indstillinger, ingen kladder
#   --force               koer ogsaa paa et site, der allerede har mere end 5 udgivne sider/indlaeg
#
# Kraever: wp-cli paa serveren, et installeret WordPress, python3 (til tools/kaffeguiden_md_html.py) og at
#          scriptet koeres fra steamgears-site-repoet (udkastene ligger i docs/library/kaffeguiden-*.md).
#
# Alt indhold oprettes som KLADDER og overskriver aldrig en side, der findes. Sitet saettes til "bed
# soegemaskiner om ikke at indeksere", indtil du selv slaar det til ved lanceringen.
# Sidst aendret: 12. november 2026
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LIB="$ROOT/docs/library"
CONV="$ROOT/tools/kaffeguiden_md_html.py"
WPPATH="" ; URL="" ; CACHE="" ; APP_USER="" ; APPLY=0 ; SKIP_CONTENT=0 ; FORCE=0

while [ $# -gt 0 ]; do
  case "$1" in
    --path) WPPATH="${2:-}"; shift 2 ;;
    --url) URL="${2:-}"; shift 2 ;;
    --cache) CACHE="${2:-}"; shift 2 ;;
    --app-user) APP_USER="${2:-}"; shift 2 ;;
    --apply) APPLY=1; shift ;;
    --skip-content) SKIP_CONTENT=1; shift ;;
    --force) FORCE=1; shift ;;
    -h|--help) awk 'NR>1 && /^#/ {sub(/^# ?/, ""); print; next} NR>1 {exit}' "$0"; exit 0 ;;
    *) echo "ukendt valg: $1 (se --help)" >&2; exit 2 ;;
  esac
done

[ -n "$WPPATH" ] || { echo "mangler --path til WordPress-mappen" >&2; exit 2; }
case "$CACHE" in ""|litespeed) ;; *) echo "--cache kan kun vaere litespeed" >&2; exit 2 ;; esac
command -v "${WP_CLI:-wp}" >/dev/null || { echo "wp-cli findes ikke (saet WP_CLI eller installer wp-cli)" >&2; exit 3; }
command -v python3 >/dev/null || { echo "python3 mangler" >&2; exit 3; }

WP=("${WP_CLI:-wp}" "--path=$WPPATH")
[ -n "$URL" ] && WP+=("--url=$URL")

# Laesning koerer altid; skrivning kun med --apply.
rd() { "${WP[@]}" "$@"; }
wr() {
  if [ "$APPLY" = 1 ]; then "${WP[@]}" "$@"; else printf '  [toer] wp'; printf ' %q' "$@"; echo; fi
}

rd core is-installed >/dev/null 2>&1 || { echo "WordPress er ikke installeret i $WPPATH" >&2; exit 4; }
published=$(rd post list --post_type=post,page --post_status=publish --format=count)
if [ "$published" -gt 5 ] && [ "$FORCE" != 1 ]; then
  echo "sitet har $published udgivne sider/indlaeg - det ligner ikke et nyt site. Brug --force, hvis det er rigtigt." >&2
  exit 5
fi
[ "$APPLY" = 1 ] && echo "== Kaffeguiden: opsaetning (aendrer sitet) ==" || echo "== Kaffeguiden: toerkoersel (intet aendres, tilfoej --apply) =="

echo "-- 1/5 sprog og indstillinger"
wr language core install da_DK --activate
wr option update blogname "Kaffeguiden"
wr option update blogdescription "Bedre kaffe derhjemme: udstyr, opskrifter og ærlige guides"
wr option update timezone_string "Europe/Copenhagen"
wr option update date_format "j. F Y"
wr option update time_format "H:i"
wr option update start_of_week 1
wr option update default_comment_status closed
wr option update default_ping_status closed
wr option update blog_public 0
wr rewrite structure "/%postname%/"
wr rewrite flush

echo "-- 2/5 tema"
if rd theme is-installed generatepress >/dev/null 2>&1; then echo "  generatepress findes"; else wr theme install generatepress; fi
wr theme activate generatepress

echo "-- 3/5 plugins"
PLUGINS=(seo-by-rank-math complianz-gdpr thirstyaffiliates updraftplus wordfence google-site-kit redirection)
[ "$CACHE" = litespeed ] && PLUGINS+=(litespeed-cache)
for p in "${PLUGINS[@]}"; do
  if rd plugin is-installed "$p" >/dev/null 2>&1; then
    rd plugin is-active "$p" >/dev/null 2>&1 && echo "  $p er aktiv" || wr plugin activate "$p"
  else
    wr plugin install "$p" --activate
  fi
done
for p in hello akismet; do
  if rd plugin is-installed "$p" >/dev/null 2>&1; then wr plugin delete "$p"; fi
done
wr language plugin install --all da_DK
wr language theme install --all da_DK

echo "-- 4/5 standardindhold"
for slug in hello-world sample-page eksempel-side hej-verden; do
  for id in $(rd post list --post_type=post,page --name="$slug" --post_status=any --field=ID); do
    wr post delete "$id" --force
  done
done

page_id() {  # slug [parent-id] -> id eller tom
  local args=(post list --post_type=page --name="$1" --post_status=any --field=ID)
  [ -n "${2:-}" ] && args+=(--post_parent="$2")
  rd "${args[@]}" | head -n1
}

make_page() {  # md-fil [parent-id]
  local file="$1" parent="${2:-}" meta title slug mt md fk id tmp
  meta=$(python3 "$CONV" "$file" --meta)
  title=$(printf '%s' "$meta" | python3 -c 'import json,sys; print(json.load(sys.stdin)["title"])')
  slug=$(printf '%s' "$meta" | python3 -c 'import json,sys; print(json.load(sys.stdin)["slug"])')
  mt=$(printf '%s' "$meta" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("meta_title",""))')
  md=$(printf '%s' "$meta" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("meta_description",""))')
  fk=$(printf '%s' "$meta" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("focus_keyword",""))')
  id=$(page_id "$slug" "$parent")
  if [ -n "$id" ]; then echo "  findes: /$slug/ (id $id) - roeres ikke"; return; fi
  tmp=$(mktemp)
  python3 "$CONV" "$file" --html > "$tmp"
  local args=(post create "$tmp" --post_type=page --post_status=draft --post_title="$title" --post_name="$slug" --porcelain)
  [ -n "$parent" ] && args+=(--post_parent="$parent")
  if [ "$APPLY" = 1 ]; then
    id=$("${WP[@]}" "${args[@]}")
    [ -n "$mt" ] && "${WP[@]}" post meta update "$id" rank_math_title "$mt" >/dev/null
    [ -n "$md" ] && "${WP[@]}" post meta update "$id" rank_math_description "$md" >/dev/null
    [ -n "$fk" ] && "${WP[@]}" post meta update "$id" rank_math_focus_keyword "$fk" >/dev/null
    echo "  kladde: /$slug/ (id $id)"
  else
    echo "  [toer] kladde: /$slug/ \"$title\" ($(wc -c < "$tmp") bytes HTML, meta: ${mt:+titel }${md:+beskrivelse }${fk:+fokusord})"
  fi
  rm -f "$tmp"
}

echo "-- 5/5 kladder"
if [ "$SKIP_CONTENT" = 1 ]; then
  echo "  springes over (--skip-content)"
else
  shopt -s nullglob
  guides_id=$(page_id guides)
  if [ -z "$guides_id" ]; then
    tmp=$(mktemp)
    printf '%s\n' '<p>Opskrifter og vejledninger til bedre kaffe derhjemme: fra cold brew og AeroPress til espresso og rengøring.</p>' > "$tmp"
    if [ "$APPLY" = 1 ]; then
      guides_id=$("${WP[@]}" post create "$tmp" --post_type=page --post_status=draft --post_title="Guides" --post_name=guides --porcelain)
      echo "  kladde: /guides/ (id $guides_id)"
    else
      guides_id="<guides-id>"; echo "  [toer] kladde: /guides/ \"Guides\""
    fi
    rm -f "$tmp"
  fi
  for f in "$LIB"/kaffeguiden-artikel-*.md "$LIB"/kaffeguiden-side-*.md; do
    parent=$(python3 "$CONV" "$f" --meta | python3 -c 'import json,sys; print(json.load(sys.stdin)["parent"])')
    if [ "$parent" = guides ]; then
      if [ "$guides_id" = "<guides-id>" ]; then make_page "$f" ""; else make_page "$f" "$guides_id"; fi
    else
      make_page "$f" ""
    fi
  done
fi

if [ -n "$APP_USER" ]; then
  echo "-- applikationsadgangskode til Command Center"
  if rd user application-password list "$APP_USER" --field=name 2>/dev/null | grep -qx "Command Center"; then
    echo "  \"$APP_USER\" har allerede en adgangskode ved navn Command Center - laves ikke igen"
  elif [ "$APPLY" = 1 ]; then
    pw=$("${WP[@]}" user application-password create "$APP_USER" "Command Center" --porcelain)
    echo "  Indsaet i Command Center -> projekt Kaffeguiden -> Moduler -> Forbindelser (vises kun denne ene gang):"
    echo "    bruger: $APP_USER"
    echo "    adgangskode: $pw"
  else
    echo "  [toer] wp user application-password create $APP_USER \"Command Center\""
  fi
fi

echo "== faerdig. Naeste skridt: se docs/library/kaffeguiden-opsaetning-2026-11-12.md §3 =="
