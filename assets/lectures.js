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

    const statuts = {
        'en-cours': 'En cours', 'termine': 'Terminé', 'en-pause': 'En pause',
        'abandonne': 'Abandonné', 'pas-commence': 'Pas commencé', 'tous': ''
    };

    // Demande à la fenêtre commune d’ouvrir le livre sélectionné dans la liste.

    ecouter(grille, 'click', (event) => {
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
        const carte = event.target.closest('.lectures-carte');

        if (!carte) return;

        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        carte.click();
    });

    // Recharge les cartes et compteurs après une nouvelle lecture sans perdre les filtres.

    ecouter(document, 'lecture:ajoutee', async () => {
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
    });

    // Applique les critères initiaux dès l’affichage de la page.

    filtrerLivres();
});
