# Publish the PHP SDK to Packagist

Use this checklist when releasing a new version of the SDK to Packagist (`bosbase/php-sdk`).

## 1) Prep the release
- Update code and docs; ensure `composer.json` metadata (name, description, license, PHP constraints) is correct.
- Run `composer validate` to catch metadata issues.
- Run tests/lint if applicable (e.g. `./vendor/bin/phpunit`).

## 2) Tag the version
- Commit your changes, then create an annotated tag that matches the semantic version:
  ```bash
  git tag -a v0.1.0 -m "PHP SDK v0.1.0"
  git push origin main --tags
  ```
- Packagist reads tags to determine available versions; no manual version bump in `composer.json` is required beyond keeping metadata current.

## 3) Trigger Packagist
- If the repository is already connected to Packagist with auto-updates:
  - The pushed tag will trigger a refresh automatically. Verify on https://packagist.org/packages/bosbase/php-sdk.
- If auto-updates are not configured:
  - Log in to Packagist, open the package, and click **Update**; or run the API trigger:
    ```bash
    curl -X POST \
      -H "Content-Type: application/json" \
      -d '{"repository":{"url":"<REPO_HTTPS_URL>"}}' \
      "https://packagist.org/api/update-package?username=<USER>&apiToken=<TOKEN>"
    ```

## 4) Verify install
- From a clean project, require the new version:
  ```bash
  composer require bosbase/php-sdk:^0.1.0
  ```
- Confirm the package installs and autoloading works.

## Notes
- Packagist versions are driven by git tags; avoid deleting/rewriting tags for published versions.
- Keep `master/main` default branch set on Packagist so metadata stays current between releases.
- If you rotate the GitHub/GitLab token, update the Packagist repository settings to keep auto-updates working.
