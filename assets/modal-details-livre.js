// Affiche la fiche d’un livre et gère ses lectures, sa couverture et les actions sur la collection.
import { initialiserPage } from './components/initialisation-page.js';
import { creerGestionnaireCouverture } from './components/couverture.js';

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

            const statut = document.createElement('strong');
            statut.className = 'details-lecture-statut';
            statut.textContent = lecture.statut || 'Statut non renseigné';

            entete.appendChild(statut);
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

        const carte = event.target.closest('.bibliotheque-livre');

        if (!carte || !bibliotheque.contains(carte)) return;

        const id = carte.dataset.livreId;

        if (!id) return;

        ouvrirDetails(id);
    });

    // OUVRIR LE FORMULAIRE DE MODIFICATION

    ecouter(boutonModifierLivre, 'click', () => {
        const id = modal.dataset.livreId;

        if (!id) return;

        // Prévenir le formulaire d'ajout qu'un livre doit être modifié
        document.dispatchEvent(
            new CustomEvent('livre:modifier', {
                detail: { id }
            })
        );
    });

    // RETIRER UN LIVRE DE MA COLLECTION

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

    // AJOUTER UNE SESSION DE LECTURE

    const boutonAjouterLecture = document.getElementById('ajouter-lecture-details');
    const formulaireLectureZone = document.getElementById('details-formulaire-lecture');
    const formulaireLecture = document.getElementById('form-ajouter-lecture');
    const boutonAnnulerLecture = document.getElementById('annuler-ajout-lecture');
    const erreurLecture = document.getElementById('lecture-message-erreur');

    // Affiche le formulaire de nouvelle session pour le livre consulté.
    ecouter(boutonAjouterLecture, 'click', () => {
        if (!formulaireLectureZone) return;

        formulaireLectureZone.hidden = false;
        boutonAjouterLecture.hidden = true;
    });

    // Annule la saisie de la nouvelle session sans l’enregistrer.
    ecouter(boutonAnnulerLecture, 'click', () => {
        if (formulaireLectureZone) {
            formulaireLectureZone.hidden = true;
        }

        if (boutonAjouterLecture) {
            boutonAjouterLecture.hidden = false;
        }

        formulaireLecture?.reset();

        if (erreurLecture) {
            erreurLecture.hidden = true;
            erreurLecture.textContent = '';
        }
    });

    // Enregistre la session puis informe les pages concernées pour actualiser leur affichage.
    ecouter(formulaireLecture, 'submit', async event => {
        event.preventDefault();

        const id = modal.dataset.livreId;
        if (!id) return;

        const boutonEnregistrer = formulaireLecture.querySelector('button[type="submit"]');

        if (boutonEnregistrer) {
            boutonEnregistrer.disabled = true;
        }

        if (erreurLecture) {
            erreurLecture.hidden = true;
            erreurLecture.textContent = '';
        }

        try {
            const reponse = await fetch(`/livre/${encodeURIComponent(id)}/lecture/ajouter`, {
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

            // Prévenir la page Mes lectures qu'une session a été ajoutée
            document.dispatchEvent(
                new CustomEvent('lecture:ajoutee', {
                    detail: {
                        livreId: id
                    }
                })
            );

            // Les autres pages récupèrent leurs cartes et compteurs actualisés lors du rechargement.
            if (!document.getElementById('page-mes-lectures')) {
                window.location.reload();
            }
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

    // Libère les ressources de couverture et invalide la requête en cours à la fermeture.
    ecouter(modal, 'close', () => {
        // Arrêter immédiatement la caméra
        arreterCameraCouvertureDetails();

        // Fermer les options de couverture
        fermerOptionsCouvertureDetails();

        // Fermer la recherche en ligne
        if (zoneRechercheDetails) {
            zoneRechercheDetails.hidden = true;
        }

        // Libérer les ressources de l'aperçu
        gestionnaireCouvertureDetails.nettoyer();

        // Empêche une réponse tardive de réafficher les données du livre fermé.
        requeteCourante++;
        delete modal.dataset.livreId;
    });

    // GESTION DE LA COUVERTURE DANS LA FICHE DÉTAILLÉE

    const boutonModifierCouverture = document.getElementById('modifier-couverture-details');
    const optionsCouvertureDetails = document.getElementById('details-options-couverture');

    const boutonFichierDetails = document.getElementById('details-couverture-fichier');
    const fichierCouvertureDetails = document.getElementById('details-fichier-couverture');

    const apercuCouvertureDetails = document.getElementById('details-livre-couverture');
    const champImageCouvertureDetails = document.getElementById('details-image-couverture');

    const gestionnaireCouvertureDetails = creerGestionnaireCouverture({
        apercu: apercuCouvertureDetails,
        fichier: fichierCouvertureDetails,
        champImage: champImageCouvertureDetails
    });

    // Masque le menu de couverture de la fiche détaillée.
    function fermerOptionsCouvertureDetails() {
        if (optionsCouvertureDetails) {
            optionsCouvertureDetails.hidden = true;
        }
    }

    // Ouvrir ou fermer le menu des trois options
    ecouter(boutonModifierCouverture, 'click', () => {
        if (!optionsCouvertureDetails) return;

        optionsCouvertureDetails.hidden = !optionsCouvertureDetails.hidden;
    });

    // Importer une image depuis la galerie
    ecouter(boutonFichierDetails, 'click', () => {
        fermerOptionsCouvertureDetails();
        fichierCouvertureDetails?.click();
    });

    // Afficher l'image sélectionnée
    ecouter(fichierCouvertureDetails, 'change', () => {
        const fichier = fichierCouvertureDetails.files?.[0];

        if (!fichier) return;

        gestionnaireCouvertureDetails.afficherFichier(fichier);
        fermerOptionsCouvertureDetails();
    });

    // APPAREIL PHOTO POUR LA COUVERTURE

    const boutonPhotoDetails = document.getElementById('details-couverture-photo');
    const cameraZoneDetails = document.getElementById('details-camera-zone');
    const cameraVideoDetails = document.getElementById('details-camera-video');
    const cameraCapturerDetails = document.getElementById('details-camera-capturer');
    const cameraAnnulerDetails = document.getElementById('details-camera-annuler');
    const cameraErreurDetails = document.getElementById('details-camera-erreur');

    let fluxCameraDetails = null;

    // Arrête la caméra de la fiche détaillée et efface son flux vidéo.
    function arreterCameraCouvertureDetails() {
        if (fluxCameraDetails) {
            fluxCameraDetails.getTracks().forEach(piste => piste.stop());
            fluxCameraDetails = null;
        }

        if (cameraVideoDetails) {
            cameraVideoDetails.srcObject = null;
        }

        if (cameraZoneDetails) {
            cameraZoneDetails.hidden = true;
        }
    }

    // Ouvrir la caméra
    ecouter(boutonPhotoDetails, 'click', async () => {
        fermerOptionsCouvertureDetails();

        if (!cameraZoneDetails || !cameraVideoDetails) return;

        if (!navigator.mediaDevices?.getUserMedia) {
            cameraZoneDetails.hidden = false;

            if (cameraErreurDetails) {
                cameraErreurDetails.hidden = false;
                cameraErreurDetails.textContent =
                    'La caméra n’est pas disponible dans ce navigateur.';
            }

            return;
        }

        if (cameraErreurDetails) {
            cameraErreurDetails.hidden = true;
            cameraErreurDetails.textContent = '';
        }

        cameraZoneDetails.hidden = false;

        try {
            fluxCameraDetails = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false
            });

            cameraVideoDetails.srcObject = fluxCameraDetails;
            await cameraVideoDetails.play();
        } catch (erreur) {
            console.error('Erreur caméra couverture :', erreur);

            arreterCameraCouvertureDetails();
            cameraZoneDetails.hidden = false;

            if (cameraErreurDetails) {
                cameraErreurDetails.hidden = false;
                cameraErreurDetails.textContent =
                    'Impossible d’ouvrir la caméra. Vérifie les autorisations de Safari.';
            }
        }
    });

    // Capturer la photo
    ecouter(cameraCapturerDetails, 'click', () => {
        if (!cameraVideoDetails || !fluxCameraDetails || !cameraVideoDetails.videoWidth) return;

        const canvas = document.createElement('canvas');

        canvas.width = cameraVideoDetails.videoWidth;
        canvas.height = cameraVideoDetails.videoHeight;

        const contexte = canvas.getContext('2d');
        if (!contexte) return;

        contexte.drawImage(cameraVideoDetails, 0, 0);

        canvas.toBlob(
            blob => {
                if (!blob || !fichierCouvertureDetails) return;

                const fichier = new File([blob], 'couverture-photo.jpg', { type: 'image/jpeg' });

                const transfert = new DataTransfer();
                transfert.items.add(fichier);

                fichierCouvertureDetails.files = transfert.files;

                gestionnaireCouvertureDetails.afficherFichier(fichier);
                arreterCameraCouvertureDetails();
            },
            'image/jpeg',
            0.9
        );
    });

    // Annuler la photo
    ecouter(cameraAnnulerDetails, 'click', () => {
        arreterCameraCouvertureDetails();
    });

    // RECHERCHE DE COUVERTURES EN LIGNE

    const boutonEnLigneDetails = document.getElementById('details-couverture-en-ligne');
    const zoneRechercheDetails = document.getElementById('details-recherche-couverture-zone');
    const inputRechercheDetails = document.getElementById('details-recherche-couverture-input');
    const boutonRechercherDetails = document.getElementById('details-lancer-recherche-couverture');
    const boutonFermerRechercheDetails = document.getElementById(
        'details-fermer-recherche-couverture'
    );
    const messageRechercheDetails = document.getElementById('details-recherche-couverture-message');
    const resultatsRechercheDetails = document.getElementById('details-resultats-couvertures');

    let rechercheCouvertureDetailsEnCours = false;

    // Ouvrir la recherche
    ecouter(boutonEnLigneDetails, 'click', () => {
        fermerOptionsCouvertureDetails();

        if (!zoneRechercheDetails) return;

        zoneRechercheDetails.hidden = false;

        const titre = document.getElementById('details-livre-nom')?.textContent.trim();

        if (inputRechercheDetails && titre && !inputRechercheDetails.value) {
            inputRechercheDetails.value = titre;
        }

        inputRechercheDetails?.focus();
    });

    // Fermer la recherche
    ecouter(boutonFermerRechercheDetails, 'click', () => {
        if (zoneRechercheDetails) {
            zoneRechercheDetails.hidden = true;
        }
    });

    // Recherche les couvertures dans Open Library et permet de sélectionner celle du livre.
    async function rechercherCouverturesDetails() {
        if (
            !inputRechercheDetails ||
            !resultatsRechercheDetails ||
            !boutonRechercherDetails ||
            rechercheCouvertureDetailsEnCours
        )
            return;

        const recherche = inputRechercheDetails.value.trim();

        if (!recherche) {
            if (messageRechercheDetails) {
                messageRechercheDetails.textContent = 'Saisis un titre ou un auteur.';
            }
            return;
        }

        rechercheCouvertureDetailsEnCours = true;
        boutonRechercherDetails.disabled = true;
        resultatsRechercheDetails.replaceChildren();

        if (messageRechercheDetails) {
            messageRechercheDetails.textContent = 'Recherche en cours...';
        }

        try {
            const url = new URL('https://openlibrary.org/search.json');

            url.searchParams.set('q', recherche);
            url.searchParams.set('fields', 'key,title,author_name,cover_i');
            url.searchParams.set('limit', '30');

            const reponse = await fetch(url.toString());

            if (!reponse.ok) {
                throw new Error('La recherche est momentanément indisponible.');
            }

            const donnees = await reponse.json();

            const livres = (donnees.docs || []).filter(livre => Number.isInteger(livre.cover_i));

            if (!livres.length) {
                if (messageRechercheDetails) {
                    messageRechercheDetails.textContent =
                        'Aucune couverture trouvée pour cette recherche.';
                }
                return;
            }

            livres.forEach(livre => {
                const bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.className = 'modal-resultat-couverture';

                const image = document.createElement('img');
                image.src = `https://covers.openlibrary.org/b/id/${livre.cover_i}-M.jpg`;
                image.alt = `Couverture de ${livre.title}`;
                image.loading = 'lazy';

                const titre = document.createElement('span');
                titre.textContent = livre.title;

                bouton.append(image, titre);

                ecouter(bouton, 'click', () => {
                    const urlCouverture = `https://covers.openlibrary.org/b/id/${livre.cover_i}-L.jpg`;

                    gestionnaireCouvertureDetails.afficherURL(urlCouverture, livre.title);

                    zoneRechercheDetails.hidden = true;
                });

                resultatsRechercheDetails.appendChild(bouton);
            });

            if (messageRechercheDetails) {
                messageRechercheDetails.textContent = `${livres.length} couverture(s) trouvée(s). Sélectionne une image.`;
            }
        } catch (erreur) {
            console.error('Erreur recherche couverture :', erreur);

            if (messageRechercheDetails) {
                messageRechercheDetails.textContent =
                    'Impossible de rechercher des couvertures pour le moment.';
            }
        } finally {
            rechercheCouvertureDetailsEnCours = false;
            boutonRechercherDetails.disabled = false;
        }
    }

    // Lance la recherche de couvertures depuis le bouton de la fiche.
    ecouter(boutonRechercherDetails, 'click', rechercherCouverturesDetails);

    // Recherche avec la touche Entrée
    ecouter(inputRechercheDetails, 'keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            rechercherCouverturesDetails();
        }
    });
});
