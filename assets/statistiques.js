// Actualise les statistiques par année et permet de consulter ou modifier l’objectif de lecture.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('page-statistiques');
    if (!page) return;
    const select = document.getElementById('stats-annee');
    const graphiques = document.getElementById('stats-graphiques');
    const chargement = document.getElementById('stats-chargement');
    const erreur = document.getElementById('stats-erreur');
    const modal = document.getElementById('stats-objectif-modal');
    const formulaireObjectif = document.getElementById('stats-objectif-form');
    const affichageObjectif = document.getElementById('stats-objectif-affichage');
    const nombreObjectif = document.getElementById('stats-objectif-nombre');
    const modifierObjectif = document.getElementById('stats-modifier-objectif');
    const enregistrerObjectif = document.getElementById('stats-enregistrer-objectif');
    const annulerObjectif = document.getElementById('stats-annuler-objectif');
    const fermerObjectif = document.getElementById('stats-fermer-objectif');
    const erreurObjectif = document.getElementById('stats-objectif-erreur');
    const succesObjectif = document.getElementById('stats-objectif-succes');
    let objectifActuel = null;
    let anneeObjectif = '';
    let enregistrementEnCours = false;
    let controleur = null;
    let anneeAffichee = select.value;

    let periodeComparaison = '3';
    const modalMois = document.getElementById('stats-mois-modal');

    // Conserve la période choisie lorsque l’année ou l’objectif actualise les graphiques.
    function appliquerPeriodeComparaison() {
        const choix = graphiques.querySelector('[data-stats-periode]');
        if (choix) choix.value = periodeComparaison;
        graphiques.querySelectorAll('[data-stats-comparaison]').forEach(zone => {
            zone.hidden = zone.dataset.statsComparaison !== periodeComparaison;
        });
    }
    ecouter(graphiques, 'change', event => {
        if (!event.target.matches('[data-stats-periode]')) return;
        periodeComparaison = event.target.value;
        appliquerPeriodeComparaison();
    });
    appliquerPeriodeComparaison();

    // Réutilise les listes mensuelles ou annuelles rendues par le serveur, sans requête ni rechargement.
    function ouvrirListeLectures(cible) {
        const annuel = cible.hasAttribute('data-stats-annee');
        const valeur = annuel ? cible.dataset.statsAnnee : cible.dataset.statsMois;
        const modele = graphiques.querySelector(`[data-stats-details-${annuel ? 'annee' : 'mois'}="${valeur}"]`);
        if (!modalMois) return;
        document.getElementById('stats-mois-modal-titre').textContent = modele?.dataset.titre || `Lectures terminées en ${valeur}`;
        const liste = document.getElementById('stats-mois-liste');
        if (modele) liste.replaceChildren(modele.content.cloneNode(true));
        else {
            const vide = document.createElement('p');
            vide.textContent = 'Aucune lecture terminée pour cette année.';
            liste.replaceChildren(vide);
        }
        modalMois.showModal();
    }
    ecouter(graphiques, 'click', event => {
        const mois = event.target.closest('[data-stats-mois], [data-stats-annee]');
        if (mois) ouvrirListeLectures(mois);
    });
    ecouter(graphiques, 'keydown', event => {
        const mois = event.target.closest('[data-stats-mois], [data-stats-annee]');
        if (mois && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            ouvrirListeLectures(mois);
        }
    });
    ecouter(document.getElementById('stats-mois-fermer'), 'click', () => modalMois.close());

    // Recharge les graphiques de l’année choisie sans recharger toute la page.
    ecouter(select, 'change', async () => {
        controleur?.abort();
        controleur = new AbortController();
        const requete = controleur;
        const url = new URL(select.dataset.url, window.location.origin);
        url.searchParams.set('annee', select.value);
        chargement.hidden = false;
        select.disabled = true;
        erreur.hidden = true;
        graphiques.setAttribute('aria-busy', 'true');
        try {
            const reponse = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: requete.signal });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible de charger les statistiques.');
            if (!page.isConnected || requete.signal.aborted) return;
            graphiques.innerHTML = resultat.html;
            appliquerPeriodeComparaison();
            anneeAffichee = select.value;
            window.history.replaceState(window.history.state, '', url.pathname + url.search);
        } catch (exception) {
            if (exception.name === 'AbortError' || !page.isConnected) return;
            select.value = anneeAffichee;
            erreur.textContent = 'Impossible de charger cette année. Veuillez réessayer.';
            erreur.hidden = false;
        } finally {
            if (controleur === requete) {
                select.disabled = false;
                chargement.hidden = true;
                graphiques.removeAttribute('aria-busy');
            }
        }
    });
    // Présente l’objectif de l’année sélectionnée, ou indique qu’aucun objectif n’est défini.
    function afficherObjectif() {
        document.getElementById('stats-objectif-annee').textContent = anneeObjectif;
        document.getElementById('stats-objectif-valeur').textContent = objectifActuel ?? '—';
        document.getElementById('stats-objectif-description').textContent = objectifActuel === null
            ? 'Vous n’avez pas encore défini votre objectif.'
            : 'Votre objectif de lecture pour cette année.';
        affichageObjectif.hidden = false;
        formulaireObjectif.hidden = true;
    }
    // Ouvre l’objectif annuel depuis les graphiques, même après leur remplacement.
    ecouter(graphiques, 'click', event => {
        if (!(event.target instanceof Element) || select.disabled) return;
        const bouton = event.target.closest('[data-voir-objectif]');
        if (!bouton) return;
        objectifActuel = bouton.dataset.objectif ? Number(bouton.dataset.objectif) : null;
        anneeObjectif = bouton.dataset.annee;
        erreurObjectif.hidden = true;
        succesObjectif.hidden = true;
        afficherObjectif();
        if (!modal.open) modal.showModal();
    });
    // Remplace la valeur de l’objectif par un champ de saisie prérempli.
    ecouter(modifierObjectif, 'click', () => {
        affichageObjectif.hidden = true;
        formulaireObjectif.hidden = false;
        nombreObjectif.value = objectifActuel ?? '';
        document.getElementById('stats-objectif-annee-champ').value = anneeObjectif;
        erreurObjectif.hidden = true;
        succesObjectif.hidden = true;
        nombreObjectif.focus();
        nombreObjectif.select();
    });
    // Revient à la consultation de l’objectif sans enregistrer la saisie.
    ecouter(annulerObjectif, 'click', () => {
        if (enregistrementEnCours) return;
        erreurObjectif.hidden = true;
        afficherObjectif();
        modifierObjectif.focus();
    });
    // Enregistre l’objectif puis actualise les graphiques avec la valeur confirmée par le serveur.
    ecouter(formulaireObjectif, 'submit', async event => {
        event.preventDefault();
        if (enregistrementEnCours || !formulaireObjectif.reportValidity()) return;
        enregistrementEnCours = true;
        enregistrerObjectif.disabled = true;
        annulerObjectif.disabled = true;
        fermerObjectif.disabled = true;
        nombreObjectif.readOnly = true;
        enregistrerObjectif.textContent = 'Enregistrement…';
        erreurObjectif.hidden = true;
        succesObjectif.hidden = true;
        try {
            const reponse = await fetch(formulaireObjectif.action, {
                method: 'POST', body: new FormData(formulaireObjectif),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible d’enregistrer votre objectif.');
            if (!page.isConnected) return;
            objectifActuel = resultat.objectif;
            graphiques.innerHTML = resultat.html;
            appliquerPeriodeComparaison();
            afficherObjectif();
            succesObjectif.textContent = 'Objectif enregistré.';
            succesObjectif.hidden = false;
            modifierObjectif.focus();
        } catch (exception) {
            if (!page.isConnected) return;
            erreurObjectif.textContent = (exception instanceof SyntaxError || exception instanceof TypeError)
                ? 'Impossible d’enregistrer votre objectif. Réessayez.' : exception.message;
            erreurObjectif.hidden = false;
        } finally {
            enregistrementEnCours = false;
            enregistrerObjectif.disabled = false;
            annulerObjectif.disabled = false;
            fermerObjectif.disabled = false;
            nombreObjectif.readOnly = false;
            enregistrerObjectif.textContent = 'Enregistrer';
        }
    });
    // Ferme la fenêtre de l’objectif lorsque aucun enregistrement n’est en cours.
    ecouter(fermerObjectif, 'click', () => { if (!enregistrementEnCours) modal.close(); });
    // Empêche la touche Échap de fermer l’objectif pendant son enregistrement.
    ecouter(modal, 'cancel', event => { if (enregistrementEnCours) event.preventDefault(); });
    // Annule le chargement en cours et remet la fenêtre de l’objectif dans son état initial.
    const nettoyer = () => {
        controleur?.abort();
        select.disabled = false;
        chargement.hidden = true;
        graphiques.removeAttribute('aria-busy');
        enregistrerObjectif.disabled = false;
        annulerObjectif.disabled = false;
        fermerObjectif.disabled = false;
        nombreObjectif.readOnly = false;
        enregistrerObjectif.textContent = 'Enregistrer';
        formulaireObjectif.hidden = true;
        affichageObjectif.hidden = false;
        if (modal.open) modal.close();
    };
    // Annule les chargements et remet la fenêtre à zéro avant la mise en cache ou le remplacement de la page.
    ecouter(document, 'turbo:before-cache', nettoyer);
    ecouter(document, 'turbo:before-render', nettoyer);
});
