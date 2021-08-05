# Running tests

### Setup:

See https://pippinsplugins.com/unit-tests-wordpress-plugins-setting-up-testing-suite/

```bash
# Get phpunit
> curl -O https://phar.phpunit.de/phpunit-7.5.20.phar

# Setup db, get WordPress test-libs, etc.
# NOTE: This requires svn
> ./bin/install-wp-tests.sh <...>

```

### Run tests:

```bash
> php phpunit-7.5.20.phar
```
