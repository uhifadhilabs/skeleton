# uhifadhi/uhifadhi

The project skeleton every uhifadhi installation starts from.

## What it is

A bare Symfony kernel, the seam (`uhifadhi/seam-module`), the shell
(`uhifadhi/shell-module`) and the uhifadhilabs Flex recipe endpoint. It is
copied once by `composer create-project` and then it is yours; every capability
after that arrives as a module, installed with composer.

Zero modules is a working installation: a fresh skeleton boots and serves a
branded, themed, navigable shell with an empty sidebar. There is no user, no
security bundle and no database yet — none of that is a placeholder, it is what
an installation with nothing in it honestly looks like.

---

# Install guide

The ordered path from nothing to a running installation with the core modules,
a PostGIS database, migrations applied and a first administrator. Each step
below depends on the one before it — the order is the point.

## 1. Install the skeleton

```bash
composer create-project uhifadhi/uhifadhi park
cd park
```

Use any name you like in place of `park`; that name becomes the directory and,
in the next step, the local hostname and the database name.

This installs **the seam and the shell** (`uhifadhi/seam-module` +
`uhifadhi/shell-module`) with their recipes, and nothing else. The seam owns two
tables — the module catalogue and the per-area install record — and the shell
draws the UI. The project boots and serves immediately, but do not reach for
migrations yet: the seam has no schema to create until an area module answers
its area contract (step 3), and the schema it will create is PostGIS, so the
database comes first.

## 2. Give it a database — with fundi, before anything else

uhifadhi stores gazetted boundaries as PostGIS geometry, so the database needs
the PostGIS extension. `fundi` runs a native PostGIS cluster for you — no
Docker — and wires the app to it. Do this **now**, right after install and
**before** the core modules and their migrations.

```bash
fundi init
```

That writes `.fundi.local.yaml` into the project. Open it and **uncomment the
one-line PostGIS opt-in**:

```yaml
database: postgis   # simplest: your ACTIVE (brew-linked) Postgres major, db = this dir's slug
```

(PostGIS is opt-in because the default is plain SQL. Uncommenting this line is
what switches fundi from "reuse your own Postgres" to "run a PostGIS cluster for
this project.") It requires the toolchain once per Postgres major:
`brew install postgresql@17 postgis`.

Then start the server:

```bash
fundi server:start
```

This creates `.env.local` with the `DATABASE_URL`, spins up a PostGIS cluster
(one per Postgres major, so 16- and 17-based projects coexist), `createdb`s this
project's database and enables the `postgis` extension in it, and serves the app
over SSL at `https://park.localhost` (your project name in place of `park`). You
write nothing into `.env` — fundi injects the URL.

## 3. Install the core modules

The core is four modules — **widget, team, area, map** — installed in one
command:

```bash
composer require uhifadhi/widget-module uhifadhi/team-module uhifadhi/area-module uhifadhi/map-module
```

- **widget** — the shared widget/preset surface the other modules render into.
- **team** — identity, the permission catalogue and sign-in. It brings the
  `team_user` storage, the `/login` screen and the `team_user_provider`, but the
  firewall is not on until you edit `security.yaml` in step 4 — installing team
  makes login *possible*, step 4 makes it *active*.
- **area** — a real area (a name, a gazetted MultiPolygon boundary, a public
  UUID). It answers the seam's area contract — it maps its own entity and
  prepends the resolution — so the seam finally has a schema to create. You
  write no `doctrine.yaml` line.
- **map** — the self-hosted Leaflet platform every map in the product is drawn
  with. Map is infrastructure: it carries its own assets and is pulled in as a
  dependency of area, listed here so it is an explicit, top-level requirement.

`storage-module` is **not** a core module — it is infrastructure a capability
module (patrol) pulls in transitively, never something you install directly.

Because the recipe endpoint is configured, composer wires each bundle up for
you: registration, config and routes all land without a hand-edit.

## 4. Activate login (edit `config/packages/security.yaml`)

Installing `team-module` gives you user storage, a `/login` screen and the
`team_user_provider` — but it does **not** turn the firewall on. `security.yaml`
is application-owned by Symfony's design (only your project knows which paths are
public), so a Flex recipe may not write it — this is a **manual step, on
purpose**. Until you do it the module is installed and inert: `/login` renders,
but nothing authenticates, because the stock config still looks users up in
memory. Skip this and a fresh park cannot log in, not even as the admin you
create in the next step.

