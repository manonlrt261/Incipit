// Gère les prêts, les contacts enregistrés et leurs suggestions dans le formulaire de prêt.
import { initialiserPage } from './components/initialisation-page.js';
import { creerFiltresLivres } from './components/filtres-livres.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('page-mes-prets');

    if (!page) return;

    const recherche = document.getElementById('prets-recherche');
    const liste = page.querySelector('.prets-liste');
    const compteur = document.getElementById('prets-compteur');

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

    // Les onglets gardent les prêts en place et permettent aussi la navigation au clavier.
    const onglets = [...page.querySelectorAll('[role="tab"]')];
    function choisirOnglet(bouton) {
        onglets.forEach(onglet => {
            const actif = onglet === bouton;
            onglet.setAttribute('aria-selected', String(actif));
            onglet.tabIndex = actif ? 0 : -1;
            document.getElementById(onglet.getAttribute('aria-controls')).hidden = !actif;
        });
        const rechercheForm = document.getElementById('prets-formulaire-recherche');
        rechercheForm.hidden = bouton.id === 'prets-tab-contacts';
    }
    onglets.forEach((bouton, index) => {
        ecouter(bouton, 'click', () => choisirOnglet(bouton));
        ecouter(bouton, 'keydown', event => {
            const cibles = { ArrowRight: (index + 1) % onglets.length, ArrowLeft: (index + onglets.length - 1) % onglets.length, Home: 0, End: onglets.length - 1 };
            if (!(event.key in cibles)) return;
            event.preventDefault();
            const cible = onglets[cibles[event.key]];
            choisirOnglet(cible); cible.focus();
        });
    });

    const listeContacts = document.getElementById('prets-contacts');
    const contactsVides = document.getElementById('prets-contacts-vide');
    const messageContacts = document.getElementById('prets-contacts-message');
    const modalContact = document.getElementById('modal-contact');
    const formContact = document.getElementById('form-contact');
    const contactId = document.getElementById('contact-id');
    const contactPrenom = document.getElementById('contact-prenom');
    const contactNom = document.getElementById('contact-nom');
    const erreurContact = document.getElementById('contact-erreur');
    const saisieContact = document.getElementById('pret-emprunteur');
    const contactPretId = document.getElementById('pret-contact-id');
    const suggestionsContacts = document.getElementById('pret-contacts-suggestions');
    const alphabetContacts = new Intl.Collator('fr', { sensitivity: 'base' });
    let contactEnregistrement = false;

    // Relit les cartes pour utiliser les contacts ajoutés ou modifiés sans recharger la page.
    function contactsDisponibles() {
        return [...listeContacts.querySelectorAll('.prets-contact')].map(carte => ({ ...carte.dataset, carte }));
    }
    const nomContact = contact => `${contact.prenom} ${contact.nom}`.trim();

    // Recrée les suggestions à partir de la liste actuelle, y compris après une création ou une modification.
    function rechercherContacts() {
        const texte = normaliser(saisieContact.value);
        suggestionsContacts.replaceChildren();
        suggestionsContacts.hidden = !texte;
        if (!texte) return;
        const contacts = contactsDisponibles().filter(contact => normaliser(nomContact(contact)).includes(texte));
        contacts.forEach(contact => {
            const bouton = document.createElement('button');
            bouton.type = 'button'; bouton.className = 'prets-suggestion';
            bouton.dataset.contactId = contact.contactId;
            const nom = document.createElement('strong'); nom.textContent = nomContact(contact);
            const precision = document.createElement('span');
            precision.textContent = `Contact n°${contact.contactId} · ${contact.encours} prêt(s) en cours`;
            bouton.append(nom, precision); suggestionsContacts.appendChild(bouton);
        });
        if (!contacts.length) {
            const message = document.createElement('p');
            message.className = 'prets-suggestions-message';
            message.textContent = 'Aucun contact trouvé. Vous pouvez saisir un nouveau prénom.';
            suggestionsContacts.appendChild(message);
        }
    }
    // Une saisie manuelle détache le contact sélectionné et relance les suggestions.
    ecouter(saisieContact, 'input', () => { contactPretId.value = ''; rechercherContacts(); });
    ecouter(saisieContact, 'focus', rechercherContacts);
    // Conserve l’identifiant du contact choisi pour distinguer les personnes portant le même prénom.
    ecouter(suggestionsContacts, 'click', event => {
        if (!(event.target instanceof Element)) return;
        const bouton = event.target.closest('[data-contact-id]');
        const contact = contactsDisponibles().find(contact => contact.contactId === bouton?.dataset.contactId);
        if (!contact) return;
        contactPretId.value = contact.contactId;
        saisieContact.value = nomContact(contact);
        suggestionsContacts.hidden = true;
    });
    // Masque les suggestions de contacts lorsque le clic a lieu en dehors de leur liste.
    ecouter(document, 'click', event => {
        if (event.target instanceof Element && event.target !== saisieContact && !suggestionsContacts.contains(event.target)) suggestionsContacts.hidden = true;
    });

    // Classe les contacts par prénom puis par nom et actualise le message de liste vide.
    function trierContacts() {
        const contacts = contactsDisponibles().sort((a, b) => alphabetContacts.compare(a.prenom, b.prenom) || alphabetContacts.compare(a.nom, b.nom) || Number(a.contactId) - Number(b.contactId));
        contacts.forEach(contact => listeContacts.appendChild(contact.carte));
        contactsVides.hidden = contacts.length !== 0;
    }

    // Met à jour les prêts associés pour que leur affichage et le tri Contact utilisent le nouveau nom.
    function afficherContact(contact) {
        const ancienne = contactsDisponibles().find(c => c.contactId === String(contact.id));
        const carte = document.createElement('article');
        carte.className = 'prets-contact';
        carte.dataset.contactId = String(contact.id);
        for (const champ of ['prenom', 'nom', 'encours', 'total', 'modifier', 'details', 'supprimer']) carte.dataset[champ] = String(contact[champ]);
        const infos = document.createElement('div');
        const titre = document.createElement('h3'); titre.textContent = nomContact(contact);
        const nombre = document.createElement('p'); nombre.textContent = `${contact.encours} prêt${contact.encours > 1 ? 's' : ''} en cours`;
        infos.append(titre, nombre);
        const actions = document.createElement('div'); actions.className = 'prets-contact-actions';
        // Conserve aussi l’accès aux prêts sur les contacts créés ou modifiés sans rechargement.
        const voirPrets = document.createElement('button');
        voirPrets.type = 'button';
        voirPrets.className = 'prets-contact-voir';
        voirPrets.dataset.actionContact = 'voir';
        voirPrets.textContent = 'Voir les prêts';
        voirPrets.setAttribute('aria-label', `Voir les prêts de ${nomContact(contact)}`);
        actions.appendChild(voirPrets);
        for (const action of ['modifier', 'supprimer']) {
            const bouton = document.createElement('button'); bouton.type = 'button'; bouton.dataset.actionContact = action;
            bouton.setAttribute('aria-label', `${action === 'modifier' ? 'Modifier' : 'Supprimer'} ${nomContact(contact)}`);
            const image = document.createElement('img'); image.alt = ''; image.src = action === 'modifier' ? listeContacts.dataset.imageModifier : listeContacts.dataset.imageSupprimer;
            bouton.appendChild(image); actions.appendChild(bouton);
        }
        carte.append(infos, actions);
        if (ancienne) ancienne.carte.replaceWith(carte); else listeContacts.appendChild(carte);
        trierContacts();
        liste.querySelectorAll('.prets-carte').forEach(pret => {
            if (pret.dataset.contactId !== String(contact.id)) return;
            pret.dataset.contactPrenom = contact.prenom; pret.dataset.contactNom = contact.nom;
            pret.querySelector('.prets-emprunteur strong').textContent = nomContact(contact);
            pret.dataset.recherche = `${pret.querySelector('h2').textContent} ${pret.querySelector('.prets-auteur').textContent} ${nomContact(contact)} ${pret.dataset.saga || ''}`;
        });
        if (contactPretId.value === String(contact.id)) saisieContact.value = nomContact(contact);
        filtrerPrets();
    }

    // Prépare le même formulaire pour créer un contact ou modifier celui qui est sélectionné.
    function ouvrirContact(contact = null) {
        formContact.reset();
        contactId.value = contact?.contactId || '';
        contactPrenom.value = contact?.prenom || ''; contactNom.value = contact?.nom || '';
        formContact.dataset.enregistrement = contact?.modifier || formContact.action;
        document.getElementById('contact-titre').textContent = contact ? 'Modifier le contact' : 'Ajouter un contact';
        erreurContact.hidden = true;
        modalContact.showModal(); contactPrenom.focus();
    }
    ecouter(document.getElementById('ouvrir-ajout-contact'), 'click', () => ouvrirContact());
    // Protège la saisie contre une fermeture pendant l’enregistrement du contact.
    modalContact.querySelectorAll('[data-fermer-contact]').forEach(bouton => ecouter(bouton, 'click', () => { if (!contactEnregistrement) modalContact.close(); }));
    ecouter(modalContact, 'cancel', event => { if (contactEnregistrement) event.preventDefault(); });
    // Enregistre le contact puis met à jour ses cartes et les prêts associés sans recharger la page.
    ecouter(formContact, 'submit', async event => {
        event.preventDefault();
        if (contactEnregistrement) return;
        contactPrenom.value = contactPrenom.value.trim(); contactNom.value = contactNom.value.trim();
        if (!formContact.reportValidity()) return;
        contactEnregistrement = true; erreurContact.hidden = true;
        modalContact.querySelectorAll('button').forEach(b => { b.disabled = true; });
        contactPrenom.readOnly = true; contactNom.readOnly = true;
        try {
            const reponse = await fetch(formContact.dataset.enregistrement, { method: 'POST', body: new FormData(formContact), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible d’enregistrer ce contact.');
            if (!page.isConnected) return;
            afficherContact(resultat.contact); modalContact.close();
            messageContacts.textContent = 'Contact enregistré.'; messageContacts.hidden = false;
        } catch (erreur) {
            erreurContact.textContent = erreur instanceof TypeError || erreur instanceof SyntaxError ? 'Impossible d’enregistrer ce contact. Réessayez.' : erreur.message;
            erreurContact.hidden = false;
        } finally {
            contactEnregistrement = false;
            modalContact.querySelectorAll('button').forEach(b => { b.disabled = false; });
            contactPrenom.readOnly = false; contactNom.readOnly = false;
        }
    });

    const modalSuppressionContact = document.getElementById('modal-suppression-contact');
    const descriptionContact = document.getElementById('suppression-contact-description');
    const erreurSuppressionContact = document.getElementById('suppression-contact-erreur');
    const confirmerContact = document.getElementById('confirmer-suppression-contact');
    const annulerContact = document.getElementById('annuler-suppression-contact');
    let contactASupprimer = null;
    let suppressionContactEnCours = false;
    let demandeSuppression = 0;
    // Adapte l’avertissement et bloque la suppression si le contact possède encore des prêts liés.
    function avertirSuppression(contact) {
        confirmerContact.disabled = Number(contact.total) > 0;
        descriptionContact.textContent = Number(contact.encours) > 0
            ? `${nomContact(contact)} a encore ${contact.encours} prêt(s) en cours. Supprimez les prêts concernés avant de supprimer ce contact.`
            : Number(contact.total) > 0
                ? 'Ce contact est lié à l’historique de prêts. Supprimez les prêts concernés avant de supprimer ce contact.'
                : `Voulez-vous supprimer ${nomContact(contact)} ? Cette suppression est définitive.`;
    }
    const modalPretsContact = document.getElementById('modal-prets-contact');
    const listePretsContact = document.getElementById('contact-liste-prets');

    // Construit une liste en lecture seule à partir de tous les prêts, même ceux masqués par les filtres.
    function afficherPretsContact(contact) {
        document.getElementById('contact-prets-titre').textContent = `Prêts de ${nomContact(contact)}`;
        // Reconstruit le contenu à chaque ouverture pour éviter les doublons et les anciennes informations.
        listePretsContact.replaceChildren();
        // Compare les identifiants plutôt que les noms pour séparer les contacts homonymes.
        const prets = [...liste.querySelectorAll('.prets-carte')]
            .filter(pret => pret.dataset.contactId === contact.contactId);
        // Reprend uniquement le titre et l’auteur, sans copier les boutons de suppression ni les identifiants des cartes.
        prets.forEach(pret => {
            const carte = document.createElement('article');
            carte.className = 'prets-carte';
            const informations = document.createElement('div');
            informations.className = 'prets-informations';
            const titre = document.createElement('h2');
            titre.textContent = pret.querySelector('h2').textContent;
            const auteur = document.createElement('p');
            auteur.className = 'prets-auteur';
            auteur.textContent = pret.querySelector('.prets-auteur').textContent;
            informations.append(titre, auteur);
            carte.appendChild(informations);
            listePretsContact.appendChild(carte);
        });
        // Affiche une explication si aucun prêt n’est lié à la personne sélectionnée.
        document.getElementById('contact-prets-vide').hidden = prets.length !== 0;
        if (!modalPretsContact.open) modalPretsContact.showModal();
    }

    // Ferme la consultation sans changer d’onglet ni modifier les filtres de la liste des prêts.
    ecouter(document.getElementById('fermer-prets-contact'), 'click', () => modalPretsContact.close());

    // Partage les actions des contacts ; seule une suppression nécessite de relire les liens sur le serveur.
    ecouter(listeContacts, 'click', async event => {
        if (!(event.target instanceof Element)) return;
        const bouton = event.target.closest('[data-action-contact]');
        const carte = bouton?.closest('.prets-contact');
        if (!carte) return;
        const contact = { ...carte.dataset };
        // La délégation couvre aussi les boutons des contacts ajoutés ou modifiés sans rechargement.
        if (bouton.dataset.actionContact === 'voir') {
            afficherPretsContact(contact);
            return;
        }
        if (bouton.dataset.actionContact === 'modifier') { ouvrirContact(contact); return; }
        const demande = ++demandeSuppression;
        contactASupprimer = contact;
        confirmerContact.disabled = true; erreurSuppressionContact.hidden = true;
        descriptionContact.textContent = 'Vérification des prêts associés…';
        modalSuppressionContact.showModal();
        try {
            const reponse = await fetch(contact.details, { headers: { Accept: 'application/json' } });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible de vérifier les prêts de ce contact.');
            if (demande !== demandeSuppression || !modalSuppressionContact.open) return;
            avertirSuppression(resultat.contact);
        } catch (erreur) {
            if (demande !== demandeSuppression || !modalSuppressionContact.open) return;
            erreurSuppressionContact.textContent = erreur.message; erreurSuppressionContact.hidden = false;
        }
    });
    // Évite les fermetures pendant la suppression et ignore les vérifications devenues obsolètes.
    ecouter(annulerContact, 'click', () => { if (!suppressionContactEnCours) modalSuppressionContact.close(); });
    ecouter(modalSuppressionContact, 'cancel', event => { if (suppressionContactEnCours) event.preventDefault(); });
    ecouter(modalSuppressionContact, 'close', () => { demandeSuppression++; contactASupprimer = null; });
    // Supprime le contact après confirmation et retire sa sélection du formulaire de prêt.
    ecouter(confirmerContact, 'click', async () => {
        if (!contactASupprimer || confirmerContact.disabled || suppressionContactEnCours) return;
        const contact = contactASupprimer;
        let suppressionBloquee = false;
        suppressionContactEnCours = true; confirmerContact.disabled = true; annulerContact.disabled = true;
        erreurSuppressionContact.hidden = true;
        try {
            const donnees = new FormData(); donnees.append('_token', formContact.elements.namedItem('_token').value);
            const reponse = await fetch(contact.supprimer, { method: 'POST', body: donnees, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) {
                if (resultat.contact) {
                    suppressionBloquee = Number(resultat.contact.total) > 0;
                    avertirSuppression(resultat.contact);
                }
                throw new Error(resultat.message || 'Impossible de supprimer ce contact.');
            }
            contactsDisponibles().find(c => c.contactId === contact.contactId)?.carte.remove();
            if (contactPretId.value === contact.contactId) { contactPretId.value = ''; saisieContact.value = ''; }
            trierContacts(); suggestionsContacts.hidden = true;
            modalSuppressionContact.close();
            messageContacts.textContent = 'Contact supprimé.'; messageContacts.hidden = false;
        } catch (erreur) {
            erreurSuppressionContact.textContent = erreur.message; erreurSuppressionContact.hidden = false;
            confirmerContact.disabled = suppressionBloquee;
        } finally {
            suppressionContactEnCours = false; annulerContact.disabled = false;
        }
    });

    // Combine la recherche par titre, saga, auteur ou nom complet du contact avec les filtres et les tris existants.

    const filtres = creerFiltresLivres({
        page, conteneur: liste, selecteur: '.prets-carte', recherche, ecouter,
        actualiser({ total, visibles }) {
            // Met à jour le compteur après une recherche, un filtre ou une suppression.
            compteur.textContent = `${visibles} ${visibles === 1 ? 'livre prêté' : 'livres prêtés'}${visibles < total ? ` sur ${total}` : ''}`;
            messageListeVide.hidden = total !== 0;
            messageAucunResultat.hidden = total === 0 || visibles !== 0;
        }
    });
    const filtrerPrets = filtres.appliquer;

    // Recherche dans les données des cartes, sans tenir compte des accents ni des majuscules.
    ecouter(recherche, 'input', filtrerPrets);
    // Le bouton Rechercher et la touche Entrée appliquent les mêmes critères sans rechargement.
    ecouter(document.getElementById('prets-formulaire-recherche'), 'submit', event => {
        event.preventDefault();
        filtrerPrets();
    });

    // Prépare la confirmation avec le titre et les données du prêt sélectionné.

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

    // Annule la suppression et conserve le prêt dans la liste.

    ecouter(boutonAnnuler, 'click', () => {
        modalSuppression.close();
    });

    // Oublie le prêt sélectionné lorsque la confirmation est fermée.
    ecouter(modalSuppression, 'close', () => {
        pretASupprimer = null;
    });

    // Empêche Échap de fermer la confirmation pendant la suppression du prêt.
    ecouter(modalSuppression, 'cancel', event => {
        if (boutonConfirmer.disabled) event.preventDefault();
    });

    // Supprime le prêt confirmé, puis actualise le contact associé et les compteurs.

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

            const contactLie = contactsDisponibles().find(c => c.contactId === carte?.dataset.contactId);
            carte?.remove();
            if (contactLie) {
                try {
                    const reponseContact = await fetch(contactLie.details, { headers: { Accept: 'application/json' } });
                    const resultatContact = await reponseContact.json();
                    if (reponseContact.ok && resultatContact.success && page.isConnected) afficherContact(resultatContact.contact);
                } catch {
                    // La suppression du prêt reste acquise ; l’avertissement relira les liens avant toute suppression de contact.
                }
            }

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

    // Propose les livres possédés dont le titre, l’auteur ou la saga correspond à la saisie du prêt.
    function rechercherLivres() {
        const texte = normaliser(titrePret.value);
        suggestions.hidden = !texte;
        let visibles = 0;
        choixLivres.forEach(bouton => {
            bouton.hidden = !normaliser(`${bouton.dataset.titre} ${bouton.dataset.auteur} ${bouton.dataset.saga || ''}`).includes(texte);
            if (!bouton.hidden) visibles++;
        });
        document.getElementById('pret-suggestions-vide').hidden = visibles !== 0;
    }
    // Recherche les livres possédés sans imposer leur sélection pour créer un prêt.
    ecouter(titrePret, 'input', () => {
        livreId.value = '';
        rechercherLivres();
    });
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

    // Ouvre un formulaire de prêt vide et efface les anciennes suggestions et erreurs.

    ecouter(document.getElementById('ouvrir-ajout-pret'), 'click', () => {
            if (!modalAjout.open) {
                formulaireAjout.reset();
                suggestions.hidden = true;
                suggestionsContacts.hidden = true;
                erreurAjout.hidden = true;
                erreurAjout.textContent = '';
                modalAjout.showModal();
            }
        });

    // Ferme le formulaire de prêt uniquement si aucun enregistrement n’est en cours.

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

    // Applique les filtres initiaux et calcule le nombre de prêts affichés.

    filtrerPrets();
});
