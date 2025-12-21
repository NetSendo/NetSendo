---
description: How to release a new version of NetSendo
---

# Release Workflow

When the user says something like:
> "Release version X.Y.Z with title 'Short Description'"

Follow these steps:

## 1. Update CHANGELOG.md

Move all content from `## [Unreleased]` section to a new version section:

```markdown
## [X.Y.Z] – Short Description - YYYY-MM-DD
```

Keep the `## [Unreleased]` section empty with the AI comment placeholder.

## 2. Update VERSION file

Change the version number in `/VERSION` file to `X.Y.Z`

## 3. Update src/config/netsendo.php

**IMPORTANT**: Update the `'version'` value on line ~15 in `/src/config/netsendo.php`:

```php
'version' => 'X.Y.Z',
```

## 4. Commit and Push

After all changes, the GitHub Actions workflow will automatically:
- Create a git tag `vX.Y.Z`
- Create a GitHub Release with title: `vX.Y.Z – Short Description`
- Include the changelog notes in the release body

---

## Files to update:
1. `CHANGELOG.md` - new version section header with title
2. `VERSION` - version number only
3. `src/config/netsendo.php` - version in PHP config (~line 15)
