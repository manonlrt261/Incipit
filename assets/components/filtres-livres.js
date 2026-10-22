// Partage la recherche, les filtres et les tris entre les listes de livres et de prêts.
export function creerFiltresLivres({
    page,
    conteneur,
    selecteur,
    recherche,
    ecouter,
    actualiser
}) {
    const formulaire = page.querySelector('[data-filtres-livres]');
    const genre = formulaire.querySelector('[data-filtre-genre]');
    const statut = formulaire.querySelector('[data-filtre-statut]');
    const contact = formulaire.querySelector('[data-tri-contact]');
    const champDate = formulaire.dataset.champDate || 'dateAjout';
    const auteurs = formulaire.querySelector('[data-tri-auteurs]');
    const date = formulaire.querySelector('[data-tri-date]');
    const note = formulaire.querySelector('[data-filtre-note]');

    // Une note absente reste distincte d’un zéro et se place après les livres notés.
    const noteCarte = carte => carte.dataset.note?.trim() ? Number(carte.dataset.note) : null;

    // Le dernier tri choisi est prioritaire ; les autres départagent les égalités sans être effacés.
    const priorites = ['note', 'contact', 'auteurs', 'date'];

    function prioriser(cle) {
        priorites.splice(priorites.indexOf(cle), 1);
        priorites.unshift(cle);
        appliquer();
    }

    const alphabet = new Intl.Collator('fr', { sensitivity: 'base', numeric: true });
    const normaliser = valeur => String(valeur ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('fr')
        .trim();

    // Combine les critères et relit les cartes pour inclure les ajouts sans rechargement.
    function appliquer() {
        const cartes = [...conteneur.querySelectorAll(selecteur)];
        const texte = normaliser(recherche?.value);
        const genreChoisi = normaliser(genre.value);
        const statutChoisi = normaliser(statut?.value);
        const tranche = note && /^[0-5]$/.test(note.value) ? Number(note.value) : null;
        let visibles = 0;

        cartes.forEach(carte => {
            const valeurNote = noteCarte(carte);

            carte.hidden = !normaliser(carte.dataset.recherche).includes(texte)
                || Boolean(genreChoisi && !(carte.dataset.genres || '').split('|').map(normaliser).includes(genreChoisi))
                || Boolean(statutChoisi && normaliser(carte.dataset.statutLecture) !== statutChoisi)
                || Boolean(tranche !== null && (valeurNote === null || Math.floor(valeurNote) !== tranche));

            if (!carte.hidden) {
                visibles++;
            }
        });

        // Départage les dates identiques par identifiant pour conserver un ordre stable.
        const recente = (a, b) =>
            (Number(b.dataset[champDate]) || 0) - (Number(a.dataset[champDate]) || 0)
            || Number(b.dataset.livreId || b.dataset.pretId || b.dataset.id)
                - Number(a.dataset.livreId || a.dataset.pretId || a.dataset.id);

        const comparerTexte = (a, b, sens) => {
            if (!a && b) {
                return 1;
            }

            if (a && !b) {
                return -1;
            }

            return alphabet.compare(a, b) * (sens.endsWith('desc') ? -1 : 1);
        };

        cartes.sort((a, b) => {
            for (const critere of priorites) {
                let comparaison = 0;

                if (critere === 'note' && note && ['asc', 'desc'].includes(note.value)) {
                    const noteA = noteCarte(a);
                    const noteB = noteCarte(b);

                    if (noteA === null && noteB !== null) {
                        return 1;
                    }

                    if (noteA !== null && noteB === null) {
                        return -1;
                    }

                    comparaison = noteA === null ? 0 : (noteA - noteB) * (note.value === 'desc' ? -1 : 1);
                } else if (critere === 'contact' && contact?.value) {
                    comparaison = comparerTexte(
                        (a.dataset.contactPrenom || '').trim(),
                        (b.dataset.contactPrenom || '').trim(),
                        contact.value
                    ) || comparerTexte(
                        (a.dataset.contactNom || '').trim(),
                        (b.dataset.contactNom || '').trim(),
                        contact.value
                    );
                } else if (critere === 'auteurs' && auteurs.value) {
                    const champ = auteurs.value.startsWith('nom-')
                        ? 'auteurNom'
                        : auteurs.value.startsWith('prenom-') ? 'auteurPrenom' : 'auteurs';
                    const autre = champ === 'auteurNom' ? 'auteurPrenom' : 'auteurNom';
                    comparaison = comparerTexte((a.dataset[champ] || '').trim(), (b.dataset[champ] || '').trim(), auteurs.value);

                    if (!comparaison && champ !== 'auteurs') {
                        comparaison = comparerTexte((a.dataset[autre] || '').trim(), (b.dataset[autre] || '').trim(), auteurs.value);
                    }
                } else if (critere === 'date' && date.value) {
                    comparaison = ((Number(a.dataset[champDate]) || 0) - (Number(b.dataset[champDate]) || 0))
                        * (date.value === 'asc' ? 1 : -1);
                }

                if (comparaison) {
                    return comparaison;
                }
            }

            return recente(a, b);
        });

        cartes.forEach(carte => conteneur.appendChild(carte));
        actualiser({ total: cartes.length, visibles, statut: statut?.value || '' });
    }

    // Applique tous les critères du formulaire sans recharger la liste.
    ecouter(formulaire, 'submit', event => {
        event.preventDefault();
        appliquer();
    });

    // Chaque choix conserve les autres filtres, notamment une tranche de note associée à la date d’ajout.
    ecouter(auteurs, 'change', () => prioriser('auteurs'));
    ecouter(date, 'change', () => prioriser('date'));
    ecouter(note, 'change', () => prioriser('note'));
    ecouter(contact, 'change', () => prioriser('contact'));

    // Efface la recherche, les filtres et les tris pour retrouver la liste complète.
    ecouter(formulaire.querySelector('[data-reinitialiser-filtres]'), 'click', () => {
        if (recherche) {
            recherche.value = '';
        }

        genre.value = '';

        if (statut) {
            statut.value = '';
        }

        if (contact) {
            contact.value = '';
        }

        if (note) {
            note.value = '';
        }

        auteurs.value = '';
        date.value = '';

        appliquer();
    });

    return {
        appliquer,

        // Synchronise les compteurs cliquables de Mes lectures avec le filtre de statut.
        choisirStatut(valeur) {
            if (statut) {
                statut.value = valeur;
            }

            appliquer();
        }
    };
}
