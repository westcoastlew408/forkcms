<?php

/**
 * ModuleInstaller
 * The base-class for the installer
 *
 * @package		installer
 * @subpackage	core
 *
 * @author		Davy Hellemans <davy@netlash.com>
 * @author		Matthias Mullie <matthias@netlash.com>
 * @author 		Tijs Verkoyen <tijs@sumocoders.be>
 * @since		2.0
 */
class ModuleInstaller
{
	/**
	 * Database connection instance
	 *
	 * @var SpoonDatabase
	 */
	private  $db;


	/**
	 * Example data?
	 *
	 * @var bool
	 */
	private $example;


	/**
	 * The active languages
	 *
	 * @var	array
	 */
	private  $languages = array();


	/**
	 * The variables passed by the installer
	 *
	 * @var	array
	 */
	private $variables = array();


	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	SpoonDatabase $db	The database-connection.
	 * @param	array $languages	The selected languages
	 * @param	bool $example		Should example data be installed
	 * @param	array $variables	The passed variables
	 */
	public function __construct(SpoonDatabase $db, array $languages, $example = false, array $variables = array())
	{
		// set DB
		$this->db = $db;
		$this->languages = $languages;
		$this->example = (bool) $example;
		$this->variables = $variables;

		// call the execute method
		$this->execute();
	}


	/**
	 * Inserts a new module
	 *
	 * @return	void
	 * @param	string $name					The name of the module
	 * @param	string[optional] $description	A description for the module
	 */
	protected function addModule($name, $description = null)
	{
		// redefine
		$name = (string) $name;

		// module does not yet exists
		if(!(bool) $this->getDB()->getVar('SELECT COUNT(name) FROM modules WHERE name = ?;', $name))
		{
			// build item
			$item = array('name' => $name,
							'description' => $description,
							'active' => 'Y');

			// insert module
			$this->getDB()->insert('modules', $item);
		}

		// activate and update description
		else $this->getDB()->update('modules', array('description' => $description, 'active' => 'Y'), 'name = ?', $name);
	}


	/**
	 * Method that will be overriden by the specific installers
	 *
	 * @return void
	 */
	protected function execute()
	{
		// just a placeholder
	}


	/**
	 * Get the database-handle
	 *
	 * @return	SpoonDatabase
	 */
	protected function getDB()
	{
		return $this->db;
	}


	/**
	 * Get the default user
	 *
	 * @return	int
	 */
	protected function getDefaultUserID()
	{
		try
		{
			// fetch default user id
			return (int) $this->getDB()->getVar('SELECT id
													FROM users
													WHERE is_god = ? AND active = ? AND deleted = ?
													ORDER BY id ASC',
													array('Y', 'Y', 'N'));
		}

		// catch exceptions
		catch(Exception $e)
		{
			return 1;
		}
	}


	/**
	 * Get the selected languages
	 *
	 * @return	void
	 */
	protected function getLanguages()
	{
		return $this->languages;
	}


	/**
	 * Get a setting
	 *
	 * @return	mixed
	 * @param	string $module	The name of the module.
	 * @param	string $name	The name of the setting.
	 */
	protected function getSetting($module, $name)
	{
		return unserialize($this->getDB()->getVar('SELECT value
													FROM modules_settings
													WHERE module = ? AND name = ?',
													array((string) $module, (string) $name)));
	}


	/**
	 * Get a variable
	 *
	 * @return	mixed
	 * @param	string $name		The name of the variable
	 */
	protected function getVariable($name)
	{
		// is the variable available?
		if(!isset($this->variables[$name])) return null;

		// return the real value
		return $this->variables[$name];
	}


	/**
	 * Imports the sql file
	 *
	 * @return	void
	 * @param	string $filename	The full path for the SQL-file
	 */
	protected function importSQL($filename)
	{
		// load the file content and execute it
		$content = trim(SpoonFile::getContent($filename));

		// file actually has content
		if(!empty($content))
		{
			/**
			 * Some versions of PHP can't handle multiple statements at once, so split them
			 * We know this isn't the best solution, but we couldn't find a beter way.
			 * @later: find a beter way to handle multiple-line queries
			 */
			$queries = preg_split("/;(\r)?\n/", $content);

			// loop queries and execute them
			foreach($queries as $query) $this->getDB()->execute($query);
		}
	}


	/**
	 * Insert an extra
	 *
	 * @return	int
	 * @param	string $module
	 * @param	string $type
	 * @param	string $label
	 * @param	string $action
	 * @param	string[optional] $data
	 * @param	bool[optional] $hidden
	 * @param	int[optional] $sequence
	 */
	protected function insertExtra($module, $type, $label, $action = null, $data = null, $hidden = false, $sequence = null)
	{
		// no sequence set
		if(is_null($sequence))
		{
			// set next sequence number for this module
			$sequence = $this->getDB()->getVar('SELECT MAX(sequence) + 1 FROM pages_extras WHERE module = ?', array((string) $module));

			// this is the first extra for this module: generate new 1000-series
			if(is_null($sequence)) $sequence = $sequence = $this->getDB()->getVar('SELECT CEILING(MAX(sequence) / 1000) * 1000 FROM pages_extras');
		}

		// redefine
		$module = (string) $module;
		$type = (string) $type;
		$label = (string) $label;
		$action = !is_null($action) ? (string) $action : null;
		$data = !is_null($data) ? (string) $data : null;
		$hidden = $hidden && $hidden !== 'N' ? 'Y' : 'N';
		$sequence = (int) $sequence;

		// build item
		$item = array('module' => $module,
						'type' => $type,
						'label' => $label,
						'action' => $action,
						'data' => $data,
						'hidden' => $hidden,
						'sequence' => $sequence);

		// doesn't already exist
		if($this->getDB()->getVar('SELECT COUNT(id) FROM pages_extras WHERE module = ? AND type = ? AND label = ?', array($item['module'], $item['type'], $item['label'])) == 0)
		{
			// insert extra and return id
			return (int) $this->getDB()->insert('pages_extras', $item);
		}

		// return id
		else return (int) $this->getDB()->getVar('SELECT id FROM pages_extras WHERE module = ? AND type = ? AND label = ?', array($item['module'], $item['type'], $item['label']));
	}


	/**
	 * Inserts a new locale item
	 *
	 * @return	void
	 * @param	string $language
	 * @param	string $application
	 * @param	string $module
	 * @param	string $type
	 * @param	string $name
	 * @param	string $value
	 */
	protected function insertLocale($language, $application, $module, $type, $name, $value)
	{
		// redefine
		$language = (string) $language;
		$application = SpoonFilter::getValue($application, array('frontend', 'backend'), '');
		$module = (string) $module;
		$type = SpoonFilter::getValue($type, array('act', 'err', 'lbl', 'msg'), '');
		$name = (string) $name;
		$value = (string) $value;

		// validate
		if($application == '') throw new Exception('Invalid application. Possible values are: backend, frontend.');
		if($type == '') throw new Exception('Invalid type. Possible values are: act, err, lbl, msg.');

		// check if the label already exists
		if(!(bool) $this->getDB()->getVar('SELECT COUNT(i.id)
											FROM locale AS i
											WHERE i.language = ? AND i.application = ? AND i.module = ? AND i.type = ? AND i.name = ?',
											array($language, $application, $module, $type, $name)))
		{
			// insert
			$this->db->insert('locale', array('user_id' => $this->getDefaultUserID(),
												'language' => $language,
												'application' => $application,
												'module' => $module,
												'type' => $type,
												'name' => $name,
												'value' => $value,
												'edited_on' => gmdate('Y-m-d H:i:s')));
		}
	}


