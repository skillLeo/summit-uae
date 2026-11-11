#!/usr/bin/env bash
# Faithful vectorisation of the Summit Legal Consultancy logo. Nothing is redrawn: every curve
# is potrace's fit of the gold coverage in the client master PNG.
#
#   package/brand/logo-04-gold-on-light.png  (gold lockup on transparent, 4167 x 4167 px)
#     -> resources/brand/emblem.svg     shield + sword + scales
#     -> resources/brand/wordmark.svg   "SUMMIT LEGAL CONSULTANCY"
#     -> resources/brand/lockup.svg     both, positioned exactly as in the master
#
# Each file is one <path>, fill="currentColor", with a tight viewBox (the exact curve bounds
# rounded outwards). The user unit is 1/6 of a master pixel, so all coordinates are integers.
#
# Requires ImageMagick 7 (magick), potrace 1.16 (brew install potrace), Node and the npm
# devDependencies (svgo, sharp). Usage: scripts/brand/trace.sh
# Afterwards scripts/brand/verify.mjs measures fidelity and writes renders to storage/app/qa/brand.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SRC="$ROOT/package/brand/logo-04-gold-on-light.png"
OUT="$ROOT/resources/brand"
QA="$ROOT/storage/app/qa/brand"
SVGO="$ROOT/node_modules/.bin/svgo"
SVGO_CONFIG="$ROOT/scripts/brand/svgo.config.mjs"

G=6        # SVG user units per master pixel
MARGIN=4   # empty master pixels kept around each part while tracing

# Per-part trace settings: scale blur alphamax opttolerance
#   emblem   traced from the 1-bit mask as is (large, mostly straight-edged shapes).
#   wordmark edge rebuilt at 3x from the anti-aliased coverage (bilinear + 1 px blur, then a 50 %
#            threshold) so the hairline serifs stay sharp while the bowls of S/C/G/O stay smooth.
EMBLEM_OPTS=(1 0 0.8 0.2)
WORDMARK_OPTS=(3 1 0.8 0.6)

for bin in magick potrace node od; do
  command -v "$bin" >/dev/null || { echo "trace.sh: '$bin' not found (potrace: brew install potrace)" >&2; exit 1; }
done
[ -x "$SVGO" ] || { echo "trace.sh: svgo missing; run npm ci" >&2; exit 1; }
[ -f "$SRC" ] || { echo "trace.sh: master not found: $SRC" >&2; exit 1; }

WORK="$(mktemp -d "${TMPDIR:-/tmp}/summit-trace.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$OUT" "$QA"
: > "$WORK/parts.tsv"

# 1. One-bit mask of the gold shape at full resolution: coverage (alpha) >= 50 % is gold.
magick "$SRC" -alpha extract -threshold 50% "$WORK/mask.png"
read -r LW LH LX LY <<<"$(magick "$WORK/mask.png" -format '%@' info: | tr 'x+' '  ')"

# 2. Split emblem | wordmark at the widest run of empty columns inside the lockup box.
read -r GAP0 GAP1 <<<"$(magick "$WORK/mask.png" -crop "${LW}x${LH}+${LX}+${LY}" +repage \
  -scale "${LW}x1!" -threshold 0 -depth 8 gray:- | od -An -v -tu1 | tr -s ' ' '\n' | awk '
  BEGIN { s = -1; best = -1; x = 0 }
  NF { if ($1 == 0) { if (s < 0) s = x; if (x - s > best) { best = x - s; b0 = s; b1 = x } } else s = -1; x++ }
  END { print b0, b1 }')"

box_of() { # COL0 COL1 -> "w h x y": gold pixels between absolute columns COL0 .. COL1-1
  local w h x y
  read -r w h x y <<<"$(magick "$WORK/mask.png" -crop "$(( $2 - $1 ))x${LH}+$1+${LY}" +repage \
    -format '%@' info: | tr 'x+' '  ')"
  echo "$w $h $(( x + $1 )) $(( y + LY ))"
}
read -r EW EH EX EY <<<"$(box_of "$LX" "$(( LX + GAP0 ))")"
read -r WW WH WX WY <<<"$(box_of "$(( LX + GAP1 + 1 ))" "$(( LX + LW ))")"
echo "lockup ${LW}x${LH}+${LX}+${LY}  emblem ${EW}x${EH}+${EX}+${EY}  wordmark ${WW}x${WH}+${WX}+${WY}"

