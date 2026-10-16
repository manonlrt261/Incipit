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

    // Combine la recherche, le genre et le statut, puis actualise le compteur des livres visibles.
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