	/**
	 * Insert a meta item
	 *
	 * @return	int
	 * @param	string $keywords
	 * @param	string $description
	 * @param	string $title
	 * @param	string $url
	 * @param	bool[optional] $keywordsOverwrite
	 * @param	bool[optional] $descriptionOverwrite
	 * @param	bool[optional] $titleOverwrite
	 * @param	bool[optional] $urlOverwrite
	 * @param	string[optional] $custom
	 */
	protected function insertMeta($keywords, $description, $title, $url, $keywordsOverwrite = false, $descriptionOverwrite = false, $titleOverwrite = false, $urlOverwrite = false, $custom = null)
	{
		// redefine
		$keywords = (string) $keywords;
		$keywordsOverwrite = $keywordsOverwrite && $keywordsOverwrite !== 'N' ? 'Y' : 'N';
		$description = (string) $description;
		$descriptionOverwrite = $titleOverwrite && $titleOverwrite !== 'N' ? 'Y' : 'N';
		$title = (string) $title;
		$titleOverwrite = $titleOverwrite && $titleOverwrite !== 'N' ? 'Y' : 'N';
		$url = (string) $url;
		$urlOverwrite = $urlOverwrite && $urlOverwrite !== 'N' ? 'Y' : 'N';
		$custom = !is_null($custom) ? (string) $custom : null;

		// build item
		$item = array('keywords' => $keywords,
						'keywords_overwrite' => $keywordsOverwrite,
						'description' => $description,
						'description_overwrite' => $descriptionOverwrite,
						'title' => $title,
						'title_overwrite' => $titleOverwrite,
						'url' => $url,
						'url_overwrite' => $urlOverwrite,
						'custom' => $custom);

		// insert meta and return id
		return (int) $this->getDB()->insert('meta', $item);
	}


	/**
	 * Insert a page
	 *
	 * @return	void
	 * @param	array $revision
	 * @param	array[optional] $meta
	 * @param	array[optional] $block
	 */
	protected function insertPage(array $revision, array $meta = null, array $block = null)
	{
		// redefine
		$revision = (array) $revision;
		$meta = (array) $meta;

		// build revision
		if(!isset($revision['language'])) throw new SpoonException('language is required for installing pages');
		if(!isset($revision['title'])) throw new SpoonException('title is required for installing pages');
		if(!isset($revision['id'])) $revision['id'] = (int) $this->getDB()->getVar('SELECT MAX(id) + 1 FROM pages WHERE language = ?', array($revision['language']));
		if(!$revision['id']) $revision['id'] = 1;
		if(!isset($revision['user_id'])) $revision['user_id'] = $this->getDefaultUserID();
		if(!isset($revision['template_id'])) $revision['template_id'] = 1;
		if(!isset($revision['type'])) $revision['type'] = 'page';
		if(!isset($revision['parent_id'])) $revision['parent_id'] = ($revision['type'] == 'page' ? 1 : 0);
		if(!isset($revision['navigation_title'])) $revision['navigation_title'] = $revision['title'];
		if(!isset($revision['navigation_title_overwrite'])) $revision['navigation_title_overwrite'] = 'N';
		if(!isset($revision['hidden'])) $revision['hidden'] = 'N';
		if(!isset($revision['status'])) $revision['status'] = 'active';
		if(!isset($revision['publish_on'])) $revision['publish_on'] = gmdate('Y-m-d H:i:s');
		if(!isset($revision['created_on'])) $revision['created_on'] = gmdate('Y-m-d H:i:s');
		if(!isset($revision['edited_on'])) $revision['edited_on'] = gmdate('Y-m-d H:i:s');
		if(!isset($revision['data'])) $revision['data'] = null;
		if(!isset($revision['allow_move'])) $revision['allow_move'] = 'Y';
		if(!isset($revision['allow_children'])) $revision['allow_children'] = 'Y';
		if(!isset($revision['allow_edit'])) $revision['allow_edit'] = 'Y';
		if(!isset($revision['allow_delete'])) $revision['allow_delete'] = 'Y';
		if(!isset($revision['no_follow'])) $revision['no_follow'] = 'N';
		if(!isset($revision['sequence'])) $revision['sequence'] = (int) $this->getDB()->getVar('SELECT MAX(sequence) + 1 FROM pages WHERE language = ? AND parent_id = ? AND type = ?', array($revision['language'], $revision['parent_id'], $revision['type']));
		if(!isset($revision['extra_ids'])) $revision['extra_ids'] = null;
		if(!isset($revision['has_extra'])) $revision['has_extra'] = $revision['extra_ids'] ? 'Y' : 'N';

		// meta needs to be inserted
		if(!isset($revision['meta_id']))
		{
			// build meta
			if(!isset($meta['keywords'])) $meta['keywords'] = $revision['title'];
			if(!isset($meta['keywords_overwrite'])) $meta['keywords_overwrite'] = false;
			if(!isset($meta['description'])) $meta['description'] = $revision['title'];
			if(!isset($meta['description_overwrite'])) $meta['description_overwrite'] = false;
			if(!isset($meta['title'])) $meta['title'] = $revision['title'];
			if(!isset($meta['title_overwrite'])) $meta['title_overwrite'] = false;
			if(!isset($meta['url'])) $meta['url'] = SpoonFilter::urlise($revision['title']);
			if(!isset($meta['url_overwrite'])) $meta['url_overwrite'] = false;
			if(!isset($meta['custom'])) $meta['custom'] = null;

			// insert meta
			$revision['meta_id'] = $this->insertMeta($meta['keywords'], $meta['description'], $meta['title'], $meta['url'], $meta['keywords_overwrite'], $meta['description_overwrite'], $meta['title_overwrite'], $meta['url_overwrite'], $meta['custom']);
		}

		// insert page
		$revision['revision_id'] = $this->getDB()->insert('pages', $revision);

		// get number of blocks to insert
		$numBlocks = $this->getDB()->getVar('SELECT MAX(num_blocks) FROM pages_templates WHERE active = ?', array('Y'));

		// get arguments (this function has a variable length argument list, to allow multiple blocks to be added)
		$blocks = array();

		// loop blocks
		for($i = 0; $i < $numBlocks; $i++)
		{
			// get block
			$block = @func_get_arg($i + 2);
			if($block === false) $block = array();
			else $block = (array) $block;

			// build block
			if(!isset($block['id'])) $block['id'] = $i;
			if(!isset($block['revision_id'])) $block['revision_id'] = $revision['revision_id'];
			if(!isset($block['status'])) $block['status'] = 'active';
			if(!isset($block['created_on'])) $block['created_on'] = gmdate('Y-m-d H:i:s');
			if(!isset($block['edited_on'])) $block['edited_on'] = gmdate('Y-m-d H:i:s');
			if(!isset($block['extra_id'])) $block['extra_id'] = null;
			else $revision['extra_ids'] = trim($revision['extra_ids'] .','. $block['extra_id'], ',');
			if(!isset($block['html'])) $block['html'] = '';
			elseif(SpoonFile::exists($block['html'])) $block['html'] = SpoonFile::getContent($block['html']);

			// insert block
			$this->getDB()->insert('pages_blocks', $block);
		}

		// blocks added
		if($revision['extra_ids'] && $revision['has_extra'] == 'N')
		{
			// update page
			$revision['has_extra'] = 'Y';
			$this->getDB()->update('pages', $revision, 'revision_id = ?', array($revision['revision_id']));
		}

		// return page id
		return $revision['id'];
	}


	/**
	 * Should example data be installed
	 *
	 * @return	bool
	 */
	protected function installExample()
	{
		return $this->example;
	}


