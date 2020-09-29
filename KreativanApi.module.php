<?php
/**
 *  KreativanApi Module
 *
 *  @author Ivan Milincic <kreativan@outlook.com>
 *  @copyright 2019 Ivan Milincic
 * 
 *  Utility:
 *  clearCache() - clear AIOM cache reset css_ prefix and refresh modules
 *  compileLess()  - clear AIOM cache reset css_ prefix
 *  writeToFile() 
 *  getFolders($dir)
 *  uplaodFile()
 *	
 *	Module:
 *	moduleSettings() - chanage module settings
 * 
 *  Fields & Templates:
 *  getFieldsetOpen() - get fields inside Fieldset (Open)
 *  setFieldOptions() - use this method to change field option based on template
 *  createRepeater() - create Repeater field
 *  createFieldsetPage() - craete FieldsetPage field
 *  setRepeaterFieldOptions() - set field options inside a Repeater or FieldsetPage
 *  createOptionsField() - create Options field
 *  addTemplateField() - add new field to the specific position in template (before-after existing field)
 *	removeTemplateField() - remove ield from template
 * 
 *  Custom:
 *  createTemplateStructure() -- create Main Page -> Subpages
 *  deleteTemplateStructure() -- delete pages, templates, fields
 * 
*/

class KreativanApi extends WireData implements Module {

    public static function getModuleInfo() {
        return array(
            'title' => 'Kreativan API',
            'version' => 100,
            'summary' => 'API related methods...',
            'icon' => 'code',
            'singular' => true,
            'autoload' => false
        );
    }

    /* ==================================================================================
        Utility
    ===================================================================================== */

    /**
     *  Clear AIOM cache 
	 *	@see  compileLess()
	 *	Reload modules
     */
    public function clearCache() {
		
		// Compile less
		$this->compileLess();

        // Refresh Modules
        $this->modules->refresh();

    }

    /**
     *  Clear AIOM cache
	 *	Delete aiom cache files and change css_prefix
     *	to force browser to clear cache.
     *  @return void
     */
    public function compileLess() {

        // delete AIOM cached css files
        $aiom_cache = $this->config->paths->assets."aiom";
        $aiom_cache_files = glob("$aiom_cache/*");
        foreach($aiom_cache_files as $file) {
            if(is_file($file))
            unlink($file);
        }

        $random_prefix = "css_".rand(10,1000)."_";
        $this->moduleSettings("AllInOneMinify", ["stylesheet_prefix" => "$random_prefix"]);

    }

    /**
     *  Get Folders 
     *	@param string $dir	main folder name
     *  @return array
     */
    public function getFolders($dir) {
        return array_filter(glob("{$dir}*"), 'is_dir');
    }


    /**
     * 	Uplaod File
     * 
     * 	@param string $file_field_name	    name of the file field in the uplaod form
     *  @param string $dest                 path to upload folder
     *  @param string $valid                allowed file extensions
     */
    public function uplaodFile($file_field_name = "", $dest = "", $valid = ['jpg', 'jpeg', 'gif', 'png']) {

        // if there is no uplaod path trow error
        if(!is_dir($dest)) {
            if(!wireMkdir($dest)) $this->error("No upload path!"); 
        }

        // WireUpload
        $upload = new WireUpload("$file_field_name");
        $upload->setMaxFiles(1);
        $upload->setOverwrite(true);
        $upload->setDestinationPath($dest);
        $upload->setValidExtensions($valid); 

        try {
            // execute upload
            $files = $upload->execute();
            // dump($files);
        } catch(Exception $e) {
            $error = $e->getMessage();
            $this->error($error); 
        }

    }
	
	/**
     *  Write To File Function
     *
     *  @param string $file     file path to write to.
     *  @param string $what     what to write to the file
     *  @example writeToFile("home.php", "<h1>O Yeah!</h1>") ;
     *  @return void
     */
    public function writeToFile($file, $what) {
        // Open the file to get existing content
        $current = file_get_contents($file);
        // Append a new person to the file
        $current .= $what;
        // Write the contents back to the file
        return file_put_contents($file, $current);
    }
	
	/* ==================================================================================
        API Methods
    ===================================================================================== */
	
