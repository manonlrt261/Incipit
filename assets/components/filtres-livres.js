// Partage la recherche, les filtres et les tris sans recréer les cartes ni cumuler les écouteurs.
export function creerFiltresLivres({ page, conteneur, selecteur, recherche, ecouter, actualiser }) {
    const formulaire = page.querySelector('[data-filtres-livres]');
    const genre = formulaire.querySelector('[data-filtre-genre]');
    const statut = formulaire.querySelector('[data-filtre-statut]');
    const contact = formulaire.querySelector('[data-tri-contact]');
    const champDate = formulaire.dataset.champDate || 'dateAjout';
    const auteurs = formulaire.querySelector('[data-tri-auteurs]');
    const date = formulaire.querySelector('[data-tri-date]');
    const alphabet = new Intl.Collator('fr', { sensitivity: 'base', numeric: true });
    const normaliser = valeur => String(valeur ?? '').normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').trim();

    // Applique les critères combinés et conserve les auteurs absents en fin de classement.
    function appliquer() {
        const cartes = [...conteneur.querySelectorAll(selecteur)];
        const texte = normaliser(recherche?.value);
        const genreChoisi = normaliser(genre.value);
        const statutChoisi = normaliser(statut?.value);
        let visibles = 0;
        cartes.forEach(carte => {
            carte.hidden = !normaliser(carte.dataset.recherche).includes(texte)
                || Boolean(genreChoisi && !(carte.dataset.genres || '').split('|').map(normaliser).includes(genreChoisi))
                || Boolean(statutChoisi && normaliser(carte.dataset.statutLecture) !== statutChoisi);
            if (!carte.hidden) visibles++;
        });
        const recente = (a, b) => (Number(b.dataset[champDate]) || 0) - (Number(a.dataset[champDate]) || 0)
            || Number(b.dataset.livreId || b.dataset.pretId || b.dataset.id) - Number(a.dataset.livreId || a.dataset.pretId || a.dataset.id);
        cartes.sort((a, b) => {
            if (contact?.value) {
                const prenomA = (a.dataset.contactPrenom || '').trim();
                const prenomB = (b.dataset.contactPrenom || '').trim();
                const nomA = (a.dataset.contactNom || '').trim();
                const nomB = (b.dataset.contactNom || '').trim();
                if (!prenomA && !nomA && (prenomB || nomB)) return 1;
                if ((prenomA || nomA) && !prenomB && !nomB) return -1;
                const comparaison = alphabet.compare(prenomA, prenomB) || alphabet.compare(nomA, nomB);
                return (contact.value === 'desc' ? -comparaison : comparaison) || recente(a, b);
            }
            if (auteurs.value) {
                const nomA = (a.dataset.auteurs || '').trim();
                const nomB = (b.dataset.auteurs || '').trim();
                if (!nomA && nomB) return 1;
                if (nomA && !nomB) return -1;
                const comparaison = alphabet.compare(nomA, nomB);
                return (auteurs.value === 'desc' ? -comparaison : comparaison) || recente(a, b);
            }
            return date.value === 'asc' ? -recente(a, b) : recente(a, b);
        });
        cartes.forEach(carte => conteneur.appendChild(carte));
        actualiser({ total: cartes.length, visibles, statut: statut?.value || '' });
    }

    // Valide les filtres de genre et de statut sans recharger la liste.
    ecouter(formulaire, 'submit', event => { event.preventDefault(); appliquer(); });
    // Le dernier tri sélectionné prend la main sans effacer les filtres de genre et de statut.
    ecouter(auteurs, 'change', () => { date.value = ''; if (contact) contact.value = ''; appliquer(); });
    ecouter(date, 'change', () => { auteurs.value = ''; if (contact) contact.value = ''; appliquer(); });
    // Sur les prêts, le contact remplace le filtre de statut et devient un tri alphabétique par prénom.
    ecouter(contact, 'change', () => { auteurs.value = ''; date.value = ''; appliquer(); });
    // Efface la recherche, les filtres et les tris pour retrouver la liste complète.
    ecouter(formulaire.querySelector('[data-reinitialiser-filtres]'), 'click', () => {
        if (recherche) recherche.value = '';
        genre.value = '';
        if (statut) statut.value = '';
        if (contact) contact.value = '';
        auteurs.value = ''; date.value = '';
        appliquer();
    });
    // Synchronise les compteurs cliquables de Mes lectures avec le filtre de statut.
    return { appliquer, choisirStatut(valeur) { if (statut) statut.value = valeur; appliquer(); } };
}
