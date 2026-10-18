// Gère les formulaires du compte et la visibilité des sections de l’accueil public.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('page-profil');
    if (!page) return;
    const onglets = [...page.querySelectorAll('[role="tab"]')];
    const soumissions = new Set();
    // Affiche le panneau du compte ou de visibilité et synchronise les attributs d’accessibilité.
    function choisirOnglet(bouton) {
        onglets.forEach(onglet => {
            const actif = onglet === bouton;
            onglet.setAttribute('aria-selected', String(actif));
            onglet.tabIndex = actif ? 0 : -1;
            document.getElementById(onglet.getAttribute('aria-controls')).hidden = !actif;
        });
    }
    // Revient directement à Visibilité après la réorganisation de l’accueil.
    if (window.location.hash === '#visibilite') {
        const onglet = document.getElementById('profil-tab-visibilite');
        if (onglet) choisirOnglet(onglet);
    }
    // Permet de changer d’onglet au clic ou avec les flèches du clavier.
    onglets.forEach((bouton, index) => {
        ecouter(bouton, 'click', () => choisirOnglet(bouton));
        ecouter(bouton, 'keydown', event => {
            let cible;
            if (event.key === 'ArrowRight') cible = (index + 1) % onglets.length;
            if (event.key === 'ArrowLeft') cible = (index + onglets.length - 1) % onglets.length;
            if (event.key === 'Home') cible = 0;
            if (event.key === 'End') cible = onglets.length - 1;
            if (cible === undefined) return;
            event.preventDefault(); choisirOnglet(onglets[cible]); onglets[cible].focus();
        });
    });
    // Efface le résultat précédent avant une nouvelle saisie ou une nouvelle soumission.
    const masquerMessages = type => {
        document.getElementById(`profil-erreur-${type}`).hidden = true;
        document.getElementById(`profil-succes-${type}`).hidden = true;
    };
    // Efface la saisie, masque le formulaire et rétablit son bouton d’ouverture.
    function fermerFormulaire(type) {
        const form = document.getElementById(`profil-form-${type}`);
        form.reset(); form.hidden = true;
        const bouton = page.querySelector(`[data-ouvrir-profil="${type}"]`);
        bouton.hidden = false; bouton.setAttribute('aria-expanded', 'false');
    }
    // Ouvre le formulaire demandé et préremplit l’adresse e-mail actuelle.
    page.querySelectorAll('[data-ouvrir-profil]').forEach(bouton => ecouter(bouton, 'click', () => {
        const type = bouton.dataset.ouvrirProfil;
        const form = document.getElementById(`profil-form-${type}`);
        masquerMessages(type); form.reset(); form.hidden = false;
        bouton.hidden = true; bouton.setAttribute('aria-expanded', 'true');
        if (type === 'email') form.elements.namedItem('email').value = document.getElementById('profil-email-actuel').textContent.trim();
        form.querySelector('input:not([type="hidden"])').focus();
    }));
    // Annule les modifications et replace le focus sur le bouton d’ouverture.
    page.querySelectorAll('[data-annuler-profil]').forEach(bouton => ecouter(bouton, 'click', () => {
        const type = bouton.dataset.annulerProfil;
        if (soumissions.has(type)) return;
        fermerFormulaire(type); masquerMessages(type);
        page.querySelector(`[data-ouvrir-profil="${type}"]`).focus();
    }));
    // Enregistre l’e-mail ou le mot de passe sans recharger la page et évite les doubles soumissions.
    page.querySelectorAll('[data-profil-form]').forEach(form => ecouter(form, 'submit', async event => {
        event.preventDefault();
        const type = form.dataset.profilForm;
        if (soumissions.has(type) || !form.reportValidity()) return;
        masquerMessages(type);
        soumissions.add(type);
        const boutons = [...form.querySelectorAll('button')];
        boutons.forEach(bouton => { bouton.disabled = true; });
        try {
            const reponse = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible d’enregistrer les modifications.');
            if (!page.isConnected) return;
            if (type === 'email') document.getElementById('profil-email-actuel').textContent = resultat.email;
            fermerFormulaire(type);
            const message = document.getElementById(`profil-succes-${type}`);
            message.textContent = resultat.message; message.hidden = false;
        } catch (exception) {
            if (!page.isConnected) return;
            const message = document.getElementById(`profil-erreur-${type}`);
            message.textContent = exception instanceof TypeError || exception instanceof SyntaxError ? 'Impossible d’enregistrer les modifications. Réessayez.' : exception.message;
            message.hidden = false;
        } finally {
            soumissions.delete(type); boutons.forEach(bouton => { bouton.disabled = false; });
        }
    }));
    const formVisibilite = document.getElementById('profil-visibilite-form');
    // Empêche une soumission classique du formulaire : chaque interrupteur est enregistré séparément.
    ecouter(formVisibilite, 'submit', event => event.preventDefault());
    // Enregistre chaque réglage de visibilité immédiatement et rétablit sa valeur précédente en cas d’échec.
    formVisibilite.querySelectorAll('[data-section]').forEach(champ => ecouter(champ, 'change', async () => {
        if (champ.disabled) return;
        const section = champ.dataset.section;
        const visible = champ.checked;
        const erreur = document.getElementById('profil-visibilite-erreur');
        const succes = document.getElementById('profil-visibilite-succes');
        erreur.hidden = true; succes.hidden = true; champ.disabled = true;
        try {
            const donnees = new FormData();
            donnees.append('_token', formVisibilite.elements.namedItem('_token').value);
            donnees.append('section', section); donnees.append('visible', visible ? '1' : '0');
            const reponse = await fetch(formVisibilite.action, { method: 'POST', body: donnees, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible d’enregistrer la visibilité.');
            if (!page.isConnected) return;
            champ.checked = resultat.visible;
            document.querySelector(`[data-etat="${section}"]`).textContent = resultat.visible ? 'Visible' : 'Masquée';
            succes.textContent = 'Visibilité enregistrée.'; succes.hidden = false;
        } catch (exception) {
            champ.checked = !visible;
            if (!page.isConnected) return;
            erreur.textContent = exception instanceof TypeError || exception instanceof SyntaxError ? 'Impossible d’enregistrer la visibilité. Réessayez.' : exception.message;
            erreur.hidden = false;
        } finally { champ.disabled = false; }
    }));
    // Efface les mots de passe et referme les formulaires avant la mise en cache de la page.
    const nettoyer = () => {
        page.querySelectorAll('input[type="password"]').forEach(champ => { champ.value = ''; });
        page.querySelectorAll('button:disabled, input:disabled').forEach(champ => { champ.disabled = false; });
        ['email', 'password'].forEach(fermerFormulaire);
    };
    // Nettoie les formulaires avant leur mise en cache ou le remplacement de la page par Turbo.
    ecouter(document, 'turbo:before-cache', nettoyer);
    ecouter(document, 'turbo:before-render', nettoyer);
});
