<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

use Psr\SimpleCache\CacheException as SimpleCacheCacheException;

class CacheException extends TemplateException implements SimpleCacheCacheException
{

}
