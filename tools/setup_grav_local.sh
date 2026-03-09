#!/usr/bin/env bash
# tools/setup_grav_local.sh
# Collega grav-site/user/ all'installazione Grav locale in C:/laragon/www/valentina/
# Eseguire DOPO aver estratto Grav in C:/laragon/www/valentina/

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
REPO_USER="$REPO_DIR/grav-site/user"
GRAV_DIR="C:/laragon/www/valentina"

echo "=== Setup Grav locale ==="
echo "Repo:  $REPO_USER"
echo "Grav:  $GRAV_DIR"
echo ""

# Verifica Grav
if [ ! -f "$GRAV_DIR/index.php" ]; then
  echo "ERRORE: Grav non trovato in $GRAV_DIR"
  echo ""
  echo "Passi:"
  echo "  1. Scarica Grav + Admin da https://getgrav.org/"
  echo "  2. Estrai in C:/laragon/www/valentina/"
  echo "  3. Riesegui questo script"
  exit 1
fi

# Backup user/ originale (solo se non è già un junction)
if [ -d "$GRAV_DIR/user" ] && [ ! -L "$GRAV_DIR/user" ]; then
  echo "Backup user/ originale → user.bak/"
  mv "$GRAV_DIR/user" "$GRAV_DIR/user.bak"
  echo "✓ Backup completato"
fi

# Crea junction (Windows symlink per cartelle)
WIN_GRAV=$(cygpath -w "$GRAV_DIR/user" 2>/dev/null || echo "$GRAV_DIR/user" | sed 's|/|\\|g')
WIN_REPO=$(cygpath -w "$REPO_USER" 2>/dev/null || echo "$REPO_USER" | sed 's|/|\\|g')

if [ -L "$GRAV_DIR/user" ] || [ -d "$GRAV_DIR/user" ]; then
  echo "Junction già esistente, skip."
else
  cmd //c "mklink /J \"$WIN_GRAV\" \"$WIN_REPO\"" 2>/dev/null \
    || cmd /c "mklink /J \"$WIN_GRAV\" \"$WIN_REPO\""
fi

echo ""
echo "✅ Setup completato!"
echo ""
echo "Prossimi passi:"
echo "  1. Apri Laragon → Start All"
echo "  2. Visita http://valentina.test"
echo "  3. Prima visita: crea account admin Grav"
echo ""
echo "  Nota: aggiorna il path PHP in .claude/launch.json"
echo "  con la versione installata da Laragon."
