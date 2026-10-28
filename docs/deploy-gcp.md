# Mise en place Google Cloud (une seule fois)

Objectif : 0 €/mois en restant dans le free tier (Cloud Run, Firestore, Cloud Storage, Secret Manager, Artifact Registry). Seul le nom de domaine est payant.

Toutes les commandes se lancent depuis Cloud Shell (console.cloud.google.com, icône `>_`), qui a déjà `gcloud`. Elles se copient telles quelles une fois `PROJECT_ID` défini.

## 0. Identifiant du projet

Choisir l'identifiant une fois pour toutes : 6 à 30 caractères, minuscules, chiffres et tirets, commence par une lettre, unique sur tout Google Cloud, non modifiable ensuite. Si `flamants-bleus` est déjà pris, essayer par exemple `flamants-bleus-impro`.

```bash
PROJECT_ID=flamants-bleus
```

La variable disparaît à la fermeture de Cloud Shell : la redéfinir au début de chaque nouvelle session, avant de reprendre à l'étape en cours.

## 1. Projet et APIs

```bash
gcloud projects create "${PROJECT_ID}" --name="Les Flamants Bleus"
gcloud config set project "${PROJECT_ID}"
# Lier un compte de facturation dans la console (obligatoire même pour le free tier), puis :
gcloud services enable run.googleapis.com firestore.googleapis.com storage.googleapis.com \
  secretmanager.googleapis.com artifactregistry.googleapis.com iamcredentials.googleapis.com
```

Créer tout de suite une **alerte budget à 1 €** (Facturation → Budgets et alertes) pour être prévenu si quelque chose sort du gratuit.

## 2. Firestore

```bash
gcloud firestore databases create --location=eur3 --type=firestore-native
```

## 3. Bucket des logos (lecture publique)

Le free tier Cloud Storage ne couvre que les régions US : le bucket est en `us-east1`, ce qui ne change rien pour quelques logos.

Le projet est rattaché à l'organisation Google Workspace de la troupe, dont les règles par défaut interdisent tout bucket public. On lève cette interdiction pour ce projet seulement ; il faut le rôle « Administrateur des règles d'administration » sur l'organisation (la première commande se l'attribue, l'effet met quelques minutes).

```bash
ORG_ID=$(gcloud projects describe "${PROJECT_ID}" --format='value(parent.id)')
gcloud organizations add-iam-policy-binding "${ORG_ID}" \
  --member="user:$(gcloud config get-value account)" --role=roles/orgpolicy.policyAdmin
gcloud resource-manager org-policies disable-enforce storage.publicAccessPrevention --project="${PROJECT_ID}"
gcloud resource-manager org-policies set-policy --project="${PROJECT_ID}" /dev/stdin <<EOF
constraint: constraints/iam.allowedPolicyMemberDomains
listPolicy:
  allValues: ALLOW
EOF
```

```bash
gcloud storage buckets create "gs://${PROJECT_ID}-logos" --location=us-east1 --uniform-bucket-level-access
gcloud storage buckets add-iam-policy-binding "gs://${PROJECT_ID}-logos" --member=allUsers --role=roles/storage.objectViewer
```

## 4. Comptes de service

```bash
# Celui qui fait tourner le site
gcloud iam service-accounts create site-run
sleep 30   # un compte tout juste créé est refusé (« does not exist ») pendant quelques secondes
for role in roles/datastore.user roles/secretmanager.secretAccessor; do
  gcloud projects add-iam-policy-binding "${PROJECT_ID}" \
    --member="serviceAccount:site-run@${PROJECT_ID}.iam.gserviceaccount.com" --role="${role}"
done
gcloud storage buckets add-iam-policy-binding "gs://${PROJECT_ID}-logos" \
  --member="serviceAccount:site-run@${PROJECT_ID}.iam.gserviceaccount.com" --role=roles/storage.objectAdmin

# Celui qu'utilise GitHub Actions pour déployer
gcloud iam service-accounts create github-deploy
sleep 30
for role in roles/run.admin roles/artifactregistry.writer roles/iam.serviceAccountUser; do
  gcloud projects add-iam-policy-binding "${PROJECT_ID}" \
    --member="serviceAccount:github-deploy@${PROJECT_ID}.iam.gserviceaccount.com" --role="${role}"
done

gcloud artifacts repositories create site --repository-format=docker --location=europe-west1
```

