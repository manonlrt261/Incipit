// Gère la recherche, les filtres par statut et l’actualisation des lectures.
import { initialiserPage } from './components/initialisation-page.js';
import { creerFiltresLivres } from './components/filtres-livres.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('page-mes-lectures');

    if (!page) return;

    const grille = document.getElementById('bibliotheque-livres');
    const boutonsFiltres = document.querySelectorAll('.lectures-statistique');
    const recherche = document.getElementById('lectures-recherche');
    const compteur = document.getElementById('lectures-resultats-compteur');
    const aucunResultat = document.getElementById('lectures-aucun-resultat');

    if (!grille) {
        console.error('La grille des livres est introuvable.');
        return;
    }

    const boutonGrille = document.getElementById('vue-grille');
    const boutonListe = document.getElementById('vue-liste');
    const boutonRepli = document.getElementById('bibliotheque-basculer-livres');
    const zoneLivres = document.getElementById('bibliotheque-liste-zone');
    const mobile = window.matchMedia('(max-width: 700px)');

    // Distingue le mode choisi par la personne du mode adapté automatiquement à l’écran.
    let vueChoisie = false;
    function changerVue(mode) {
        const enGrille = mode === 'grille';
        grille.classList.toggle('vue-grille', enGrille);
        grille.classList.toggle('vue-liste', !enGrille);
        boutonGrille.classList.toggle('actif', enGrille);
        boutonListe.classList.toggle('actif', !enGrille);
        boutonGrille.setAttribute('aria-pressed', String(enGrille));
        boutonListe.setAttribute('aria-pressed', String(!enGrille));
    }

    // Affiche une liste sur téléphone et une grille sur ordinateur, jusqu’au choix manuel d’un mode.
    function adapterVue() {
        if (!vueChoisie) changerVue(mobile.matches ? 'liste' : 'grille');
    }
    ecouter(mobile, 'change', adapterVue);
    ecouter(boutonGrille, 'click', () => {
        vueChoisie = true;
        changerVue('grille');
    });
    ecouter(boutonListe, 'click', () => {
        vueChoisie = true;
        changerVue('liste');
    });
    adapterVue();

    // Masque les résultats sans effacer les filtres ni le mode d’affichage sélectionné.
    ecouter(boutonRepli, 'click', () => {
        const ouvert = boutonRepli.getAttribute('aria-expanded') === 'true';
        boutonRepli.setAttribute('aria-expanded', String(!ouvert));
        boutonRepli.setAttribute('aria-label', ouvert ? 'Afficher les livres' : 'Masquer les livres');
        zoneLivres.hidden = ouvert;
    });

    // Conserve le repli indépendant de la section des livres retirés de la collection.
    const boutonRetires = document.getElementById('basculer-retires');
    ecouter(boutonRetires, 'click', () => {
        const ouvert = boutonRetires.getAttribute('aria-expanded') === 'true';
        boutonRetires.setAttribute('aria-expanded', String(!ouvert));
        document.getElementById('contenu-retires').hidden = ouvert;
        document.getElementById('chevron-retires').textContent = ouvert ? '⌄' : '⌃';
    });

    // Consulte les livres retirés avec leur historique, indépendamment de la grille des livres possédés.
    let requeteRetire;
    let numeroRetire = 0;
    async function ouvrirLivreRetire(carte) {
        const numero = ++numeroRetire;
        requeteRetire?.abort();
        requeteRetire = new AbortController();
        const erreur = document.getElementById('lectures-retire-erreur');
        erreur.hidden = true;
        try {
            const reponse = await fetch(carte.dataset.retireDetailsUrl, { signal: requeteRetire.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!reponse.ok || reponse.redirected) throw new Error('Impossible de charger les détails du livre. Réessayez.');
            const html = await reponse.text();
            if (numero !== numeroRetire || !page.isConnected) return;
            const zone = document.getElementById('lectures-retire-details');
            zone.innerHTML = html;
            const modale = zone.querySelector('dialog');
            // La fermeture rend le focus au livre sélectionné pour continuer la navigation au clavier.
            ecouter(zone.querySelector('#auteur-details-fermer'), 'click', () => modale.close());
            ecouter(modale, 'close', () => carte.focus({ preventScroll: true }));
            modale.showModal();
        } catch (e) {
            if (e.name === 'AbortError' || numero !== numeroRetire || !page.isConnected) return;
            erreur.textContent = e.message;
            erreur.hidden = false;
        }
    }
    const livresRetires = document.getElementById('contenu-retires');
    ecouter(livresRetires, 'click', event => {
        const carte = event.target.closest('[data-retire-details-url]');
        if (carte) ouvrirLivreRetire(carte);
    });
    ecouter(livresRetires, 'keydown', event => {
        const carte = event.target.closest('[data-retire-details-url]');
        if (!carte || !['Enter', ' '].includes(event.key)) return;
        event.preventDefault();
        ouvrirLivreRetire(carte);
    });

    const statuts = {
        'en-cours': 'En cours', 'termine': 'Terminé', 'en-pause': 'En pause',
        'abandonne': 'Abandonné', 'pas-commence': 'Pas commencé', 'tous': ''
    };

    // Demande à la fenêtre commune d’ouvrir le livre sélectionné dans la liste.

    ecouter(grille, 'click', (event) => {
        if (event.target.closest('[data-pile-action]')) return;
        const carte = event.target.closest('.lectures-carte');

        if (!carte) return;

        const id = carte.dataset.livreId;

        if (!id) return;

        document.dispatchEvent(
            new CustomEvent('livre:ouvrir-details', {
                detail: { id }
            })
        );
    });

    // Actualise le nombre de résultats et les compteurs actifs avec les filtres partagés.

    const filtres = creerFiltresLivres({
        page, conteneur: grille, selecteur: '.lectures-carte', recherche, ecouter,
        actualiser({ visibles, statut }) {
            if (compteur) compteur.textContent = `${visibles} livre${visibles > 1 ? 's' : ''} affiché${visibles > 1 ? 's' : ''}`;
            if (aucunResultat) aucunResultat.hidden = visibles !== 0;
            boutonsFiltres.forEach(bouton => {
                const actif = statuts[bouton.dataset.filtre] === statut;
                bouton.classList.toggle('actif', actif);
                bouton.setAttribute('aria-pressed', String(actif));
            });
        }
    });
    const filtrerLivres = filtres.appliquer;

    // Permet de filtrer les lectures en cliquant sur un compteur de statut.

    boutonsFiltres.forEach((bouton) => {
        ecouter(bouton, 'click', () => {
            filtres.choisirStatut(statuts[bouton.dataset.filtre]);
        });
    });

    // Actualise la liste à mesure que la recherche est saisie.

    ecouter(recherche, 'input', filtrerLivres);
    // Le bouton et la touche Entrée appliquent la recherche sans rechargement.
    ecouter(document.getElementById('lectures-recherche-form'), 'submit', event => {
        event.preventDefault();
        filtrerLivres();
    });

    // Permet d’ouvrir les cartes avec Entrée ou Espace, comme avec un clic.

    ecouter(grille, 'keydown', (event) => {
        if (event.target.closest('[data-pile-action]')) return;
        const carte = event.target.closest('.lectures-carte');

        if (!carte) return;

        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        carte.click();
    });

    // Recharge les cartes et les six compteurs après un ajout de livre ou un changement de session.
    let numeroActualisation = 0;
    async function actualiserLectures() {
        const numeroRequete = ++numeroActualisation;
        try {
            const reponse = await fetch(window.location.pathname, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!reponse.ok) {
                throw new Error('Actualisation impossible.');
            }

            const html = await reponse.text();
            // Ignore une réponse ancienne si un autre ajout a déjà demandé une actualisation.
            if (numeroRequete !== numeroActualisation) return;

            const documentActualise = new DOMParser()
                .parseFromString(html, 'text/html');

            const nouvelleGrille = documentActualise
                .getElementById('bibliotheque-livres');

            if (!nouvelleGrille) {
                throw new Error('Liste des livres introuvable.');
            }

            // Remplace seulement les cartes ; les écouteurs placés sur la grille restent actifs.
            grille.innerHTML = nouvelleGrille.innerHTML;

            // Reprend les totaux calculés par le serveur pour chaque statut.
            document.querySelectorAll('[data-compteur]').forEach((element) => {
                const nouveau = documentActualise.querySelector(
                    `[data-compteur="${element.dataset.compteur}"]`
                );

                if (nouveau) {
                    element.textContent = nouveau.textContent;
                }
            });

            // Referme et vide le formulaire une fois la nouvelle lecture enregistrée.
            const formulaireZone = document.getElementById(
                'details-formulaire-lecture'
            );

            const formulaire = document.getElementById(
                'form-ajouter-lecture'
            );

            const boutonLecture = document.getElementById(
                'ajouter-lecture-details'
            );

            formulaire?.reset();

            if (formulaireZone) {
                formulaireZone.hidden = true;
            }

            if (boutonLecture) {
                boutonLecture.hidden = false;
            }

            filtrerLivres();

        } catch (erreur) {
            console.error(
                'Erreur lors de l’actualisation des lectures :',
                erreur
            );
        }
    }

    // Partage la même actualisation sans ajouter d’écouteurs aux cartes remplacées.
    ecouter(document, 'lecture:ajoutee', actualiserLectures);
    ecouter(document, 'bibliotheque:livre-ajoute', actualiserLectures);

    // Applique les critères initiaux dès l’affichage de la page.

    filtrerLivres();
});
