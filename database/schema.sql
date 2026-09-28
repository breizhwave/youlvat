-- Régie Bénévoles — schéma SQLite 3 (via PHP PDO pdo_sqlite)
-- À exécuter à chaque connexion : PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;
-- Identifiants TEXT générés en PHP (bin2hex(random_bytes(8))) ; les données importées gardent leurs slugs.
-- Dates : 'YYYY-MM-DD'. Heures : 'HH:MM'. Horodatages : ISO 8601 UTC, mis à jour par PHP.

CREATE TABLE IF NOT EXISTS events (
  id          TEXT PRIMARY KEY,
  nom         TEXT NOT NULL,
  lieu        TEXT,
  debut       TEXT NOT NULL,
  fin         TEXT NOT NULL,
  sheet_url   TEXT,
  image_url   TEXT,               -- logo / visuel de l'événement (https://…)
  contact     TEXT,               -- e-mail de contact
  contact_tel TEXT,               -- téléphone de contact
  contact_infos TEXT,             -- autres coordonnées, une par ligne (adresse, site web, réseaux…)
  contact_html  TEXT,             -- texte de la page contact (HTML nettoyé), en français
  contact_html_br TEXT,           -- idem en breton (vide = on affiche la version française)
  deroule     TEXT,               -- une étape par ligne : "20:00 Ouverture des portes"
  created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
  updated_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
);

CREATE TABLE IF NOT EXISTS postes (
  id           TEXT PRIMARY KEY,
  event_id     TEXT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  nom          TEXT NOT NULL,
  couleur      INTEGER NOT NULL DEFAULT 0 CHECK (couleur BETWEEN 0 AND 7),
  responsable  TEXT,
  description  TEXT,
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
  updated_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
);
CREATE INDEX IF NOT EXISTS idx_postes_event ON postes(event_id);

-- Règle métier : un créneau dont l'heure de début est < 06:00 appartient à la NUIT du jour indiqué
-- (jour 2026-12-31, 00:00-05:00 = nuit du 31 au 1er). fin < debut = le créneau passe minuit.
CREATE TABLE IF NOT EXISTS creneaux (
  id          TEXT PRIMARY KEY,
  event_id    TEXT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  poste_id    TEXT NOT NULL REFERENCES postes(id) ON DELETE CASCADE,
  jour        TEXT NOT NULL,
  debut       TEXT NOT NULL,
  fin         TEXT NOT NULL,
  besoin      INTEGER NOT NULL DEFAULT 1 CHECK (besoin >= 1),
  note        TEXT,
  created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
  updated_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
);
CREATE INDEX IF NOT EXISTS idx_creneaux_event_jour ON creneaux(event_id, jour);
CREATE INDEX IF NOT EXISTS idx_creneaux_poste ON creneaux(poste_id);

-- Annuaire global, réutilisable d'une édition à l'autre. Pour l'instant : noms uniquement.
CREATE TABLE IF NOT EXISTS benevoles (
  id           TEXT PRIMARY KEY,
  prenom       TEXT NOT NULL,
  nom          TEXT NOT NULL,
  tel          TEXT,
  email        TEXT,
  taille       TEXT CHECK (taille IS NULL OR taille IN ('XS','S','M','L','XL','XXL')),
  competences  TEXT,
  notes        TEXT,
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
  updated_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
);
CREATE INDEX IF NOT EXISTS idx_benevoles_nom ON benevoles(nom, prenom);

CREATE TABLE IF NOT EXISTS affectations (
  id           TEXT PRIMARY KEY,
  event_id     TEXT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  creneau_id   TEXT NOT NULL REFERENCES creneaux(id) ON DELETE CASCADE,
  benevole_id  TEXT NOT NULL REFERENCES benevoles(id) ON DELETE CASCADE,
  statut       TEXT NOT NULL DEFAULT 'prevu' CHECK (statut IN ('prevu','present','absent')),
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
  updated_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
  UNIQUE (creneau_id, benevole_id)
);
CREATE INDEX IF NOT EXISTS idx_aff_event ON affectations(event_id);
CREATE INDEX IF NOT EXISTS idx_aff_benevole ON affectations(benevole_id);
