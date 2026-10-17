// Gère les filtres, le tri, l’achat et la suppression des livres de la wishlist.
import { initialiserPage } from './components/initialisation-page.js';
import { creerFiltresLivres } from './components/filtres-livres.js';


initialiserPage((ecouter) => {
    const page = document.getElementById('page-wishlist');
    if (!page) return;
    const grille = document.getElementById('wishlist-livres');
    const recherche = document.getElementById('wishlist-recherche');
    const message = document.getElementById('wishlist-message');
    const filtres = creerFiltresLivres({
        page, conteneur: grille, selecteur: '.wishlist-carte', recherche, ecouter,
        actualiser({ total, visibles }) {
            document.getElementById('wishlist-compteur').textContent = `${visibles} livre${visibles > 1 ? 's' : ''} dans ma wishlist`;
            document.getElementById('wishlist-vide').hidden = total !== 0;
            document.getElementById('wishlist-aucun-resultat').hidden = total === 0 || visibles !== 0;
        }
    });
    const filtrer = filtres.appliquer;
    // Recherche instantanée ; les filtres de genre et de statut sont aussi appliqués avec Valider.
    ecouter(recherche, 'input', filtrer);
    ecouter(document.getElementById('wishlist-recherche-form'), 'submit', event => {
        event.preventDefault();
        filtrer();
    });
    // Gère les actions des cartes, y compris celles ajoutées après le chargement.
    ecouter(grille, 'click', async event => {
        if (!(event.target instanceof Element)) return;
        const bouton = event.target.closest('button[data-action]');
        const carte = bouton?.closest('.wishlist-carte');
        if (!carte || bouton.disabled) return;
        message.hidden = true;
        const action = bouton.dataset.action;
        if (action === 'modifier') {
            document.dispatchEvent(new CustomEvent('livre:modifier', { detail: { id: carte.dataset.id } }));
            return;
        }
        const boutons = [...carte.querySelectorAll('button')];
        boutons.forEach(element => { element.disabled = true; });
        try {
            const donnees = new FormData();
            donnees.append('_token', carte.dataset.token);
            const reponse = await fetch(carte.dataset[action], {
                method: 'POST', body: donnees,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible d’enregistrer cette action.');
            carte.remove();
            filtrer();
        } catch (erreur) {
            message.textContent = erreur.message;
            message.hidden = false;
        } finally {
            boutons.forEach(element => { element.disabled = false; });
        }
    });
    filtrer();
});