	 /**
      *	Get Fields inside Fieldset (Open)
      *	@param string $template		template name
      *	@param string $SET			Fieldset (Open) name
      * @return array
      */
    public function getFieldsetOpen($template = "", $SET = "") {
        $tmpl = $this->templates->get($template);
        $SET_start = false;
        $fields_arr = [];
        foreach($tmpl->fields as $field) {
            if ($field->name == $SET) {
                $SET_start = true;
            } elseif ($field->name == "{$SET}_END") {
                break;
            } elseif ($SET_start == 'true') {
                $fields_arr[] = $field;
            }
        }
        return $fields_arr;
    }

    /**
     *  Module Settings
     *  
     *  @param string $module     module class name
     *  @param array $data        module settings  
     * 
     */
    public function moduleSettings($module, $data = []) {

        $old_data = $this->modules->getModuleConfigData($module);
        $data = array_merge($old_data, $data);
        $this->modules->saveModuleConfigData($module, $data);

    }
		 
    /**
     *  Change Field Options 
     *  
     *  @param string $template     Template name
     *  @param string $field        Field Name
     *  @param array $options       eg: ["option" => value]
     * 
     *  @example $this->fieldOptions("home", "text", ["label" => "My Text"]);
     */

    public function fieldOptions($template, $field, $options) {
        // change field settings for this template
        $t = wire('templates')->get($template);
        $f = $t->fieldgroup->getField($field, true);
        foreach($options as $key => $value) {
            $f->$key = $value;
        }
        $this->fields->saveFieldgroupContext($f, $t->fieldgroup);//save new setting in context
    }

    /**
     *  Create Repeater
     * 
     *  @param string $name         The name of your repeater field
     *  @param string $label        Label for your repeater
     *  @param string $fields       Array of fields names to add to repeater eg: ["title","body","text"]
     *  @param string $items_label  Label for repeater items eg: {title} 
     *  @param string $tags         Tags for the repeater field
     * 
     *  @example    $this->createRepeater("dropdown", "Dropdown", $fields_array, "{title}", "Repeaters");
     */     
    public function createRepeater($name, $label, $fields, $items_label, $tags) {

        // Create field
        $f = new Field();
        $f->type = $this->modules->get("FieldtypeRepeater");
        $f->name = $name;
        $f->label = $label;
        $f->tags = $tags;
        $f->repeaterReadyItems = 3;
        $f->repeaterTitle = $items_label;

        // Create fieldgroup
        $fg = new Fieldgroup();
        $fg->name = "repeater_$name";

        // Add fields to fieldgroup
        foreach($fields as $field) {
            $fg->append($this->fields->get($field));
        }

        $fg->save();

        // Create template
        $tmp = new Template();
        $tmp->name = "repeater_$name";
        $tmp->flags = 8;
        $tmp->noChildren = 1;
        $tmp->noParents = 1;
        $tmp->noGlobal = 1;
        $tmp->slashUrls = 1;
        $tmp->fieldgroup = $fg;

        $tmp->save();

        // Setup page for the repeater - Very important
        $p = "for-field-{$f->id}";
        $f->parent_id = $this->pages->get("name=$p")->id;
        $f->template_id = $tmp->id;
        $f->repeaterReadyItems = 3;

        // Now, add the fields directly to the repeater field
        foreach($fields as $field) {
            $f->repeaterFields = $this->fields->get($field);
        }

        $f->save();

        return $f;

    }

