# Contributing

Bug reports and pull requests are welcome.

## Layout

- `Atelier/` is the module itself, exactly what ends up in `Modules/Atelier`.
- `dev/src/` holds the CSS layers; `python3 dev/assemble.py` rebuilds `Atelier/Public/css/atelier.css`. Commit both.
- `docs/screenshots/` are the images used in the README.

## Releasing

1. Bump `version` in `Atelier/module.json` and add an entry to `CHANGELOG.md`.
2. Commit and push to `main`.
3. Tag and push: `git tag v1.5.1 && git push origin v1.5.1`.

The workflow checks that the tag matches `module.json`, builds `Atelier.zip` (root folder `Atelier/`) and attaches it to a GitHub release. FreeScout installations then offer the update in Manage > Modules.

The release asset **must** be named `Atelier.zip` and the module must stay at the root of the zip: the update URL is `releases/latest/download/Atelier.zip`.
