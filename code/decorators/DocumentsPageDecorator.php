<?php
/**
 * Use DocumentsPageDecorator::add_extension() in order to be able to use UploadFolderManager::printUploadFolders().
 * Example implementation:

DocumentsPageDecorator::init();
DataObject::add_extension('DocumentsPage', 'DocumentsPageDecorator');

class DocumentsPage extends Page {}
class DocumentsPage_Controller extends Page_Controller {}

 */

class DocumentsPageDecorator extends SiteTreeDecorator {

	public static function add_extension( $className ) {
		Object::add_extension($className, 'BannerDecorator');
		if( Director::isDev() && class_exists('UploadFolderManager') ) {
			UploadFolderManager::setOptions($className, array());
		}
	}

	static function init() {
		SortableDataObject::add_sortable_classes(array('DocumentsPageDecorator_Category', 'DocumentsPageDecorator_Document'));
	} 

	function extraStatics() {
		return array(
			'has_many' => array(
				'Categories' => 'DocumentsPageDecorator_Category',
				'Documents' => 'DocumentsPageDecorator_Document',
			)
		);
	}

	function updateCMSFields( $fields ) {
		$fields->addFieldToTab('Root.Content.Documents', $field = new DataObjectManager(
			$this->owner, // controller
			'Categories', // name
			'DocumentsPageDecorator_Category' // sourceClass
		));
		$fields->addFieldToTab('Root.Content.Documents', $field = new DataObjectManager(
			$this->owner, // controller
			'Documents', // name
			'DocumentsPageDecorator_Document' // sourceClass
		));
	}

}

class DocumentsPageDecorator_Category extends DataObject {

	static $db = array(
		'Title' => 'Varchar(255)',
	);

	static $has_one = array(
		'Page' => 'Page',
	);

	static $has_many = array(
		'Documents' => 'DocumentsPageDecorator_Document',
	);

	static $singular_name = 'Category';

	function getCMSFields($params = null) {
		$fields = FormUtils::createMain();
		$fields->addFieldToTab('Root.Main', $field = new TextField('Title'));
		return $fields;
	}

}

class DocumentsPageDecorator_Document extends DataObject {

	static $db = array(
		'Title' => 'Varchar(255)',
		'Description' => 'HTMLText',
	);

	static $has_one = array(
		'Page' => 'Page',
		'Document' => 'File',
		'Category' => 'DocumentsPageDecorator_Category',
	);

	static $summary_fields = array(
		'Title'
	);

	static $singular_name = 'Document';

	function getCMSFields($params = null) {
		$fields = FormUtils::createMain();
		$fields->addFieldToTab('Root.Main', $field = new TextField('Title'));
		$fields->addFieldToTab('Root.Main', $field = new SimpleTinyMCEField('Description'));
		$fields->addFieldToTab('Root.Main', $field = new FileUploadField('Document'));
		UploadFolderManager::setUploadFolder($this->owner, $field);
		if( $categories = DataObject::get('DocumentsPageDecorator_Category') ) {
			$categories = $categories->map();
		}
		$fields->addFieldToTab('Root.Main', $field = new DropdownField(
				'CategoryID', 'Category', @$categories
		));
		$this->extend('updateCMSFields', $fields);
		return $fields;
	}
	
	function FileExtension() {
		if( ($document = $this->Document()) && ($document->exists()) ) {
			return substr(strrchr($document->Filename,'.'),1);
		}
		else {
			return false;
		}
	}

}

?>