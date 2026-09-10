# uhifadhi/skeleton

The open-source observatory for nature conservation and protected areas.

This repository is the starter every installation is created from: a bare
Symfony application carrying the uhifadhi core. `composer create-project` copies
it once and then it is yours — every capability after that arrives as a module,
installed with composer.

## Contents

- [What uhifadhi is](#what-uhifadhi-is)
- [The tree](#the-tree)
- [What the core is](#what-the-core-is)
- [Spatial data and deployment](#spatial-data-and-deployment)
- [Install guide](#install-guide)
  - [1. Create the project](#1-create-the-project)
  - [2. Give it a database](#2-give-it-a-database)
  - [3. Run the migrations](#3-run-the-migrations)
  - [4. Create the first administrator](#4-create-the-first-administrator)
  - [5. Serve it](#5-serve-it)
  - [6. Add modules](#6-add-modules)
- [What is behind sign-in](#what-is-behind-sign-in)
- [Extending or replacing the welcome page](#extending-or-replacing-the-welcome-page)
- [Why the core is required at `@dev`](#why-the-core-is-required-at-dev)
- [Learn more](#learn-more)
- [Licence](#licence)

## What uhifadhi is

An installation of uhifadhi is one organisation's own observatory over the
protected areas it manages. Each area is a real place in the database: a
gazetted boundary drawn on the map, the zones inside it, and the record of what
happens there. Around the areas stands the organisation itself — its people,
the positions they hold, and the permissions each position carries. On top of
that come the capabilities the organisation actually runs — patrols, incidents,
rosters — and each of those arrives as a **module** that an administrator
installs and then switches on for the areas that want it. An area that runs no
patrols never sees the patrol screens.

The whole platform is one sentence:

> **A module registers with the registry and renders in the shell.**

The registry is where a module declares itself — its screens, its place in the
catalogue, the permissions it wants an administrator to be able to grant. The
shell is the frame every screen is drawn in: the document, the navigation, the
theme. Everything a deployment can *do* arrives as a module on top of those two,
and the shell never learns any module's name — it renders what the registry
tells it is installed.

A fresh installation is empty, and honestly so. There are no demo areas, no
sample team and no pre-installed capabilities: an organisation creates its own
areas, invites its own people and installs the modules it needs. The install
guide below is the ordered path from nothing to that first signed-in screen.

## The tree

Uhifadhi is structured like the thing it protects.

**The seed** is this starter, `uhifadhi/skeleton`: planted once by
`composer create-project`, so boring it never changes. **The core** —
[`uhifadhi/uhifadhi`](https://github.com/uhifadhilabs/uhifadhi) — is updated
forever through composer, and it holds the registry every module registers with,
the shell you see, the team, the areas and the atlas every map and chart is
drawn with. **The branches** are the modules, `uhifadhi/<name>-module`, one per
capability. **The contracts**
([`src/Uhifadhi/Contracts/docs`](https://github.com/uhifadhilabs/uhifadhi/tree/main/src/Uhifadhi/Contracts/docs))
are the interfaces every branch carries without carrying the core: a module can
depend on them alone, and they are MIT, because an interface anybody may
implement should cost nobody anything.

**The tree is a picture, not a naming scheme.** It is the fastest way to explain
the shape and it lives in prose only — the packages are named for what they do,
so an import says what it is without the metaphor.

## What the core is

Two words carry the whole product. **The core** arrives whole and is never
picked apart: the module registry, the shell every screen renders in, the atlas
every map and chart is drawn with, the people, and the ground. **A module** is
what an administrator installs on top and switches on per area: patrols,
incidents, rosters.

The core is one package, `uhifadhi/uhifadhi`, and it is what this template
requires. Inside it are five bundles, and a developer reading
`config/bundles.php` will see all five listed. That list is not a claim that any
of them runs alone:

> Each core bundle can be installed into a Symfony application that also has the
> registry and the shell; Composer enforces that dependency, and a bundle listed
> in an application's bundle list is not a promise that it runs alone.

Which is why an installer document says "the core" and "modules", and "bundle"
is a word for developers.

## Spatial data and deployment

Spatial data lives in **PostGIS**, through
[`fundistadi/postgis-bundle`](https://github.com/fundistadi/postgis-bundle),
which the core brings with it. Geometry columns are typed —
`geometry(MultiPolygon,4326)` for a gazetted boundary, `point`, `linestring` —
and they get their GiST indexes straight from `doctrine:migrations:diff`. There
is no hand-written DDL anywhere in an installation.

Deployment is a standard Symfony application. This repository ships a production
`Dockerfile` (FrankenPHP): build the image and run it wherever you host
containers, next to any PostGIS database.

---

# Install guide

From nothing to a running installation. Each step depends on the one before it —
the order is the point.

## 1. Create the project

Until the packages are tagged and listed on Packagist, both flags are required:
`--repository` because there is no Packagist listing to resolve
`uhifadhi/skeleton` from, and `--stability=dev` because the only version that
exists is the `main` branch, which the default minimum stability of `stable`
does not match.

```bash
composer create-project uhifadhi/skeleton park \
  --stability=dev \
  --repository='{"type":"vcs","url":"https://github.com/uhifadhilabs/skeleton"}'
cd park
```

Use any name you like in place of `park`; it becomes the directory and, in the
next step, the local hostname and the database name.

That installs the core and wires it up: `config/bundles.php` already names every
bundle, `config/packages/` carries one commented file per core bundle plus
`security.yaml`, and `config/routes/` mounts the screens. There is nothing to
paste and no firewall to turn on. What there is not yet is a database.

The project this creates carries its own `composer.json` with the `vcs` entries
for the core and for devkit already in it, so a `composer require` run inside
the project — step 4 and step 6 included — needs no flags of its own.

## 2. Give it a database

uhifadhi stores gazetted boundaries as PostGIS geometry, so the database needs
the PostGIS extension.

`fundi` runs a native PostGIS cluster and wires the application to it:

```bash
fundi init
```

That writes `.fundi.local.yaml`. Open it and uncomment the one-line PostGIS
opt-in:

```yaml
database: postgis   # your ACTIVE (brew-linked) Postgres major, db = this dir's slug
```

It needs the toolchain once per Postgres major: `brew install postgresql@17
postgis`. Then:

```bash
fundi server:start
```

This creates `.env.local` with `DATABASE_URL`, spins up a PostGIS cluster,
creates this project's database, enables the `postgis` extension in it, and
serves the application over SSL at `https://park.localhost`. You write nothing
into `.env`.

To use a database of your own instead, set `DATABASE_URL` in `.env.local`
yourself and make sure `CREATE EXTENSION postgis` has been run in it.

## 3. Run the migrations

The core ships entities, not migration versions: the tables are the core's, the
migration history is **yours**. So you generate the migration, once, against the
schema the installed core describes.

The pattern is **diff → migrate → diff again**, and the second diff **must say
"No changes."** The first writes a migration for everything the core adds;
migrate applies it; the second proves the schema now matches the mapping
exactly. Churn on that second diff is a bug in the core, not something to route
around.

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:diff
php bin/console cache:clear
php bin/console asset-map:compile
```

`asset-map:compile` is not optional: the compiled asset manifest is stale until
you rebuild it, and stylesheets and scripts serve the old bytes until you do.

There is no catalogue command to run. The registry reconciles itself with what
is installed when the cache is warmed.

## 4. Create the first administrator

The firewall is on from the moment the project exists, and a fresh installation
has no account to get through it. This step is how the first administrator comes
to exist, and it runs **after** the migrations, because it writes to the table
they just created.

The command belongs to devkit, the development-only package that assembles every
command the platform offers. Requiring it as a dev dependency is what makes the
command exist; a production build never has it, and never has the command.

```bash
composer require --dev "uhifadhi/devkit-module:^0.1@dev"
php bin/console team:user:create you@example.org Ada Mwangi --tier=super-admin
```

Leave `--password=` off and the passphrase is read from standard input, so it
need never reach a shell history or a process list:

```bash
printf '%s' "$PASSPHRASE" | php bin/console team:user:create you@example.org Ada Mwangi
```

The tier defaults to `super-admin`, which is what this account is for: the first
administrator of an installation with nobody else in it. `--tier=admin` and
`--tier=staff` make lesser accounts once somebody can sign in.

## 5. Serve it

```bash
fundi server:start
```

Open `https://park.localhost` and sign in as the administrator from step 4.
Without `fundi`, `symfony server:start -d`, or `php -S 127.0.0.1:8000 -t public`
for a quick look.

## 6. Add modules

A module is one `composer require` and then the migration steps from section 3
again, because a module adds its own tables and its own assets:

```bash
composer require uhifadhi/patrol-module
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:diff       # must say "No changes"
php bin/console cache:clear
php bin/console asset-map:compile
```

The module then appears in the catalogue, and an administrator switches it on
for the areas that want it from that area's module grid.

---

## What is behind sign-in

Everything, and that is the installation's one rule.
`config/packages/security.yaml` names the four addresses a stranger has to reach
— sign-in, the forgotten-password screen, an invitation link, and the endpoint a
field client gets a token at — and shuts everything else, including every route
a module adds tomorrow. Installing a module never means editing that file.

What a signed-in person may *do* is not decided there. Each module declares its
own permissions and checks them in its own controllers, per action, per object
and per area — which a path rule could not express anyway.

## Extending or replacing the welcome page

The shell ships the welcome page at `/`, mounted by one line of consent in
`config/routes/shell.yaml`:

```yaml
shell:
    resource: '@ShellBundle/config/routes/welcome.php'
```

The shell loads that resource nowhere; the import is what makes `/` answer. Edit
the file to point `/` at your own home screen, or delete it and the address is
yours again — nothing is left behind. `debug:router` shows what you are
replacing: a route named `welcome`. Your own first page extends one of the
shell's three frames and fills one block:

```twig
{# templates/home/index.html.twig #}
{% extends '@Shell/page.html.twig' %}

{% block shell_page_title %}Home{% endblock %}

{% block shell_page %}
    <p>The first page of a new installation.</p>
{% endblock %}
```

## Why the core is required at `@dev`

`composer.json` requires `uhifadhi/uhifadhi: ^1.0@dev` from a `vcs` repository
rather than a plain version from a registry, and both halves have the same
one-sentence reason: until the core is tagged 1.0.0 it lives on a branch in a
private repository, and `create-project` needs `--stability=dev` to accept it.

Devkit is untagged for the same reason, which is why step 4 spells its stability
out too. Composer reads `repositories` only from the root package and never from
a dependency, so this file carries the `vcs` entry for devkit as well as the one
for the core — and both entries come out once the packages are published to
Packagist.

## Learn more

- [The architecture](docs/architecture.md) — what this repository is, what the
  core is, and what is deliberately not here.
- [Maintaining the skeleton](docs/maintaining-the-skeleton.md) — what happens
  to this copy after `create-project`, and how an installation takes a new core.

## Licence

**AGPL-3.0-or-later** — see [LICENSE](LICENSE). Use, modify and self-host freely;
if you offer a modified uhifadhi to users over a network, they are entitled to the
source of what they're running. Science is never paywalled.