    /**
     *  Create FieldsetPage
     * 
     *  This is basically same as repeater, except it's using "FieldtypeFieldsetPage" module, and using fewer params.
     *  To change field options we can use same  repeaterFieldOptions();
     * 
     *  @param string $name        The name of your repeater field
     *  @param string $label       The label for your repeater
     *  @param array $fields       Array of fields names to add to repeater
     *  @param string $tags        Tags for the repeater field
     * 
     *  @example    $this->createFieldsetPage("my_block", "My Block", $fields_array, "Blocks");
     */     
    public function createFieldsetPage($name, $label, $fields, $tags) {

        // Create field
        $f = new Field();
        $f->type = $this->modules->get("FieldtypeFieldsetPage");
        $f->name = $name;
        $f->label = $label;
        $f->tags = $tags;

        // Create fieldgroup
        $fg = new Fieldgroup();
        $fg->name = "repeater_$name";

        // Add fields to fieldgroup
        foreach($fields as $field) {
            $fg->append($this->fields->get($field));
        }

        $fg->save();

        // Create template
        $tmp = new Template();
        $tmp->name = "repeater_$name";
        $tmp->flags = 8;
        $tmp->noChildren = 1;
        $tmp->noParents = 1;
        $tmp->noGlobal = 1;
        $tmp->slashUrls = 1;
        $tmp->fieldgroup = $fg;

        $tmp->save();

        // Setup page for the repeater - Very important
        $p = "for-field-{$f->id}";
        $f->parent_id = $this->pages->get("name=$p")->id;
        $f->template_id = $tmp->id;

        // Now, add the fields directly to the repeater field
        foreach($fields as $field) {
            $f->repeaterFields = $this->fields->get($field);
        }

        $f->save();

        return $f;

    }

    /**
     *  Repeater & FieldsetPage Field Options
     *  (Yep, FieldsetPage works same as Repeater)
     * 
     *  Using the same fieldOptions() method with custom params. Just because repeater template name has "repaeter_" prefix
     *  @param string $repeater_name   name of the repeater field
     *  @param string $field_name      name of the field
     *  @param array $options          field options ["option" => "value"]
     *  
     *  @example $this->fieldOptions("my_repeater_name", "text", ["label" => "My Text"]);
     * 
     */
    public function repeaterFieldOptions($repeater_name, $field_name, $options) {
        $this->fieldOptions("repeater_$repeater_name", $field_name, $options);
    }
	
	
	/**
     *  Create Options Field
     *  @param string $inputfield   InputfieldRadios / InputfieldAsmSelect / InputfieldCheckboxes / InputfieldSelect / InputfieldSelectMultiple
     *  @param string $name         Field name
     *  @param string $label        field label
     *  @param array $options_arr   eg: ["one", "two", "three"]
     *  @param string $tags         Field tag
     * 
     */
    public function createOptionsField($inputfield, $name, $label, $options_arr, $tags = "") {

        $i = 1;
        $options = "";
        foreach($options_arr as $opt) {
            $options .= $i++ . "={$opt}\n";
        }
        $f = new Field();
        $f->type = $this->modules->get("FieldtypeOptions");
        $f->inputfieldClass = $inputfield; // input type: radio, select etc...
        $f->name = $name;
        $f->label = $label;
        $f->tags = $tags;
        $f->save(); 
        // save before adding options
        // $options = "1=Blue\n2=Green\n3=Brown\n";
        $set_options = new \ProcessWire\SelectableOptionManager();
        $set_options->setOptionsString($f, $options, false);
        $f->save();
        // Radio options 1 column
        if($inputfield == "InputfieldRadios") {
            $f->required = "1";
            $f->defaultValue = "1";
            $f->optionColumns = "1";
            $f->save();
        }
		
    }


    /**
     *  addTemplateField()
     *  Add field to a specific position in template
     *  
     *  @param string &tmpl             template name
     *  @param string $new_field        name of the field we want to add
     *  @param string $mark_field       field name, we will add new field before or after this field
     *  @param string $before_after     before / after 
     * 
     */
    public function addTemplateField($tmpl, $new_field, $mark_field, $before_after = "after") {

        // get template
        $template = $this->templates->get("$tmpl");

        // get existing field from the template, 
        // we will insert new field before or after this field
        $existingField = $template->fieldgroup->fields->get("$mark_field");

        // new field that we want to insert
        $newField = $this->fields->get("$new_field");

        // insert new field before existing one
        if($before_after == "before") {
            $template->fieldgroup->insertBefore($newField, $existingField);
        } else {
            $template->fieldgroup->insertAfter($newField, $existingField);
        }

        $template->fieldgroup->save();

    }
	
