// Gère la recherche, les filtres et les modes d’affichage de la pile à lire.
import './components/pile-a-lire.js';
import { initialiserPage } from './components/initialisation-page.js';

// Active les interactions uniquement lorsque la liste de la pile à lire et ses filtres sont présents.
function initialiserBibliotheque(ecouter) {
    const conteneur = document.getElementById('bibliotheque-livres');

    if (!conteneur || !document.getElementById('bibliotheque-filtres')) {
        return;
    }


    const boutonGrille = document.getElementById('vue-grille');
    const boutonListe = document.getElementById('vue-liste');
    const boutonRepli = document.getElementById('bibliotheque-basculer-livres');
    const zone = document.getElementById('bibliotheque-liste-zone');
    const mobile = window.matchMedia('(max-width: 700px)');
    let choixManuel = false;

    // Adapte le mode initial à l’écran, puis respecte le choix manuel tant que la page reste ouverte.
    function changerVue(grille) {
        conteneur.classList.toggle('vue-grille', grille);
        conteneur.classList.toggle('vue-liste', !grille);
        boutonGrille.classList.toggle('actif', grille);
        boutonListe.classList.toggle('actif', !grille);
        boutonGrille.setAttribute('aria-pressed', String(grille));
        boutonListe.setAttribute('aria-pressed', String(!grille));
    }
    changerVue(!mobile.matches);
    ecouter(mobile, 'change', () => {
        if (!choixManuel) {
            changerVue(!mobile.matches);
        }
    });
    ecouter(boutonGrille, 'click', () => {
        choixManuel = true;
        changerVue(true);
    });
    ecouter(boutonListe, 'click', () => {
        choixManuel = true;
        changerVue(false);
    });

    // Replie la sélection sans perdre ses filtres ni son mode d’affichage.
    ecouter(boutonRepli, 'click', () => {
        const ouvert = boutonRepli.getAttribute('aria-expanded') === 'true';
        boutonRepli.setAttribute('aria-expanded', String(!ouvert));
        boutonRepli.setAttribute(
            'aria-label',
            ouvert ? 'Afficher les livres' : 'Masquer les livres'
        );
        zone.hidden = ouvert;
    });

    const formulaireFiltres = document.getElementById('bibliotheque-filtres');
    const formulaireRecherche = document.getElementById('bibliotheque-recherche');

    const recherche = document.getElementById('recherche-livre');
    const filtreGenre = document.getElementById('filtre-genre');
    const filtreStatut = document.getElementById('filtre-statut');
    const triAuteurs = document.getElementById('tri-auteurs');
    const triDateAjout = document.getElementById('tri-date-ajout');
    const ordreAlphabetique = new Intl.Collator('fr', { sensitivity: 'base', numeric: true });

    const reinitialiser = document.getElementById('reinitialiser-filtres');
    const compteur = document.getElementById('bibliotheque-compteur');
    const aucunResultat = document.getElementById('bibliotheque-aucun-resultat');

    // Ignore les accents et les majuscules pour faciliter les recherches.
    function normaliser(texte) {
        return String(texte ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    // Trie les cartes existantes sans les recréer, en conservant leurs actions et leur état de visibilité.
    function trierLivres(livres) {
        const dateAjout = (livre) => Number(livre.dataset.dateAjout) || 0;
        const parDateRecente = (a, b) =>
            dateAjout(b) - dateAjout(a) || Number(b.dataset.livreId) - Number(a.dataset.livreId);

        livres.sort((a, b) => {
            if (triAuteurs.value) {
                const auteurA = (a.dataset.auteurs || '').trim();
                const auteurB = (b.dataset.auteurs || '').trim();

                // Les livres sans auteur restent à la fin, quel que soit le sens du tri.
                if (!auteurA && auteurB) {
                    return 1;
                }
                if (auteurA && !auteurB) {
                    return -1;
                }


                const comparaison = ordreAlphabetique.compare(auteurA, auteurB);
                return (
                    (triAuteurs.value === 'desc' ? -comparaison : comparaison) ||
                    parDateRecente(a, b)
                );
            }
            return triDateAjout.value === 'asc' ? -parDateRecente(a, b) : parDateRecente(a, b);
        });

        livres.forEach((livre) => conteneur.appendChild(livre));
    }

    // Combine recherche et filtres, applique le tri choisi et actualise le compteur des livres visibles.
    function appliquerFiltres() {
        const livres = [...conteneur.querySelectorAll('.bibliotheque-livre')];

        const texte = normaliser(recherche.value);
        const genre = normaliser(filtreGenre.value);
        const statut = normaliser(filtreStatut.value);

        let nombreVisible = 0;

        livres.forEach((livre) => {
            const texteLivre = normaliser(livre.dataset.recherche);

            const genresLivre = (livre.dataset.genres || '').split('|').map(normaliser);

            const statutLivre = normaliser(livre.dataset.statut);

            const correspondRecherche = !texte || texteLivre.includes(texte);

            const correspondGenre = !genre || genresLivre.includes(genre);

            const correspondStatut = !statut || statutLivre === statut;

            const visible = correspondRecherche && correspondGenre && correspondStatut;

            livre.hidden = !visible;

            if (visible) {
                nombreVisible++;
            }
        });

        trierLivres(livres);

        compteur.textContent = `${nombreVisible} livre${nombreVisible > 1 ? 's' : ''} dans ma pile à lire`;

        aucunResultat.hidden = nombreVisible !== 0;
    }

    // Applique les filtres sans recharger la page.
    ecouter(formulaireFiltres, 'submit', (evenement) => {
        evenement.preventDefault();
        appliquerFiltres();
    });

    // Lance la recherche sans recharger la page.
    ecouter(formulaireRecherche, 'submit', (evenement) => {
        evenement.preventDefault();
        appliquerFiltres();
    });

    // Efface la recherche et les filtres pour réafficher tous les livres.
    ecouter(reinitialiser, 'click', () => {
        recherche.value = '';
        filtreGenre.value = '';
        filtreStatut.value = '';
        triAuteurs.value = '';
        triDateAjout.value = '';

        appliquerFiltres();
    });

    // Le dernier menu de tri choisi détermine l’ordre ; les filtres de genre et de statut sont conservés.
    ecouter(triAuteurs, 'change', () => {
        triDateAjout.value = '';
        appliquerFiltres();
    });
    ecouter(triDateAjout, 'change', () => {
        triAuteurs.value = '';
        appliquerFiltres();
    });

    const rechercheURL = new URLSearchParams(window.location.search).get('recherche');

    // Reprend une recherche transmise dans le lien menant à cette page.
    if (rechercheURL) {
        recherche.value = rechercheURL;
    }

    // Intègre le livre ajouté dans les filtres, le tri et le compteur actuels.
    ecouter(document, 'bibliotheque:livre-ajoute', appliquerFiltres);

    appliquerFiltres();
}

initialiserPage(initialiserBibliotheque);
