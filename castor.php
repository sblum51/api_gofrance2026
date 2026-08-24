<?php

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;
use function Castor\ssh_run;

// Cible de déploiement : serveur partagé agoraone, le même que celui qui
// héberge les API orun et caporientation. Une seule cible pour l'instant,
// aucune préproduction n'est provisionnée.
const TARGETS = [
    'prod' => 'deployer@api.notifs.cloud',
];

const ENV_FILES = [
    'prod' => 'docker/server/.env.dist',
];

// Domaine public servi par le Caddy partagé (à mettre dans ~/docker/.env).
const DOMAINS = [
    'prod' => 'api.go-france.net',
];

const REPO = 'git@github.com:sblum51/api_gofrance2026.git';

const BRANCHES = [
    'prod' => 'main',
];

const APP_DIR = '~/gofrance-api';
const IMAGE   = 'gofrance-api:latest';
const SERVICE = 'gofrance-api';

/**
 * Résout l'hôte SSH à partir du nom de cible.
 */
function host(string $target): string
{
    if (!isset(TARGETS[$target])) {
        throw new \RuntimeException(sprintf(
            'Cible inconnue : "%s". Valeurs possibles : %s',
            $target,
            implode(', ', array_keys(TARGETS))
        ));
    }

    return TARGETS[$target];
}

/**
 * Branche Git à déployer. Priorité au paramètre CLI quand il est fourni
 * (ex. `castor deploy --branch=feat/foo`), sinon la branche par défaut.
 */
function branch(string $target, string $branch = ''): string
{
    if ('' !== $branch) {
        return $branch;
    }

    return BRANCHES[$target] ?? 'main';
}

#[AsTask(description: 'Prépare le serveur (dossiers + compose + modèle .env + clés JWT)')]
function setup(string $target = 'prod'): void
{
    $h = host($target);
    io()->title(sprintf('Préparation → %s (%s)', $target, $h));

    if (!io()->confirm('⚠ Préparer la PRODUCTION ?', false)) {
        io()->warning('Abandon.');

        return;
    }

    io()->section('Répertoires applicatifs');
    // `storage` est le dossier qui porte tout le contenu client : il est créé
    // ici une fois pour toutes et n'est jamais touché par les déploiements.
    ssh_run(
        'mkdir -p ' . APP_DIR . '/jwt ' . APP_DIR . '/logs'
        . ' ' . APP_DIR . '/storage/media/logos'
        . ' ' . APP_DIR . '/storage/media/covers'
        . ' ' . APP_DIR . '/storage/media/parcours'
        . ' ' . APP_DIR . '/storage/media/parcours-points'
        . ' ' . APP_DIR . '/storage/data',
        $h
    );
    // FrankenPHP tourne en www-data (UID 33) : le volume monté doit lui être
    // accessible en écriture, sinon les uploads et la génération des JSON
    // échouent silencieusement côté conteneur.
    ssh_run('chown -R 33:33 ' . APP_DIR . '/storage ' . APP_DIR . '/logs || true', $h);

    io()->section('Compose + modèle .env');
    run(['scp', 'compose.prod.yaml', $h . ':' . APP_DIR . '/compose.yaml']);
    $tpl = ENV_FILES[$target] ?? 'docker/server/.env.dist';
    // On dépose un modèle sans jamais écraser le .env existant (secrets serveur).
    run(['scp', $tpl, $h . ':' . APP_DIR . '/.env.dist']);

    io()->section('Clés JWT');
    if (is_file('config/jwt/private.pem') && is_file('config/jwt/public.pem')) {
        run(['scp', 'config/jwt/private.pem', 'config/jwt/public.pem', $h . ':' . APP_DIR . '/jwt/']);
        // generate-keypair écrit en 0600 pour l'utilisateur courant ; le
        // conteneur tourne sous un autre UID et ne pourrait pas lire les clés.
        ssh_run('chmod 0644 ' . APP_DIR . '/jwt/private.pem ' . APP_DIR . '/jwt/public.pem', $h);
    } else {
        io()->warning('Clés JWT absentes en local — générer : php bin/console lexik:jwt:generate-keypair');
    }

    io()->success('Squelette en place sur ' . $h);
    io()->note([
        'À terminer sur le serveur :',
        '  cd ' . APP_DIR . ' && cp -n .env.dist .env',
        '  $EDITOR .env   # APP_SECRET, DATABASE_URL, JWT_PASSPHRASE, DATATOURISME_API_KEY',
        'Infra partagée (~/docker) :',
        '  - créer la base et le rôle "gofrance" sur le Postgres partagé',
        '  - GOFRANCE_API_DOMAIN=' . (DOMAINS[$target] ?? 'à définir') . ' dans ~/docker/.env',
        '  - ajouter le bloc docker/server/Caddyfile.snippet à ~/docker/Caddyfile, puis recharger Caddy',
        'DNS : api.go-france.net pointe aujourd\'hui vers redirect.ovh.net,',
        '      il faut le basculer sur l\'IP du serveur agoraone.',
        'Puis : castor deploy',
    ]);
}