	/**
     *  Remove field from template
     *  @param string $template - template name
     *  @param string $field - field name
     */
    public function removeTemplateField($template, $field) {
		$template = $this->templates->get($template);
		$field = $this->fields->get($field);
		$template->fieldgroup->remove($field);
		$template->fieldgroup->save();
	}
	
	
	/**
     *  Create Template Structure
     *  Page -> Subpage
     * 
     *  @param array $main  eg: ["name" => "my_template_name", "fields" => ["One", "Two", "Three"]]
     * 
     *  ["name"]        (string) Template name    
     *  ["fields"]      (array) Template fields eg: ["One", "Two", "Three"]
     *  ["icon"]        (string) Template icon eg: fa-icon
     *  ["parent"]      (integer) Parent page ID
     *  ["page_title"]  (string) Page title
     * 
     *  @param array $item  eg: ["name" => "my_template_name", "fields" => ["One", "Two", "Three"]];
     * 
     *  ["name"]        (string) Template name
     *  ["fields"]      (array) Template fields eg: ["One", "Two", "Three"]
     *  ["icon"]        (string) Template icon eg: fa-icon
     *  ["page_title"]  (string) Page title
     * 
     *  @param string $tag Template tag
     * 
     * 
     */
    public function createTemplateStructure($main, $item, $tag = "") {

        $main_name          = $main["name"] ? $main["name"] : "";
        $main_fields        = $main["fields"] ? $main["fields"] : "";
        $main_icon          = $main["icon"] ? $main["icon"] : "";
        $main_parent        = $main["parent"] ? $main["parent"] : "";
        $main_page_title    = $main["page_title"] ? $main["page_title"] : "";

        $item_name          = $item["name"] ? $item["name"] : "";
        $item_fields        = $item["fields"] ? $item["fields"] : "";
        $item_icon          = $item["icon"] ? $item["icon"] : "";
        $item_page_title    = $item["page_title"] ? $item["page_title"] : "";

        // Main Fieldgroup
        $main_fg = new Fieldgroup();
        $main_fg->name = $main_name;
        foreach($main_fields as $field) {
            $main_fg->add($this->fields->get($field)); 
        }
        $main_fg->save();

        // Main Template 
        $main_t = new Template();
        $main_t->name = $main_name;
        $main_t->fieldgroup = $main_fg;
        if(!empty($main_icon)) {
            $main_t->pageLabelField = "$main_icon";
        }
        $main_t->save();
        

        // Item Fieldgroup
        $item_fg = new Fieldgroup();
        $item_fg->name = $item_name;
        foreach($item_fields as $field) {
            $item_fg->add($this->fields->get($field)); 
        }
        $item_fg->save();

        // Item Template 
        $item_t = new Template();
        $item_t->name = $item_name;
        $item_t->fieldgroup = $item_fg; // add the field group
        $item_t->save();

        // Item Template options
        $item_t = wire('templates')->get($item_name);
        $item_t->noChildren = "1";
        $item_t->tags = $tag;
        $item_t->pageLabelField = $item_icon;
        $item_t->parentTemplates = array(wire('templates')->get($main_name)); // allowedForParents
        $item_t->save();


        // Main Template Options
        $main_t = wire('templates')->get("main-menu");
        $main_t->noParents = '-1';
        $main_t->tags = $tag;
        $main_t->pageLabelIcon = $main_icon;
        $main_t->childTemplates = array(wire('templates')->get($item_name)); // allowedForChildren
        $main_t->save();

        // Create Example Pages
        if(!empty($main_parent)) {

            $main_p = new Page();
            $main_p->template = $main_name;
            $main_p->parent = $main_parent;
            $main_p->title = $main_page_title;
            $main_p->save();

            $item_p = new Page();
            $item_p->template = $item_name;
            $item_p->parent = $main_p;
            $item_p->title = $item_page_title;
            $item_p->save();

        }

    }

