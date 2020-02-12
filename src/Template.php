<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

use Psr\SimpleCache\CacheInterface;
use StudyPortals\Template\Parser\Factory;
use StudyPortals\Template\Parser\FactoryException;
use StudyPortals\Template\Parser\TokenListException;

/**
 * Template
 *
 * This is a special purpose extension of the {@link TemplateNodeTree}.
 * It is used as the top-level node in a Template tree. It provides an
 * entry point into Factory and implements the template cache.
 *
 * This is the only node that can be created without a Parent and thus has
 * the ability to server as the root node in a template tree.
 * It is possible for a Template to be part of another template tree, so if
 * you encounter this class while traversing a tree, it does
 * not mean your at the root of the tree. Always use
 * {@link Node::getRoot()} for this purpose.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */

class Template extends TemplateNodeTree
{

    /**
     * @var CacheInterface|null $CacheStore
     **/
    protected static $CacheStore;

    /**
     * @var boolean $cache_enabled
     */

    protected static $cache_enabled = true;

    /**
     * @var array<mixed> $default_variables
     */

    protected static $default_variables = [];

    /**
     * @var string $file_name
     */

    protected $file_name;

    /**
     * @var TemplateNodeTree $Parent
     */

    protected $Parent;

    /**
     * Construct a new Template4 (stub).
     *
     * This method just creates an empty named Node. In order to build a
     * fully functional template you need to manually attach child Nodes or,
     * preferably, use {@link Template::templateFactory()} to construct a
     * template from a predefined template file.
     *
     * This method throws an exception if the provided {@link $name} argument
     * is invalid.
     *
     * @param string $name
     * @throws TemplateException
     * @see Template::templateFactory()
     */

    public function __construct($name)
    {

        parent::__construct($name);
    }

    /**
     * Prepare the Template for serialisation.
     *
     * Ensures the {@link $_file_name} property is included when
     * serialised.
     *
     * @return array
     */

    public function __sleep()
    {

        return array_merge(parent::__sleep(), ["\0*\0file_name"]);
    }

    /**
     * Construct a Template tree from a predefined template file.
     *
     * @deprecated please use `create`
     * @see Template::create()
     *
     * This method takes the predefined template definition from {@link
     * $template_file} and parses it into a Template tree returning the {@link
     * Template} at the top of the tree. The name for this {@link Template}
     * node will be the filename of the original {@link $template_file}, with
     * all illegal characters stripped.
     *
     * The optional third argument {@link $html} is used to
     * indicate the template to be parsed contains HTML; it is enabled by
     * default. When switched on, several parsing optimisations geared towards
     * HTML (but destructive to plain-text) are enabled. If you need to parse a
     * plaint-text template file, disabled this option.
     * The optional second argument {@link $locale} should
     * always be an empty string (c.q. its default value). This argument is
     * there to ensure method consistency with LocalizedFactory which inherits
     * from this method. The order was chosen in such a way to minimise the need
     * to overwrite the default arguments.
     *
     * This method provides an automated template cache. It compares the date
     * of the original template against the cached template. If the original
     * template has been updated, or the cache does not exist, the cache is
     * refreshed. In all other situations, the template is read directly from
     * the cache.
     * Using the template cache reduces the template load/parse time
     * dramatically (in most situations, reading the cache is ~200 times faster
     * than parsing the actual template).
     *
     * Template4 is able to utilise the caching framework provided by the
     * {@link Cache} class for optimal caching flexibility. If no cache handler
     * is provided Template4 falls back to a simple file-system based caching
     * approach:
     * The cached template is stored at the same location and under the same
     * name as the original {@link $template_file}, with "-cache" appended to
     * its name.
     *
     * @param string $template_file
     * @throws CacheException
     * @throws FactoryException
     * @throws TemplateException
     * @throws TokenListException
     * @return Template
     * @see Template::_parseTemplate()
     * @see Template::setTemplateCacheHandler()
     */