Replace `config/packages/security.yaml` with the file below. **It is the whole
file — one paste over the stock one, nothing to edit afterward** (the commented
lines are examples you may want later). This is the canonical config from
`team-module`'s own README, which carries the fully-annotated original:

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    # The staff accounts, looked up by email. This replaces the stock
    # users_in_memory provider — you have user storage now.
    providers:
        team_user_provider:
            entity:
                class: Uhifadhi\Team\Entity\User
                property: email

    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt|assets|build)/
            security: false

        main:
            lazy: true
            provider: team_user_provider

            # Refuses a deactivated account at the door (accounts are never
            # deleted — leaving is deactivation).
            user_checker: team.user_checker

            # Intercepts the POST to /login. login_path and check_path are the
            # same route so a failed sign-in re-renders the form with its error.
            form_login:
                login_path: team_login
                check_path: team_login
                enable_csrf: true
                default_target_path: '/'   # where a fresh sign-in lands

            remember_me:
                secret: '%kernel.secret%'
                lifetime: 604800 # one week
                always_remember_me: false

            logout:
                path: team_logout
                target: team_login

            # Super Admin impersonation (guarded by ROLE_ALLOWED_TO_SWITCH below).
            switch_user: true

            # Blunts credential stuffing — needs symfony/rate-limiter installed.
            #login_throttling:
            #    max_attempts: 5

    # The tier ladder — NOT a permission tree. Admin+ hold the three umbrella
    # capability roles; Staff grant nothing by tier (their capabilities come
    # from their assigned position). There is no ROLE_MANAGER.
    role_hierarchy:
        ROLE_ADMIN: [ROLE_AREAS, ROLE_MODULES, ROLE_TEAM]
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]

    # ONLY THE FIRST MATCHING RULE APPLIES. This is a back-of-house install:
    # the last rule takes everything, and each public path is a deliberate
    # exception. The three that MUST stay public are the ones a stranger reaches
    # with nobody to ask — sign-in, forgotten-password, and an invite link.
    access_control:
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/reset-password, roles: PUBLIC_ACCESS }
        - { path: ^/invite/, roles: PUBLIC_ACCESS }
        #- { path: ^/team, roles: ROLE_TEAM }     # the umbrella; the row is the voter's
        #- { path: ^/areas, roles: ROLE_AREAS }   # the umbrella; the verb is the voter's
        - { path: ^/, roles: IS_AUTHENTICATED_REMEMBERED }

# Hashing is expensive by design; floored in tests only (documented Symfony practice).
when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4
```

With this in place `/` is closed and an unauthenticated visitor is sent to
`/login` — which is exactly what makes the admin you create next able to sign in.

## 5. Run migrations

The modules ship entities, not migration versions: the tables are the bundles',
but the migration history is **yours**. So the install owns the migrations — you
generate them, once, against the schema the installed modules describe.

The pattern is **diff → migrate → diff again**, and the second diff **must say
"No changes."** The first diff writes a migration for everything the modules
added; migrate applies it; the second diff proves the schema now matches the
mapping exactly. Churn on that second diff — a migration that keeps finding
differences — is a bundle bug, not something to route around (PostGIS columns in
particular must round-trip cleanly).

`asset-map:compile` is **not optional** here: after installing or upgrading any
module on AssetMapper, the compiled asset manifest is stale until you rebuild
it, and CSS/JS will serve the old version until you do.

### With the Symfony CLI

```bash
symfony console doctrine:migrations:diff       # writes your migration
symfony console doctrine:migrations:migrate    # applies it
symfony console doctrine:migrations:diff       # MUST report "No changes"
symfony console seam:catalogue:seed            # register installed modules in the catalogue
symfony console asset-map:compile              # rebuild the asset manifest
symfony console cache:clear
```

### Without it (plain PHP)

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:diff       # MUST report "No changes"
php bin/console seam:catalogue:seed
php bin/console asset-map:compile
php bin/console cache:clear
```

