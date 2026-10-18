// Réorganise uniquement les cartes de l’accueil en mode édition, puis sauvegarde avant le retour au profil.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage(ecouter => {
    const page = document.getElementById('edition-accueil');
    if (!page) return;
    const quitter = document.getElementById('quitter-edition-accueil');
    const message = document.getElementById('edition-accueil-message');
    let attente;
    let carteActive = null;
    let depart = null;
    let pointeur = null;
    let derniereCible = null;

    // Les mêmes cartes se déplacent au clavier ou après un appui prolongé, sans perdre leurs données.
    page.querySelectorAll('[data-ordre-section] .accueil-carte-livre').forEach(carte => {
        carte.tabIndex = 0;
        carte.setAttribute('aria-label', `Déplacer ${carte.querySelector('h3').textContent}`);
        carte.querySelectorAll('img').forEach(image => image.draggable = false);
    });
    function terminerDeplacement() {
        clearTimeout(attente);
        carteActive?.classList.remove('accueil-livre-deplace');
        carteActive = null;
        depart = null;
        pointeur = null;
        derniereCible = null;
    }
    ecouter(page, 'pointerdown', event => {
        const carte = event.target.closest('[data-ordre-section] .accueil-carte-livre');
        if (!carte || event.button !== 0) return;
        terminerDeplacement();
        depart = { x: event.clientX, y: event.clientY };
        pointeur = event.pointerId;
        attente = setTimeout(() => {
            if (!page.isConnected || !depart) return;
            carteActive = carte;
            carte.classList.add('accueil-livre-deplace');
            carte.focus({ preventScroll: true });
            message.textContent = 'Déplacez le livre vers sa nouvelle position.';
        }, 300);
    });
    ecouter(document, 'pointermove', event => {
        if (event.pointerId !== pointeur || !depart) return;
        if (!carteActive) {
            if (Math.hypot(event.clientX - depart.x, event.clientY - depart.y) > 12) terminerDeplacement();
            return;
        }
        event.preventDefault();
        const cible = document.elementFromPoint(event.clientX, event.clientY)?.closest('.accueil-carte-livre');
        // Une carte reste dans sa section afin de préserver la distinction favoris / wishlist.
        if (!cible || cible === carteActive || cible === derniereCible || cible.parentElement !== carteActive.parentElement) return;
        derniereCible = cible;
        const cartes = [...cible.parentElement.children];
        if (cartes.indexOf(carteActive) < cartes.indexOf(cible)) cible.after(carteActive);
        else cible.before(carteActive);
    });
    ecouter(document, 'pointerup', terminerDeplacement);
    ecouter(document, 'pointercancel', terminerDeplacement);
    ecouter(page, 'contextmenu', event => {
        if (event.target.closest('[data-ordre-section] .accueil-carte-livre')) event.preventDefault();
    });
    ecouter(page, 'keydown', event => {
        const carte = event.target.closest('[data-ordre-section] .accueil-carte-livre');
        if (!carte || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const precedent = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
        const voisine = precedent ? carte.previousElementSibling : carte.nextElementSibling;
        if (!voisine) return;
        if (precedent) voisine.before(carte);
        else voisine.after(carte);
        carte.focus({ preventScroll: true });
        message.textContent = 'Position modifiée. Quittez le mode édition pour enregistrer.';
    });

    // En cas d’échec, conserve les positions à l’écran et permet de réessayer sans quitter l’édition.
    ecouter(quitter, 'click', async () => {
        terminerDeplacement();
        if (quitter.disabled) return;
        quitter.disabled = true;
        message.textContent = 'Enregistrement…';
        const ordre = {};
        page.querySelectorAll('[data-ordre-section]').forEach(zone => {
            ordre[zone.dataset.ordreSection] = [...zone.querySelectorAll('.accueil-carte-livre')].map(carte => Number(carte.dataset.livreId));
        });
        try {
            const reponse = await fetch(page.dataset.url, {
                method: 'POST',
                body: new URLSearchParams({ _token: page.dataset.token, ordre: JSON.stringify(ordre) }),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible d’enregistrer les positions. Réessayez.');
            window.location.assign(page.dataset.retour);
        } catch (erreur) {
            message.textContent = erreur.message;
            quitter.disabled = false;
        }
    });
});
