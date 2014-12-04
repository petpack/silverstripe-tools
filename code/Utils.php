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
	
	
	/**
	 * (unused / dangerous - deletes too much!) 
	 * Somewhat like abandonRelationships, except inclusive: Removes all records 
	 * 	which are related to the specified dataobject.
	 * NOTE: requires the loggable extension, since it writes logs to record what it does.
	 * @param DataObject $obj
	 */
	public static function deleteRelatedRecords(DataObject $obj,Array $include = null) {
		
		//$parents = $obj->;
		
		
		$fields = $obj->has_many();
		$log = "Removing related records for " . $obj->class . " " . trim($obj->recordTitle()) . "\n";
		if ($fields) {
			foreach ($fields as $field => $class) {
				
				if ($include && !in_array($field,$include)) continue;
				
				$items = $obj->$field();
				$log .= "Removing has_many '$field' relationships:\n";
				
				if ($items && $items->exists()) {
					foreach($items as $itm) {
						
						//error_log("Recursing into related $field ($class objects) for " . $itm->recordTitle());
						
						if ($itm->has_many() || $item->has_one())	//only recurse when it makes sense
							self::deleteRelatedRecords($itm);
						
						$log .= " - $class with ID: " . $itm->ID . " (" . $itm->recordTitle() . ") removed.\n";
						
						$itm->delete();
					}
				}
			}
		}
		
		$fields = $obj->has_one();
		if ($fields) {
			foreach ($fields as $field => $class) {
				if ($include && !in_array($field,$include)) continue;
				$itm = $obj->$field();
				$log .= "Removing has_one '$field' relationship.\n - $class with ID: " . $itm->ID . " (" . $itm->recordTitle() . ") removed.\n";
				$itm->delete();
			}
		}
		
		error_log($log);
		
		if ($log)
			$obj->CreateLogEntry($log);
		
		//$fields = $obj->has_one();
		
		//error_log(print_r($fields,true));
		
	}

	public static function uriForPath( $path ) {
		$rv = str_replace(preg_replace('!/+$!', '', Director::baseFolder()).'/', Director::baseURL(), $path);
		return $rv;
	}
	
	private  static $usage_cache = Array();
	
	/**
	 * (abandoned) Find references and dataobjects that refer to the given dataobject.
	 * partial implemenation: doesn't do many-many at all, only gets data
	 * objects for has_one, is slow.
	 */
	function find_usage(DataObject $obj) {
		
		if (!$obj->ID) return false;
		
		$classname = $obj->class;
		
		//if (isset(self::$usage_cache[$classname])) return self::$usage_cache[$classname];
		
		$ret = Array();
		
		//get all dataobject types:
		$classes = ClassInfo::subclassesFor("DataObject");
		
		//go through them:
		foreach ($classes as $cls) {
			
			$sng = singleton($cls); /** @var DataObject $sng **/
			//$sng = new DataObject();
			
			//$fields = Object::combined_static($cls, 'has_one', 'DataObject'); 
			$fields = $sng->has_one();
			if (!$fields) $fields = Array();
			foreach ($fields as $field => $type) {
				if ($type == $classname) {
					
					if (!isset($ret[$cls])) $ret[$cls]=Array();
					
					$cs = null;
					//this searches the DB for objects pointing to $obj:
					$cs = DataObject::get($cls,$field . "ID = " . $obj->ID);
					
					$ret[$cls][] = Array(
							'name'=>$field,
							'type'=>'one',
							'used_by' => $cs
					);
					
				}
			}
			
			//$hm = Object::combined_static($cls, 'has_many', 'DataObject');
			
			$fields = $sng->has_many();
			if (!$fields) $fields = Array();
			
			//$fields = array_merge($fields,$hm);
			
			foreach ($fields as $field => $type) {
				//echo "$cls: $field -> $type\n";
				if ($type == $classname) {
					if (!isset($ret[$cls])) $ret[$cls]=Array();
					//$ret[$cls][] = Array('name'=>$field,'type'=>'many');
					
					$cs =null;
					//$cs = DataObject::get($classname,$cls . "ID = " . $obj->ID);
					
					//echo "$cls.$field: $type\n";
					
					$ret[$cls][] = Array(
							'name'=>$field,
							'type'=>'many',
							'used_by' => $cs
					);
					
				}
			}
			
			//TODO: 
			//$fields = $sng->many_many();
			
			//not needed:
			//$fields = DataObject::database_fields($class);
		
		}
		
		//self::$usage_cache[$classname] = $ret;
		
		return $ret;
	}

}

/* testing code:
 * 
 
class mert extends PetPackScript {
	function process() {
		//$obj = DataObject::get_by_id("Member", 172191);
		
		$obj = DataObject::get_by_id("Pet", 59551);
		
		
		//echo $obj->class . "\n";
		
		
		$v = Utils::find_usage($obj);
		echo "\n\n";
		var_dump($v);
	}
}

*/