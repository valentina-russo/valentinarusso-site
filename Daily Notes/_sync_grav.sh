#!/usr/bin/env bash
# Allinea il Grav di prova locale al tema e alle pagine del repo, e svuota la sua cache.
S="C:/Users/marco/AppData/Local/Temp/claude/D--valentinarussomentaladvisor-it/c3a65fc5-b6ab-4ce2-bbe0-bdc47273c5ad/scratchpad/gravtest/grav"
R="D:/valentinarussomentaladvisor.it/grav-site/user"
cp -r "$R/themes/valentina/." "$S/user/themes/valentina/"
cp -r "$R/pages/." "$S/user/pages/"
py -c "
import shutil, os
for d in ('cache','tmp'):
    p = os.path.join(r'$S', d)
    if os.path.isdir(p):
        for n in os.listdir(p):
            q = os.path.join(p, n)
            shutil.rmtree(q, ignore_errors=True) if os.path.isdir(q) else os.remove(q)
"
echo "tema e pagine allineati, cache di prova svuotata"
