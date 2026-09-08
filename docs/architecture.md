# The architecture

**Uhifadhi is one skeleton and a set of modules.** The skeleton
(`uhifadhi/skeleton` — this repository) is copied once by
`composer create-project` and then it is yours; it is never updated again.
Everything else arrives as a module, updated forever through composer. A module
**registers with the seam** (`uhifadhi/seam-module`) and **renders in the shell**
(`uhifadhi/shell-module`); everything a deployment can do — patrols, incidents,
rosters — is a module.

That is the whole design constraint here: a line added to the skeleton is a line
every future installation is stuck with, so it stays boring on purpose. It is a
bare Symfony kernel, the seam, the shell, and the one thing that lets it grow —
the uhifadhilabs Flex recipe endpoint, which auto-wires a module when you
install it.

## What is in it

- A minimal Symfony 8.1 kernel (`src/Kernel.php`, `public/index.php`,
  `bin/console`, `config/`) — the `symfony/skeleton` shape, pruned.
- The seam (`uhifadhi/seam-module`) and the shell (`uhifadhi/shell-module`):
  the module catalogue every module registers with, and the page frames, theme
  and navigation seams every module renders into.
- The uhifadhilabs recipe endpoint, in `extra.symfony.endpoint`.
- A production `Dockerfile` (FrankenPHP) — the deploy shape ships with the
  skeleton because every installation needs one on day one.
- One smoke test: the kernel boots and a request completes.

## What is deliberately not in it

No authentication, no asset pipeline, no entities or fixtures, no modules, no
deploy configs beyond the `Dockerfile`. None of that is missing — each arrives
later as its own bundle, on its own release cadence.

Doctrine is the honest exception, and it is worth being precise about. It is
not a *direct* dependency of the skeleton: `composer.json` does not require it.
It is here because the seam requires it, and the seam requires it because the
seam owns tables — so a new project has doctrine-bundle, the ORM and
doctrine-migrations-bundle, and the two config files their recipes wrote. That
is the rule working, not an exception to it: capability arrives through a
bundle, and the bundle that adds tables brings what creates them. The skeleton's
job is only to have its `doctrine.yaml` name the right namespace when it does.

**What is not here yet is capability.** Until a module is installed, an
installation is the seam, the shell and that one page — which is a working
installation, not a half-finished one. Modules join one at a time, each proven by
a real `create-project` before the next begins.