	/**
	 * Make a module searchable
	 *
	 * @return	void
	 * @param	string $module						The module to make searchable.
	 * @param	bool[optional] $searchable			Enable/disable search for this module by default?
	 * @param	int[optional] $weight				Set default search weight for this module.
	 */
	protected function makeSearchable($module, $searchable = true, $weight = 1)
	{
		// redefine
		$module = (string) $module;
		$searchable = $searchable && $searchable !== 'N' ? 'Y' : 'N';
		$weight = (int) $weight;

		// make module searchable
		$this->getDB()->execute('INSERT INTO search_modules (module, searchable, weight) VALUES (?, ?, ?)
									ON DUPLICATE KEY UPDATE searchable = ?, weight = ?', array($module, $searchable, $weight, $searchable, $weight));
	}


	/**
	 * Set the rights for an action
	 *
	 * @return	void
	 * @param	int $groupId			The group wherefor the rights will be set.
	 * @param	string $module			The module wherin the action appears.
	 * @param	string $action			The action wherefor the rights have to set.
	 * @param	int[optional] $level	The leve, default is 7 (max).
	 */
	protected function setActionRights($groupId, $module, $action, $level = 7)
	{
		// redefine
		$groupId = (int) $groupId;
		$module = (string) $module;
		$action = (string) $action;
		$level = (int) $level;

		// action doesn't exist
		if(!(bool) $this->getDB()->getVar('SELECT COUNT(id)
											FROM groups_rights_actions
											WHERE group_id = ? AND module = ? AND action = ?',
											array($groupId, $module, $action)))
		{
			// build item
			$item = array('group_id' => $groupId,
							'module' => $module,
							'action' => $action,
							'level' => $level);

			// insert
			$this->getDB()->insert('groups_rights_actions', $item);
		}
	}


	/**
	 * Sets the rights for a module
	 *
	 * @return	void
	 * @param	int $groupId		The group wherefor the rights will be set.
	 * @param	string $module		The module too set the rights for.
	 */
	protected function setModuleRights($groupId, $module)
	{
		// redefine
		$groupId = (int) $groupId;
		$module = (string) $module;

		// module doesn't exist
		if(!(bool) $this->getDB()->getVar('SELECT COUNT(id)
											FROM groups_rights_modules
											WHERE group_id = ? AND module = ?',
											array((int) $groupId, (string) $module)))
		{
			// build item
			$item = array('group_id' => $groupId,
							'module' => $module);

			// insert
			$this->getDB()->insert('groups_rights_modules', $item);
		}
	}


	/**
	 * Stores a module specific setting in the database.
	 *
	 * @return	void
	 * @param	string $module				The module wherefore the setting will be set.
	 * @param	string $name				The name of the setting.
	 * @param	mixed[optional] $value		The optional value.
	 * @param	bool[optional] $overwrite	Overwrite no matter what.
	 */
	protected function setSetting($module, $name, $value = null, $overwrite = false)
	{
		// redefine
		$module = (string) $module;
		$name = (string) $name;
		$value = serialize($value);
		$overwrite = (bool) $overwrite;

		// doens't already exist
		if(!(bool) $this->getDB()->getVar('SELECT COUNT(name)
											FROM modules_settings
											WHERE module = ? AND name = ?;',
											array($module, $name)))
		{
			// build item
			$item = array('module' => $module,
							'name' => $name,
							'value' => $value);

			// insert setting
			$this->getDB()->insert('modules_settings', $item);
		}

		// overwrite
		elseif($overwrite)
		{
			// insert setting
			$this->getDB()->execute('INSERT INTO modules_settings (module, name, value) VALUES (?, ?, ?)
										ON DUPLICATE KEY UPDATE value = ?', array($module, $name, $value, $value));
		}
	}
}


/**
 * CoreInstall
 * Installer for the core
 *
 * @package		installer
 * @subpackage	core
 *
 * @author		Davy Hellemans <davy@netlash.com>
 * @author 		Tijs Verkoyen <tijs@netlash.com>
 * @since		2.0
 */
class CoreInstall extends ModuleInstaller
{
	/**
	 * Installe the module
=======
	 * Install the module
>>>>>>> 5eaabb4c54538b67f8e239ccb827c120483cabfa
	 *
	 * @return	void
	 */
	protected function execute()
	{
		// load install.sql
		$this->importSQL(PATH_WWW .'/backend/modules/locale/installer/install.sql');

		// add 'locale' as a module
		$this->addModule('locale', 'The module to manage your website/cms locale.');

		// general settings
		$this->setSetting('locale', 'languages', array('de', 'en', 'es', 'fr', 'nl'));

		// module rights
		$this->setModuleRights(1, 'locale');

		// action rights
		$this->setActionRights(1, 'locale', 'add');
		$this->setActionRights(1, 'locale', 'analyse');
		$this->setActionRights(1, 'locale', 'edit');
		$this->setActionRights(1, 'locale', 'index');
		$this->setActionRights(1, 'locale', 'mass_action');

		// insert locale for backend locale
		$this->insertLocale('nl', 'backend', 'locale', 'err', 'AlreadyExists', 'Deze vertaling bestaat reeds.');
		$this->insertLocale('nl', 'backend', 'locale', 'err', 'ModuleHasToBeCore', 'De module moet core zijn voor vertalingen in de frontend.');
		$this->insertLocale('nl', 'backend', 'locale', 'err', 'NoSelection', 'Er waren geen vertalingen geselecteerd.');
		$this->insertLocale('nl', 'backend', 'locale', 'lbl', 'Add', 'vertaling toevoegen');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'Added', 'De vertaling "%1$s" werd toegevoegd.');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'Deleted', 'De geselecteerde vertalingen werden verwijderd.');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'Edited', 'De vertaling "%1$s" werd opgeslagen.');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'EditTranslation', 'bewerk vertaling "%1$s"');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'HelpAddName', 'De Engelstalige referentie naar de vertaling, bvb. "Add". Deze waarde moet beginnen met een hoofdletter en mag geen spaties bevatten.');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'HelpAddValue', 'De vertaling zelf, bvb. "toevoegen".');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'HelpEditName', 'De Engelstalige referentie naar de vertaling, bvb. "Add". Deze waarde moet beginnen met een hoofdletter en mag geen spaties bevatten.');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'HelpEditValue', 'De vertaling zelf, bvb. "toevoegen".');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'HelpName', 'De Engelstalige referentie naar de vertaling, bvb. "Add".');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'HelpValue', 'De vertaling zelf, bvb. "toevoegen".');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'NoItems', 'Er zijn nog geen vertalingen. <a href="%1$s">Voeg de eerste vertaling toe</a>.');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'NoItemsFilter', 'Er zijn geen vertalingen voor deze filter. <a href="%1$s">Voeg de eerste vertaling toe</a>.');
		$this->insertLocale('nl', 'backend', 'locale', 'msg', 'NoItemsAnalyse', 'Er werden geen ontbrekende vertalingen gevonden.');
		// --
		$this->insertLocale('en', 'backend', 'locale', 'err', 'AlreadyExists', 'This translation already exists.');
		$this->insertLocale('en', 'backend', 'locale', 'err', 'ModuleHasToBeCore', 'The module needs to be core for frontend translations.');
		$this->insertLocale('en', 'backend', 'locale', 'err', 'NoSelection', 'No translations were selected.');
		$this->insertLocale('en', 'backend', 'locale', 'lbl', 'Add', 'add translation');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'Added', 'The translation "%1$s" was added.');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'Deleted', 'The selected translations were deleted.');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'Edited', 'The translation "%1$s" was saved.');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'EditTranslation', 'edit translation "%1$s"');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'HelpAddName', 'The English reference for the translation, eg. "Add" This value should start with a capital and may not contain special characters.');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'HelpAddValue', 'The translation, eg. "add".');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'HelpEditName', 'The English reference for the translation, eg. "Add". This value should start with a capital and may not contain spaces.');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'HelpEditValue', 'The translation, eg. "add".');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'HelpName', 'The english reference for this translation, eg. "Add".');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'HelpValue', 'The translation, eg. "add".');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'NoItems', 'There are no translations yet. <a href="%1$s">Add the first translation</a>.');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'NoItemsFilter', 'There are no translations yet for this filter. <a href="%1$s">Add the first translation</a>.');
		$this->insertLocale('en', 'backend', 'locale', 'msg', 'NoItemsAnalyse', 'No missing translations were found.');

		// insert local for dashboard
		$this->insertLocale('nl', 'backend', 'dashboard', 'lbl', 'AllStatistics', 'alle statistieken');
		$this->insertLocale('nl', 'backend', 'dashboard', 'lbl', 'TopKeywords', 'top zoekwoorden');
		$this->insertLocale('nl', 'backend', 'dashboard', 'lbl', 'TopReferrers', 'top verwijzende sites');
		// --
		$this->insertLocale('en', 'backend', 'dashboard', 'lbl', 'AllStatistics', 'all statistics');
		$this->insertLocale('en', 'backend', 'dashboard', 'lbl', 'TopKeywords', 'top keywords');
		$this->insertLocale('en', 'backend', 'dashboard', 'lbl', 'TopReferrers', 'top referrers');

		// insert locale for backend core
		$this->insertLocale('nl', 'backend', 'core', 'err', 'ActionNotAllowed', 'Je hebt onvoldoende rechten voor deze actie.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'AddingCategoryFailed', 'Er ging iets mis.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'AddTagBeforeSubmitting', 'Voeg de tag eerst toe.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'AkismetKey', 'Akismet API-key werd nog niet geconfigureerd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'AlphaNumericCharactersOnly', 'Enkel alfanumerieke karakters zijn toegestaan.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'AuthorIsRequired', 'Gelieve een auteur in te geven.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'BrowserNotSupported', '<p>Je gebruikt een verouderde browser die niet ondersteund wordt door Fork CMS. Gebruik een van de volgende alternatieven:</p><ul><li><a href="http://www.firefox.com/">Firefox</a>: een zeer goeie browser met veel gratis extensies.</li><li><a href="http://www.apple.com/safari">Safari</a>: één van de snelste en meest geavanceerde browsers. Goed voor Mac-gebruikers.</li><li><a href="http://www.google.com/chrome">Chrome</a>: De browser van Google: ook heel erg snel.</li></li><a href="http://www.microsoft.com/windows/products/winfamily/ie/default.mspx">Internet Explorer*</a>: update naar de nieuwe versie van Internet Explorer.</li></ul>');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'CookiesNotEnabled', 'Om Fork CMS te gebruiken moeten cookies geactiveerd zijn in uw browser. Activeer cookies en vernieuw deze pagina.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'DateIsInvalid', 'Ongeldige datum.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'DateRangeIsInvalid', 'Ongeldig datum bereik');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'DebugModeIsActive', 'Debug-mode is nog actief.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'EmailAlreadyExists', 'Dit e-mailadres is al in gebruik.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'EmailIsInvalid', 'Gelieve een geldig emailadres in te geven.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'EmailIsRequired', 'Gelieve een e-mailadres in te geven.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'EmailIsUnknown', 'Dit e-mailadres zit niet in onze database.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'EndDateIsInvalid', 'Ongeldige einddatum');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'FieldIsRequired', 'Dit veld is verplicht.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'ForkAPIKeys', 'Fork API-keys nog niet geconfigureerd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'FormError', 'Er ging iets mis, kijk de gemarkeerde velden na.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'GoogleMapsKey', 'Google maps API-key werd nog niet geconfigureerd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'InvalidAPIKey', 'Ongeldige API key.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'InvalidDomain', 'Ongeldig domein.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'InvalidEmailPasswordCombination', 'De combinatie van e-mail en wachtwoord is niet correct. <a href="#" rel="forgotPasswordHolder" class="toggleBalloon">Bent u uw wachtwoord vergeten?</a>');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'InvalidName', 'Ongeldige naam.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'InvalidURL', 'Ongeldige URL.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'InvalidValue', 'Ongeldige waarde.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'JavascriptNotEnabled', 'Om Fork CMS te gebruiken moet Javascript geactiveerd zijn in uw browser. Activeer javascript en vernieuw deze pagina.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'JPGGIFAndPNGOnly', 'Enkel jpg, gif en png bestanden zijn toegelaten.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'ModuleNotAllowed', 'Je hebt onvoldoende rechten voor deze module.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'NameIsRequired', 'Gelieve een naam in te geven.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'NicknameIsRequired', 'Gelieve een publicatienaam in te geven.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'NoCommentsSelected', 'Er waren geen reacties geselecteerd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'NoItemsSelected', 'Er waren geen items geselecteerd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'NoModuleLinked', 'Kan de URL niet genereren. Zorg dat deze module aan een pagina hangt.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'NonExisting', 'Dit item bestaat niet.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'NoSelection', 'Er waren geen items geselecteerd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'PasswordIsRequired', 'Gelieve een wachtwoord in te geven.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'PasswordRepeatIsRequired', 'Gelieve het gewenste wachtwoord te herhalen.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'PasswordsDontMatch', 'De wachtwoorden zijn verschillend, probeer het opnieuw.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'RobotsFileIsNotOK', 'robots.txt zal zoekmachines blokkeren.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'RSSTitle', 'Blog RSS titel is nog niet ingevuld. <a href="%1$s">Configureer</a>');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'SettingsForkAPIKeys', 'De Fork API-keys zijn niet goed geconfigureerd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'SomethingWentWrong', 'Er liep iets fout.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'StartDateIsInvalid', 'Ongeldige startdatum');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'SurnameIsRequired', 'Gelieve een achternaam in te geven.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'TooManyLoginAttempts', 'Te veel loginpogingen. Als je je wachtwoord vergeten bent, click op de "Wachtwoord vergeten?"-link.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'TimeIsInvalid', 'Ongeldige tijd.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'TitleIsRequired', 'Geef een titel in.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'URLAlreadyExists', 'Deze URL bestaat reeds.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'ValuesDontMatch', 'De waarden komen niet overeen.');
		$this->insertLocale('nl', 'backend', 'core', 'err', 'XMLFilesOnly', 'Enkel xml bestanden zijn toegelaten.');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'AccountManagement', 'account beheer');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Active', 'actief');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Add', 'toevoegen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'AddCategory', 'categorie toevoegen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'AddTemplate', 'template toevoegen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Advanced', 'geavanceerd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'AllComments', 'alle reacties');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'AllowComments', 'reacties toestaan');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'AllPages', 'alle pagina\'s');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Amount', 'aantal');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Analyse', 'analyse');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Analysis', 'analysi');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Analytics', 'analytics');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'APIKey', 'API key');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'APIKeys', 'API keys');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'APIURL', 'API URL');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Application', 'applicatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Approve', 'goedkeuren');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Archive', 'archief');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Archived', 'gearchiveerd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Articles', 'artikels');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'At', 'om');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Authentication', 'authenticatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Author', 'auteur');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Avatar', 'avatar');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Back', 'terug');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Backend', 'backend');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Block', 'blok');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Blog', 'blog');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'BrowserNotSupported', 'browser niet ondersteund');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'By', 'door');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Cancel', 'annuleer');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Categories', 'categorieën');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Category', 'categorie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ChangePassword', 'wijzig wachtwoord');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ChooseALanguage', 'kies een taal');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ChooseAModule', 'kies een module');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ChooseAnApplication', 'kies een applicatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ChooseATemplate', 'kies een template');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ChooseAType', 'kies een type');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ChooseContent', 'kies inhoud');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Comment', 'reactie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Comments', 'reacties');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ConfirmPassword', 'bevestig wachtwoord');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Contact', 'contact');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ContactForm', 'contactformulier');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Content', 'inhoud');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ContentBlocks', 'inhoudsblokken');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Core', 'core');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'CustomURL', 'aangepaste URL');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Dashboard', 'dashboard');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Date', 'datum');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'DateAndTime', 'datum en tijd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'DateFormat', 'formaat datums');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Dear', 'beste');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'DebugMode', 'debug mode');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Default', 'standaard');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Delete', 'verwijderen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'DeleteThisTag', 'verwijder deze tag');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Description', 'beschrijving');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Developer', 'developer');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Domains', 'domeinen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Draft', 'kladversie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Drafts', 'kladversies');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Edit', 'wijzigen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'EditedOn', 'bewerkt op');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Editor', 'editor');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'EditProfile', 'bewerk profiel');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'EditTemplate', 'template wijzigen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Email', 'e-mail');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'EnableModeration', 'moderatie inschakelen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'EndDate', 'einddatum');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Error', 'error');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Example', 'voorbeeld');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Execute', 'uitvoeren');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ExitPages', 'uitstappagina\'s');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ExtraMetaTags', 'extra metatags');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'FeedburnerURL', 'feedburner URL');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'File', 'bestand');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Filename', 'bestandsnaam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'FilterCommentsForSpam', 'filter reacties op spam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'From', 'van');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Frontend', 'frontend');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'General', 'algemeen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'GeneralSettings', 'algemene instellingen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'GoToPage', 'ga naar pagina');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Group', 'groep');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Hidden', 'verborgen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Home', 'home');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Import', 'importeer');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Interface', 'interface');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'InterfacePreferences', 'voorkeuren interface');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'IP', 'IP');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ItemsPerPage', 'items per pagina');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Keyword', 'zoekwoord');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Keywords', 'sleutelwoorden');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Label', 'label');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'LandingPages', 'landingpagina\'s');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Language', 'taal');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Languages', 'talen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'LastEdited', 'laatst bewerkt');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'LastEditedOn', 'laatst bewerkt op');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'LastSaved', 'laatst opgeslagen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'LatestComments', 'laatste reacties');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Layout', 'layout');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Loading', 'loading');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Locale', 'locale');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'LoginDetails', 'login gegevens');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'LongDateFormat', 'lange datumformaat');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MainContent', 'hoofdinhoud');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MarkAsSpam', 'markeer als spam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Marketing', 'marketing');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Meta', 'meta');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MetaData', 'metadata');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MetaInformation', 'meta-informatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MetaNavigation', 'metanavigatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Moderate', 'modereer');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Moderation', 'moderatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Module', 'module');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Modules', 'modules');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ModuleSettings', 'module-instellingen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Move', 'verplaats');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MoveToModeration', 'verplaats naar moderatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MoveToPublished', 'verplaats naar gepubliceerd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'MoveToSpam', 'verplaats naar spam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Name', 'naam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'NavigationTitle', 'navigatietitel');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'NewPassword', 'nieuw wachtwoord');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'News', 'nieuws');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Next', 'volgende');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'NextPage', 'volgende pagina');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Nickname', 'publicatienaam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'None', 'geen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Notifications', 'verwitigingen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'NoTheme', 'geen thema');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'NumberFormat', 'formaat getallen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'NumberOfBlocks', 'aantal blokken');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Numbers', 'getallen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'OK', 'OK');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Or', 'of');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Overview', 'overzicht');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Page', 'pagina');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Pages', 'pagina\'s');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PageTitle', 'paginatitel');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Pageviews', 'paginaweergaves');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Pagination', 'paginering');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Password', 'wachtwoord');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PerDay', 'per dag');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PerVisit', 'per bezoek');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Permissions', 'rechten');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PersonalInformation', 'persoonlijke gegevens');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PingBlogServices', 'ping blogservices');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Port', 'poort');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Preview', 'preview');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Previous', 'vorige');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PreviousPage', 'vorige pagina');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PreviousVersions', 'vorige versies');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Profile', 'profiel');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Publish', 'publiceer');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Published', 'gepubliceerd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PublishedArticles', 'gepubliceerde artikels');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PublishedOn', 'gepubliceerd op');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'PublishOn', 'publiceer op');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RecentArticlesFull', 'recente artikels (volledig)');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RecentArticlesList', 'recente artikels (lijst)');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RecentComments', 'recente reacties');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RecentlyEdited', 'recent bewerkt');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RecentVisits', 'recente bezoeken');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ReferenceCode', 'referentiecode');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Referrer', 'referrer');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RepeatPassword', 'herhaal wachtwoord');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ReplyTo', 'reply-to');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RequiredField', 'verplicht veld');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ResetAndSignIn', 'resetten en aanmelden');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ResetYourPassword', 'reset je wachtwoord');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'RSSFeed', 'RSS feed');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Save', 'opslaan');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SaveDraft', 'kladversie opslaan');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Scripts', 'scripts');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Search', 'zoeken');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SearchAgain', 'opnieuw zoeken');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SearchForm', 'zoekformulier');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Send', 'verzenden');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SendingEmails', 'e-mails versturen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SEO', 'SEO');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Server', 'server');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Settings', 'instellingen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ShortDateFormat', 'korte datumformaat');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SignIn', 'aanmelden');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SignOut', 'afmelden');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Sitemap', 'sitemap');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SMTP', 'SMTP');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SortAscending', 'sorteer oplopend');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SortDescending', 'sorteer aflopend');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SortedAscending', 'oplopend gesorteerd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SortedDescending', 'aflopend gesorteerd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Spam', 'spam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'SpamFilter', 'spamfilter');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'StartDate', 'startdatum');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Statistics', 'statistieken');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Status', 'status');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Strong', 'sterk');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Summary', 'samenvatting');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Surname', 'achternaam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Synonym', 'synoniem');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Synonyms', 'synoniemen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Tags', 'tags');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Template', 'template');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Templates', 'templates');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Term', 'term');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Text', 'tekst');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Themes', 'thema\'s');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ThemesSelection', 'thema-keuze');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Till', 'tot');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'TimeFormat', 'formaat tijd');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Title', 'titel');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Titles', 'titels');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'To', 'aan');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Today', 'vandaag');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'TrafficSources', 'verkeersbronnen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Translation', 'vertaling');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Translations', 'vertalingen');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Type', 'type');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'UpdateFilter', 'filter updaten');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'URL', 'URL');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'UsedIn', 'gebruikt in');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Userguide', 'userguide');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Username', 'gebruikersnaam');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Users', 'gebruikers');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'UseThisDraft', 'gebruik deze kladversie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'UseThisVersion', 'laad deze versie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Value', 'waarde');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'View', 'bekijken');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'ViewReport', 'bekijk rapport');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'VisibleOnSite', 'Zichtbaar op de website');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Visitors', 'bezoekers');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'VisitWebsite', 'bezoek website');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'WaitingForModeration', 'wachten op moderatie');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Weak', 'zwak');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'WebmasterEmail', 'e-mailadres webmaster');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Website', 'website');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'WebsiteTitle', 'titel website');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Weight', 'gewicht');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'WhichModule', 'welke module');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'WhichWidget', 'welke widget');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Widget', 'widget');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'Widgets', 'widgets');
		$this->insertLocale('nl', 'backend', 'core', 'lbl', 'WithSelected', 'met geselecteerde');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ACT', 'actie');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ActivateNoFollow', 'Activeer <code>rel="nofollow"</code>');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'Added', 'Het item werd toegevoegd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'AddedCategory', 'De categorie "%1$s" werd toegevoegd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ClickToEdit', 'Klik om te wijzigen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentDeleted', 'De reactie werd verwijderd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentMovedModeration', 'De reactie werd verplaatst naar moderatie.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentMovedPublished', 'De reactie werd gepubliceerd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentMovedSpam', 'De reactie werd gemarkeerd als spam.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentsDeleted', 'De reacties werden verwijderd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentsMovedModeration', 'De reacties werden verplaatst naar moderatie.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentsMovedPublished', 'De reacties werden gepubliceerd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentsMovedSpam', 'De reacties werden gemarkeerd als spam.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'CommentsToModerate', '%1$s reactie(s) te modereren.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ConfigurationError', 'Sommige instellingen zijn nog niet geconfigureerd:');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ConfirmDelete', 'Ben je zeker dat je het item "%1$s" wil verwijderen?');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ConfirmDeleteCategory', 'Ben je zeker dat je deze categorie "%1$s" wil verwijderen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ConfirmMassDelete', 'Ben je zeker dat je deze item(s) wil verwijderen?');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ConfirmMassSpam', 'Ben je zeker dat je deze item(s) wil markeren als spam?');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'DE', 'Duits');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'Deleted', 'Het item werd verwijderd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'DeletedCategory', 'De categorie "%1$s" werd verwijderd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'EditCategory', 'bewerk categorie "%1$s"');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'EditComment', 'bewerk reactie');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'Edited', 'Het item werd opgeslagen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'EditedCategory', 'De categorie "%1$s" werd opgeslagen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'EditorImagesWithoutAlt', 'Er zijn afbeeldingen zonder alt-attribute <small>(<a href="http://www.anysurfer.org/elke-afbeelding-heeft-een-alt-attribuut" target="_blank">lees meer</a>)</small>.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'EditorInvalidLinks', 'Er zijn ongeldige links.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'EN', 'Engels');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ERR', 'foutbericht');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ES', 'Spaans');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ForgotPassword', 'Wachtwoord vergeten?');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'FR', 'Frans');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpAvatar', 'Een vierkante afbeelding geeft het beste resultaat.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpBlogger', 'Selecteer het bestand dat u heeft geëxporteerd van <a href="http://blogger.com">Blogger</a>.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpDrafts', 'Hier kan je jouw kladversie zien. Dit zijn tijdelijke versies.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpEmailFrom', 'E-mails verzonden vanuit het CMS gebruiken deze instellingen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpEmailTo', 'Notificaties van het CMS worden hiernaar verstuurd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpFeedburnerURL', 'bijv. http://feeds.feedburner.com/jouw-website');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpForgotPassword', 'Vul hieronder je e-mail adres in. Je krijgt een e-mail met instructies hoe je een nieuw wachtwoord instelt.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpMetaCustom', 'Deze extra metatags worden in de <code>&lt;head&gt;</code> sectie van de pagina gezet.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpMetaDescription', 'Vat de inhoud kort samen. Deze samenvatting wordt getoond in de resultaten van zoekmachines.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpMetaKeywords', 'Kies een aantal goed gekozen termen die de inhoud omschrijven.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpMetaURL', 'Vervang de automatisch gegenereerde URL door een zelfgekozen URL.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpNickname', 'De naam waaronder je wilt publiceren (bijvoorbeeld als auteur van een blogartikel).');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpResetPassword', 'Vul je gewenste, nieuwe wachtwoord in.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpRevisions', 'De laatst opgeslagen versies worden hier bijgehouden. De huidige versie wordt pas overschreven als je opslaat.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpRSSDescription', 'Beschrijf bondig wat voor soort inhoud de RSS-feed zal bevatten.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpRSSTitle', 'Geef een duidelijke titel aan de RSS-feed');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'HelpSMTPServer', 'Mailserver die wordt gebruikt voor het versturen van e-mails.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'Imported', 'De data werd geïmporteerd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'LBL', 'label');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'LoginFormForgotPasswordSuccess', '<strong>Mail sent.</strong> Please check your inbox!');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'MSG', 'bericht');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NL', 'Nederlands');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NoAkismetKey', 'Om de spamfilter te activeren moet je een Akismet-key <a href="%1$s">ingeven</a>.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NoComments', 'Er zijn nog geen reacties in deze categorie.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NoItems', 'Er zijn nog geen items.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NoPublishedComments', 'Er zijn nog geen gepubliceerde reacties.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NoRevisions', 'Er zijn nog geen vorige versies.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NoTags', 'Je hebt nog geen tags ingegeven.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NoUsage', 'Nog niet gebruikt.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'NowEditing', 'je bewerkt nu');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'PasswordResetSuccess', 'Je wachtwoord werd gewijzigd.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'Redirecting', 'U wordt omgeleidt.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ResetYourPasswordMailContent', 'Reset je wachtwoord door op de link hieronder te klikken. Indien je niet hier niet om gevraagd hebt hoef je geen actie te ondernemen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'ResetYourPasswordMailSubject', 'Wijzig je wachtwoord');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'Saved', 'De wijzigingen werden opgeslagen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'SavedAsDraft', '"%1$s" als kladversie opgeslagen.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'UsingADraft', 'Je gebruikt een kladversie.');
		$this->insertLocale('nl', 'backend', 'core', 'msg', 'UsingARevision', 'Je hebt een oudere versie ingeladen. Sla op om deze versie te gebruiken.');
		// --
		$this->insertLocale('en', 'backend', 'core', 'err', 'ActionNotAllowed', 'You have insufficient rights for this action.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'AddingCategoryFailed', 'Something went wrong.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'AddTagBeforeSubmitting', 'Add the tag before submitting.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'AkismetKey', 'Akismet API-key is not yet configured.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'AlphaNumericCharactersOnly', 'Only alphanumeric characters are allowed.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'AuthorIsRequired', 'Please provide an author.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'BrowserNotSupported', '<p>You\'re using an older browser that is not supported by Fork CMS. Use one of the following alternatives:</p><ul><li><a href="http://www.firefox.com/">Firefox</a>: a very good browser with a lot of free extensions.</li><li><a href="http://www.apple.com/safari">Safari</a>: one of the fastest and most advanced browsers. Good for Mac users.</li><li><a href="http://www.google.com/chrome">Chrome</a>: Google\'s browser - also very fast.</li></li><a href="http://www.microsoft.com/windows/products/winfamily/ie/default.mspx">Internet Explorer*</a>: update to the latest version of Internet Explorer.</li></ul>');
		$this->insertLocale('en', 'backend', 'core', 'err', 'CookiesNotEnabled', 'You need to enable cookies in order to use Fork CMS. Activate cookies and refresh this page.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'DateIsInvalid', 'Invalid date.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'DateRangeIsInvalid', 'Invalid date range.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'DebugModeIsActive', 'Debug-mode is active.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'EndDateIsInvalid', 'Invalid end date.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'EmailAlreadyExists', 'This e-mailaddress is in use.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'EmailIsInvalid', 'Please provide a valid e-mailaddress.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'EmailIsRequired', 'Please provide a valid e-mailaddress.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'EmailIsUnknown', 'This e-mailaddress is not in our database.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'FieldIsRequired', 'This field is required.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'ForkAPIKeys', 'Fork API-keys are not configured.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'FormError', 'Something went wrong, check the marked fields.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'GoogleMapsKey', 'Google maps API-key is not configured.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'InvalidAPIKey', 'Invalid API key.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'InvalidDomain', 'Invalid domain.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'InvalidEmailPasswordCombination', 'Your e-mail and password combination is incorrect. <a href="#" rel="forgotPasswordHolder" class="toggleBalloon">Did you forget your password?</a>');
		$this->insertLocale('en', 'backend', 'core', 'err', 'InvalidName', 'Invalid name.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'InvalidURL', 'Invalid URL.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'InvalidValue', 'Invalid value.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'JavascriptNotEnabled', 'To use Fork CMS, javascript needs to be enabled. Activate javascript and refresh this page.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'JPGGIFAndPNGOnly', 'Only jpg, gif and png files are allowed.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'ModuleNotAllowed', 'You have insufficient rights for this module.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'NameIsRequired', 'Please provide a name.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'NicknameIsRequired', 'Please provide a publication name.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'NoCommentsSelected', 'No comments were selected.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'NoItemsSelected', 'No items were selected.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'NoModuleLinked', 'Cannot generate URL. Create a page that has this module attached to it.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'NonExisting', 'This item doesn\'t exist.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'NoSelection', 'No items were selected.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'PasswordIsRequired', 'Please provide a password.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'PasswordRepeatIsRequired', 'Please repeat the desired password.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'PasswordsDontMatch', 'The passwords differ, please try again.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'RobotsFileIsNotOK', 'robots.txt will block search-engines.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'RSSTitle', 'Blog RSS title is not configured. <a href="%1$s">Configure</a>');
		$this->insertLocale('en', 'backend', 'core', 'err', 'SettingsForkAPIKeys', 'The Fork API-keys are not configured.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'SomethingWentWrong', 'Something went wrong.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'StartDateIsInvalid', 'Invalid start date.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'SurnameIsRequired', 'Please provide a last name.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'TooManyLoginAttempts', 'Too many login attempts. Click the forgot password link if you forgot your password.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'TimeIsInvalid', 'Invalid time.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'TitleIsRequired', 'Provide a title.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'URLAlreadyExists', 'This URL already exists.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'ValuesDontMatch', 'The values don\'t match.');
		$this->insertLocale('en', 'backend', 'core', 'err', 'XMLFilesOnly', 'Only XMl files are allowed.');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'AccountManagement', 'account management');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Active', 'active');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Add', 'add');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'AddCategory', 'add category');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'AddTemplate', 'add template');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Advanced', 'advanced');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'AllComments', 'all comments');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'AllowComments', 'allow comments');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'AllPages', 'all pages');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Amount', 'amount');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Analyse', 'analyse');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Analysis', 'analysis');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Analytics', 'analytics');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'APIKey', 'API key');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'APIKeys', 'API keys');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'APIURL', 'API URL');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Application', 'application');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Approve', 'approve');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Archive', 'archive');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Archived', 'archived');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Articles', 'articles');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'At', 'at');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Authentication', 'authentication');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Author', 'author');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Avatar', 'avatar');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Back', 'back');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Backend', 'backend');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Block', 'block');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Blog', 'blog');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'BrowserNotSupported', 'browser not supported');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'By', 'by');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Cancel', 'cancel');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Categories', 'categories');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Category', 'category');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ChangePassword', 'change password');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ChooseALanguage', 'choose a language');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ChooseAModule', 'choose a module');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ChooseAnApplication', 'choose an application');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ChooseATemplate', 'choose a template');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ChooseAType', 'choose a type');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ChooseContent', 'choose content');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Comment', 'comment');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Comments', 'comments');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ConfirmPassword', 'confirm password');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Contact', 'contact');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ContactForm', 'contact form');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Content', 'content');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ContentBlocks', 'content blocks');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Core', 'core');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'CustomURL', 'custom URL');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Dashboard', 'dashboard');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Date', 'date');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'DateAndTime', 'date and time');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'DateFormat', 'date format');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Dear', 'dear');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'DebugMode', 'debug mode');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Default', 'default');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Delete', 'delete');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'DeleteThisTag', 'delete this tag');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Description', 'description');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Developer', 'developer');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Domains', 'domains');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Draft', 'draft');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Drafts', 'drafts');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Edit', 'edit');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'EditedOn', 'edited on');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Editor', 'editor');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'EditProfile', 'edit profile');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'EditTemplate', 'edit template');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Email', 'e-mail');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'EnableModeration', 'enable moderation');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'EndDate', 'end date');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Error', 'error');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Example', 'example');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Execute', 'execute');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ExitPages', 'exit pages');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ExtraMetaTags', 'extra metatags');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'FeedburnerURL', 'feedburner URL');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'File', 'file');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Filename', 'filename');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'FilterCommentsForSpam', 'filter comments for spam');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'From', 'from');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Frontend', 'frontend');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'General', 'general');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'GeneralSettings', 'general settings');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'GoToPage', 'go to page');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Group', 'group');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Hidden', 'hidden');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Home', 'home');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Import', 'import');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Interface', 'interface');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'InterfacePreferences', 'interface preferences');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'IP', 'IP');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ItemsPerPage', 'items per page');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Keyword', 'keyword');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Keywords', 'keywords');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Label', 'label');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'LandingPages', 'landing pages');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Language', 'language');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Languages', 'languages');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'LastEdited', 'last edited');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'LastEditedOn', 'last edited on');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'LastSaved', 'last saved');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'LatestComments', 'latest comments');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Layout', 'layout');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Loading', 'loading');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Locale', 'locale');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'LoginDetails', 'login details');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'LongDateFormat', 'long date format');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MainContent', 'main content');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Marketing', 'marketing');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MarkAsSpam', 'mark as spam');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Meta', 'meta');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MetaData', 'metadata');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MetaInformation', 'meta information');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MetaNavigation', 'meta navigation');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Moderate', 'moderate');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Moderation', 'moderation');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Module', 'module');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Modules', 'modules');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ModuleSettings', 'module settings');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Move', 'move');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MoveToModeration', 'move to moderation');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MoveToPublished', 'move to published');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'MoveToSpam', 'move to spam');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Name', 'name');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'NavigationTitle', 'navigation title');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'NewPassword', 'new password');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'News', 'news');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Next', 'next');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'NextPage', 'next page');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Nickname', 'publication name');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'None', 'none');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Notifications', 'notifications');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'NoTheme', 'no theme');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'NumberFormat', 'number format');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'NumberOfBlocks', 'number of blocks');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Numbers', 'numbers');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'OK', 'OK');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Or', 'or');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Overview', 'overview');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Page', 'page');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Pages', 'pages');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PageTitle', 'pagetitle');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Pageviews', 'pageviews');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Pagination', 'pagination');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Password', 'password');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PerDay', 'per day');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PerVisit', 'per visit');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Permissions', 'permissions');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PersonalInformation', 'personal information');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PingBlogServices', 'ping blogservices');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Port', 'port');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Preview', 'preview');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Previous', 'previous');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PreviousPage', 'previous page');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PreviousVersions', 'previous versions');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Profile', 'profile');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Publish', 'publish');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Published', 'published');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PublishedArticles', 'published articles');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PublishedOn', 'published on');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'PublishOn', 'publish on');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RecentArticlesFull', 'recent articles (full)');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RecentArticlesList', 'recent articles (list)');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RecentComments', 'recent comments');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RecentlyEdited', 'recently edited');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RecentVisits', 'recent visits');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ReferenceCode', 'reference code');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Referrer', 'referrer');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RepeatPassword', 'repeat password');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ReplyTo', 'reply-to');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RequiredField', 'required field');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ResetAndSignIn', 'reset and sign in');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ResetYourPassword', 'reset your password');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'RSSFeed', 'RSS feed');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Save', 'save');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SaveDraft', 'save draft');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Scripts', 'scripts');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Search', 'search');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SearchAgain', 'search again');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SearchForm', 'search form');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Send', 'send');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SendingEmails', 'sending e-mails');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SEO', 'SEO');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Server', 'server');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Settings', 'settings');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ShortDateFormat', 'short date format');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SignIn', 'log in');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SignOut', 'sign out');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Sitemap', 'sitemap');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SMTP', 'SMTP');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SortAscending', 'sort ascending');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SortDescending', 'sort descending');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SortedAscending', 'sorted ascending');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SortedDescending', 'sorted descending');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Spam', 'spam');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'SpamFilter', 'spamfilter');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'StartDate', 'start date');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Statistics', 'statistics');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Status', 'status');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Strong', 'strong');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Summary', 'summary');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Surname', 'surname');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Synonym', 'synonym');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Synonyms', 'synonyms');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Tags', 'tags');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Template', 'template');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Templates', 'templates');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Term', 'term');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Text', 'text');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Themes', 'themes');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ThemesSelection', 'theme selection');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Till', 'till');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'TimeFormat', 'time format');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Title', 'title');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Titles', 'titles');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'To', 'to');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Today', 'today');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'TrafficSources', 'traffic sources');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Translation', 'translation');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Translations', 'translations');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Type', 'type');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'UpdateFilter', 'update filter');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'URL', 'URL');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'UsedIn', 'used in');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Userguide', 'userguide');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Username', 'username');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Users', 'users');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'UseThisDraft', 'use this draft');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'UseThisVersion', 'use this version');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Value', 'value');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'View', 'view');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'ViewReport', 'view report');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'VisibleOnSite', 'visible on site');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Visitors', 'visitors');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'VisitWebsite', 'visit website');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'WaitingForModeration', 'waiting for moderation');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Weak', 'weak');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'WebmasterEmail', 'e-mail webmaster');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Website', 'website');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'WebsiteTitle', 'website title');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Weight', 'weight');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'WhichModule', 'which module');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'WhichWidget', 'which widget');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Widget', 'widget');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'Widgets', 'widgets');
		$this->insertLocale('en', 'backend', 'core', 'lbl', 'WithSelected', 'with selected');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ACT', 'action');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ActivateNoFollow', 'Activate <code>rel="nofollow"</code>');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'Added', 'The item was added.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'AddedCategory', 'The category "%1$s" was added.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ClickToEdit', 'Click to edit');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentDeleted', 'The comment was deleted.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentMovedModeration', 'The comment was moved to moderation.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentMovedPublished', 'The comment was published.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentMovedSpam', 'The comment was marked as spam.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentsDeleted', 'The comments were deleted.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentsMovedModeration', 'The comments were moved to moderation.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentsMovedPublished', 'The comments were published.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentsMovedSpam', 'The comments were marked as spam.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'CommentsToModerate', '%1$s comment(s) to moderate.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ConfigurationError', 'Some settings aren\'t configured yet:');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ConfirmDelete', 'Are you sure you want to delete the item "%1$s"?');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ConfirmDeleteCategory', 'Are you sure you want to delete the category "%1$s"?');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ConfirmMassDelete', 'Are your sure you want to delete this/these item(s)?');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ConfirmMassSpam', 'Are your sure you want to mark this/these item(s) as spam?');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'DE', 'German');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'Deleted', 'The item was deleted.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'DeletedCategory', 'The category "%1$s" was deleted.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'EditCategory', 'edit category "%1$s"');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'EditComment', 'edit comment');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'Edited', 'The item was saved.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'EditedCategory', 'The category "%1$s" was saved.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'EditorImagesWithoutAlt', 'There are images without an alt-attribute.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'EditorInvalidLinks', 'There are invalid links.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'EN', 'English');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ERR', 'error');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ES', 'Spanish');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ForgotPassword', 'Forgot password?');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'FR', 'French');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpAvatar', 'A square picture produces the best results.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpBlogger', 'Select the file that you exported from <a href="http://blogger.com">Blogger</a>.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpDrafts', 'Here you can see your draft. These are temporarily versions.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpEmailFrom', 'E-mails sent from the CMS use these settings.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpEmailTo', 'Notifications from the CMS are sent here.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpFeedburnerURL', 'eg. http://feeds.feedburner.com/your-website');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpForgotPassword', 'Below enter your e-mail. You will receive an e-mail containing instructions on how to get a new password.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpMetaCustom', 'These custom metatags will be placed in the <code>&lt;head&gt;</code> section of the page.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpMetaDescription', 'Briefly summarize the content. This summary is shown in the results of search engines.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpMetaKeywords', 'Choose a number of wellthought terms that describe the content.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpMetaURL', 'Replace the automaticly generated URL by a custom one.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpNickname', 'The name you want to be published as (e.g. as the author of an article).');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpResetPassword', 'Provide your new password.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpRevisions', 'The last saved versions are kept here. The current version will only be overwritten when you save your changes.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpRSSDescription', 'Briefly describe what kind of content the RSS feed will contain.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpRSSTitle', 'Provide a clear title for the RSS feed.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'HelpSMTPServer', 'Mailserver that should be used for sending e-mails.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'Imported', 'The data was imported.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'LBL', 'label');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'LoginFormForgotPasswordSuccess', '<strong>Mail sent.</strong> Please check your inbox!');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'MSG', 'message');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NL', 'Dutch');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NoAkismetKey', 'If you want to enable the spam-protection you should <a href="%1$s">configure</a> an Akismet-key.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NoComments', 'There are no comments in this category yet.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NoItems', 'There are no items yet.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NoPublishedComments', 'There are no published comments.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NoRevisions', 'There are no previous versions yet.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NoTags', 'You didn\'t add tags yet.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NoUsage', 'Not yet used.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'NowEditing', 'now editing');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'PasswordResetSuccess', 'Your password has been changed.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'Redirecting', 'You are being redirected.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ResetYourPasswordMailContent', 'Reset your password by clicking the link below. If you didn\'t ask for this, you may just ignore this message.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'ResetYourPasswordMailSubject', 'Change your password');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'Saved', 'The changes were saved.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'SavedAsDraft', '"%1$s" saved as draft.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'UsingADraft', 'You\'re using a draft.');
		$this->insertLocale('en', 'backend', 'core', 'msg', 'UsingARevision', 'You\'re using an older version. Save to overwrite the current version.');

		// insert locale for frontend core
		$this->insertLocale('nl', 'frontend', 'core', 'act', 'Archive', 'archief');
		$this->insertLocale('nl', 'frontend', 'core', 'act', 'Category', 'categorie');
		$this->insertLocale('nl', 'frontend', 'core', 'act', 'Comment', 'reageer');
		$this->insertLocale('nl', 'frontend', 'core', 'act', 'Comments', 'reacties');
		$this->insertLocale('nl', 'frontend', 'core', 'act', 'CommentsRss', 'reacties-rss');
		$this->insertLocale('nl', 'frontend', 'core', 'act', 'Detail', 'detail');
		$this->insertLocale('nl', 'frontend', 'core', 'act', 'Rss', 'rss');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'AuthorIsRequired', 'Auteur is een verplicht veld.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'CommentTimeout', 'Slow down cowboy, er moeten wat tijd tussen iedere reactie zijn.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'ContactErrorWhileSending', 'Er ging iets mis tijdens het verzenden, probeer later opnieuw.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'EmailIsInvalid', 'Gelieve een geldig emailadres in te geven.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'EmailIsRequired', 'E-mail is een verplicht veld.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'FormError', 'Er ging iets mis, kijk de gemarkeerde velden na.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'InvalidURL', 'Dit is een ongeldige URL.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'MessageIsRequired', 'Bericht is een verplicht veld.');
		$this->insertLocale('nl', 'frontend', 'core', 'err', 'NameIsRequired', 'Gelieve een naam in te geven.');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Archive', 'archief');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Archives', 'archieven');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'By', 'door');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Category', 'categorie');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Categories', 'categorieën');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Comment', 'reactie');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'CommentedOn', 'reageerde op');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Comments', 'reacties');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Date', 'datum');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Email', 'e-mail');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'EN', 'Engels');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'FR', 'Frans');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'GoTo', 'ga naar');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'GoToPage', 'ga naar pagina');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'In', 'in');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Message', 'bericht');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Name', 'naam');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'NextPage', 'volgende pagina');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'NL', 'Nederlands');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'On', 'op');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'PreviousPage', 'vorige pagina');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'RecentComments', 'recente reacties');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'RequiredField', 'verplicht veld');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Send', 'verstuur');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Search', 'zoeken');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'SearchAgain', 'zoek opnieuw');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'SearchTerm', 'zoekterm');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Tags', 'tags');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Title', 'titel');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'Website', 'website');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'WrittenOn', 'geschreven op');
		$this->insertLocale('nl', 'frontend', 'core', 'lbl', 'YouAreHere', 'je bent hier');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'Comment', 'reageer');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'CommentsOn', 'Reacties op %1$s');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'ContactMessageSent', 'Uw e-mail werd verzonden.');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'ContactSubject', 'E-mail via contactformulier');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'EN', 'Engels');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'FR', 'Frans');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'NL', 'Nederlands');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'NotificationSubject', 'Verwittiging');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'SearchNoItems', 'Er zijn geen resultaten.');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'TagsNoItems', 'Er werden nog geen tags gebruikt.');
		$this->insertLocale('nl', 'frontend', 'core', 'msg', 'WrittenBy', 'geschreven door %1$s');
		// --
		$this->insertLocale('en', 'frontend', 'core', 'act', 'Archive', 'archive');
		$this->insertLocale('en', 'frontend', 'core', 'act', 'Category', 'category');
		$this->insertLocale('en', 'frontend', 'core', 'act', 'Comment', 'comment');
		$this->insertLocale('en', 'frontend', 'core', 'act', 'Comments', 'comments');
		$this->insertLocale('en', 'frontend', 'core', 'act', 'CommentsRss', 'comments-rss');
		$this->insertLocale('en', 'frontend', 'core', 'act', 'Detail', 'detail');
		$this->insertLocale('en', 'frontend', 'core', 'act', 'Rss', 'rss');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'AuthorIsRequired', 'Author is a required field.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'CommentTimeout', 'Slow down cowboy, there should be some time between the comments.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'ContactErrorWhileSending', 'Something went wrong while trying to send, please try again later.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'EmailIsInvalid', 'Please provide a valid e-email.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'EmailIsRequired', 'E-mail is a required field.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'FormError', 'Something went wrong, please check the marked fields.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'InvalidURL', 'This is an invalid URL.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'MessageIsRequired', 'Message is a required field.');
		$this->insertLocale('en', 'frontend', 'core', 'err', 'NameIsRequired', 'Please provide a name.');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Archive', 'archive');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Archives', 'archives');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'By', 'by');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Category', 'category');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Categories', 'categories');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Comment', 'comment');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'CommentedOn', 'commented on');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Comments', 'comments');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Date', 'date');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Email', 'e-mail');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'EN', 'English');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'FR', 'French');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'GoTo', 'go to');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'GoToPage', 'go to page');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'In', 'in');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Message', 'message');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Name', 'name');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'NextPage', 'next page');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'NL', 'Dutch');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'On', 'on');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'PreviousPage', 'previous page');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'RecentComments', 'recent comments');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'RequiredField', 'required field');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Send', 'send');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Search', 'search');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'SearchAgain', 'search again');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'SearchTerm', 'searchterm');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Tags', 'tags');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Title', 'title');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'Website', 'website');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'WrittenOn', 'written on');
		$this->insertLocale('en', 'frontend', 'core', 'lbl', 'YouAreHere', 'you are here');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'Comment', 'comment');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'CommentsOn', 'Comments on %1$s');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'ContactMessageSent', 'Your e-mail was sent.');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'ContactSubject', 'E-mail via contact form.');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'EN', 'English');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'FR', 'French');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'NL', 'Dutch');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'NotificationSubject', 'Notification');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'SearchNoItems', 'There were no results.');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'TagsNoItems', 'No tags were used.');
		$this->insertLocale('en', 'frontend', 'core', 'msg', 'WrittenBy', 'written by %1$s');
	}
}

?>