# Template4

A PHP Template-engine.

- [Usage](#usage)
  - [Scoping, Sections and Nesting](#scoping-sections-and-nesting)
  - [Repeaters](#repeaters)
  - [Includes](#includes)
  - [Dynamic merging of Templates](#dynamic-merging-of-templates)
  - [Caching](#caching)
- [Development](#development)
- [References](#references)

## Usage

```php
use StudyPortals\Template\Template;

$template = Template::create('Templates/Home.tp4');

$template->title = 'Hello world!';

$template->header->foo = 'bar';

foreach(['item 1', 'item 2'] as $item){

  $template->listOfItems->name = $item;
  $template->listOfItems->repeat();
}

echo $template;
```

Any scalar value and any object implementing `__toString()` (including
additional templates) can be passed into `$template` for use as a variable.

### Scoping, Sections and Nesting

`Section`-elements can be added into a template to create scoping-boundaries.

A variable defined in a `Section` (such as `foo` in section `header` above) is
only available in that `Section` and its descendants.

Redefining an existing variable (i.e. one already provided by a higher scope)
overwrites the variable inside the `Section` and its descendants. It does not
impact the variable in the higher scope.

Scoping is applied at the `TemplateNodeTree`-level, so anything extending from
there (e.g. `Section`, `Repeater` and all include-statements) act as a
scoping-boundary.

`TemplateNodeTree`-elements can be infinitely nested &ndash; up to the point
where you might run into the "physical" recursion limits in PHP (which is highly
unlikely to happen in any real-world scenario).

### Repeaters

A special type of `Section` is the `Repeater`.

It behaves like a regular section, with one major difference: When you can call
its `repeat()` method, the current state of the `Repeater` is rendered out and
stored to be outputted later.

Subsequently, all variables in the `Repeater` scope are cleared, allowing you to
fill it from scratch. This enables the rendering of all kinds of dynamic
repeating structures (lists, menus, etc.).

### Includes

Files can be included from the file-system using the `include` (for static HTML
content) and `include template` (for Template4 files) syntax.

Included templates behave no differently than if you were to copy-paste their
contents in place of the `inclue template`-statement.

### Dynamic merging of Templates

If you pass anything which extends from `TemplateNodeTree` into a template
instance, it is merged into that instance. This behaviour is identical to using
an `include template`-statement in the template file itself.

This enables advanced dynamic (and even recursive) behaviour. For an example of
this, have a look at the PHP-code in [`📂 tests/smoke`](./tests/smoke/).

### Caching

To improve performance, it is advisable to cache the parsed templates. This
causes an intermediate representation of the template (using PHP's serialisation
format) to be stored.

To use caching, enable it _prior_ to creating a template.

```php
Template::setTemplateCache('on');
```

Optionally, you can use the `setTemplateCacheStore()` to provide a
[Psr-compliant `CacheInterface`](https://github.com/php-fig/simple-cache/blob/master/src/CacheInterface.php).
Instead of generating `.tp4-cache`-files alongside the template files, the
provided `CacheInterface` will now be used.

```php
Template::setTemplateCache('on');
Template::setTemplateCacheStore($somePsrCache);
```

When the template files changes, the cache is automatically invalidated.

⚠ Note that included files are cached as part of the main template. Changes to
the included files will **not** invalidate the main template's cache...

## Development

Requires PHP 7.2.

```bash
composer install

# Run smoke-tests
composer run phpunit

# Run compliance-tests
composer run phpstan
composer run phpcs
composer run phpmd
```

## References

1. [`📄 docs/TESTING.md`](./docs/TESTING.md)
2. [`📄 docs/SYNTAX.md`](./docs/SYNTAX.md)
3. https://github.com/studyportals/template4-parser
4. https://github.com/studyportals/CMS
