<?php
/**
 * GridEditor instance configuration object
 *
 * @author alan
 */
class GridEditorConfiguration
{

    /**
     * Holds all config warnings
     * @var array
     */
    public $warnings = array();
    /**
     * Page title
     * @var string $title
     */
    public $title = '';
    /**
     * Name to use for a single resource
     * @var string
     */
    public $resourceName = 'Resource';
    /**
     * Array of xPDO WHERE queries
     * @var array
     */
    public $resourceQuery = array();
    /**
     * Templates to restrict to
     * @var array of int $templates
     */
    public $templates = array();
    /**
     * Default properties to apply to new resources
     * @var array
     */
    public $newResourceDefaults = array();
    /**
     * Field & label to use for dropdown filtering
     * @var stdClass $filter
     */
    public $filter = false;
    /**
     * Fields to use for text search
     * @var array of string $fields
     */
    public $searchFields = array();
    /**
     * Field to group resources by
     * @var $group
     */
    public $group = false;
    /**
     * Resource Controls to display
     * @var array of string $controls
     */
    public $controls = array('publish', 'edit', 'delete', 'new');
    /**
     * Specify number of resources per page in grid
     * @var int
     */
    public $perPage = 5;
    /**
     * Fields to show in grid
     * @var array of GridEditorFieldObject $fields
     */
    public $fields = array();
    /**
     * Parent resource id to use when creating new resource
     * @var int Resource Id
     */
    public $parentResourceId = 0;
    /**
     * Array of field names for quick reference
     * @var array of string Field Names
     */
    public $fieldList = array();
    /**
     * Config Chunk suffix (name)
     * @var string
     */
    public $chunk = 'demo';
    public $chunkId = false;
    /**
     * Extra javascript files to load into cmp
     * @var array
     */
    public $javascripts = array();

    /**
     * Field to sort grid resources by
     * @var string
     */
    public $sortBy = 'menuindex';
    public $sortDir = 'ASC';

    /**
     * Reference to a MODx instance
     * @private modX $modx
     */
    private $modx;

    /**
     * Constructor -> generate from input object
     */
    public function __construct(modX &$modx)
    {
        $this->modx = $modx;
    }

    /**
     * @static Create an instance populated from a config chunk
     * @param string $chunkName Name of chunk to use
     * @param modX $modx Modx Instance
     * @param string $chunkPrefix. System prefix of chunk name.
     * @return GridEditorConfiguration instance
     */
    public static function fromChunk($chunkName, modX &$modx, $chunkPrefix = '')
    {
        // Create a new instance
        $config = new self($modx);
        // Try to grab the chunk
        $chunk = & $modx->getObject('modChunk', array('name' => $chunkName));
        // If chunk doesnt exists, bail out and record warning
        if (!$chunk instanceof modChunk) {
            $config->warning('Specified configuration chunk does not exist');
            return $config;
        };
        $chunkValue = $chunk->process();
        // If chunk is empty, bail out and record warning
        if (empty($chunkValue)) {
            $config->warning('Specified configuration chunk is empty');
            return $config;
        };
        // Populate config from JSON string
        $config->fromJSON($chunkValue, $modx);
        // Save the chunk name
        $config->chunk = str_replace($chunkPrefix, '', $chunkName);
        $config->chunkId = $chunk->get('id');

        unset($config->modx);

        // Return the config object
        return $config;
    }

    //

    /**
     * Log a warning message
     * @param string $msg Warning Message
     * @return bool Always false
     */
    private function warning($msg)
    {
        $this->warnings[] = $msg;
        return false;
    } //

