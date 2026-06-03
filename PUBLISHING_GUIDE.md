# Publishing Guide for akindutire/authorization-pkg

This guide walks you through publishing this Laravel package to Packagist.

## Prerequisites

Before publishing, ensure:

- [ ] All tests pass (`composer test`)
- [ ] Code follows PSR-12 standards
- [ ] Documentation is complete and accurate
- [ ] CHANGELOG.md is updated
- [ ] Version number follows Semantic Versioning
- [ ] composer.json is properly configured
- [ ] GitHub repository is public

## Step 1: Prepare the Package

### 1.1 Update Version Number

Update the version in your code and documentation:

```bash
# Update DOCUMENTATION.md
# Update README.md
# Update any version references
```

### 1.2 Update CHANGELOG.md

Document all changes since the last version:

```markdown
## [2.0.0] - 2026-06-02

### Added
- Auto-invalidating reflection cache
- Automatic $casts initialization in HasPermissions trait
- ReflectionCacheKeyGenerator for consistent cache keys
- Array support for grantPermission() and revokePermission()
- Role-based abilities configuration

### Changed
- Config file renamed from authorization.php to akindutire-authorization.php
- Improved documentation with accurate examples

### Fixed
- Cache warming now uses correct cache keys
- Permission methods now properly support both strings and arrays
```

### 1.3 Run Tests

```bash
composer test
```

### 1.4 Run Static Analysis

```bash
composer analyse  # or vendor/bin/phpstan analyse
```

### 1.5 Check composer.json

Ensure your `composer.json` is complete:

```json
{
    "name": "akindutire/authorization-pkg",
    "description": "A modern, attribute-based authorization package for Laravel 9+ that works with any Eloquent model using PHP 8 attributes",
    "type": "library",
    "license": "MIT",
    "keywords": [
        "laravel",
        "authorization",
        "permissions",
        "php8",
        "attributes",
        "eloquent",
        "acl"
    ],
    "authors": [
        {
            "name": "Akindutire Ayomide",
            "email": "akinsamuel33@gmail.com"
        }
    ],
    "require": {
        "php": "^8.1|^8.2|^8.3",
        "illuminate/support": "^9.0|^10.0|^11.0|^12.0|^13.0",
        "illuminate/database": "^9.0|^10.0|^11.0|^12.0|^13.0",
        "illuminate/http": "^9.0|^10.0|^11.0|^12.0|^13.0"
    },
    "autoload": {
        "psr-4": {
            "Akindutire\\Authorization\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Akindutire\\Authorization\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Akindutire\\Authorization\\AuthorizationServiceProvider"
            ],
            "aliases": {
                "EntityPermission": "Akindutire\\Authorization\\Facades\\EntityPermission"
            }
        }
    }
}
```

## Step 2: Create Git Tag

### 2.1 Commit All Changes

```bash
git add .
git commit -m "Prepare v2.0.0 release"
```

### 2.2 Create and Push Tag

```bash
# Create annotated tag
git tag -a v2.0.0 -m "Release version 2.0.0"

# Push to GitHub
git push origin main
git push origin v2.0.0
```

**Important:** Packagist uses Git tags to determine versions. Always use semantic versioning (v1.0.0, v2.1.0, etc.)

## Step 3: Publish to Packagist

### 3.1 Create Packagist Account

1. Go to https://packagist.org
2. Click "Sign in with GitHub"
3. Authorize Packagist to access your GitHub account

### 3.2 Submit Package

1. Go to https://packagist.org/packages/submit
2. Enter your repository URL: `https://github.com/akindutire/authorization-pkg`
3. Click "Check"
4. Click "Submit"

Packagist will:
- Clone your repository
- Read composer.json
- Create the package page
- Make it available via `composer require akindutire/authorization-pkg`

### 3.3 Set Up Auto-Update Hook (Recommended)

#### Option A: GitHub Service Hook (Recommended)

1. On Packagist, go to your package page
2. Click "Show API Token" and copy the token
3. Go to your GitHub repository → Settings → Webhooks → Add webhook
4. Payload URL: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
5. Content type: `application/json`
6. Secret: Paste your Packagist API token
7. Which events: Just the push event
8. Active: ✓
9. Click "Add webhook"

#### Option B: Manual Updates

If you don't set up auto-update, you'll need to manually update on Packagist after each Git push.

## Step 4: Verify Installation

Test that users can install your package:

```bash
# In a new Laravel project
composer require akindutire/authorization-pkg

# Check it installs correctly
php artisan vendor:publish --tag=authorization-config
```

## Step 5: Add Badges to README

Add status badges to your README.md:

```markdown
[![Latest Version](https://img.shields.io/packagist/v/akindutire/authorization-pkg.svg?style=flat-square)](https://packagist.org/packages/akindutire/authorization-pkg)
[![Total Downloads](https://img.shields.io/packagist/dt/akindutire/authorization-pkg.svg?style=flat-square)](https://packagist.org/packages/akindutire/authorization-pkg)
[![License](https://img.shields.io/packagist/l/akindutire/authorization-pkg.svg?style=flat-square)](https://packagist.org/packages/akindutire/authorization-pkg)
```

## Step 6: Announce Release

Consider announcing your package:

1. **Laravel News**: Submit to https://laravel-news.com/links
2. **Reddit**: Post to r/laravel and r/PHP
3. **Twitter/X**: Share with #Laravel hashtag
4. **Dev.to**: Write an article about the package
5. **LinkedIn**: Share with your network

## Ongoing Maintenance

### For Bug Fixes (Patch Releases: 2.0.1, 2.0.2)

```bash
# Fix the bug
git add .
git commit -m "Fix: Description of bug fix"
git tag -a v2.0.1 -m "Fix: Description"
git push origin main
git push origin v2.0.1
```

### For New Features (Minor Releases: 2.1.0, 2.2.0)

```bash
# Add the feature
git add .
git commit -m "Feature: Description of feature"
git tag -a v2.1.0 -m "Add: New feature"
git push origin main
git push origin v2.1.0
```

### For Breaking Changes (Major Releases: 3.0.0)

```bash
# Make breaking changes
# Update CHANGELOG with BREAKING CHANGES section
git add .
git commit -m "BREAKING: Description of changes"
git tag -a v3.0.0 -m "BREAKING: Major version release"
git push origin main
git push origin v3.0.0
```

## Troubleshooting

### Package not showing up on Packagist

- Verify your composer.json has a valid `name` field
- Ensure the repository is public
- Check that the tag was pushed (`git tag -l`)
- Wait a few minutes for Packagist to index

### "Package not found" when installing

- Make sure you're using the correct package name: `akindutire/authorization-pkg`
- Verify the package appears on Packagist
- Try `composer clear-cache` then install again

### Auto-update not working

- Check the webhook delivery in GitHub Settings → Webhooks
- Verify the Packagist API token is correct
- Ensure the payload URL is correct

## Security

If a security vulnerability is discovered:

1. **DO NOT** open a public issue
2. Email security concerns to: akinsamuel33@gmail.com
3. Wait for confirmation before disclosing
4. Release a patch version as soon as possible

## License

This package is MIT licensed. See LICENSE file for details.

## Support

- **Documentation**: https://github.com/akindutire/authorization-pkg
- **Issues**: https://github.com/akindutire/authorization-pkg/issues
- **Email**: akinsamuel33@gmail.com

---

**Last Updated:** 2026-06-02
