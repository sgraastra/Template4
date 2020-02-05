# PHP Template Repository (RFC-5)

For use with newly created **PHP 7.2+** projects &ndash; for a good example see
[**PageModels**](https://github.com/studyportals/PageModels).

## Using the Template

Create a new repository in GitHub using `repo-template-php` as your template.
Once you've checked out your new repository, make the following modifications:

1. Add proper `name`- and `description`-properties to your [`composer.json`]()
2. Remove [`README.md`]() (and ideally replace it with your own `README.md`)
3. Update `CC_TEST_REPORTER_ID=...` in [`.travis.yml`]() with a proper Code
   Climate ID to enable code-coverage reporting &ndash; if you don't want to use
   Code Climate, remove [`.travis.yml`]()
4. Execute `composer install`

### Initialise Automatic Updates

When you've finished with the above steps, execute
`sh .github/repo-template/update.sh -f` to initialise the
[automatic update functionality](#automatic-updates). A commit is automatically
created for you; its commit message should pop up.

⚠ On **Windows**, the `sh` command _might_ fail &ndash; in that case execute
`.\.github\repo-template\update.sh -f` instead.

## The Coding Standard

Uses **[PHP Code Sniffer](https://github.com/squizlabs/PHP_CodeSniffer)**
(`phpcs`) to enforce the
**[PSR-12 extended coding style](https://www.php-fig.org/psr/psr-12/)**.

Apart from PSR-12, Code Sniffer also enforces
**[strict type-checks for scalar types](https://wiki.php.net/rfc/scalar_type_hints_v5)**.
You need to manually enable these checks in _each_ PHP file as follows:

```php
<?php declare(strict_types=1);

// Code goes here...
```

Enable the full
[**PHP Mess Detector** (`phpmd`) rule-set](https://phpmd.org/rules/index.html)
with the exception of the
[controversial-rules](https://phpmd.org/rules/controversial.html), the
[naming-rules](https://phpmd.org/rules/naming.html) (which are covered by
PSR-12) and the [Unused variable rule]() (which is covered by PHPStan &ndash;
see below).

Use the **[PHP Static Analysis Tool](https://github.com/phpstan/phpstan)**
(`phpstan`) to enforce maximum type-strictness (and catch a large variety of
additional errors in the process).

Provide a skeleton for setting up
**[PHPUnit](https://github.com/sebastianbergmann/phpunit)** (including code
coverage analysis) and **[Code Climate](https://codeclimate.com/)**.

Finally, use
**[composer-git-hooks](https://github.com/BrainMaestro/composer-git-hooks)** to
run all of the above checks before allowing you to push to GitHub.

## Automatic Updates

The [`.github/repo-template/update.sh`](.github/repo-template/update.sh)
shell-script is intended to keep your repository's copy of the template
configuration up-to-date with main template on GitHub.

It is set up as a Git `post-checkout` hook using `composer-git-hooks`. Whenever
you switch to a branch that is _not_ `master` or `develop`, the script will
attempt to automatically update the template configuration.

The functionality is still somewhat experimental. If it gets on your nerve, feel
free to update [`composer.json`](composer.json) and remove the `post-checkout`
line under `extra:{hooks}`.

## Caveats

Due to limitations with regards to configuring `phpmd`, there are two separate
configuration files: One for the `src/`-folder and one for the `tests/`-folder.

For PHPUnit to properly work, all your test-files should end in `*Test.php`
_and_ they need to be located in the `tests/` folder.

For code-coverage to work you need to install **[XDebug](https://xdebug.org/)**;
this is not enforced through `composer.json` as that might lead to unnecessarily
loading it on production-environments...