    /**
     * Populate object from an object.
     * Adds warnings to stack for any malformed or missing params
     */
    private function fromJSON($json)
    {
        // Minify json
        $json = json_minify($json);
        // Attempt to parse JSON
        if (!$data = json_decode($json)) {
            $this->warning("Configuration chunk appears to be malformed. Decoding failed.");
            return false;
        };

        // Check for a page title
        if (!isset($data->title)) {
            $this->warning("Property `title` omitted, using default from lexicon");
            $this->title = $this->modx->lexicon('grideditor.defaults.title');
        } else {
            $this->title = $data->title;
        };

        // Override Name for a single Resource
        if(isset($data->resourceName)){
            $this->resourceName = (string) $data->resourceName;
        }

        // Add New Resource defaults
        if (isset($data->newResourceDefaults)) {
            if (!is_object($data->newResourceDefaults)) {
                $this->warning('Property `newResourceDefaults` is the wrong type. Type `' . gettype($data->newResourceDefaults) . '` is not an object');
            } else {
                $this->newResourceDefaults = array_merge($this->newResourceDefaults,(array) $data->newResourceDefaults);
            }
        };


        // Add grid perPage count
        if(isset($data->perPage) && is_numeric($data->perPage)){
            $this->perPage = $data->perPage;
        }

        // Prepare sorting
        if(isset($data->sortBy)){
            $this->sortBy = $data->sortBy;
        }
        if(isset($data->sortDir)){
            $this->sortDir = $data->sortDir;
        }


        // Prepare resource selection filters
        if(isset($data->resourceQuery))
            $this->resourceQuery = (array) $data->resourceQuery;

        // Prepare Fields
        $this->prepareResourceFields($data);
        $this->prepareTvFields($data);
        $this->prepareFieldOrder();

        // Add resource ID as a hidden resource field
        $id = new stdClass;
        $id->field = 'id';
        $id->hidden = true;
        $this->fields['id'] = new GridEditorResourceField($id, $this->modx);
        $this->fieldList[] = 'id';

        // Add published status as a hidden resource field
        $published = new stdClass;
        $published->field = 'published';
        $published->hidden = true;
        $published->label = 'Hide ME!!!!!!!!';
        $this->fields['published'] = new GridEditorResourceField($published, $this->modx);
        $this->fieldList[] = 'published';

        // Prepare searching & filtering
        $this->prepareSearchFields($data);
        $this->prepareFilterInfo($data);
        $this->prepareGroupingField($data);

        // Prepare control object
        $this->prepareGridControls($data);

        // Add 'new' resource parent
        $this->prepareNewResourceParent($data);

        // Add extra JS files
        $this->prepareAdditionalJavascripts($data);

        return true;
    }

    //

    private function prepareResourceFields($fields)
    {
        if (!isset($fields->fields) || count($fields->fields) < 1) {
            return $this->warning('No resource fields specified');
        };
        $fields = $fields->fields;
        foreach ($fields as $field) {
            $fieldObj = new GridEditorResourceField($field, $this->modx);
            if (!$fieldObj->isValid) {
                $this->warning(array(
                    'key' => 'invalid_resource_field',
                    'data' => array(
                        'field' => $field->field
                    )
                ));
                continue;
            };
            $this->fields[$fieldObj->field] = $fieldObj;
            $this->fieldList[] = $fieldObj->field;
        }
    }

    //

    private function prepareTvFields($fields)
    {
        if (!isset($fields->tvs) || count($fields->tvs) < 1) {
            return;
        };
        $fields = $fields->tvs;
        foreach ($fields as $field) {
            $fieldObj = new GridEditorTvField($field, $this->modx);
            if (!$fieldObj->isValid) {
                $this->warning(array(
                    'key' => 'invalid_tv_field',
                    'data' => array(
                        'field' => $field->field
                    )
                ));
                continue;
            };

            $safeFieldName = str_replace(array('-','.'),'_',$fieldObj->field);

            $this->fields[$safeFieldName] = $fieldObj;
            $this->fieldList[] = $fieldObj->field;
        }
    }

    //

    /**
     * Sort all fields according to $order param. Not set defaults to zero
     */
    private function prepareFieldOrder()
    {
        $sorts = array();
        foreach ($this->fields as $key => $field) {
            $sorts[$key] = $field->order;
        };
        array_multisort($sorts, $this->fields);
    }

    //

    /**
     * Check all listed search fields are valid. Only add valid ones. Warn others.
     * @param array $fields
     * @return bool
     */
    private function prepareSearchFields($fields)
    {
        if (!isset($fields->search) || count($fields->search) < 1) {
            return;
        };
        $fields = $fields->search;
        foreach ($fields as $field) {
            if (!in_array($field, $this->fieldList)) {
                $this->warning(array(
                    'key' => 'invalid_search_field',
                    'data' => array(
                        'field' => $field
                    )
                ));
                continue;
            }
            $this->searchFields[] = $field;
        }
        return true;
    }