    public static function templateFactory($template_file)
    {

        $cache_file = "$template_file-cache";

        // Load from cache

        if (self::$cache_enabled) {
            try {
                $Template = self::loadCachedTemplate($template_file, $cache_file);
            } catch (CacheException $e) {
                unset($Template);
            }
        }

        // Parse from template-file

        if (!isset($Template) || ($Template instanceof Template) == false) {
            $name = basename($template_file);
            $name = (string) substr($name, 0, (int) strrpos($name, '.'));
            $name = (string) preg_replace('/[^A-Z0-9]+/i', '', $name);

            $TemplateTokens = Factory::parseTemplate($template_file);

            $Template = new Template($name);
            $Template->file_name = $template_file;
            Factory::buildTemplate($TemplateTokens, $Template);

            if (self::$cache_enabled) {
                self::storeCachedTemplate($Template, $cache_file);
            }
        }

        static::attachDefaultVariables($Template);

        return $Template;
    }

    /**
     * Create a Template from a template-file.
     *
     * @param string $template_file
     * @return Template
     * @throws TemplateException
     */

    public static function create(string $template_file): Template
    {

        try {
            return self::templateFactory($template_file);
        } catch (
            \Psr\SimpleCache\CacheException
            | CacheException
            | TokenListException
            | FactoryException $e
        ) {
            throw new TemplateException(
                "Cannot create template for file ${template_file}",
                0,
                $e
            );
        }
    }

    /**
     * Attach the default variables.
     *
     * @param Template $Template
     *
     * @throws TemplateException
     * @return void
     */
    protected static function attachDefaultVariables(Template $Template)
    {

        foreach (static::$default_variables as $name => $value) {
            $Template->setValue($name, $value);
        }
    }

    /**
     * Set a default variable to be included when a Template is created.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public static function setDefaultVariable($name, $value)
    {

        static::$default_variables[$name] = $value;
    }

    /**
     * Save a serialised copy of the template-tree to the cache.
     *
     * Errors writing the cache will only generate a failed assertion. This
     * ensures normal operation (although with a major performance hit)
     * continues if caching fails.
     *
     * @param Template $Template
     * @param string $cache_file
     * @throws CacheException
     * @return void
     */

    protected static function storeCachedTemplate(Template $Template, $cache_file)
    {

        /*
         * Some sanity-checks on the to-be-cached Template.
         *
         * We recently had some issues with invalid templates getting cached,
         * causing all kinds of crazy problems (see #2973). This checks are
         * both intended to signal the issue (so I know I'm actually looking
         * in the right place) and to prevent invalid templates from getting
         * cached (and thus prevent them from causing further issues).
         */

        $Root = $Template->getRoot();

        if ($Root !== $Template) {
            throw new CacheException('Trying to cache a non-root Template');
        }

        if (count($Template->children) == 0) {
            throw new CacheException('Template has no children');
        }

        foreach ($Template->children as $key => $Child) {
            if (!is_numeric($key) && !($Child instanceof TemplateNodeTree)) {
                throw new CacheException('Template has an invalid named-Child element');
            }

            if (!($Child instanceof Node)) {
                throw new CacheException('Template has an invalid Child element');
            }
        }

        // Fallback to simple file-system caching

        if (!(self::$CacheStore instanceof CacheInterface)) {
            $result = @file_put_contents($cache_file, serialize($Template), LOCK_EX | FILE_TEXT);
            assert($result > 0);

            return;
        }

        $template_mtime = @filemtime($Template->getFileName());

        $result = self::$CacheStore->set(md5($template_mtime . $cache_file), $Template);
        assert($result === true);
    }

    /**
     * Attempt to load a previously cached template file.
     *
     * This method can throw a {@link CacheException} which indicates a
     * recoverable error with the template cache. Simply re-create the cache
     * and continue.
     * Alternatively, this method can throw a {@link TemplateException} which
     * indicates a fatal, non-recoverable, problem with the cache. It's probably
     * best to let this exception cascade on so it shows up on your radar.
     * Otherwise, more serious issues might go unnoticed.
     *
     * @param string $template_file
     * @param string $cache_file
     * @throws CacheException
     * @throws TemplateException
     * @return Template
     */

