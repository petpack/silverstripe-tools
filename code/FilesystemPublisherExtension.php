<?php
/**
 * Provides the most common FilesystemPublisher configuration as an extension, with support for:
 * 
 * - Regenerating cached sibling nodes on publish
 * - Excluding Page types and their subclasses from inclusion in the cache
 * - A filter for excluding nodes from the cache
 * 
 * Usage: SiteTree::add_extension('SiteTree', 'FilesystemPublisherExtension');
 */
class FilesystemPublisherExtension extends SiteTreeDecorator {

	public static $update_siblings_on_publish = false;
	public static $delete_cache_on_publish = false;
	public static $exclude_classes = array('UserDefinedForm', 'MemberProfilePage', 'RedirectorPage');
	public static $cache_filter = '';
	protected static $subclass_filter;

	/**
	 * Excludes the specified class and it's subclasses.
	 * @param string $class
	 */
	static function exclude_class( $class ) {
		self::$exclude_classes[] = $class;
	}

	/**
	 * Excludes the specified classes and their subclasses.
	 * @param array $classes
	 */
	static function exclude_classes( $classes ) {
		foreach( $classes as $class ) {
			self::$exclude_classes[] = $class;
		}
	}

	/**
	 * Called after a page is published.
	 */
	function onAfterPublish($original) {
		if( self::$delete_cache_on_publish ) {
			$this->deleteAllCachedFiles();
		}
	}

	function deleteAllCachedFiles() {
		$publisher = $this->owner->getExtensionInstance('FilesystemPublisher'); /* @var $publisher FilesystemPublisher */
		$publisher->setOwner($this->owner);
		$files = $publisher->urlsToPaths($this->allPagesToCache());
		foreach( $files as $url => $file ) {
			$file = $publisher->getDestDir().'/'.$file;
			if( is_file($file) ) {
				unlink($file);
			}
		}
	}

	/**
	 * Return a list of all the pages to cache
	 */
	function allPagesToCache() {
		// Get each page type to define its sub-urls
		$urls = array();
		if( class_exists('Subsite') ) {
			$pages = Subsite::get_from_all_subsites('SiteTree', self::get_cache_filter());
		}
		else {
			$pages = DataObject::get('SiteTree', self::get_cache_filter());
		}
		foreach( $pages as $page ) {
			$urls = array_merge($urls, $page->getURLsToCache());
		}
		// add any custom URLs which are not SiteTree instances
		$urls[] = Director::absoluteBaseURL().'sitemap.xml';
		return $urls;
	}

	function pagesAffectedByChanges() {
		return $this->getURLsToCache();
	}

	function getURLsToCache() {
		// Defines any pages which should not be cached
		$excluded = array();
		$urls = array();
		if( $this->owner->canView(new Member(array('ID' => -1))) ) {
			$urls[] = $this->owner->AbsoluteLink();
		}
		$urls = array_merge($urls, $this->owner->subPagesToCache());
		if( self::$update_siblings_on_publish ) {
			if( $p = $this->owner->Parent ) {
				$siblings = $p->Children(self::get_cache_filter());
			}
			else {
				$siblings = DataObject::get('SiteTree', 'ParentID = 0 && '.self::get_cache_filter());
			}
			if( $siblings ) {
				foreach( $siblings as $sibling ) {
					$urls[] = $sibling->AbsoluteLink();
					$urls = array_merge($urls, (array) $sibling->subPagesToCache());
				}
			}
		}
		//* debug */ Debug::show($urls);
		return $urls;
	}

	/**
	 * Get a list of URLs to cache related to this page
	 */
	function subPagesToCache() {
		$urls = array();
		// only cache the RSS feed if anyone can view this page
		if( $this->owner->ProvideComments && $this->owner->canView() ) {
			$urls[] = Director::absoluteBaseURL().'pagecomment/rss/'.$this->owner->ID;
		}
		return $urls;
	}

	static function get_cache_filter() {
		if( !isset(self::$subclass_filter) ) {
			$excludeClasses = array();
			foreach( self::$exclude_classes as $class ) {
				$excludeClasses[] = $class;
				foreach( ClassInfo::subclassesFor($class) as $subClass ) {
					$excludeClasses[] = $subClass;
				}
			}
			self::$subclass_filter = ($excludeClasses ? "ClassName NOT IN ('".implode("', '", $excludeClasses)."')" : '');
		}
		return self::$cache_filter.(self::$cache_filter ? ' AND ' : '').self::$subclass_filter;
	}
	
	/**
	 * 
	 * @param string $filter
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	static function add_cache_filter( $filter ) {
		if( !empty(self::$cache_filter) ) {
			self::$cache_filter .= ' AND ';
		}
		self::$cache_filter .= $filter;
	}

}

?>