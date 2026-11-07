#!/bin/sh
# Lance une copie jetable de l'appli pour les tests manuels (voir test/PROCEDURE.md).
# La vraie base data/regie.sqlite n'est jamais modifiée : elle est copiée (sqlite3 .backup) dans le bac à sable.
#   sh test/sandbox.sh [dossier] [port]     ex. sh test/sandbox.sh /tmp/regie-test 8765
set -e
ROOT=$(cd "$(dirname "$0")/.." && pwd)
DIR=${1:-${TMPDIR:-/tmp}/regie-test}
PORT=${2:-8765}
case "$DIR" in "$ROOT"|"$ROOT"/*) echo "Le bac à sable doit être hors du dépôt." >&2; exit 1;; esac
rm -rf "$DIR"; mkdir -p "$DIR"
rsync -a --exclude .git --exclude 'data/regie.sqlite*' --exclude test "$ROOT"/ "$DIR"/
if [ -f "$ROOT/data/regie.sqlite" ]; then
  if command -v sqlite3 >/dev/null; then sqlite3 "$ROOT/data/regie.sqlite" ".backup '$DIR/data/regie.sqlite'"
  else cp "$ROOT/data/regie.sqlite" "$DIR/data/regie.sqlite"; fi
fi
echo "Bac à sable : $DIR"
echo "Ouvrir      : http://localhost:$PORT/index.php"
exec php -S "localhost:$PORT" -t "$DIR"
