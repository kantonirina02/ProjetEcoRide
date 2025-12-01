# Manuel utilisateur EcoRide

## 1. Visiteur
1. Rendez-vous sur https://ecoride.local (ou domaine deploye).
2. Depuis l'onglet *Covoiturages* :
   - renseignez ville de depart, d'arrivee, date
   - utilisez les filtres : ecologique, prix max (credits), duree max (min), note minimale.
3. Cliquez sur "Detail" pour voir : vehicule, avis, preferences conducteur.
4. Pour reserver, creez un compte ou connectez-vous.

## 2. Creation de compte
1. Ouvrez */signup*.
2. Saisissez email, pseudo, mot de passe (= 8 caracteres).
3. Vous recevez 20 credits a l'inscription.
4. Connectez-vous via */signin*.

## 3. Espace utilisateur (`/account`)
### Profil
- Photo de profil (JPG/PNG, <= 5 Mo) : bouton "Modifier la photo".
- Solde credit affiche sous le nom.

### Preferences conducteur
- Activez le switch "Je suis conducteur" si vous proposez des trajets.
- Definissez Fumeurs/Animaux/Musique puis "Enregistrer".

### Vehicules
- Formulaire : marque, modele, energie, couleur, plaque, date de premiere immatriculation, case "Vehicule eco".
- Bouton "Enregistrer" ajoute ou met a jour.

### Reservations
- Liste "A venir" / "Passees"
- Boutons :
  - **Annuler** (tant que le trajet n'a pas commence).
  - **Tout s'est bien passe / Signaler un probleme** quand un retour est attendu (apres trajet).

### Trajets conducteur
- Boutons par trajet :
  - **Demarrer** (1 h avant l'heure prevue)
  - **Arrivee a destination** (notifie les passagers)
  - **Annuler le trajet** (credits rembourses, email envoye)

## 4. Recherche & reservation
1. Depuis la fiche d'un trajet, cliquez "Reserver".
2. Une double confirmation vous demande d'utiliser les credits.
3. Une fois confirme, la reservation apparait dans votre tableau.

## 5. Espace employe (`/employee`)
- Accessible aux roles `ROLE_EMPLOYEE` / `ROLE_ADMIN`.
- Filtrer/valider/rejeter les avis utilisateurs.
- Section "Trajets signales" : details conducteur + passager + note.

## 6. Espace administrateur (`/admin`)
- Statistiques journaliers (trajets, credits), inscriptions.
- Moderation des avis (memes boutons que l'espace employe).
- Gestion comptes : suspendre / reactiver.
- Formulaire creation employe : email + pseudo + mot de passe provisoire.
- Tableau des dernieres recherches (logs MongoDB).

## 7. Deploiement utilisateur
- Pour l'utiliser en local :
  - lancer l'API : `symfony server:start --port=8001`
  - servir les pages : `npx serve .`
  - configurer `MONGODB_URL` et `DATABASE_URL`.

## 8. Support
- Courriel : support@ecoride.internal
- Guide technique : voir `docs/technique.md`
