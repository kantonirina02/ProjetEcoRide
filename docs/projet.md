# Documentation de gestion de projet

## Contexte
EcoRide est une plateforme de covoiturage interne visant a encourager les trajets domicile-travail partages, avec un angle ecologique fort. L'application couvre :
- espace visiteur (recherche, filtres, vue detail)
- espace utilisateur (reservations, vehicules, trajets)
- espace employe (moderation, suivi incidents)
- espace administrateur (metriques, comptes, revenus)

## Gouvernance & roles
| Role | Responsabilites |
| --- | --- |
| Product Owner (Jose) | Priorisation des US, validation livrables |
| Developpeur full-stack | Analyse, implementation front/back, livraison |
| Employe / Moderateur | Validation avis, suivi problemes |
| Administrateur | Gestion comptes, indicateurs, deploiement |

## Methodologie
- **Cadre** : Kanban continu (priorite > en cours > revue > termine > merge)
- **Rituels** :
  - Revue hebdo avec PO (demonstration + collecte de feedback)
  - Stand-up asynchrone (notes dans Kanban)
- **Outils** : GitHub Projects / Trello (exemple de board mirror dans `docs/kanban.md`).

## Perimetre fonctionnel
| Bloc | Statut |
| --- | --- |
| US1-5 (visiteur) | ? livre |
| US6 (reservation + double confirmation) | ? |
| US7-9 (compte, vehicules, trajet) | ? |
| US10 (historique + annulation) | ? + mails |
| US11 (demarrer / terminer / feedback) | ? |
| US12 (moderation + incidents) | ? |
| US13 (admin metriques + creation employe) | ? |

## Qualite & securite
- Controles server-side Symfony (validation, auth session).
- Regles ESLint/Prettier cote front (npm scripts).
- Logs MongoDB pour audits de recherche.
- Politique RGPD : e-mails chiffres en DB (hash via `password_hasher`).

## Suivi des risques
| Risque | Mesure |
| --- | --- |
| Rupture de service API | Observabilite via Monolog + alertes mails |
| Fuite de donnees | Sessions cote serveur, aucune donnee sensible cote LocalStorage |
| Charge sur DB | Index Doctrine (ride startAt, participants status) |

## Livrables produits
- Charte graphique (`docs/charte.md`)
- Documentation technique (`docs/technique.md`)
- Manuel utilisateur (`docs/manuel-utilisateur.md`)
- Doc de gestion projet (ce document)
- Script SQL (`docs/sql/bootstrap.sql`)
- Procedure de deploiement (`docs/deploiement.md`)

Pour export en PDF : ouvrir chaque `.md` dans VS Code, utiliser `Markdown PDF` ou `Print to PDF`.
