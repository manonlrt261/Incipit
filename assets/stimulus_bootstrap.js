// Démarre Stimulus pour activer les contrôleurs déclarés dans les pages.
import { startStimulusApp } from '@symfony/stimulus-bundle';

// Prépare la protection CSRF même sur la connexion, sans attendre l’ouverture d’un formulaire Stimulus.
import './controllers/csrf_protection_controller.js';

startStimulusApp();
