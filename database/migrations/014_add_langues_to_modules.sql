-- Migration 014 : Ajout de la colonne langues (JSON) pour le support i18n des modules
ALTER TABLE modules ADD COLUMN langues TEXT DEFAULT NULL AFTER mode_affichage;
