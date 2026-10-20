// Affiche la fiche d’un livre et gère ses lectures, sa couverture et les actions sur la collection.
import { creerBadgeLecture } from './components/badge-lecture.js';
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage(ecouter => {
    const modal = document.getElementById('modal-details-livre');
    const bibliotheque = document.getElementById('bibliotheque-livres');
    const boutonFermer = document.getElementById('fermer-details-livre');
    const boutonModifierLivre = document.getElementById('modifier-details-livre');
    const boutonRetirerLivre = document.getElementById('retirer-details-livre');
    const tokenRetraitLivre = document.getElementById('details-livre-token-retrait');

    if (!modal) return;

    // Ouvre la fiche demandée par une autre partie de l’application.
    ecouter(document, 'livre:ouvrir-details', event => {
        const id = event.detail?.id;
        if (id) ouvrirDetails(id);
    });

    let requeteCourante = 0;
    let lecturesCourantes = [];
    let suppressionEnCours = false;

    // Remplit un champ de la fiche avec une valeur de remplacement si l’information manque.
    function afficherTexte(id, valeur, defaut = '—') {
        const element = document.getElementById(id);

        if (element) {
            element.textContent =
                valeur === null || valeur === undefined || valeur === '' ? defaut : String(valeur);
        }
    }

    // Actualise l’aperçu de couverture avec l’image disponible.
    function afficherCouverture(livre) {
        const conteneur = document.getElementById('details-livre-couverture');

        if (!conteneur) return;

        conteneur.replaceChildren();

        if (!livre.image_couverture) {
            const texte = document.createElement('span');
            texte.textContent = 'Aucune couverture';
            conteneur.appendChild(texte);
            return;
        }

        const image = document.createElement('img');
        const chemin = livre.image_couverture;

        image.src = /^(https?:\/\/|\/)/i.test(chemin) ? chemin : `/${chemin}`;

        image.alt = `Couverture de ${livre.titre}`;
        conteneur.appendChild(image);
    }

    // Construit les cartes des sessions avec leur statut, leurs dates et leurs notes.
    function afficherLectures(lectures) {
        const conteneur = document.getElementById('details-livre-liste-lectures');

        if (!conteneur) return;

        conteneur.replaceChildren();
        lecturesCourantes = Array.isArray(lectures) ? lectures : [];

        if (!Array.isArray(lectures) || lectures.length === 0) {
            const message = document.createElement('p');
            message.className = 'details-livre-aucune-lecture';
            message.textContent = 'Aucune lecture enregistrée pour ce livre.';
            conteneur.appendChild(message);
            return;
        }

        lectures.forEach(lecture => {
            const carte = document.createElement('article');
            carte.className = 'details-lecture-carte';
            carte.dataset.lectureId = lecture.id;

            const entete = document.createElement('div');
            entete.className = 'details-lecture-entete';

            const statut = creerBadgeLecture(lecture.statut);

            entete.appendChild(statut);
            const actions = document.createElement('div');
            actions.className = 'details-session-actions';
            for (const [action, libelle] of [['modifier', 'Modifier cette session'], ['poubelle', 'Supprimer cette session']]) {
                const bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.dataset.actionLecture = action;
                bouton.dataset.lectureId = lecture.id;
                bouton.setAttribute('aria-label', libelle);
                bouton.title = libelle;
                const image = document.createElement('img');
                image.src = conteneur.dataset[action];
                image.alt = '';
                bouton.appendChild(image);
                actions.appendChild(bouton);
            }
            entete.appendChild(actions);
            carte.appendChild(entete);

            if (lecture.note !== null && lecture.note !== undefined && lecture.note !== '') {
                const note = Number(lecture.note);

                if (Number.isFinite(note) && note >= 0 && note <= 5) {
                    const etoiles = document.createElement('div');
                    etoiles.className = 'details-lecture-etoiles';
                    etoiles.setAttribute('aria-label', `Note : ${note} sur 5`);

                    for (let numero = 1; numero <= 5; numero++) {
                        const etoile = document.createElement('span');
                        etoile.textContent = '★';

                        // Remplit chaque étoile partiellement pour représenter aussi les notes décimales.
                        const remplissage = Math.max(0, Math.min(1, note - (numero - 1))) * 100;

                        etoile.style.background = `linear-gradient(90deg, #e6a33c ${remplissage}%, #d8d8d8 ${remplissage}%)`;
                        etoile.style.backgroundClip = 'text';
                        etoile.style.webkitBackgroundClip = 'text';
                        etoile.style.webkitTextFillColor = 'transparent';

                        etoiles.appendChild(etoile);
                    }

                    carte.appendChild(etoiles);
                }
            }

            if (lecture.date_debut || lecture.date_fin) {
                const dates = document.createElement('p');
                dates.className = 'details-lecture-dates';

                const debut = lecture.date_debut || 'Date inconnue';
                const fin = lecture.date_fin || 'En cours';

                dates.textContent = `Du ${debut} au ${fin}`;
                carte.appendChild(dates);
            }

            if (lecture.avis) {
                const avis = document.createElement('p');
                avis.className = 'details-lecture-avis';
                avis.textContent = lecture.avis;
                carte.appendChild(avis);
            }

            conteneur.appendChild(carte);
        });
    }

    // Remplit la fiche du livre et affiche ses sessions de lecture.
    function afficherLivre(livre, lectures) {
        afficherTexte('details-livre-nom', livre.titre, 'Sans titre');
        afficherTexte('details-livre-sous-titre', livre.sous_titre, '');
        afficherTexte('details-livre-auteurs', livre.auteurs, 'Auteur non renseigné');
        afficherTexte('details-livre-saga', livre.saga);
        afficherTexte('details-livre-tome', livre.tome);
        afficherTexte('details-livre-genres', livre.genres);
        afficherTexte('details-livre-isbn13', livre.isbn13);
        afficherTexte('details-livre-isbn10', livre.isbn10);
        afficherTexte('details-livre-edition', livre.edition);
        afficherTexte('details-livre-publication', livre.date_publication);
        afficherTexte('details-livre-pages', livre.nombre_pages);
        afficherTexte('details-livre-langue', livre.langue);
        afficherTexte('details-livre-texte-resume', livre.resume, 'Aucun résumé renseigné.');

        afficherCouverture(livre);
        afficherLectures(lectures);
    }

    // Ouvre la fiche puis charge le livre ; ignore une réponse si la fiche a changé ou a été fermée.
    async function ouvrirDetails(id) {
        if (String(modal.dataset.livreId) !== String(id)) reinitialiserSession();
        const numeroRequete = ++requeteCourante;

        if (!modal.open) {
            modal.showModal();
        }

        afficherTexte('details-livre-nom', 'Chargement...');
        afficherTexte('details-livre-auteurs', '');
        afficherTexte('details-livre-sous-titre', '');

        try {
            const reponse = await fetch(`/livre/${encodeURIComponent(id)}/details`, {
                headers: {
                    Accept: 'application/json'
                }
            });

            const resultat = await reponse.json();

            if (!reponse.ok || !resultat.success) {
                throw new Error(resultat.message || 'Impossible de charger le livre.');
            }

            if (numeroRequete !== requeteCourante || !modal.open) return;

            modal.dataset.livreId = id;
            afficherLivre(resultat.livre, resultat.lectures);
        } catch (erreur) {
            if (numeroRequete !== requeteCourante || !modal.open) return;

            console.error('Erreur détails du livre :', erreur);
            afficherTexte('details-livre-nom', 'Impossible de charger le livre');
            afficherTexte('details-livre-auteurs', erreur.message);
        }
    }

    // Ouvre la fiche détaillée depuis une carte de la bibliothèque.
    ecouter(bibliotheque, 'click', event => {
        if (!(event.target instanceof Element)) return;

        if (event.target.closest('[data-pile-action]')) return;
        const carte = event.target.closest('.bibliotheque-livre');

        if (!carte || !bibliotheque.contains(carte)) return;

        const id = carte.dataset.livreId;

        if (!id) return;

        ouvrirDetails(id);
    });

    // Réutilise le formulaire commun pour modifier le livre affiché.

    ecouter(boutonModifierLivre, 'click', () => {
        const id = modal.dataset.livreId;

        if (!id) return;

        document.dispatchEvent(
            new CustomEvent('livre:modifier', {
                detail: { id }
            })
        );
    });

    // Retire le livre après confirmation en conservant son historique de lecture.

    ecouter(boutonRetirerLivre, 'click', async () => {
        const id = modal.dataset.livreId;

        if (!id) return;

        const confirmation = window.confirm(
            'Retirer ce livre de ta collection ?\n\n' +
                'Ses informations et son historique de lecture seront conservés.'
        );

        if (!confirmation) return;

        boutonRetirerLivre.disabled = true;

        try {
            const donnees = new FormData();
            donnees.append('_token', tokenRetraitLivre?.value || '');

            const reponse = await fetch(`/livre/${encodeURIComponent(id)}/retirer`, {
                method: 'POST',
                body: donnees,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const resultat = await reponse.json();

            if (!reponse.ok || !resultat.success) {
                throw new Error(resultat.message || 'Impossible de retirer le livre.');
            }

            modal.close();
            window.location.reload();
        } catch (erreur) {
            console.error('Erreur retrait du livre :', erreur);
            alert(erreur.message);
        } finally {
            boutonRetirerLivre.disabled = false;
        }
    });

    // Prépare les commandes du formulaire partagé entre ajout et modification d’une session.

    const boutonAjouterLecture = document.getElementById('ajouter-lecture-details');
    const formulaireLectureZone = document.getElementById('details-formulaire-lecture');
    const formulaireLecture = document.getElementById('form-ajouter-lecture');
    const boutonAnnulerLecture = document.getElementById('annuler-ajout-lecture');
    const erreurLecture = document.getElementById('lecture-message-erreur');

    const champStatutLecture = formulaireLecture?.elements.namedItem('statut');
    const erreurSession = document.getElementById('details-erreur-session');

    // Préremplit la date locale du jour sans remplacer une date déjà saisie.
    function actualiserDatesSession() {
        // Applique la même note automatique lors de la création ou modification d’une session.
        const note = formulaireLecture?.elements.namedItem('note');
        if (note) {
            note.readOnly = champStatutLecture?.value === 'Abandonné';
            if (note.readOnly) note.value = '0';
        }
        const fin = formulaireLecture?.elements.namedItem('dateFin');
        const debut = formulaireLecture?.elements.namedItem('dateDebut');
        const termine = champStatutLecture?.value === 'Terminé';
        if (fin) {
            fin.disabled = !termine;
            if (termine && !fin.value) {
                const jour = new Date();
                fin.value = `${jour.getFullYear()}-${String(jour.getMonth() + 1).padStart(2, '0')}-${String(jour.getDate()).padStart(2, '0')}`;
            }
        }
        if (debut) debut.disabled = ['Pas commencé', 'À lire'].includes(champStatutLecture?.value);
    }
    ecouter(champStatutLecture, 'change', actualiserDatesSession);

    // Efface le mode modification pour ne jamais réutiliser l’identifiant d’une ancienne session.
    function reinitialiserSession() {
        formulaireLecture?.reset();
        if (formulaireLectureZone) formulaireLectureZone.hidden = true;
        if (boutonAjouterLecture) boutonAjouterLecture.hidden = false;
        if (erreurLecture) erreurLecture.hidden = true;
        if (erreurSession) erreurSession.hidden = true;
    }

    // Préremplit la session sélectionnée ou prépare une nouvelle session terminée par défaut.
    function ouvrirFormulaireSession(lecture = null) {
        reinitialiserSession();
        if (!formulaireLecture || !formulaireLectureZone) return;
        const valeurs = {
            lectureId: lecture?.id || '',
            statut: lecture?.statut || 'Terminé',
            dateDebut: lecture?.date_debut || '',
            dateFin: lecture?.date_fin || '',
            note: lecture?.note ?? '',
            avis: lecture?.avis || ''
        };
        for (const [nom, valeur] of Object.entries(valeurs)) {
            const champ = formulaireLecture.elements.namedItem(nom);
            if (champ) champ.value = valeur;
        }
        afficherTexte('details-titre-formulaire-lecture', lecture ? 'Modifier la session de lecture' : 'Ajouter une session de lecture');
        actualiserDatesSession();
        formulaireLectureZone.hidden = false;
        if (boutonAjouterLecture) boutonAjouterLecture.hidden = true;
        formulaireLectureZone.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
    }
    ecouter(boutonAjouterLecture, 'click', () => ouvrirFormulaireSession());
    ecouter(boutonAnnulerLecture, 'click', reinitialiserSession);

    // Un seul écouteur sur la liste gère les icônes, même après le rechargement des sessions.
    ecouter(document.getElementById('details-livre-liste-lectures'), 'click', async event => {
        const bouton = event.target.closest('[data-action-lecture]');
        if (!bouton || suppressionEnCours) return;
        const lecture = lecturesCourantes.find(item => String(item.id) === bouton.dataset.lectureId);
        if (!lecture) return;
        if (bouton.dataset.actionLecture === 'modifier') {
            ouvrirFormulaireSession(lecture);
            return;
        }
        if (!window.confirm('Supprimer définitivement cette session de lecture ? Le livre et les autres sessions seront conservés.')) return;
        const livreId = modal.dataset.livreId;
        if (!livreId) return;
        suppressionEnCours = true;
        bouton.disabled = true;
        try {
            const donnees = new FormData();
            donnees.set('_token', formulaireLecture.elements.namedItem('_token').value);
            const reponse = await fetch(`/livre/${encodeURIComponent(livreId)}/lecture/${encodeURIComponent(lecture.id)}/supprimer`, {
                method: 'POST', body: donnees,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible de supprimer cette session.');
            await actualiserApresSession(livreId);
        } catch (erreur) {
            if (erreurSession && String(modal.dataset.livreId) === String(livreId)) {
                erreurSession.textContent = erreur.message;
                erreurSession.hidden = false;
            }
        } finally {
            suppressionEnCours = false;
            bouton.disabled = false;
        }
    });

    // Actualise la fiche et les compteurs de Mes lectures après chaque création, modification ou suppression.
    async function actualiserApresSession(livreId) {
        if (String(modal.dataset.livreId) === String(livreId)) {
            reinitialiserSession();
            if (modal.open) await ouvrirDetails(livreId);
        }
        document.dispatchEvent(new CustomEvent('lecture:ajoutee', { detail: { livreId } }));
        if (!document.getElementById('page-mes-lectures')) window.location.reload();
    }

    // Enregistre la session puis informe les pages concernées pour actualiser leur affichage.
    ecouter(formulaireLecture, 'submit', async event => {
        event.preventDefault();

        const id = modal.dataset.livreId;
        if (!id) return;

        const boutonEnregistrer = formulaireLecture.querySelector('button[type="submit"]');

        if (boutonEnregistrer?.disabled) return;
        if (boutonEnregistrer) boutonEnregistrer.disabled = true;

        if (erreurLecture) {
            erreurLecture.hidden = true;
            erreurLecture.textContent = '';
        }

        try {
            const lectureId = formulaireLecture.elements.namedItem('lectureId')?.value;
            const chemin = lectureId
                ? `/livre/${encodeURIComponent(id)}/lecture/${encodeURIComponent(lectureId)}/modifier`
                : `/livre/${encodeURIComponent(id)}/lecture/ajouter`;
            const reponse = await fetch(chemin, {
                method: 'POST',
                body: new FormData(formulaireLecture),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const resultat = await reponse.json();

            if (!reponse.ok || !resultat.success) {
                throw new Error(resultat.message || 'Impossible d’enregistrer la lecture.');
            }

            await actualiserApresSession(id);
        } catch (erreur) {
            console.error('Erreur ajout de lecture :', erreur);

            if (erreurLecture) {
                erreurLecture.textContent = erreur.message;
                erreurLecture.hidden = false;
            }
        } finally {
            if (boutonEnregistrer) {
                boutonEnregistrer.disabled = false;
            }
        }
    });

    // Ferme la fenêtre en cours.
    ecouter(boutonFermer, 'click', () => {
        modal.close();
    });

    // Invalide les réponses tardives à la fermeture de la fiche.
    ecouter(modal, 'close', () => {
        requeteCourante++;
        delete modal.dataset.livreId;
    });
});
