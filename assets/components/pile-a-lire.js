// Partage les actions de sélection entre la pile et les livres possédés de Mes lectures.
import { initialiserPage } from './initialisation-page.js';

initialiserPage((ecouter) => {
    const page = document.querySelector('[data-pile-token]');
    if (!page) return;
    const modal = document.getElementById('modal-selection-pile');
    const recherche = document.getElementById('pile-recherche-selection');
    const erreur = document.getElementById('pile-erreur');

    const normaliser = texte => texte.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr');

    // Recherche sans tenir compte des accents dans les livres possédés proposés par le serveur.
    function filtrerSelection() {
        const texte = normaliser(recherche.value);
        let visibles = 0;
        modal.querySelectorAll('[data-selection-recherche]').forEach(carte => {
            carte.hidden = !normaliser(carte.dataset.selectionRecherche).includes(texte);
            if (!carte.hidden) visibles++;
        });
        const message = document.getElementById('pile-selection-vide');
        message.hidden = visibles !== 0;
        message.textContent = recherche.value.trim()
            ? 'Aucun livre possédé ne correspond à cette recherche.'
            : 'Aucun livre disponible. Ajoutez d’abord un livre dans Mes lectures.';
    }
    // Réinitialise la recherche à chaque ouverture pour proposer tous les livres disponibles.
    ecouter(document.getElementById('ouvrir-selection-pile'), 'click', () => {
        recherche.value = '';
        filtrerSelection();
        modal.showModal();
    });
    ecouter(document.getElementById('fermer-selection-pile'), 'click', () => modal.close());
    ecouter(recherche, 'input', filtrerSelection);
    ecouter(document.getElementById('pile-rechercher-selection'), 'click', filtrerSelection);
    ecouter(recherche, 'keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); filtrerSelection(); }
    });

    // Un seul écouteur gère les cartes et la modale, y compris après une actualisation des lectures.
    ecouter(document, 'click', async event => {
        const bouton = event.target.closest('[data-pile-action]');
        if (!bouton || bouton.disabled) return;
        const id = bouton.dataset.livreId;
        const action = bouton.dataset.pileAction;
        // Bloque les doubles clics pendant l’enregistrement et après un ajout réussi.
        bouton.disabled = true;
        try {
            const reponse = await fetch(`/livre/${encodeURIComponent(id)}/pile/${encodeURIComponent(action)}`, {
                method: 'POST',
                body: new URLSearchParams({ _token: page.dataset.pileToken }),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible de modifier la pile à lire.');
            if (document.getElementById('page-pile-a-lire')) {
                // Reprend les cartes et les choix disponibles calculés par le serveur après chaque action.
                window.location.reload();
            } else {
                bouton.textContent = 'Dans ma pile à lire';
                bouton.dataset.pileSelectionnee = 'true';
            }
        } catch (e) {
            bouton.disabled = false;
            if (erreur) {
                erreur.textContent = e.message;
                erreur.hidden = false;
            } else {
                window.alert(e.message);
            }
        }
    });
});
