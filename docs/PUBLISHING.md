# Publish LibreLiveTopology

This project can be published to a new, empty GitHub repository named `LibreLiveTopology`. Runtime paths, package names, and database tables use the new project identity.

## Prepare the source

- Keep `LICENSE` and the README credits when publishing.
- Replace `<repository-url>` in the installation and contribution guides with the new repository's clone URL.
- Review `git status --short` and the staged diff before committing.
- Local `.env` files, dependencies, screenshots outside `docs/screenshots/`, chat exports, and local review artifacts are ignored.
- Check that release notes describe the exact commit being published.

## Start with fresh history

Export tracked files from a reviewed commit into a **separate empty directory**, then initialize the new repository there. This preserves the working repository and its history.

```bash
# In this working repository, after committing the reviewed files:
git archive --format=zip --output=../LibreLiveTopology-source.zip HEAD
```

Extract the archive into your new directory, then run:

```bash
git init -b main
git add .
git diff --cached --stat
git commit -m "Initial release of LibreLiveTopology"
git remote add origin <repository-url>
git push -u origin main
```

Create the GitHub repository without an initial README or license so the first push has no conflicting initial commit. Review the Actions results after pushing. Publish version tags only when `VERSION`, `composer.json`, and `CHANGELOG.md` agree.
