<?php
class Utils {

	protected static $currentHost = null;

	public static function ThemeDir( $subtheme = false ) {
		if( $theme = SSViewer::current_theme() ) {
			return THEMES_DIR . "/$theme" . ($subtheme ? "_$subtheme" : null);
		}
		return project();
	}

	public static function BasePath() {
		$rv = BASE_PATH;
		if( !$rv ) {
			$file = __FILE__;
			while( substr($file, strrpos($file, '/') + 1) != 'public' )
				$file = dirname($file);
		}
		return $rv;
	}

	/**
	 * Remove non-digits from a string
	 * @param  string $number
	 * @return string
	 */
	public static function cleanNumber($number) {
		return preg_replace('/[^0-9]/', '', $number);
	}

	/**
	 * Grab a URL using cURL and return the document as a string
	 */
	public static function getViaCURL( $URL ) {
		if (!function_exists('curl_init')) {
			user_error('CURL functions not available - CURL Module not installed?');
			die();
		}

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $URL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_HEADER, 0);

		$rv = curl_exec($ch);

		curl_close($ch);

		return $rv;
	}

	public static function ProjectDir() {
		return self::BasePath().'/'.project();
	}
	
	public static function GetURIFromID( $id ) {
		if( $siteTree = DataObject::get_by_id('SiteTree', $id) ) {
			return $siteTree->RelativeLink();
		}
	}

	public static function GetAnchorFromID( $id, $class = NULL ) {
		$sitetree = DataObject::get_one('SiteTree', "\"SiteTree\".ID = '$id'");
		if( is_object($sitetree) )
			return '<a href="' . $sitetree->RelativeLink() . '"' . ($class ? ' class="'.$class.'"' : '') . '>' . $sitetree->Title . '</a>';
		return '';
	}

	/**
	 * Returns a script tag containing the given $script
	 * @param script The script content
	 */
	public static function customScript( $script ) {
		$tag = "<script type=\"text/javascript\">\n//<![CDATA[\n";
		$tag .= "$script\n";
		$tag .= "\n//]]>\n</script>\n";
		return $tag;
	}

	/**
	 * Load the given javascript template with the page, returning the result.
	 * @param file The template file to load.
	 * @param vars The array of variables to load.  These variables are loaded via string search & replace.
	 */
	public static function javascriptTemplate($file, Array $vars = null) {
		$script = file_get_contents(Director::getAbsFile($file));
		$search = array();
		$replace = array();

		if($vars) foreach($vars as $k => $v) {
			$search[] = '$' . $k;
			$replace[] = str_replace("\\'","'", Convert::raw2js($v));
		}
		return self::customScript(str_replace($search, $replace, $script));
	}

	public static function createGroup( $code, $title, $description, $subsiteIds = null ) {
		if( class_exists('Subsite') ) {
			$oldState = Subsite::$disable_subsite_filter;
			Subsite::disable_subsite_filter();
		}
		if( !$group = DataObject::get_one('Group', "Code = '$code'") ) {
			$group = new Group();
			$group->Title = $title;
			$group->Description = $description;
			$group->Code = $code;
		}
		if( $subsiteIds ) {
			$group->AccessAllSubsites = false;
			// you have to write() before calling setByIDList(), because the write adds the current subsite
			$group->write();
			$group->Subsites()->setByIDList($subsiteIds);
		}
		else {
			$group->write();
		}
		if( class_exists('Subsite') ) {
			Subsite::disable_subsite_filter($oldState);
		}
	}

	public static function currentHost() {
		if( self::$currentHost === null )
			self::$currentHost = ( isset($_SERVER['HTTP_HOST']) ?
										'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . "://{$_SERVER['HTTP_HOST']}" :
										'' );
		return self::$currentHost;
	}
	
	/**
	 * Returns the full url to the current page.
	 */
	public static function currentURL() {
		$base = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$protocol = ( isset($_SERVER["HTTPS"]) && strtolower( $_SERVER["HTTPS"] ) == "on" ) ? 'https' : 'http';
		return $protocol.'://'.$base;
	}
	
	/**
	 * Converts to lowercase, removes non-word characters (alphanumerics and underscores) and converts spaces to hyphens. Also strips leading and trailing whitespace.
	 *
	 * If value is "Joel is a slug", the output will be "joel-is-a-slug".
	 *
	 * Taken from Django's slugify template tag.
	 *
	 * @param string $value
	 * @return string
	 * @see https://docs.djangoproject.com/en/dev/ref/templates/builtins/#slugify
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	public static function slugify( $value, $lowerCase = true ) {
		if( $lowerCase ) {
			$value = strtolower($value);
		}
		return preg_replace('/[-\s]+/', '-', trim(preg_replace('/[^\w\s-]/', '', $value)));
	}

	public static function reverseSet( DataObjectSet $set ) {
		$array = array();
		foreach( $set as $item ) {
			$array[] = $item;
		}
		return new DataObjectSet(array_reverse($array));
	}

	/**
	 * Removes the connection between a DataObject and its relations
	 * Can optionally delete the relation
	 *
	 * @param DataObject $caller
	 * @param string $relationship e.g, a Member belongs to a Group and the relationship is called "Groups"
	 * @param mixed $subsite integer | DataObject
	 * @param boolean $removeObjects delete the related object as well as the relationship?
	 * @author Adam Rice <development@HashNotAdam.com>
	 */
	public static function abandonRelationships( $caller, $relationship, $subsite = 0, $removeObjects = false ) {
		$relatedObjects = $caller->{$relationship}();

		if( $relatedObjects && $relatedObjects->exists() ) {
			Subsite::temporarily_set_subsite(is_object($subsite) ? $subsite->ID : $subsite);

			// something-to-many or 1-to-1 relationship?
			$manyRelationship = (gettype($relatedObjects) == 'ComponentSet');
			if( !$manyRelationship )
				$callerID = "{$caller->ClassName}ID";

			foreach( $relatedObjects as $object ) {
				if( $manyRelationship )
					$caller->{$relationship}()->remove($object);
				elseif( !$removeObjects ) {
					$object->$callerID = NULL;
					$object->write();
				}
				if( $removeObjects )
					$object->delete();
			}

			Subsite::restore_previous_subsite();
		}
	}

	public static function uriForPath( $path ) {
		$rv = str_replace(preg_replace('!/+$!', '', Director::baseFolder()).'/', Director::baseURL(), $path);
		return $rv;
	}

}