    protected static function loadCachedTemplate($template_file, $cache_file)
    {
        $template_base = basename($template_file);
        $template_mtime = @filemtime($template_file);

        // Attempt to utilise an external cache-engine

        $Template = self::loadCachedTemplateFromCacheEngine(
            $cache_file,
            $template_mtime,
            $template_base
        );

        // Fall-back to simple filesystem-based cache

        if (!($Template instanceof Template)) {
            $Template = self::loadCachedTemplateFromFile(
                $cache_file,
                $template_mtime,
                $template_base
            );
        }

        // No cache available

        if (!($Template instanceof Template)) {
            throw new CacheException(
                "Cache-file for template $template_base was not found or
                was inaccessible"
            );
        }

        return $Template;
    }

    /**
     * Load cached template from CacheEngine.
     *
     * @param string $cache_file
     * @param integer|false $template_mtime
     * @param string $template_base
     * @return Template|null
     * @throws CacheException
     */

    protected static function loadCachedTemplateFromCacheEngine(
        string $cache_file,
        $template_mtime,
        string $template_base
    ): ?Template {

        if ($template_mtime !== false && self::$CacheStore instanceof CacheInterface) {
            $cache_handler = get_class(self::$CacheStore);
            $cache_entry = md5($template_mtime . $cache_file);

            $Template = self::$CacheStore->get($cache_entry);

             // Delete the invalid entry
            if (is_null($Template)) {
                self::$CacheStore->delete($cache_entry);

                throw new CacheException(
                    "$cache_handler encountered an unknown error while
                    retrieving '$template_base'"
                );
            }

            if ($Template instanceof Template) {
                return $Template;
            }

            throw new CacheException(
                "$cache_handler failed to locate a cached copy of
                template $template_base"
            );
        }

        return null;
    }

    /**
     * Load cached template from filesystem.
     *
     * @param string $cache_file
     * @param integer|false $template_mtime
     * @param string $template_base
     * @return Template
     * @throws TemplateException
     * @throws CacheException
     */

    protected static function loadCachedTemplateFromFile(
        string $cache_file,
        $template_mtime,
        string $template_base
    ): ?Template {

        if (is_readable($cache_file)) {
            // Use cache if it is "fresh" or if the original template is missing

            if (
                $template_mtime === false ||
                ($template_mtime !== false && $template_mtime <= @filemtime($cache_file))
            ) {
                $cached_data = @file_get_contents($cache_file);

                assert($cached_data !== false);

                $Template = @unserialize($cached_data);

                // Remove the corrupted cache
                if (!($Template instanceof Template)) {
                    unlink($cache_file);

                    throw new TemplateException(
                        "Corrupted cache encountered for template $template_base"
                    );
                }

                return $Template;
            }

            throw new CacheException(
                "Cache-file expired for template $template_base"
            );
        }

        return null;
    }

    /**
     * Set the global state of the template cache.
     *
     * Enables or disables the creation and use of cached templates. Enabled
     * by default, disabling simplifies development, but comes at a significant
     * performance penalty.
     *
     * @param string $state [on|off]
     * @return void
     */

    public static function setTemplateCache($state)
    {

        self::$cache_enabled = (bool) filter_var($state, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set the global template CacheStore.
     *
     * Template4 is able to utilise the caching infrastructure provided
     * through the {@link Cache} classes. To enable this feature simple pass a
     * CacheStore to this method. When no store is provided, Template4 falls
     * back to a simple file-system cache.
     *
     * @param CacheInterface $CacheStore
     * @return void
     */

    public static function setTemplateCacheStore(CacheInterface $CacheStore)
    {

        self::$CacheStore = $CacheStore;
    }

    /**
     * Get the name of the file this Template instance was created from.
     *
     * Returns the full file name (relative to the PHP-file calling the
     * {@link Template::templateFactory()} method) of the template file used to
     * build this Template instance.
     *
     * @return string
     */

    public function getFileName()
    {

        return $this->file_name;
    }
}
