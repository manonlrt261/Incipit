import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './bibliotheque.js';
import './modal-ajout-livre.js';
import './modal-details-livre.js';
import './styles/lectures.css';
import './lectures.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');


function initialiserMotDePasse() {
    const bouton = document.getElementById('toggle-password');
    const champ = document.getElementById('password');
    const image = document.getElementById('password-icon');

    if (!bouton || !champ || !image) {
        return;
    }

    bouton.addEventListener('click', () => {
        const afficherMotDePasse = champ.type === 'password';

        champ.type = afficherMotDePasse ? 'text' : 'password';

        image.src = afficherMotDePasse
            ? image.dataset.imageMasquer
            : image.dataset.imageAfficher;

        bouton.setAttribute(
            'aria-label',
            afficherMotDePasse
                ? 'Masquer le mot de passe'
                : 'Afficher le mot de passe'
        );

        bouton.setAttribute('aria-pressed', String(afficherMotDePasse));
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiserMotDePasse);
} else {
    initialiserMotDePasse();
}

import './prets.js';
import './styles/prets.css';

import './wishlist.js';
import './styles/wishlist.css';

import './statistiques.js';
import './styles/statistiques.css';

import './profil.js';
import './styles/profil.css';

import './navigation.js';

import './tableau-de-bord.js';

import './edition-accueil.js';