# 3. Bitmap + potrace for one part. Records the exact curve box and the path origin (in units).
trace_part() { # NAME SCALE BLUR ALPHAMAX OPTTOLERANCE W H X Y
  local name=$1 k=$2 blur=$3 alphamax=$4 opttol=$5 w=$6 h=$7 x=$8 y=$9
  local rx=$(( x - MARGIN )) ry=$(( y - MARGIN )) rw=$(( w + 2 * MARGIN )) rh=$(( h + 2 * MARGIN ))
  local u=$(( G / k ))  # potrace quantum: 1/u bitmap pixel == 1/G master pixel
  if [ "$k" -eq 1 ]; then
    magick "$WORK/mask.png" -crop "${rw}x${rh}+${rx}+${ry}" +repage -negate -type bilevel "$WORK/$name.pbm"
  else
    magick "$SRC" -alpha extract -crop "${rw}x${rh}+${rx}+${ry}" +repage -filter Triangle \
      -resize "$(( k * 100 ))%" -blur "0x$blur" -threshold 50% -negate -type bilevel "$WORK/$name.pbm"
  fi
  potrace --backend svg --flat --tight --unit "$u" --turdsize 2 --alphamax "$alphamax" \
    --opttolerance "$opttol" --output "$WORK/$name.potrace.svg" "$WORK/$name.pbm"

  # Path data: absolute M + relative segments, origin = bitmap bottom-left, y up, 1/G px units.
  tr '\n' ' ' < "$WORK/$name.potrace.svg" | sed -E 's/.*<path d="([^"]*)".*/\1/' > "$WORK/$name.d"
  # --tight: viewBox = exact curve box (bitmap px); translate = minus its origin (y flipped).
  local vw vh tx ty
  read -r vw vh <<<"$(sed -nE 's/.*viewBox="0 0 ([0-9.]+) ([0-9.]+)".*/\1 \2/p' "$WORK/$name.potrace.svg")"
  read -r tx ty <<<"$(sed -nE 's/.*translate\((-?[0-9.]+),(-?[0-9.]+)\).*/\1 \2/p' "$WORK/$name.potrace.svg")"
  awk -v n="$name" -v vw="$vw" -v vh="$vh" -v tx="$tx" -v ty="$ty" -v rx="$rx" -v ry="$ry" \
      -v rh="$rh" -v k="$k" -v u="$u" -v G="$G" '
    function snap(v,  r) { r = sprintf("%.0f", v); return (v - r < 0.001 && r - v < 0.001) ? r + 0 : v }
    function fl(v) { v = snap(v); return v == int(v) ? v : (v < 0 ? int(v) - 1 : int(v)) }
    function ce(v) { v = snap(v); return v == int(v) ? v : (v < 0 ? int(v) : int(v) + 1) }
    BEGIN {
      left = -tx; top = rh * k - ty
      printf "%s %d %d %d %d %d %d\n", n, fl(rx * G + left * u), fl(ry * G + top * u),
        ce(rx * G + (left + vw) * u), ce(ry * G + (top + vh) * u), rx * G, (ry + rh) * G
    }' >> "$WORK/parts.tsv"
}
trace_part emblem   "${EMBLEM_OPTS[@]}"   "$EW" "$EH" "$EX" "$EY"
trace_part wordmark "${WORDMARK_OPTS[@]}" "$WW" "$WH" "$WX" "$WY"

# 4. Assemble: one <path> per part inside the union box, then SVGO applies the transforms and
#    merges the parts into a single path with integer coordinates.
write_svg() { # NAME PART...
  local name=$1; shift
  local x0 y0 x1 y1
  read -r x0 y0 x1 y1 <<<"$(awk -v want=" $* " 'index(want, " " $1 " ") {
      if (!n++) { a = $2; b = $3; c = $4; d = $5 }
      else { if ($2 < a) a = $2; if ($3 < b) b = $3; if ($4 > c) c = $4; if ($5 > d) d = $5 } }
    END { print a, b, c, d }' "$WORK/parts.tsv")"
  {
    printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" fill="currentColor">' \
      $(( x1 - x0 )) $(( y1 - y0 ))
    local part ox oy
    for part in "$@"; do
      read -r _ _ _ _ _ ox oy <<<"$(grep "^$part " "$WORK/parts.tsv")"
      printf '<path transform="translate(%d %d) scale(1 -1)" d="%s"/>' \
        $(( ox - x0 )) $(( oy - y0 )) "$(cat "$WORK/$part.d")"
    done
    printf '</svg>\n'
  } > "$WORK/$name.raw.svg"
  "$SVGO" --config "$SVGO_CONFIG" --quiet --input "$WORK/$name.raw.svg" --output "$OUT/$name.svg"
  printf '  "%s": { "x": %d, "y": %d, "w": %d, "h": %d }' "$name" "$x0" "$y0" $(( x1 - x0 )) $(( y1 - y0 )) \
    >> "$WORK/boxes.json"
  echo "$name.svg  viewBox 0 0 $(( x1 - x0 )) $(( y1 - y0 ))  $(wc -c < "$OUT/$name.svg" | tr -d ' ') bytes"
}
printf '{\n  "unitsPerMasterPx": %d,\n  "master": "package/brand/logo-04-gold-on-light.png",\n' "$G" > "$WORK/boxes.json"
printf '  "settings": { "emblem": "%s", "wordmark": "%s" },\n' "${EMBLEM_OPTS[*]}" "${WORDMARK_OPTS[*]}" >> "$WORK/boxes.json"
write_svg emblem emblem;            printf ',\n' >> "$WORK/boxes.json"
write_svg wordmark wordmark;        printf ',\n' >> "$WORK/boxes.json"
write_svg lockup emblem wordmark;   printf '\n}\n' >> "$WORK/boxes.json"
cp "$WORK/boxes.json" "$QA/trace-boxes.json"

# 5. Fidelity check + renders (storage/app/qa/brand).
node "$ROOT/scripts/brand/verify.mjs"
