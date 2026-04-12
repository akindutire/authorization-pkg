# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release
- PHP 8 attribute-based authorization system
- `HasAny` and `HasAll` attributes for permission checking
- `ValidateSubjectAction` middleware with reflection-based validation
- `PermissionSvc` service for permission management
- `HasPermissions` trait for Eloquent models
- `EntityPermission` facade
- Support for any Eloquent model (User, TeamMember, etc.)
- Configurable default actions per role
- Permission inheritance (allowed - revoked)
- Comprehensive test suite
- GitHub Actions CI workflow
- Laravel 9, 10, 11 support
- PHP 8.0, 8.1, 8.2, 8.3 support

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- N/A

## [1.0.0] - TBD

Initial release.
