# AI Changelog Workflow

## Purpose
Instructions for AI assistants to properly update CHANGELOG.md after completing work, ensuring nothing is lost between chat sessions.

---

## At the End of Each AI Chat Session

Use this prompt to ensure AI updates the changelog:

```
Please update CHANGELOG.md with the changes made in this session.

Rules:
1. Add changes to the [Unreleased] section at the top
2. Use English only
3. Use Keep a Changelog format: Added, Changed, Fixed, Removed, etc.
4. Be specific about what was changed (file names, feature names)
5. Do NOT modify existing version sections
6. Do NOT bump the VERSION file (I will do this manually when releasing)
```

---

## CHANGELOG.md Format

The file should have an `[Unreleased]` section at the top where AI changes accumulate:

```markdown
## [Unreleased]

### Added
- New feature X in `ComponentName.vue`

### Changed
- Updated `SomeController.php` to improve performance

### Fixed
- Fixed bug in email sending logic

---

## [1.0.4] - 2025-12-21
...
```

---

## When Ready to Release

Use this prompt:

```
I'm ready to release version X.Y.Z. Please:
1. Move all [Unreleased] changes to new section ## [X.Y.Z] - YYYY-MM-DD
2. Update VERSION file to X.Y.Z
3. Clear the [Unreleased] section (keep the header)
```

Then merge to main → release is created automatically.

---

## Quick Reference

| When | Prompt |
|------|--------|
| **After each task** | "Update CHANGELOG.md with changes from this session" |
| **Release time** | "Release version X.Y.Z - move Unreleased to version section and update VERSION" |
