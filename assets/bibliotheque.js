// Gère les filtres, les modes d’affichage et le repli des livres de la bibliothèque.
import { initialiserPage } from './components/initialisation-page.js';

// Active les interactions uniquement lorsque la bibliothèque et ses filtres sont présents.
function initialiserBibliotheque(ecouter) {
    const conteneur = document.getElementById('bibliotheque-livres');

    if (!conteneur || !document.getElementById('bibliotheque-filtres')) {
        return;
    }

    const boutonRepli = document.getElementById('bibliotheque-basculer-livres');
    const zoneLivres = document.getElementById('bibliotheque-liste-zone');
    // Synchronise la visibilité des livres et le libellé accessible du bouton de repli.
    function actualiserRepli() {
        if (!boutonRepli || !zoneLivres) return;
        const ouvert = boutonRepli.getAttribute('aria-expanded') === 'true';
        zoneLivres.hidden = !ouvert;
        boutonRepli.setAttribute('aria-label', ouvert ? 'Masquer les livres' : 'Afficher les livres');
    }
    // Affiche ou masque la liste des livres au clic sur la flèche.
    ecouter(boutonRepli, 'click', () => {
        boutonRepli.setAttribute('aria-expanded', String(boutonRepli.getAttribute('aria-expanded') !== 'true'));
        actualiserRepli();
    });
    actualiserRepli();

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

    const boutonGrille = document.getElementById('vue-grille');
    const boutonListe = document.getElementById('vue-liste');

    const boutonRetires = document.getElementById('basculer-retires');
    const contenuRetires = document.getElementById('contenu-retires');
    const chevronRetires = document.getElementById('chevron-retires');

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
        const dateAjout = livre => Number(livre.dataset.dateAjout) || 0;
        const parDateRecente = (a, b) => dateAjout(b) - dateAjout(a)
            || Number(b.dataset.livreId) - Number(a.dataset.livreId);

        livres.sort((a, b) => {
            if (triAuteurs.value) {
                const auteurA = (a.dataset.auteurs || '').trim();
                const auteurB = (b.dataset.auteurs || '').trim();

                // Les livres sans auteur restent à la fin, quel que soit le sens du tri.
                if (!auteurA && auteurB) return 1;
                if (auteurA && !auteurB) return -1;
                const comparaison = ordreAlphabetique.compare(auteurA, auteurB);
                return (triAuteurs.value === 'desc' ? -comparaison : comparaison)
                    || parDateRecente(a, b);
            }
            return triDateAjout.value === 'asc'
                ? -parDateRecente(a, b)
                : parDateRecente(a, b);
        });

        livres.forEach(livre => conteneur.appendChild(livre));
    }

    // Combine recherche et filtres, applique le tri choisi et actualise le compteur des livres visibles.
    function appliquerFiltres() {
        const livres = [
            ...conteneur.querySelectorAll('.bibliotheque-livre')
        ];

        const texte = normaliser(recherche.value);
        const genre = normaliser(filtreGenre.value);
        const statut = normaliser(filtreStatut.value);

        let nombreVisible = 0;

        livres.forEach((livre) => {
            const texteLivre = normaliser(livre.dataset.recherche);

            const genresLivre = (livre.dataset.genres || '')
                .split('|')
                .map(normaliser);

            const statutLivre = normaliser(livre.dataset.statut);

            const correspondRecherche =
                !texte || texteLivre.includes(texte);

            const correspondGenre =
                !genre || genresLivre.includes(genre);

            const correspondStatut =
                !statut || statutLivre === statut;

            const visible =
                correspondRecherche &&
                correspondGenre &&
                correspondStatut;

            livre.hidden = !visible;

            if (visible) {
                nombreVisible++;
            }
        });

        trierLivres(livres);

        compteur.textContent =
            `${nombreVisible} livre${nombreVisible > 1 ? 's' : ''} dans ma bibliothèque`;

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

    // Bascule entre grille et liste et indique quel mode d’affichage est actif.
    function changerVue(mode) {
        const grilleActive = mode === 'grille';

        conteneur.classList.toggle('vue-grille', grilleActive);
        conteneur.classList.toggle('vue-liste', !grilleActive);

        boutonGrille.classList.toggle('actif', grilleActive);
        boutonListe.classList.toggle('actif', !grilleActive);

        boutonGrille.setAttribute(
            'aria-pressed',
            String(grilleActive)
        );

        boutonListe.setAttribute(
            'aria-pressed',
            String(!grilleActive)
        );
    }

    // Passe à l’affichage des livres en grille.
    ecouter(boutonGrille, 'click', () => changerVue('grille'));
    // Passe à l’affichage des livres en liste.
    ecouter(boutonListe, 'click', () => changerVue('liste'));

    // Affiche ou masque les livres retirés de la collection.
    ecouter(boutonRetires, 'click', () => {
        const estOuvert =
            boutonRetires.getAttribute('aria-expanded') === 'true';

        boutonRetires.setAttribute(
            'aria-expanded',
            String(!estOuvert)
        );

        contenuRetires.hidden = estOuvert;
        chevronRetires.textContent = estOuvert ? '⌄' : '⌃';
    });

    const rechercheURL = new URLSearchParams(
        window.location.search
    ).get('recherche');

    if (rechercheURL) {
        recherche.value = rechercheURL;
    }

    // Permet d'actualiser les filtres lorsqu'un livre est ajouté
    // dynamiquement à la bibliothèque.
    ecouter(document, 
        'bibliotheque:livre-ajoute',
        appliquerFiltres
    );

    appliquerFiltres();
}

initialiserPage(initialiserBibliotheque);
