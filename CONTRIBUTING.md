# Contributing to Laravel Authorization Package

Thank you for considering contributing to this package! This document outlines the development process.

## Development Setup

1. Clone the repository:
```bash
git clone https://github.com/yourusername/authorization-pkg.git
cd authorization-pkg
```

2. Install dependencies:
```bash
composer install
```

3. Run tests:
```bash
composer test
```

## Testing

We use PHPUnit for testing. The package includes:

- **Unit Tests** (`tests/Unit/`) - Test individual classes in isolation
- **Feature Tests** (`tests/Feature/`) - Test integrated components

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage report
composer test-coverage

# Run static analysis
composer analyse
```

### Writing Tests

- All new features must include tests
- Aim for high code coverage
- Test both success and failure scenarios
- Use descriptive test method names

Example:
```php
/** @test */
public function it_allows_request_when_user_has_required_permission()
{
    // Arrange
    $user = TestUser::create([...]);

    // Act
    $result = $service->subject($user)->hasAny(['can_view']);

    // Assert
    $this->assertTrue($result);
}
```

## Code Style

- Follow PSR-12 coding standards
- Use type hints for all parameters and return types
- Add PHPDoc blocks for public methods
- Keep methods focused and small

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Write or update tests
5. Ensure all tests pass (`composer test`)
6. Commit your changes with descriptive messages
7. Push to your fork
8. Open a Pull Request

### PR Requirements

- All tests must pass
- Code must follow PSR-12 standards
- Include tests for new features
- Update README.md if needed
- Add entry to CHANGELOG.md (if exists)

## Bug Reports

When reporting bugs, please include:

- PHP version
- Laravel version
- Package version
- Detailed steps to reproduce
- Expected vs actual behavior
- Error messages or stack traces

## Feature Requests

We welcome feature requests! Please:

- Check if the feature already exists
- Explain the use case
- Provide examples if possible
- Consider submitting a PR

## Questions?

Feel free to open an issue for questions or discussion.

Thank you for contributing!