    /**
     *  Delete Template Structure
     *  @param array $temp_array Array of template names
     *  @param array $fields_arr Array of field names
     * 
     */
    public function deleteTemplateStructure($temp_array, $fields_arr) {

        // 1. Delete Pages
        foreach($temp_array as $tmp) {
            $p_arr = $this->pages->find("template=$tmp, include=all");
            if($p_arr->count) {
                foreach($p_arr as $p) {
                    $p->delete(true);
                }
            }
        }

        // 2. Delete Templates
        foreach($temp_array as $tmp) {
            $t = $this->templates->get($tmp);
            if($t && !empty($t)) {
                $this->templates->delete($t);
            }
        }

        // 3. Delete Fieldgroup
        foreach($temp_array as $tmp) {
            $fg = $this->fieldgroups->get($tmp);
            if($fg && !empty($fg)) {
                $this->fieldgroups->delete($fg);
            }
        }

        // 4. Delete Fields
        foreach($fields_arr as $field) {
            $f = $this->fields->get($field);
            if($f && !empty($f)) {
                $this->fields->delete($f);
            }
        }

    }
	
	
	/* =================================================================
        Images & Files
    ==================================================================== */
    /**
     *  Add images/files to the page
     *  @param object|Page $page
     *  @param string $field_name - images/files field name
     *  @param array $allowed_files - this is required for WireUpload
     *
     */
    public function fileToPageUpload($page, $field_name, $allowed_files = ['jpg', 'jpeg', 'gif', 'png']) {

        $dest = $this->config->paths->files . $p->id . "/";

        if($page->{$field_name}) {
            // upload image
            $upload = new WireUpload("dropzoneFiles");
            $upload->setOverwrite(false);
            $upload->setDestinationPath($dest);
            $upload->setValidExtensions($allowed_files);
            $upload->execute();
            $page->of(false);
            foreach($upload->execute() as $filename) $page->{$field_name}->add($dest . $filename);
            $page->save();
        } else {

            throw new WireException("File/image field name doesn't exists");

        }

    }

    /**
     *  Delete image/file from a page
     *  @param object|Page $page
     *  @param string $field_name - images/files field name
     *  @param string $file_name - image/file basename
     *
     */
    public function deletePageFile($page, $field_name, $file_name) {

        if(empty($page) && !empty($page->{$field_name})) {

            $this_file = $page->{$field_name}->get("name=$file_name");

            if($this_file && $this_file != "") {
                $page->of(false);
                if($page->{$field_name}->count == 1) $page->{$field_name}->removeAll();
                if(!empty($this_file && $this_file != "")) $page->{$field_name}->delete($this_file);
                $page->save();
            }

        }

    }
		
	/* =================================================================
    Hanna Code
  ==================================================================== */
  // Check if hanna code already exists, by name
  public function isHannaCodeExists($name) {
    $query = $this->database->prepare("SELECT COUNT(*) FROM hanna_code WHERE name=:name");
		$query->bindValue(':name', $name);
		$query->execute();
		list($n) = $query->fetch(PDO::FETCH_NUM);
    return ($n > 0) ? true : false;
  }

  /**
   *  Import hanna code
   *  @param string $code - hanna import/export code
   */
  public function importHannaCode($code) {

    if(!$modules->isInstalled("TextformatterHannaCode")) {
      $this->error("HannaCode is not installed");
      return false;
    }

    $data = $code;
		if(!preg_match('{!HannaCode:([^:]+):(.*?)/!HannaCode}s', $data, $matches)) throw new WireException("Unrecognized Hanna Code format");
    $name = $matches[1];
		$data = $matches[2];
		$data = base64_decode($data);
    if($data === false) throw new WireException("Failed to base64 decode import data");
		$data = json_decode($data, true);
		if($data === false) throw new WireException("Failed to json decode import data");
		if(empty($data['name']) || empty($data['code'])) throw new WireException("Import data does not contain all required fields");

    // db
    $query = $this->database->prepare("SELECT COUNT(*) FROM hanna_code WHERE name=:name");
		$query->bindValue(':name', $name);
		$query->execute();
		list($n) = $query->fetch(PDO::FETCH_NUM);
		if($n > 0) {
			$this->error($this->_('Hanna Code with that name already exists'));
			$this->session->redirect('../');
			return '';
		}

    $data['type'] = (int) $data['type'];
	  $sql = 	"INSERT INTO hanna_code SET `name`=:name, `type`=:type, `code`=:code, `modified`=:modified";
		$query = $this->database->prepare($sql);
		$query->bindValue(':name', $name);
		$query->bindValue(':type', $data['type']);
		$query->bindValue(':code', $data['code']);
		$query->bindValue(':modified', time());

    if($query->execute()) {
			$this->message("Imported Hanna Code: $name");
			$id = (int) $this->database->lastInsertId();
		} else {
			throw new WireException("Error importing (database insert)");
		}
		return '';
  }


}
