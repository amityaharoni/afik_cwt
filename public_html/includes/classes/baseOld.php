<?
	abstract class base_factory{
		protected $class_name;
		protected $cont_class_name;
		protected $db_table_name;
		protected $db_table_info;
		protected $con;
		protected $all_objects_loaded = false;
		
		public $controller = null;
				
		// protected static $instances;
		
		protected function __construct($class_name, $db_table_name, $cont_class_name = null) {
			global $data;
			$this->data = $data;
			$this->class_name 		= $class_name;
			$this->cont_class_name 	= $cont_class_name;
			$this->db_table_name 	= $db_table_name;
			$this->con 				= db_con::get_con();	
			
			// get table info
			$q_table_info = "DESCRIBE `{$db_table_name}`";
			$result = $this->con->fetchData($q_table_info);
				
			if (!empty($result['rows'])){					
				$this->db_table_info = array();
				
				foreach ($result['rows'] as $row){
					$this->db_table_info[$row['Field']] = $row['Type'];
				}
			}
			/////////////////
			
			if (!empty($this->cont_class_name)){
				$this->controller = new $this->cont_class_name();
			}
		}		
		
		public function get_db_table_name(){
			return $this->db_table_name;
		}
		
		public function get_db_table_info(){
			return $this->db_table_info;
		}
		
		public static function get_factory(){
			if (static::$factory == null){
				static::$factory = new static();
			}
			return static::$factory;
		}
		
		protected abstract function extend_instance(&$instance); // used to extend the object, for example: add options to product, see in product.php		
		
		public abstract function dt_custom_search($columns); // used to implement custom search in the admin page, return array of matcing ID's
		
		protected abstract function new_object_created(&$new_object); // used to perform additional actions after new object was created
		
		public function add_new($data){ // expect data as key value pairs, where key is the name of the db value
			
			if (!empty($data)){
				if (!emptY($this->db_table_info)){			
					$insert_sql = "INSERT INTO `{$this->db_table_name}` ";
					$fields = "(";
					$values = "(";
					foreach ($this->db_table_info as $field => $type){
						
						if (!empty($data[$field])){
							$trans_key = "";
							if (substr($field, -9) === "_trans_id"){ // check if its a translatable field															
								$trans_key = $this->data->translator->get_max_id() + 1;
								$translator = $this->data->translator->add_new(
									array(
										"key" => $trans_key, 
										"value" => $data[$field], 
										"lang" => language::$current_lang
									));
							}
							
							$value = "";
							if (!empty($trans_key))
								$value = $trans_key;
							else
								$value = clear_string($data[$field]);
								
							$fields .= "`".$field."`,";
							$values .= "'".$value."',";
						}
					}
					if ($fields != "("){
						$fields = rtrim($fields, ',') . ")";
						$values = rtrim($values, ',') . ")";
						
						$insert_sql .= $fields . " VALUES " . $values;
						
						if ($this->con->query($insert_sql, true)){
							$new_id = $this->con->insert_id;
							$new_object = $this->get_by_id($new_id);
							$this->new_object_created($new_object);
							return $new_object;
						}
					}
				}
			}
			return null;
		}
		
		protected function create_instance($data, $fields_info = null){
			
			if (!empty($data)){
				$class_name = $this->class_name;
				$instance = $class_name::get_instance($data, $fields_info, $this->db_table_name);
				$this->extend_instance($instance);
				return $instance;
			}
			return null;
		}
		
		public function search($filter, $order, $limit, &$found_rows = null){
			$res = array();
			$sql = "";
			$fields = " dt_table.* ";
			// $from = " " . $this->db_table_name . " as dt_table ";
			$from = " ";
			$trans_from = "";
			$where = "";
			$main_table_where = "";
			$order_by = "";			
			$joined_translations = array();
							
			// build a where string
			if (!empty($filter) && !empty($filter["groups"])){
				foreach ($filter["groups"] as $filter_group){
					$group_where = "";
					$main_table_group_where = "";
					
					foreach($filter_group["columns"] as $col){
						$col_new_name = "";
						if (substr($col["col_name"], -9) === "_trans_id"){ // check if its a translatable field
							$col_new_name = str_replace("_trans_id", "", $col["col_name"]);
							
							$trans_from	.= " LEFT JOIN (
											SELECT 
												* 
											FROM 
												`" . translator::db_table_name ."` as tt_$col_new_name
											WHERE
												(tt_$col_new_name.lang = 1 OR tt_$col_new_name.lang is null)
												AND
												tt_$col_new_name.`value` " . $col["condition"] . " '" . clear_string($col["value"]) . "'
											) as t_$col_new_name 
										on dt_table.".$col["col_name"]." = t_$col_new_name.key ";
							$fields .= ", t_$col_new_name.value as '$col_new_name' "; 
							
							$joined_translations[] = "t_$col_new_name";
						}
							
						
						if (!empty($col_new_name)){ // there is a translation for this column							
							if (!empty($col["relation"]) && !empty($group_where))
								$group_where .= " " . $col["relation"] . " ";
							$group_where .= " t_$col_new_name.id is not null ";
						}
						else{
							if (!empty($col["relation"]) && !empty($main_table_group_where))
								$main_table_group_where .= " " . $col["relation"] . " ";

							if ($col["value"][0] == "(" && trim($col["value"][strlen($col["value"])-1]) == ")") // values like " IN (1,2,3,4) " dont need quote
								$main_table_group_where .= " dtt_table.`" . $col["col_name"] . "` " . $col["condition"] . " " . clear_string($col["value"]) . " ";
							else
								$main_table_group_where .= " dtt_table.`" . $col["col_name"] . "` " . $col["condition"] . " '" . clear_string($col["value"]) . "' ";
						}
					}
					
					if (!empty($main_table_group_where)){
						$main_table_group_where = " ( " . $main_table_group_where . " ) ";
						
						if (!empty($filter_group["relation"]) && !empty($main_table_where))
							$main_table_where .= " " . $filter_group["relation"] . " ";
						
						$main_table_where .= $main_table_group_where;
					}
					
					if (!empty($group_where)){
						$group_where = " ( " . $group_where . " ) ";
					
						if (!empty($filter_group["relation"]) && !empty($where))
							$where .= " " . $filter_group["relation"] . " ";
						
						$where .= $group_where;
					}
					
					if (!empty($this->contant_filters)){
						foreach ($this->contant_filters as $filters_field_name => $filters_field_value){
							$where .= " AND `$filters_field_name` = " . $filters_field_value;
						}
					}
				}
			}
			
			if (!empty($main_table_where)){
				$from .= " (SELECT * FROM `".$this->db_table_name . "` as  dtt_table WHERE " . $main_table_where ." ) as dt_table ";
			}
			else{
				$from .= " `".$this->db_table_name . "` as dt_table ";
			}
			
			if (!empty($trans_from))
				$from .= $trans_from;
			
			if (!empty($order)){
				$trans_where = "";
				
				foreach ($order as $col){
					if (!empty($order_by))
						$order_by .= ", ";
					if (substr($col["col_name"], -9) === "_trans_id"){ // check if its a translatable field
						$col_new_name = str_replace("_trans_id", "", $col["col_name"]);						
						$order_by .= $col_new_name . " " . $col["dir"];
						
						if (!in_array("t_$col_new_name", $joined_translations)){
							// add translation
							$from .= " LEFT JOIN `" . translator::db_table_name ."` as t_$col_new_name on dt_table.".$col["col_name"]." = t_$col_new_name.key ";
							$fields .= ", t_$col_new_name.value as '$col_new_name' "; 
							
							if (!empty($trans_where))
								$trans_where .= " AND ";
							$trans_where .= " (t_$col_new_name.lang = 1 OR t_$col_new_name.lang is null) ";
							
							$joined_translations[] = "t_$col_new_name";
						}
					}
					else
						$order_by .= $col["col_name"] . " " . $col["dir"];
				}
				
				if (!empty($trans_where)){
					if (!empty($where))
						$where .= " AND ";
					$where .= $trans_where;
				}
			}
			
			if (!empty($limit))
				$sql = "SELECT SQL_CALC_FOUND_ROWS ";
			else
				$sql = "SELECT ";
			
			$sql .= $fields . " FROM " . $from;
			
			if (!empty($where))
				$sql .= " WHERE " . $where;
			if (!empty($order_by))
				$sql .= " ORDER BY " . $order_by;
			if (!empty($limit))
				$sql .= " LIMIT " . $limit;

			error_log($sql);
			$result = $this->con->fetchData($sql);
			
			
			if (!empty($result['rows'])){
				$resFilterLength = $this->con->fetchData("SELECT FOUND_ROWS() as 'found_rows'");			
				$found_rows = $resFilterLength['rows'][0]['found_rows'];
				// error_log($recordsFiltered);
				
				foreach ($result['rows'] as $row){
					if (!empty(static::$instances['by_id'][$row['id']])){
						$res[] = static::$instances['by_id'][$row['id']];
						// error_log(get_class($this));
						// error_log(print_r(self::$instances['by_id'][$row['id']],true));
					}
					else{
						// create a new instance
						$instance = $this->create_instance($row, $result['fields_info']);
					
						if (!empty($instance)){
							static::$instances['by_id'][$row['id']] = $instance;
						}
						$res[] = $instance;
					}
				}
			}		
			// error_log(print_r($res,true));
			return $res;
		}
		
		public function get_by_column($column_name, $value, $is_unique_selector = false){
			$res = array();
			if (!empty($value)){
				// check if already loaded and return if exist
				if (!empty(static::$instances['by_'.$column_name][$value]))
					return static::$instances['by_'.$column_name][$value];		
					
				$sql = "SELECT * FROM `".$this->db_table_name."` WHERE `".$column_name."` = '".clear_string($value)."'";
				
				if (!empty($this->contant_filters)){
					foreach ($this->contant_filters as $filters_field_name => $filters_field_value){
						$sql .= " AND `$filters_field_name` = " . $filters_field_value;
					}
				}
				
				if (empty($sql)) return $res;
				
				$result = $this->con->fetchData($sql);
				
				if (!empty($result['rows'])){					
					foreach ($result['rows'] as $row){
						// check if already loaded and return if exist
						if (!emptY(static::$instances['by_id'][$row['id']])){
							$res[] = static::$instances['by_id'][$row['id']];
						}
						else{
							// create a new instance
							$instance = $this->create_instance($row, $result['fields_info']);
						
							if (!empty($instance)){
								static::$instances['by_id'][$row['id']] = $instance;
								if ($is_unique_selector){									
									if ($column_name != "id")
										static::$instances['by_'.$column_name][$value] = &static::$instances['by_id'][$row['id']]; // store instance by reference
									return $instance; // is_unique_selector = true : there is only one instanse
								}else
									static::$instances['by_'.$column_name][$value][] = &static::$instances['by_id'][$row['id']]; // store instance by reference
							}
							$res[] = $instance;
						}
					}
				}
			}
			return $res;
		}
		
		public function get_by_id($id){
			if (!empty($id)){
				return $this->get_by_column("id", (int)$id, true);
			}
			return null;
		}
		
		public function get_all(){
			$res = array();
			if ($this->all_objects_loaded && !empty(static::$instances['by_id']))
				return static::$instances['by_id'];
					
			$res = static::search("","","");
			
			if (!empty($res))
				$this->all_objects_loaded = true;
				
			return $res;
		}
		
		public function get_total_count(){
			$sql = "SELECT COUNT(`id`) as 'count' FROM   `{$this->db_table_name}` ";
			if (!empty($this->contant_filters)){
				foreach ($this->contant_filters as $filters_field_name => $filters_field_value){
					$sql .= " AND `$filters_field_name` = " . $filters_field_value;
				}
			}
			
			if ($res = db_con::get_con()->fetchData($sql))
				return $res['rows'][0]['count'];
			return 0;
		}
		
		public function get_max_id(){
			$sql = "SELECT MAX(`id`) as 'max_id' FROM   `{$this->db_table_name}` ";
			if (!empty($this->contant_filters)){
				foreach ($this->contant_filters as $filters_field_name => $filters_field_value){
					$sql .= " AND `$filters_field_name` = " . $filters_field_value;
				}
			}
			
			if ($res = db_con::get_con()->fetchData($sql))
				if (!empty($res['rows'][0]['max_id']))					
					return $res['rows'][0]['max_id'];
			
			return 1;
		}
	}
	
	abstract class base_data_object{
		public $fields_info;
		protected $data;
		protected $data_fields;
		protected $child_class_name;
		protected $translation_key;
		protected $db_table_name;
		
		protected function __construct($data_fields, $fields_info, $db_table_name) {
			global $data;
			$this->data = $data;
			
			$this->fields_info = $fields_info;
			$this->db_table_name = $db_table_name;
			
			if (!empty($data_fields)){
				$this->child_class_name = get_class($this);
				// $this->translation_key = $this->child_class_name . "-" . $data['id'];
				foreach ($data_fields as $key => $value){
					// if its a translatable field look for a translation in a db
					// if (substr($key, -9) === "_trans_id"){
						// $key = str_replace("_trans_id", "", $key);
						// $this->data_fields[$key] = translator::get_translator($value);
						
					// }else
						$this->data_fields[$key] = $value;
				}
			}
		}
		
		protected abstract function extend_deletion();
		
		public static function get_instance($data_fields, $fields_info, $db_table_name){
			return new static($data_fields, $fields_info, $db_table_name);
		}
		
		public function __isset($key){
			// if (isset($this->data_fields[$key]))
			if (array_key_exists ($key, $this->data_fields) || array_key_exists ($key."_trans_id", $this->data_fields))
				return true;
			return false;
		}
		
		public function __get($key){
			if (isset($this->data_fields[$key."_trans_id"])){
				$this->data_fields[$key] = translator::get_translator($this->data_fields[$key."_trans_id"]);	
				return $this->data_fields[$key];
				unset($this->data_fields[$key."_trans_id"]);
			}
			else if (isset($this->data_fields[$key]))
				return $this->data_fields[$key];
			return null;
		}
		
		public function __set($key, $value){			
			if (!empty($this->data_fields[$key]) && 
				is_object($this->data_fields[$key]) && 
				$this->data_fields[$key] instanceof translator_object){ // translator
				
				$this->data_fields[$key]->value = $value;
			}
			else if (isset($this->data_fields[$key."_trans_id"])){
				$this->data_fields[$key] = translator::get_translator($this->data_fields[$key."_trans_id"]);
				if (!empty($this->data_fields[$key]))
					$this->data_fields[$key]->value = $value;	
				unset($this->data_fields[$key."_trans_id"]);
			}
			else{
				$this->data_fields[$key] = $value;
			}
		}
		
		public function save(){
		
			if (!empty($this->data_fields)){
				$table_name = $this->db_table_name;
				$where = " `id` = '" . (int)$this->data_fields['id'] . "'";
				$fields = "";
				
				foreach ($this->data_fields as $data_field_name => $data_value){
					if ($data_field_name == 'id') continue;					
										
					if (is_object($data_value)){ // its a translator... or something
						if ($data_value instanceof base_data_object){ 
							$data_value->save();
						}						
					}
					else if (is_array($data_value)){// its an array of translators... or something
						foreach ($data_value as $subdata){
							if ($subdata instanceof base_data_object){ 
								$subdata->save();
							}
						}
					}
					else {
						foreach ($this->fields_info as $field_info){ // check if there is a field with this name in the db
							
							if (substr($field_info['name'], -9) === "_trans_id" &&
								str_replace("_trans_id", "", $field_info['name']) == $data_field_name &&
								(empty($data_value) || !is_object($data_value))){ // translator was not created on insert, create it now								
								
								$trans_key = $this->data->translator->get_max_id() + 1;
								$translator = $this->data->translator->add_new(
									array(
										"key" => $trans_key, 
										"value" => $data_value, 
										"lang" => language::$current_lang
									));
									
								if (!empty($fields))
									$fields .= " , ";	
									
								$fields .= " `".$field_info['name']."` = '".clear_string($trans_key)."' ";
							}							
							else if ($field_info['name'] == $data_field_name){
								if (!empty($fields))
									$fields .= " , ";	
								
								$fields .= " `".$data_field_name."` = '".clear_string($data_value)."' ";
								
								break;
							}
						}
					}
				}
				
				if (!empty($fields)){
					$sql = "UPDATE {$table_name} SET {$fields} WHERE {$where}";
					// error_log($sql);
					db_con::get_con()->query($sql, true);
				}
			}
		}
		
		public function delete(){
			global $defaults;
			
			if (!empty($this->data_fields)){				
				foreach ($this->data_fields as $data_field_name => $data_value){					
					if (is_object($data_value)){ // its a translator
						if ($data_value instanceof translator_object){ 
							$data_value->delete(); // don't keep old translations
						}
					}
					else if (is_array($data_value)){// its an array of translators
						foreach ($data_value as $subdata){
							if ($subdata instanceof translator_object){ 
								$subdata->delete(); // don't keep old translations
							}
						}
					}
					else if (!empty($data_value) && file_exists($defaults['upload_files_folder'].$data_value)){ // its a file - remove it
						unlink($defaults['upload_files_folder'].$data_value);
					}
					else if (!empty($data_value) && file_exists($defaults['upload_images_folder'].$data_value)){ // its an image - remove it
						unlink($defaults['upload_images_folder'].$data_value);
					}
					else if (!empty($data_value) && file_exists($defaults['upload_thumbs_folder'].$data_value)){ // its a thumb - remove it
						unlink($defaults['upload_thumbs_folder'].$data_value);
					}
				}
				
				$sql = "DELETE FROM {$this->db_table_name} WHERE `id` = '{$this->data_fields['id']}'";
				// error_log($sql);
				$this->extend_deletion();	
				unset($this);
				return db_con::get_con()->query($sql, true);
			}
			return false;
		}
	}
	
	abstract class base_enum {
		private static $constCacheArray = NULL;

		private static function getConstants() {
			if (self::$constCacheArray == NULL) {
				self::$constCacheArray = array();
			}
			$calledClass = get_called_class();
			if (!array_key_exists($calledClass, self::$constCacheArray)) {
				$reflect = new ReflectionClass($calledClass);
				self::$constCacheArray[$calledClass] = $reflect->getConstants();
			}
			return self::$constCacheArray[$calledClass];
		}

		public static function is_valid_name($name, $strict = false) {
			$constants = self::getConstants();

			if ($strict) {
				return array_key_exists($name, $constants);
			}

			$keys = array_map('strtolower', array_keys($constants));
			return in_array(strtolower($name), $keys);
		}

		public static function is_valid_value($value) {
			$values = array_values(self::getConstants());
			return in_array($value, $values, $strict = true);
		}
		
		public static function get_key_for_value($value) {
			$values = array_values(self::getConstants());
			$key = array_search($value, $values);
			if ($key)
				return $key;
			return "";
		}
		
		public static function get_dispaly_text($value){
			return self::get_key_for_value($value);
		}
	}
?>