### Create the first administrator

There is no user yet. Create the first one interactively — this is how the first
administrator comes to exist:

```bash
php bin/console team:user:create
```

You now have a running installation: sign in at `https://park.localhost` with
the account you just created.

## 6. Capability modules (add as needed)

Capability modules are the ones you add when a deployment actually needs them.
Each is the same two-step move: `composer require` it, then **re-run the
migration steps in section 5** (diff → migrate → diff-again-says-"No changes" →
`asset-map:compile` → `cache:clear`), because a new module adds its own tables
and assets.

- **Patrols** — `composer require uhifadhi/patrol-module`, then re-run the
  migrations.
- **Incidents** — `composer require uhifadhi/incident-module`, then re-run the
  migrations.

Adding a future capability module is another entry with the same shape: require
it, then migrate.

> **Current compatibility note.** As of the latest tags, `patrol-module`
> (v0.5.0) and `incident-module` (v0.2.0) still constrain `area-module` to
> `^0.2 … ^0.6` and `map-module` to `^0.1 || ^0.2`, while the core set above
> resolves to `area-module` v0.11 and `map-module` v0.3. Requiring either
> capability module on a latest-core install therefore fails to resolve today.
> Both need a compatibility release widening those constraints (`area ^0.11`,
> `map ^0.3`) before the two commands above will install. The install pattern is
> correct and unchanged; only the published constraints are behind.

---

## Extending or replacing the welcome page

The shell ships the welcome page at `/`, mounted by one line of consent in
`config/routes/shell.yaml`:

```yaml
shell:
    resource: '@UhifadhiShellBundle/config/routes/welcome.php'
```

The shell loads that resource nowhere; the import is what makes `/` answer. Edit
the file to point `/` at your own home screen, or delete it and the address is
yours again — nothing is left behind. `debug:router` shows what you are
replacing: a route named `welcome`. Your own first page extends one of the
shell's three frames and fills one block:

```twig
{# templates/home/index.html.twig #}
{% extends '@UhifadhiShell/page.html.twig' %}

{% block shell_page_title %}Home{% endblock %}

{% block shell_page %}
    <p>The first page of a new installation.</p>
{% endblock %}
```

## Bring your own area (advanced)

Installing `uhifadhi/area-module` is the normal way the seam's area contract is
answered. To use your own area entity instead, implement
`Uhifadhi\Seam\Entity\AreaInterface` (it asks for `getId()` and nothing else)
and name it in **your own** `config/packages/doctrine.yaml` — application config
overrules a module's prepended answer:

```yaml
doctrine:
    orm:
        resolve_target_entities:
            Uhifadhi\Seam\Entity\AreaInterface: App\Entity\ManagementUnit
```

You write that line only to disagree. Install the module and there is no
doctrine edit at all.

## Learn more

- [The architecture](docs/architecture.md) — one skeleton and a set of modules,
  what is in this repository, and what is deliberately not (including why
  Doctrine is here at all).
- [Maintaining the skeleton](docs/maintaining-the-skeleton.md) — the version
  rhythm (a ring's minor is the skeleton's minor), `symfony.lock` as the recipe
  ledger, and the re-sync rule for the two recipes the skeleton ships with.

## Licence

**AGPL-3.0-or-later** — see [LICENSE](LICENSE). Use, modify and self-host freely;
if you offer a modified uhifadhi to users over a network, they are entitled to the
source of what they're running. Science is never paywalled.
