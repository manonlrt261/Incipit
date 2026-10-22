// Réorganise les livres des pages publiques et choisit les auteurs ex æquo en mode édition.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('edition-accueil');
    if (!page) {
        return;
    }


    const quitter = document.getElementById('quitter-edition-accueil');
    const message = document.getElementById('edition-accueil-message');
    let attente;
    let carteActive = null;
    let depart = null;
    let pointeur = null;

    // Autorise le remplacement uniquement parmi les ex æquo et évite d’afficher deux fois le même auteur.
    const choixAuteurs = [...page.querySelectorAll('[data-choix-auteur]')];
    function actualiserAuteurs() {
        const selection = choixAuteurs.map((champ) => champ.value);
        choixAuteurs.forEach((champ) => {
            [...champ.options].forEach((option) => {
                option.disabled = option.value !== champ.value && selection.includes(option.value);
            });
            champ.closest('.accueil-auteur').querySelector('h3').textContent =
                champ.selectedOptions[0].textContent;
        });
    }
    choixAuteurs.forEach((champ) =>
        ecouter(champ, 'change', () => {
            actualiserAuteurs();
            message.textContent = 'Auteur modifié. Quittez le mode édition pour enregistrer.';
        })
    );
    actualiserAuteurs();

    // Les mêmes cartes se déplacent au clavier ou après un appui prolongé, sans perdre leurs données.
    page.querySelectorAll('[data-ordre-section] [data-ordre-livre]').forEach((carte) => {
        carte.tabIndex = 0;
        carte.setAttribute('aria-label', `Déplacer ${carte.querySelector('h3, h2').textContent}`);
        carte.querySelectorAll('img').forEach((image) => (image.draggable = false));
    });

    // Annule l’appui en attente et libère le pointeur pour permettre un nouveau déplacement.
    function terminerDeplacement() {
        clearTimeout(attente);
        carteActive?.classList.remove('accueil-livre-deplace');
        carteActive = null;
        depart = null;
        const ancienPointeur = pointeur;
        pointeur = null;
        if (ancienPointeur !== null && page.hasPointerCapture?.(ancienPointeur)) {
            page.releasePointerCapture(ancienPointeur);
        }
    }


    ecouter(page, 'pointerdown', (event) => {
        const carte = event.target.closest('[data-ordre-section] [data-ordre-livre]');
        if (!carte || event.button !== 0) {
            return;
        }
        terminerDeplacement();
        depart = { x: event.clientX, y: event.clientY };
        pointeur = event.pointerId;

        // Capture sur un parent stable : déplacer une carte ne doit pas interrompre le suivi tactile de Safari.
        try {
            page.setPointerCapture(event.pointerId);
        } catch {
            /* Un événement synthétique ne possède pas de pointeur natif. */
        }
        event.preventDefault();
        attente = setTimeout(() => {
            if (!page.isConnected || !depart) {
                return;
            }
            carteActive = carte;
            carte.classList.add('accueil-livre-deplace');
            carte.focus({ preventScroll: true });
            message.textContent = 'Déplacez le livre vers sa nouvelle position.';
        }, 300);
    });
    ecouter(document, 'pointermove', (event) => {
        if (event.pointerId !== pointeur || !depart) {
            return;
        }
        if (!carteActive) {
            if (Math.hypot(event.clientX - depart.x, event.clientY - depart.y) > 12) {
                terminerDeplacement();
            }
            return;
        }
        event.preventDefault();
        const cible = document
            .elementFromPoint(event.clientX, event.clientY)
            ?.closest('[data-ordre-livre]');

        // Une carte reste dans sa section afin de préserver la distinction favoris / wishlist.
        if (!cible || cible === carteActive || cible.parentElement !== carteActive.parentElement) {
            return;
        }


        const cartes = [...cible.parentElement.children];
        if (cartes.indexOf(carteActive) < cartes.indexOf(cible)) {
            cible.after(carteActive);
        } else {
            cible.before(carteActive);
        }
    });
    ecouter(document, 'pointerup', terminerDeplacement);
    ecouter(document, 'pointercancel', terminerDeplacement);

    // Libère également le mode déplacement si le navigateur perd le pointeur ou si la fenêtre perd le focus.
    ecouter(page, 'lostpointercapture', terminerDeplacement);
    ecouter(window, 'blur', terminerDeplacement);
    ecouter(page, 'contextmenu', (event) => {
        if (event.target.closest('[data-ordre-section] [data-ordre-livre]')) {
            event.preventDefault();
        }
    });
    ecouter(page, 'keydown', (event) => {
        const carte = event.target.closest('[data-ordre-section] [data-ordre-livre]');
        if (!carte || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
            return;
        }
        event.preventDefault();
        const precedent = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
        const cartes = [...carte.parentElement.querySelectorAll(':scope > [data-ordre-livre]')];
        const voisine = cartes[cartes.indexOf(carte) + (precedent ? -1 : 1)];
        if (!voisine) {
            return;
        }
        if (precedent) {
            voisine.before(carte);
        } else {
            voisine.after(carte);
        }
        carte.focus({ preventScroll: true });
        message.textContent = 'Position modifiée. Quittez le mode édition pour enregistrer.';
    });

    // En cas d’échec, conserve les positions à l’écran et permet de réessayer sans quitter l’édition.
    async function enregistrerPuisNaviguer(destination) {
        terminerDeplacement();
        if (quitter.disabled) {
            return;
        }
        quitter.disabled = true;
        message.textContent = 'Enregistrement…';
        const ordre = {};
        page.querySelectorAll('[data-ordre-section]').forEach((zone) => {
            ordre[zone.dataset.ordreSection] = [...zone.querySelectorAll('[data-ordre-livre]')].map(
                (carte) => Number(carte.dataset.livreId)
            );
        });

        // Les pages de livres n’envoient pas cette section : les choix d’auteurs y sont préservés.
        if (choixAuteurs.length) {
            ordre.auteurs = choixAuteurs.map((champ) => Number(champ.value));
        }
        try {
            const reponse = await fetch(page.dataset.url, {
                method: 'POST',
                body: new URLSearchParams({
                    _token: page.dataset.token,
                    ordre: JSON.stringify(ordre)
                }),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) {
                throw new Error(
                    resultat.message || 'Impossible d’enregistrer les positions. Réessayez.'
                );
            }
            window.location.assign(destination);
        } catch (erreur) {
            message.textContent = erreur.message;
            quitter.disabled = false;
        }
    }


    ecouter(quitter, 'click', () => enregistrerPuisNaviguer(page.dataset.retour));

    // Enregistre aussi avant de passer à une liste complète ou de revenir à l’accueil en mode édition.
    ecouter(page, 'click', (event) => {
        const lien = event.target.closest('[data-edition-navigation]');
        if (!lien) {
            return;
        }
        event.preventDefault();
        enregistrerPuisNaviguer(lien.href);
    });
});
