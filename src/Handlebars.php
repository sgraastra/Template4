<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

use StudyPortals\CMS\Site\Site;
use StudyPortals\Template\Parser\FactoryException;
use StudyPortals\Template\Parser\HandlebarsFactory;
use StudyPortals\Template\Parser\TokenListException;
use StudyPortals\Utils\File;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 */

class Handlebars extends Template
{

    /**
     * Construct a handlebars Template tree from a predefined template file.
     *
     * @param string $template_file
     * @throws CacheException
     * @throws FactoryException
     * @throws TemplateException
     * @throws TokenListException
     * @throws \StudyPortals\Cache\CacheException
     * @return Handlebars
     * @see Factory::templateFactory()
     */

    public static function templateFactory($template_file)
    {

        $name = basename($template_file);
        $directory = dirname($template_file);

        $extension = File::getExtension($template_file);
        $name = (string) substr($name, 0, (int) strrpos($name, '.'));

        $cache_file = "$directory/$name-handlebars.$extension-cache";
        $name = (string) preg_replace('/[^A-Z0-9]+/i', '', $name);

        // Load from cache

        if (Template::$cache_enabled) {
            try {
                $Template = parent::loadCachedTemplate($template_file, $cache_file);
            } catch (CacheException $e) {
                // Caching-failures can happen; ignore and rebuild from scratch
            }
        }

        // Parse from template-file

        if (!isset($Template) || ($Template instanceof Handlebars) == false) {
            $TemplateTokens = HandlebarsFactory::parseTemplate($template_file);

            $Template = new Handlebars($name);
            $Template->file_name = $template_file;
            HandlebarsFactory::buildTemplate($TemplateTokens, $Template);

            if (Template::$cache_enabled) {
                parent::storeCachedTemplate($Template, $cache_file);
            }
        }

        // Add Site's base URL (this must be some kind of clean-code violation... :)

        if (Site::singleton() instanceof Site) {
            $Template->base_url = Site::singleton()->base_url;
        }

        return $Template;
    }
}
