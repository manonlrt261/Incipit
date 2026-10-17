// Gère la recherche de prêts, leur ajout et leur suppression avec confirmation.
import { initialiserPage } from './components/initialisation-page.js';
import { creerFiltresLivres } from './components/filtres-livres.js';


initialiserPage((ecouter) => {
    const page = document.getElementById('page-mes-prets');

    if (!page) return;

    const recherche = document.getElementById('prets-recherche');
    const liste = page.querySelector('.prets-liste');

    const messageAucunResultat = document.getElementById(
        'prets-aucun-resultat'
    );

    const messageListeVide = document.getElementById(
        'prets-liste-vide'
    );

    const messageErreur = document.getElementById(
        'prets-message-erreur'
    );

    const modalSuppression = document.getElementById(
        'modal-suppression-pret'
    );

    const descriptionSuppression = document.getElementById(
        'prets-suppression-description'
    );

    const boutonConfirmer = document.getElementById(
        'confirmer-suppression-pret'
    );

    const boutonAnnuler = document.getElementById(
        'annuler-suppression-pret'
    );

    const modalAjout = document.getElementById(
        'modal-ajout-pret'
    );

    const formulaireAjout = document.getElementById('form-ajout-pret');
    const erreurAjout = document.getElementById('prets-ajout-erreur');
    const boutonEnregistrer = document.getElementById('enregistrer-ajout-pret');
    const fermerAjout = document.getElementById('fermer-ajout-pret');
    const annulerAjout = document.getElementById('annuler-ajout-pret');
    let ajoutEnCours = false;

    let pretASupprimer = null;

    // Ignore les accents et les majuscules pour faciliter les recherches.
    const normaliser = texte => String(texte).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').trim();

    // 1. RECHERCHE INSTANTANÉE

    const filtres = creerFiltresLivres({
        page, conteneur: liste, selecteur: '.prets-carte', recherche, ecouter,
        actualiser({ total, visibles }) {
            messageListeVide.hidden = total !== 0;
            messageAucunResultat.hidden = total === 0 || visibles !== 0;
        }
    });
    const filtrerPrets = filtres.appliquer;

    // Actualise les résultats à mesure que la recherche est saisie.
    ecouter(recherche, 'input', filtrerPrets);
    ecouter(document.getElementById('prets-formulaire-recherche'), 'submit', event => {
        event.preventDefault();
        filtrerPrets();
    });

    // 2. OUVERTURE DE LA CONFIRMATION DE SUPPRESSION

    ecouter(liste, 'click', event => {
        if (!(event.target instanceof Element) || boutonConfirmer.disabled) return;
        const bouton = event.target.closest('.prets-supprimer');

        if (!bouton) return;

        pretASupprimer = {
            id: bouton.dataset.id,
            titre: bouton.dataset.titre,
            url: bouton.dataset.url,
            token: bouton.dataset.token
        };

        descriptionSuppression.textContent =
            `Voulez-vous vraiment supprimer le prêt du livre « ${pretASupprimer.titre} » ?`;

        messageErreur.hidden = true;
        messageErreur.textContent = '';

        modalSuppression.showModal();
    });

    // 3. ANNULATION DE LA SUPPRESSION

    ecouter(boutonAnnuler, 'click', () => {
        modalSuppression.close();
    });

    // Gère la fermeture de la confirmation de suppression.
    ecouter(modalSuppression, 'close', () => {
        pretASupprimer = null;
    });

    // Gère la fermeture de la confirmation de suppression.
    ecouter(modalSuppression, 'cancel', event => {
        if (boutonConfirmer.disabled) event.preventDefault();
    });

    // 4. SUPPRESSION DU PRÊT EN BASE DE DONNÉES

    ecouter(boutonConfirmer, 'click', async () => {
        if (!pretASupprimer || boutonConfirmer.disabled) return;

        const pret = pretASupprimer;

        boutonConfirmer.disabled = true;
        boutonAnnuler.disabled = true;
        boutonConfirmer.textContent = 'Suppression...';

        try {
            const donnees = new FormData();
            donnees.append('_token', pret.token);

            const reponse = await fetch(pret.url, {
                method: 'POST',
                body: donnees,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const resultat = await reponse.json();

            if (!reponse.ok || !resultat.success) {
                throw new Error(
                    resultat.message || 'La suppression a échoué.'
                );
            }

            const carte = [
                ...liste.querySelectorAll('.prets-carte')
            ].find(element => element.dataset.pretId === pret.id);

            carte?.remove();

            modalSuppression.close();

            filtrerPrets();

        } catch (erreur) {
            modalSuppression.close();

            messageErreur.textContent =
                erreur.message || 'Impossible de supprimer ce prêt.';

            messageErreur.hidden = false;

        } finally {
            boutonConfirmer.disabled = false;
            boutonAnnuler.disabled = false;
            boutonConfirmer.textContent = 'Supprimer';
        }
    });

    const titrePret = document.getElementById('pret-titre');
    const auteurPret = document.getElementById('pret-auteur');
    const livreId = document.getElementById('pret-livre-id');
    const suggestions = document.getElementById('pret-livres-possedes');
    const choixLivres = [...suggestions.querySelectorAll('.prets-suggestion')];

    // Propose les livres possédés dont le titre ou l’auteur correspond à la saisie du prêt.
    function rechercherLivres() {
        const texte = normaliser(titrePret.value);
        suggestions.hidden = !texte;
        let visibles = 0;
        choixLivres.forEach(bouton => {
            bouton.hidden = !normaliser(`${bouton.dataset.titre} ${bouton.dataset.auteur}`).includes(texte);
            if (!bouton.hidden) visibles++;
        });
        document.getElementById('pret-suggestions-vide').hidden = visibles !== 0;
    }
    // Recherche les livres possédés sans imposer leur sélection pour créer un prêt.
    ecouter(titrePret, 'input', () => {
        livreId.value = '';
        rechercherLivres();
    });
    // Recherche les livres possédés sans imposer leur sélection pour créer un prêt.
    ecouter(titrePret, 'focus', rechercherLivres);
    // Détache le prêt du livre suggéré si l’auteur est modifié manuellement.
    ecouter(auteurPret, 'input', () => { livreId.value = ''; });
    // Reprend le titre et l’auteur du livre possédé choisi dans les suggestions.
    ecouter(suggestions, 'click', event => {
        if (!(event.target instanceof Element)) return;
        const bouton = event.target.closest('.prets-suggestion');
        if (!bouton) return;
        titrePret.value = bouton.dataset.titre;
        auteurPret.value = bouton.dataset.auteur;
        livreId.value = bouton.dataset.livreId;
        suggestions.hidden = true;
        document.getElementById('pret-emprunteur').focus();
    });

    // 5. OUVERTURE DE LA MODALE D'AJOUT

    ecouter(document.getElementById('ouvrir-ajout-pret'), 'click', () => {
            if (!modalAjout.open) {
                formulaireAjout.reset();
                suggestions.hidden = true;
                erreurAjout.hidden = true;
                erreurAjout.textContent = '';
                modalAjout.showModal();
            }
        });

    // 6. FERMETURE DE LA MODALE D'AJOUT

    function fermerModalAjout() {
        if (modalAjout.open && !ajoutEnCours) {
            modalAjout.close();
        }
    }

    ecouter(fermerAjout, 'click', fermerModalAjout);

    ecouter(annulerAjout, 'click', fermerModalAjout);

    ecouter(modalAjout, 'cancel', event => {
        if (ajoutEnCours) event.preventDefault();
    });

    // Enregistre le nouveau prêt et actualise la liste après validation du serveur.
    ecouter(formulaireAjout, 'submit', async event => {
        event.preventDefault();
        if (ajoutEnCours) return;
        for (const nom of ['titre', 'auteur', 'emprunteur']) {
            const champ = formulaireAjout.elements.namedItem(nom);
            champ.value = champ.value.trim();
        }
        if (!formulaireAjout.reportValidity()) return;

        ajoutEnCours = true;
        boutonEnregistrer.disabled = true;
        fermerAjout.disabled = true;
        annulerAjout.disabled = true;
        boutonEnregistrer.textContent = 'Enregistrement…';
        erreurAjout.hidden = true;
        erreurAjout.textContent = '';

        try {
            const reponse = await fetch(formulaireAjout.action, {
                method: 'POST',
                body: new FormData(formulaireAjout),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) {
                throw new Error(resultat.message || 'Impossible d’enregistrer ce prêt.');
            }
            modalAjout.close();
            window.location.reload();
        } catch (erreur) {
            erreurAjout.textContent = erreur.message || 'Impossible d’enregistrer ce prêt.';
            erreurAjout.hidden = false;
        } finally {
            ajoutEnCours = false;
            boutonEnregistrer.disabled = false;
            fermerAjout.disabled = false;
            annulerAjout.disabled = false;
            boutonEnregistrer.textContent = 'Enregistrer le prêt';
        }
    });

    // 7. INITIALISATION

    filtrerPrets();
});
