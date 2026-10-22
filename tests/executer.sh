#!/usr/bin/env bash
# Lance chaque suite dans un processus distinct et signale tout échec.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

if [[ "${1:-}" != "--local" ]]; then
    echo "Usage : bash tests/executer.sh --local [--mongodb]"
    echo "Utiliser uniquement une base locale de développement, jamais une base de production."
    exit 2
fi

resultat=0

lancer() {
    echo "Suite : $1"
    shift
    if "$@"; then
        echo "Résultat : SUCCÈS"
    else
        echo "Résultat : ÉCHEC"
        resultat=1
    fi
}

lancer "Règles de lecture et saisies invalides" php tests/verifier-donnees-lecture.php
lancer "Filtres combinables" node tests/verifier-filtres.cjs
lancer "Accès privés" php tests/verifier-acces-prives.php --local
lancer "Confidentialité visiteurs" php tests/verifier-confidentialite-visiteurs.php --local
lancer "Recherche tableau de bord" php tests/verifier-recherche-dashboard.php --local
lancer "Restauration collection" php tests/verifier-restauration-collection.php --local

if [[ "${2:-}" == "--mongodb" ]]; then
    lancer "Journal et panne MongoDB" php tests/verifier-journal.php --local
    lancer "Archive MongoDB" php tests/verifier-archive-journal.php
else
    echo "NON EXÉCUTÉ : journal et archive MongoDB (ajouter --mongodb après préparation des instances locales)."
fi

exit "$resultat"