Ajouter une règle de nettoyage sur le dépôt Artifact Registry (garder les 3 dernières images) pour rester sous 0,5 Go.

## 5. GitHub → Google sans clé (Workload Identity Federation)

```bash
gcloud iam workload-identity-pools create github --location=global
gcloud iam workload-identity-pools providers create-oidc github --location=global --workload-identity-pool=github \
  --issuer-uri=https://token.actions.githubusercontent.com \
  --attribute-mapping=google.subject=assertion.sub,attribute.repository=assertion.repository \
  --attribute-condition="assertion.repository=='thetis20/flamants-bleus'"
PROJECT_NUMBER=$(gcloud projects describe "${PROJECT_ID}" --format='value(projectNumber)')
gcloud iam service-accounts add-iam-policy-binding "github-deploy@${PROJECT_ID}.iam.gserviceaccount.com" \
  --role=roles/iam.workloadIdentityUser \
  --member="principalSet://iam.googleapis.com/projects/${PROJECT_NUMBER}/locations/global/workloadIdentityPools/github/attribute.repository/thetis20/flamants-bleus"
```

## 6. Connexion Google (OAuth)

Console → APIs et services → Écran de consentement OAuth : type « Externe », nom « Les Flamants Bleus », scopes `openid` et `email` uniquement, puis **publier** l'application (sinon seuls les comptes de test peuvent se connecter ; avec ces scopes, aucune validation Google n'est demandée).

Identifiants → Créer un ID client OAuth → Application Web, URI de redirection autorisés :

- `https://cielesflamantsbleus.fr/connexion/google/retour`
- `http://localhost:8000/connexion/google/retour` (dev)

## 7. Secrets

Les valeurs ne s'écrivent jamais dans le dépôt ni dans un chat. Secret Manager en est la seule copie : pas de coffre à part, l'ID client reste visible dans la console et le secret client peut y être régénéré (Identifiants → client OAuth → ajouter un secret, puis `gcloud secrets versions add`). `read -s` les demande sans les afficher ni les garder dans l'historique du shell : coller la valeur puis Entrée.

```bash
openssl rand -hex 32 | gcloud secrets create app-secret --data-file=-
read -rs -p 'ID client : ' value && printf '%s' "${value}" | gcloud secrets create google-client-id --data-file=-; echo
read -rs -p 'Secret client : ' value && printf '%s' "${value}" | gcloud secrets create google-client-secret --data-file=-; echo
unset value
```

## 8. Variables GitHub

Dépôt GitHub → Settings → Secrets and variables → Actions → onglet **Variables** (ce ne sont pas des secrets). Cette commande affiche les valeurs à recopier :

```bash
PROJECT_NUMBER=$(gcloud projects describe "${PROJECT_ID}" --format='value(projectNumber)')
cat <<EOF
GCP_PROJECT_ID        ${PROJECT_ID}
GCP_WIF_PROVIDER      projects/${PROJECT_NUMBER}/locations/global/workloadIdentityPools/github/providers/github
GCP_DEPLOY_SA         github-deploy@${PROJECT_ID}.iam.gserviceaccount.com
GCP_RUN_SA            site-run@${PROJECT_ID}.iam.gserviceaccount.com
LOGO_BUCKET           ${PROJECT_ID}-logos
EOF
```

Ajouter aussi `ADMIN_BOOTSTRAP_EMAIL` : l'adresse Gmail du premier membre.

Un push sur `main` lance les tests puis le déploiement.

## 9. Nom de domaine

```bash
gcloud domains verify cielesflamantsbleus.fr   # validation via Search Console, une fois
gcloud beta run domain-mappings create --service=flamants-bleus --domain=cielesflamantsbleus.fr --region=europe-west1
```

La commande affiche les enregistrements DNS (A/AAAA) à créer chez le registrar. Le certificat HTTPS est fourni par Google, compter jusqu'à une heure après la propagation DNS.

## Ce qui reste gratuit, et ce qui ne l'est pas

Free tier mensuel : Cloud Run 2 M requêtes et 180 000 vCPU-secondes, Firestore 50 000 lectures et 20 000 écritures par jour, 1 Go stocké, Cloud Storage 5 Go en région US, Secret Manager 6 versions actives, Artifact Registry 0,5 Go. Un site de troupe en est très loin. Le service s'éteint sans visite : la première page après un moment d'inactivité met une à deux secondes à s'afficher.
