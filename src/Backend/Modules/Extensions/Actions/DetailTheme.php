<?php

namespace Backend\Modules\Extensions\Actions;

use Backend\Core\Engine\Base\ActionIndex as BackendBaseActionIndex;
use Backend\Core\Engine\Language as BL;
use Backend\Core\Engine\Authentication as BackendAuthentication;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Core\Engine\DatagridArray as BackendDataGridArray;
use Backend\Modules\Extensions\Engine\Model as BackendExtensionsModel;

/**
 * This is the detail-action it will display the details of a theme.
 *
 * @author Matthias Mullie <forkcms@mullie.eu>
 */
class DetailTheme extends BackendBaseActionIndex
{
    /**
     * Theme we request the details of.
     *
     * @var	string
     */
    private $currentTheme;

    /**
     * Datagrids.
     *
     * @var	BackendDataGrid
     */
    private $dataGridTemplates;

    /**
     * Information fetched from the info.xml.
     *
     * @var	array
     */
    private $information = array();

    /**
     * List of warnings.
     *
     * @var	array
     */
    private $warnings = array();

    /**
     * Execute the action.
     */
    public function execute()
    {
        // get parameters
        $this->currentTheme = $this->getParameter('theme', 'string');

        // does the item exist
        if($this->currentTheme !== null && BackendExtensionsModel::existsTheme($this->currentTheme)) {
            parent::execute();
            $this->loadData();
            $this->loadDataGridTemplates();
            $this->parse();
            $this->display();
        }

        // no item found, redirect to index, because somebody is fucking with our url
        else $this->redirect(BackendModel::createURLForAction('Themes') . '&error=non-existing');
    }

    /**
     * Load the data.
     * This will also set some warnings if needed.
     */
    private function loadData()
    {
        // inform that the theme is not installed yet
        if(!BackendExtensionsModel::isThemeInstalled($this->currentTheme)) {
            $this->warnings[] = array('message' => BL::getMessage('InformationThemeIsNotInstalled'));
        }

        // path to information file
        $pathInfoXml = FRONTEND_PATH . '/Themes/' . $this->currentTheme . '/info.xml';

        // information needs to exists
        if(is_file($pathInfoXml)) {
            try {
                // load info.xml
                $infoXml = @new \SimpleXMLElement($pathInfoXml, LIBXML_NOCDATA, true);

                // convert xml to useful array
                $this->information = BackendExtensionsModel::processThemeXml($infoXml);

                // empty data (nothing useful)
                if(empty($this->information)) $this->warnings[] = array('message' => BL::getMessage('InformationFileIsEmpty'));
            }

            // warning that the information file is corrupt
            catch(\Exception $e) {
                $this->warnings[] = array('message' => BL::getMessage('InformationFileCouldNotBeLoaded'));
            }
        }

        // warning that the information file is missing
        else $this->warnings[] = array('message' => BL::getMessage('InformationFileIsMissing'));
    }

    /**
     * Load the data grid which contains the events.
     */
    private function loadDataGridTemplates()
    {
        // no hooks so don't bother
        if(!isset($this->information['templates'])) return;

        // build data for display in datagrid
        $templates = array();
        foreach($this->information['templates'] as $template) {
            // set template name & path
            $record['name'] = $template['label'];
            $record['path'] = $template['path'];

            // set positions
            $record['positions'] = array();
            foreach($template['positions'] as $position) $record['positions'][] = $position['name'];
            $record['positions'] = implode(', ', $record['positions']);

            // add template to list
            $templates[] = $record;
        }

        // create data grid
        $this->dataGridTemplates = new BackendDataGridArray($templates);

        // add label for path
        $this->dataGridTemplates->setHeaderLabels(array('path' => BL::msg('PathToTemplate')));

        // no paging
        $this->dataGridTemplates->setPaging(false);
    }

    /**
     * Parse.
     */
    protected function parse()
    {
        parent::parse();

        // assign theme data
        $this->tpl->assign('name', $this->currentTheme);
        $this->tpl->assign('warnings', $this->warnings);
        $this->tpl->assign('information', $this->information);
        $this->tpl->assign('showExtensionsInstallTheme', !BackendExtensionsModel::isThemeInstalled($this->currentTheme) && BackendAuthentication::isAllowedAction('install_theme'));

        // data grids
        $this->tpl->assign('dataGridTemplates', (isset($this->dataGridTemplates) && $this->dataGridTemplates->getNumResults() > 0) ? $this->dataGridTemplates->getContent() : false);
    }
}
