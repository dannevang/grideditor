<?php
/**
 * Helper Class
 * 
 * @package grideditor
 * @copyright Alan Pich 2012
 */
 class grideditorHelper {
	 
    /**
     * @var array $config Useful paths etc
     * @access public
     */
    public $config = array();

    /**
     * @var modX $modx Reference to MODx instance
     * @access private
     */
    private $modx;
    
    /**
     * @var array $resFieldEditors Resource field Ext editor xtypes
     * @access private
     */
    private $resFieldEditors = array(
            'pagetitle' => 'textfield',
            'longtitle' => 'textfield',
            'introtext' => 'textfield',
            'description' => 'textarea',
            'alias' => 'textfield',
            'template' => 'modx-combo-template',
            'hidemenu' => 'modx-combo-boolean'
        );
    
    /**
     * @var array $tvEditorTypeMap TV type editor xtypes
     * @access private
     */
    private $tvEditorTypeMap = array(
            'text' => 'textfield',
            'listbox' => 'grideditor-combo-tv'
        );
    
    /**
     * Constructor - sets up config array
     * @param modX $modx Current instance of MODx
     */
    function __construct(modX &$modx){
           // Make MODx instance accessible
           $this->modx =& $modx;

           // Setup config data
           $core = $this->modx->getOption('core_path').'components/grideditor/';
           $assets = $this->modx->getOption('assets_url').'components/grideditor/';
           $this->config = array(
                   'corePath' => $core,
                   'processorPath' => $core.'processors/',
                   'controllerPath' => $core.'controllers/',
                   'templatePath' => $core.'templates/',
                   'docsPath' => $core.'docs/',
                   'assetsUrl' => $assets,
                   'jsUrl' => $assets.'mgr/js/',
                   'cssUrl' => $assets.'mgr/css/',
                   'imgUrl' => $assets.'mgr/img/',
                   'connectorUrl' => $assets.'mgr/connector.php',

                    'configChunkPrefix' => 'grideditor.config.'
           );
    }//

    
    /**
     * Get config array as JSON string (for javascript insert)
     * @return string JSON encoded self::$config array
     */
    public function configJSON(){
           return $this->modx->toJSON($this->config); 
    }//
     
     
    /**
     * Sanitize json input. Removes all invalid chars & comments
     * @param string $raw Raw JSON input
     * @return mixed Parse JSON -> object|array etc
     */
    public function sanitizedJSONdecode($raw){
        $safe = preg_replace("/[^[:print:]]/",'',$raw);
        return json_decode($safe);        
    }//
    
    /**
     * Prepare custom config for passing to Ext/Javascript
     * @param Object $data Parsed config object
     * @return Object prepared for Ext use
     */
    public function getExtConfig($conf){
        $C = new stdClass;
        // Title for both <title> and <h2> page titles
        $C->title = isset($conf->title)? $conf->title : $this->modx->lexicon('grideditor.cmp.default.title');
        // Control buttons for resources (edit,publish,delete etc)
        $C->controls = isset($conf->controls)? $conf->controls : array();
        // Grid Field data
        $C->fields = array();
        // Config chunk name
        $C->chunk = $conf->chunk;
        // Filter field
        if( isset($conf->filter) && isset($conf->filter->field) && !empty($conf->filter->field)){
            $C->filter = new stdClass;
            $C->filter->field = $conf->filter->field;
            $C->filter->label = isset($conf->filter->label)? $conf->filter->label : 'No Filter';
        };
        // Search Fields
        $C->searchFields = isset($conf->search)? $conf->search : NULL;
        
        // Add resource id field
        $idField = new stdClass;
        $idField->field = 'id';
        $idField->hidden = true;
        $C->fields[] = $idField;
   
        // Add resource fields
        if(isset($conf->fields)){
            foreach($conf->fields as $field){
                if($F = $this->prepFieldForExt($field)){
                    $C->fields[] = $F;
                };
            };
        };
        
        // Add TV fields
        if(isset($conf->tvs)){
            foreach($conf->tvs as $tv){
                if($F = $this->prepTvForExt($tv)){
                    $C->fields[] = $F;
                };
            };
        };
        return $C;
    }//
    
    
    /**
     * Prepare resource field data for Ext use
     * @param object $field The config data for this field
     * @return object Prepared data
     */
    private function prepFieldForExt($field){
        // Sanity check
        $resourceFields = array_keys($this->resFieldEditors);
        if(!isset($field->field) || !in_array($field->field,$resourceFields)){ return FALSE; };
        // Prepare Data
        $F = new stdClass;
        $F->field = $field->field;
        $F->label = isset($field->label)? $field->label : $field->field;
        $F->editable = isset($field->editable)? $field->editable : false;
        $F->editor = $this->getFieldEditor($field->field); 
        $F->sortable = isset($field->sortable)? $field->sortable : true;
        // Return object
        return $F;
    }//
    
    
    /**
     * Return a js renderer function for a resource field
     * @param string $field Name of resource field
     * @return string Renderer function
     */
    private function getFieldEditor($field){
        // Sanity check
        if(!in_array($field, array_keys($this->resFieldEditors))){ return 'textfield'; };
        // Create an Ext xtype object
        $obj = new stdClass;
        $obj->xtype = $this->resFieldEditors[$field];
        return $obj;
    }//
    
    
    /**
     * Prepare TV field data for Ext use
     * @param object $field The config data for this TV
     * @return object Prepared data
     */
    private function prepTvForExt($field){
        // Sanity Checks
        if(!isset($field->field) || empty($field->field)){ return false; };
        $tv = $this->modx->getObject('modTemplateVar',array('name' => $field->field));
        if( ! $tv instanceof modTemplateVar ){ return false; };
        // Prepare data
        $F = new stdClass;
        $F->field = 'tv_'.$field->field;
        $F->label = isset($field->title)? $field->label : $field->field;
        $F->editable = isset($field->editable)? $field->editable : false;
        $F->editor = $this->getTvEditor($tv); 
        $F->sortable = isset($field->sortable)? $field->sortable : true;
        // Return object
        return $F;
    }//
    
    
    /**
     * Return a js renderer function for a TV field
     * @param modTemplateVar $field TV field in question
     * @return string Renderer function
     */
    private function getTvEditor(modTemplateVar $tv){
        // Default to textfield
        $editor = 'textfield';
        
        /* @var int $editorType - Type of editor modx uses */
        $editorType = $tv->get('type');
        
        if( isset($this->tvEditorTypeMap[$editorType])){
            $editor = $this->tvEditorTypeMap[$editorType];
        }
        
        // Create an Ext xtype object
        $obj = new stdClass;
        $obj->xtype = $editor;
        // Add config for combo boxes
        if( $editor == 'grideditor-combo-tv'){
           $obj->tvName = $tv->get('name');
        };
        // Default fallback to textfield
        return $obj;
    }//
    
    
    /**
     * Get params for TV input (list options etc.)
     * @param modTemplateVar $tv TV field
     * @return array Params
     */
    private function getTvEditorParams( modTemplateVar $tv ){
        switch($tv->get('type')){
            
            case 'listbox': $params = array();
                            $options = explode('||',$tv->get('elements'));
                            foreach($options as $opt){
                                $bits = explode('==',$opt);
                                $params[] = array(
                                    $bits[0],
                                    ((isset($bits[1]))? $bits[1] : $bits[0])
                                );
                            }
                            break;
                        
            default: $params = array();
        }
        return $params;
    }//
    
    
    /**
     * Get the XPDOQuery Where clause array for finding resources
     * @return array The Clause array
     */
    public function getResourceQueryWhereArray($conf){
       $c = $this->modx->newQuery('modResource');
       foreach($conf->templates as $tplID){
           // If tplID is a string, convert it to ID int
           if( is_string($tplID)){
               $tpl = $this->modx->getObject('modTemplate',array('name'=>$tplID));
               if(!$tpl instanceof modTemplate){ continue; };
               $tplID = $tpl->get('id');
           };
           $c->where(array(
               'template' => $tplID
           ));
       }
       return $c;
    }//
	 
 };// end class grideditorHelper

 
 
 
 function in_array_r($needle, $haystack) {
    $found = false;
    foreach ($haystack as $item) {
    if ($item === $needle) { 
            $found = true; 
            break; 
        } elseif (is_array($item)) {
            $found = in_array_r($needle, $item); 
            if($found) { 
                break; 
            } 
        }    
    }
    return $found;
};//