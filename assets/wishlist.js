// Gère les filtres, le tri, l’achat et la suppression des livres de la wishlist.
import { initialiserPage } from './components/initialisation-page.js';
import { creerFiltresLivres } from './components/filtres-livres.js';


initialiserPage((ecouter) => {
    // Les fiches privées et publiques utilisent les mêmes interactions, sans nouvel appel réseau.
    ecouter(document, 'click', event => {
        if (!(event.target instanceof Element)) return;
        const fermeture = event.target.closest('[data-fermer-fiche]');
        if (fermeture) {
            fermeture.closest('dialog')?.close();
            return;
        }
        if (event.target.closest('button, a, input, select')) return;
        const carte = event.target.closest('[data-fiche-souhait]');
        if (carte) document.getElementById(carte.dataset.ficheSouhait)?.showModal();
    });
    ecouter(document, 'keydown', event => {
        if (!(event.target instanceof Element) || !event.target.matches('[data-fiche-souhait]')) return;
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        document.getElementById(event.target.dataset.ficheSouhait)?.showModal();
    });

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
        // Les commandes de la fiche utilisent la même carte et les mêmes routes que la liste.
        const fiche = bouton?.closest('.wishlist-details');
        const carte = bouton?.closest('.wishlist-carte') || (fiche
            ? [...grille.querySelectorAll('.wishlist-carte')].find(element => element.dataset.id === fiche.dataset.souhaitId)
            : null);
        if (!carte || bouton.disabled) return;
        message.hidden = true;
        const action = bouton.dataset.action;
        if (action === 'modifier') {
            fiche?.close();
            document.dispatchEvent(new CustomEvent('livre:modifier', { detail: { id: carte.dataset.id } }));
            return;
        }
        const boutons = [...carte.querySelectorAll('button'), ...(fiche?.querySelectorAll('button[data-action]') || [])];
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
            const ficheLiee = document.getElementById(carte.dataset.ficheSouhait);
            if (ficheLiee?.open) ficheLiee.close();
            ficheLiee?.remove();
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
