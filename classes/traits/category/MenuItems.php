<?php

namespace OFFLINE\Mall\Classes\Traits\Category;

use Cache;
use Cms\Classes\Controller;
use InvalidArgumentException;
use OFFLINE\Mall\Models\Category;
use OFFLINE\Mall\Models\GeneralSettings;

trait MenuItems
{
    /**
     * @param $item
     * @param $url
     * @param $theme
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function resolveCategoryItem($item, $url, $theme)
    {
        $category = self::find($item->reference);
        if ( ! $category) {
            return;
        }

        return self::getMenuItem($category, $url);
    }

    /**
     * @param $item
     * @param $url
     * @param $theme
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function resolveCategoriesItem($item, $url, $theme)
    {
        $category = new Category();
        $locale   = $category->getLocale();

        if (Cache::has($category->treeCacheKey($locale))) {
            return Cache::get($category->treeCacheKey($locale));
        }

        $structure = [];
        $iterator  = function ($items, $baseUrl = '') use (&$iterator, &$structure, $url, $locale) {
            $branch = [];
            foreach ($items as $item) {
                $branchItem = self::getMenuItem($item, $url);
                $item->setTranslateContext($locale);
                if ($item->children) {
                    $branchItem['items'] = $iterator($item->children, $item->slug);
                }
                $branch[] = $branchItem;
            }

            return $branch;
        };

        $structure['items'] = $iterator($category->getEagerRoot());

        Cache::forever($category->treeCacheKey($locale), $structure);

        return $structure;
    }

    /**
     * Creates a single menu item result array
     *
     * @param $item Category
     * @param $url  string
     *
     * @return array
     * @throws \Cms\Classes\CmsException
     */
    protected static function getMenuItem($item, $url)
    {
        if ( ! $pageUrl = GeneralSettings::get('category_page')) {
            throw new InvalidArgumentException(
                'Mall: Please select a category page via the backend settings.'
            );
        }

        $controller = new Controller();
        $entryUrl   = $controller->pageUrl($pageUrl, ['slug' => $item->nestedSlug]);

        $result             = [];
        $result['url']      = $entryUrl;
        $result['isActive'] = $entryUrl === $url;
        $result['mtime']    = $item->updated_at;
        $result['title']    = $item->name;
        $result['code']     = $item->code;

        return $result;
    }

    public static function getMenuTypeInfo($type)
    {
        $result = [];
        if ($type === 'mall-category') {
            $result = [
                'references' => Category::listSubCategoryOptions(),
            ];
        }

        if ($type === 'all-mall-categories') {
            $result = [
                'dynamicItems' => true,
            ];
        }

        return $result;
    }

    /**
     * Lists all categories with nested sub categories
     * This is used for the 'mall-category' menu type
     *
     * @return array
     */
    protected static function listSubCategoryOptions()
    {
        $category = self::getNested();
        $iterator = function ($categories) use (&$iterator) {
            $result = [];
            foreach ($categories as $category) {
                if ( ! $category->children) {
                    $result[$category->id] = $category->name;
                } else {
                    $result[$category->id] = [
                        'title' => $category->name,
                        'items' => $iterator($category->children),
                    ];
                }
            }

            return $result;
        };

        return $iterator($category);
    }

    /**
     * Return a locale specific id map cache key.
     *
     * @param $locale
     *
     * @return string
     */
    protected function mapCacheKey($locale)
    {
        return Category::MAP_CACHE_KEY . '.' . $locale;
    }

    /**
     * Return a locale specific tree cache key.
     *
     * @param $locale
     *
     * @return string
     */
    protected function treeCacheKey($locale)
    {
        return Category::TREE_CACHE_KEY . '.' . $locale;
    }


    /**
     * Purge all cached category data.
     * @return void
     */
    protected function purgeCache()
    {
        foreach ($this->getLocales() as $locale) {
            Cache::forget($this->treeCacheKey($locale));
            Cache::forget($this->mapCacheKey($locale));
        }
    }

    /**
     * Pre-populate the cache.
     * @return void
     */
    protected function warmCache()
    {
        foreach ($this->getLocales() as $locale) {
            $this->getSlugMap($locale);
        }
    }
}