#[AsTask(description: 'Déploiement complet (demande confirmation)')]
function deploy(string $target = 'prod', string $branch = ''): void
{
    $b = branch($target, $branch);
    io()->title(sprintf('Déploiement → %s (%s) — branche %s', $target, host($target), $b));

    if (!io()->confirm('⚠ Déploiement en PRODUCTION. Confirmer ?', false)) {
        io()->warning('Abandon.');

        return;
    }

    pull($target, $branch);
    build($target);
    up($target);
    migrate($target);
    check($target);
    prune($target);
}

#[AsTask(description: 'Git pull (clone au premier passage; surcharge via --branch=)')]
function pull(string $target = 'prod', string $branch = ''): void
{
    $b = branch($target, $branch);
    io()->section(sprintf('Git pull (%s)', $b));
    ssh_run(
        'if [ -d ' . APP_DIR . '/repo/.git ]; then'
        . ' cd ' . APP_DIR . '/repo'
        . ' && git fetch origin'
        . ' && git checkout -B ' . escapeshellarg($b) . ' origin/' . escapeshellarg($b)
        . ' && git reset --hard origin/' . escapeshellarg($b) . ';'
        . ' else'
        . ' rm -rf ' . APP_DIR . '/repo'
        . ' && git clone --branch ' . escapeshellarg($b) . ' ' . REPO . ' ' . APP_DIR . '/repo;'
        . ' fi',
        host($target)
    );
}

#[AsTask(description: 'Build de l\'image Docker (Dockerfile multi-étapes, target=prod)')]
function build(string $target = 'prod'): void
{
    io()->section('Docker build');
    ssh_run(
        'cd ' . APP_DIR . '/repo'
        . ' && SECRET_OPT=""'
        . ' && if [ -f "$HOME/.composer/auth.json" ]; then'
        . '      SECRET_OPT="--secret id=composer_auth,src=$HOME/.composer/auth.json";'
        . '    fi'
        . ' && DOCKER_BUILDKIT=1 docker build $SECRET_OPT'
        . '   -f docker/Dockerfile -t ' . IMAGE . ' --target prod .',
        host($target)
    );
}

#[AsTask(description: 'Redémarre les conteneurs')]
function up(string $target = 'prod'): void
{
    io()->section('Docker up');
    ssh_run('cd ' . APP_DIR . ' && docker compose up -d --force-recreate', host($target));
}

#[AsTask(description: 'Joue les migrations Doctrine')]
function migrate(string $target = 'prod'): void
{
    io()->section('Migrations');
    ssh_run(
        'cd ' . APP_DIR . ' && docker compose exec -T ' . SERVICE
        . ' php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration',
        host($target)
    );
}

#[AsTask(description: 'Vérifie le déploiement (vendor, conteneurs, stockage)')]
function check(string $target = 'prod'): void
{
    io()->section('Check');
    ssh_run('docker run --rm ' . IMAGE . ' ls vendor/ | wc -l', host($target));
    ssh_run('cd ' . APP_DIR . ' && docker compose ps', host($target));
    // Le stockage est le point sensible : on vérifie qu'il est bien monté et
    // qu'il contient toujours les contenus, déploiement après déploiement.
    ssh_run(
        'cd ' . APP_DIR . ' && docker compose exec -T ' . SERVICE
        . ' sh -c \'ls var/storage/data | wc -l\' | xargs -I{} echo "JSON d organisations : {}"',
        host($target)
    );
}

#[AsTask(description: 'Nettoie les images Docker orphelines')]
function prune(string $target = 'prod'): void
{
    ssh_run('docker image prune -f --filter "dangling=true"', host($target));
}

#[AsTask(description: 'Suit les logs')]
function logs(string $target = 'prod'): void
{
    ssh_run('cd ' . APP_DIR . ' && docker compose logs --tail 50 -f', host($target));
}