    /**
     * Check filter field is valid
     * @param object $info
     * @return boolean
     */
    private function prepareFilterInfo($info)
    {
        if (!isset($info->filter)) {
            return;
        };
        $info = $info->filter;
        if (!isset($info->field) || empty($info->field)) {
            return $this->warning('No filter field specified');
        };
        if (!in_array($info->field, $this->fieldList)) {
            return $this->warning('Ignoring filter field [' . $info->field . '] as does not appear in resource or tv list');
        };
        $this->filter = new stdClass;
        $this->filter->field = $info->field;
        $this->filter->label = isset($info->label) ? $info->label : $info->field;
    }

    //

    /**
     * Check the selected grouping field is valid, and set it
     * @param object $info
     * @return boolean
     */
    private function prepareGroupingField($info)
    {
        if (!isset($info->grouping)) {
            return true;
        };
        $info = $info->grouping;

        if (!isset($info->field) || empty($info->field)) {
            return $this->warning('No grouping field specified');
        };
        if (!in_array($info->field, $this->fieldList)) {
            return $this->warning('Ignoring grouping field [' . $info->field . '] as does not appear in resource or tv list');
        };
        $this->grouping = new stdClass;
        $this->grouping->field = $info->field;
        $this->grouping->label = isset($info->label) ? $info->label : 'Filter results';
    }

    //

    /**
     * Sanitize controls input to legitimate control names
     * @param type $data
     */
    private function prepareGridControls($data)
    {
        if (!isset($data->controls) || count($data->controls) < 1) {
            return;
        };
        $data = $data->controls;
        $controls = array();
        foreach ($this->controls as $key => $val) {
            if (in_array($val, $data)) {
                $controls[] = $val;
            }
        };
        $this->controls = $controls;
    }

    //

    /**
     * Allow config to specify a parent for new resources
     * @param object $data config info
     * @return bool
     */
    private function prepareNewResourceParent($data)
    {
        if (!isset($data->newResourceParent)) {
            return;
        };
        $pId = $data->newResourceParent;
        // Check is integer
        if (!is_integer($data->newResourceParent)) {
            return $this->warning('Resource Parent ID of type [' . gettype($pId) . '] is not of type integer. Ignoring...');
        };
        // Check is existing resource
        $res = $this->modx->getObject('modResource', $pId);
        if (!$res instanceof modResource) {
            return $this->warning('Invalid parent resource id [' . $pId . ']');
        };
        $this->parentResourceId = $pId;
    }

    //

    /**
     * Additional javascripts to be loaded
     * @param object $data config info
     */
    private function prepareAdditionalJavascripts($data)
    { #
        if (!isset($data->javascripts)) {
            return;
        };
        // Ensure is array
        if (!is_array($data->javascripts)) {
            return $this->warning('Property [javascripts] should be of type `array`. Type `' . gettype($data->javascripts) . '` supplied.');
        };
        foreach ($data->javascripts as $js) {
            // Allow inclusion from grideditor js folder using ~/ prefix
            if (substr($js, 0, 2) == '~/') {
                $prefix = $this->modx->getOption('assets_url') . 'components/grideditor/mgr/js/';
                $js = $prefix . substr($js, 2);
            };
            // Add src to javascripts array
            $this->javascripts[] = $js;
        };
    }

    //

    /**
     * Parses and prepares templates array. Converts template names to IDs and
     * removes invalid ids/names while issuing a warning
     * @param array $templates
     * @return boolean Valid
     */
    private function prepareTemplates($templates)
    {
        for ($k = 0; $k < count($templates); $k++) {
            $tpl = $templates[$k];

            if (is_integer($tpl)) {
                // Use as template ID
                $modTpl = $this->modx->getObject('modTemplate', $tpl);
                // Bail out & warn if template doesnt exist
                if (!$modTpl instanceof modTemplate) {
                    $this->warning('Invalid item in property `templates` - [' . $tpl . '] is not a valid Template ID');
                    return false;
                };
                // Assume template exists then
                $this->templates[] = $tpl;
            } else {
                // Use as template name
                $modTpl = $this->modx->getObject('modTemplate', array('templatename' => $tpl));
                // Bail out & warn if template doesnt exist
                if (!$modTpl instanceof modTemplate) {
                    $this->warning('Invalid item in property `templates` - [' . $tpl . '] is not a valid Template name');
                    return false;
                };
                $this->templates[] = $modTpl->get('id');
            };

        }
    }

    /**
     * Return json-encoded configuration
     *
     * @return string
     */
    public function toJSON()
    {
        return json_encode($this);
    }


}

;// end class GridEditorConfiguration
