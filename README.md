# SAÉ 5 — Jardins de Cocagne

Rayane ABABSA et Théo BOREL · BUT 3 Informatique · 2026–2027

## Démarrer la démonstration locale

Depuis la racine du dépôt, démarrer Docker avec Docker Compose v2 et vérifier que le port local 8000 est libre.

```sh
docker compose up --build -d app
```

Ouvrir [l’accueil](http://127.0.0.1:8000/). Les tournées et le calendrier restent accessibles sans connexion et utilisent des données de démonstration. Les résultats de formulaire ne sont pas conservés après la fermeture de la page.

## R15 — Organisation des tournées

Ouvrir [les tournées](http://127.0.0.1:8000/tournees), garder l’exemple et cliquer sur **Calculer les tournées**. Les tournées affichent pour chaque véhicule les paniers affectés, les dépôts dans l’ordre de visite et la distance estimée à vol d’oiseau, retour au chargement compris.

Le calcul propose une répartition que l’on peut modifier dans le champ **Dépôts affectés aux véhicules**, avec une ligne au format `dépôt;véhicule`. Cliquer sur **Vérifier la répartition manuelle** pour contrôler la proposition. Par exemple, avec les données d’origine, cette répartition est valide.

```text
1;1
2;1
3;2
4;2
```

La répartition automatique peut laisser un dépôt sans véhicule même si une solution existe. La liste des dépôts non affectés permet de repérer ce cas et de proposer une correction manuelle.

## R16 — Calendrier de livraison

Ouvrir [le calendrier](http://127.0.0.1:8000/calendrier). Par exemple, choisir l’année **2027**, la tournée **Mercredi matin**, le **mercredi**, une semaine de départ **impaire** et saisir `1,31,32` pour les semaines fermées. Cliquer sur **Générer le calendrier** avec **25**, puis **20** et **17** livraisons. Vérifier que le nombre de dates placées correspond au nombre demandé.

Le calendrier tient compte des jours fériés et des semaines fermées. Pour déplacer une livraison, modifier une date dans la colonne **Date retenue** puis cliquer sur **Vérifier les dates**. La colonne **Date prévue** indique le point de départ de la vérification.

## R17 — Connexion de démonstration

Le compte `adherent@example.test` est disponible en mode `dev`. Dans PowerShell 7, choisir un mot de passe réservé à cette démonstration et démarrer l’application avec son hachage.

```powershell
docker compose build app
$env:APP_SECRET = (docker compose run --rm --no-deps -T app php -r 'echo bin2hex(random_bytes(32));').Trim()
$env:SAE5_R17_DEMO_PASSWORD = Read-Host 'Mot de passe de démonstration' -MaskInput
$env:SAE5_R17_DEMO_PASSWORD_HASH = (docker compose run --rm --no-deps -T --env SAE5_R17_DEMO_PASSWORD app php -r 'echo password_hash(getenv($argv[1]), PASSWORD_DEFAULT);' SAE5_R17_DEMO_PASSWORD).Trim()
docker compose up -d app
```

Depuis [l’accueil](http://127.0.0.1:8000/), cliquer sur **Connexion adhérent** et saisir l’adresse de démonstration et le mot de passe choisi. Dans le même terminal, lancer le test du parcours.

```powershell
docker compose run --rm --no-deps -T --volume "./tests:/app/tests:ro" --env BASE_URL=http://app:8000 --env SAE5_R17_DEMO_PASSWORD app php tests/Securite/connexion_adherent.php
```

## Lancer les vérifications

Depuis la racine du dépôt, laisser l’application démarrée et lancer les quatre scripts suivants. Docker monte le dossier `tests` en lecture seule et utilise les dépendances déjà installées dans l’image.

```sh
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" app php tests/Tournee/repartition_paniers.php
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" app php tests/Tournee/organisation_tournees.php
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" app php tests/Calendrier/calendrier_livraisons.php
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" --env BASE_URL=http://app:8000 app php tests/smoke.php
```

Le [workflow GitHub Actions](.github/workflows/verifications.yml) reconstruit l’image, exécute ces scripts et vérifie les dépendances verrouillées avec `composer audit` sur les demandes de fusion vers `main` et les mises à jour de `main`.

## Arrêter

```sh
docker compose down
```
