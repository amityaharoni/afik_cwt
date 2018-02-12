<?
	class infoCat_factory extends base_factory{
//////////////////////////////////////////////////////////////////////////
//	BASIC FUNCTIONALITY		
		const db_table_name = "info_cats";
		const instance_class_name = "infoCat";		
		const cont_class_name = "infoCat_controller";
			
		protected static $factory = null;
		protected static $instances;		
		
		protected function __construct() {			
			parent::__construct(self::instance_class_name, self::db_table_name, self::cont_class_name);
		}			
/////////////////////////////////////////////////////////////////////////		
//	CUSTOM FUNCTIONS
		protected function extend_instance(&$instance){
			
		}
		
		protected function new_object_created(&$new_object){
			
		}
		
		public function dt_custom_search($columns){
			$ret = array();
			$sql = "SELECT `id` FROM `".self::db_table_name."` WHERE 1 ";
			
			if (!empty($_GET['custom_search'])){
				foreach ($_GET['custom_search'] as $field){
					switch ($field['name']){
						case "name":
							if (!emptY($field['value']))
								$sql .= " AND `name` = '" .clear_string($field['value']) . "' ";
						break;
						case "parent_cat_id":
							if (!emptY($field['value']))
								$sql .= " AND `parent_cat_id` = '" .clear_string($field['value']) . "' ";
						break;
					}
				}
			}
			
			$result = $this->con->fetchData($sql);			
			if (!empty($result['rows'])){
				foreach ($result['rows'] as $row){
					$ret[] = $row['id'];
				}
			}
			
			return $ret;
		}
/////////////////////////////////////////////////////////////////////////	

		public function get_parent_cat_name($cat, $get_full_path = false){
			$ret = "";
			
			if (!empty($cat->parent_cat_id)){
				$parent_cat = $this->data->infoCat->get_by_id($cat->parent_cat_id);			
				if (!emptY($parent_cat))
					$ret = $parent_cat->name;
					
				if ($get_full_path){
					$long_path = $this->get_parent_cat_name($parent_cat, $get_full_path);
					if (!empty($long_path))
						$ret = $long_path . " -> " . $ret;
				}
			}
				
			return $ret;
		}	

		public function get_top_menu(){
			return $this->get_by_column("show_on_top_menu", 1);
		}	

		public function get_side_menu(){
			return $this->get_by_column("show_on_side_menu", 1);
		}		
	}
	
	

	
//////////////////////////////////////////////////////////////////////////////////////	
//////////////////////////////////////////////////////////////////////////////////////	
//////////////////////////////////////////////////////////////////////////////////////
	
	
	class infoCat extends base_data_object{
		
		protected function __construct($data, $fields_info, $db_table_name) {
			parent::__construct($data, $fields_info, $db_table_name);				
		}
		
		protected function extend_deletion(){
			
		}
		
		public static function sort_by_example($a, $b){
			return $a->something > $b->something ? 1 : -1;
		}
		
		public function get_subcats(){
			$res = array();
			
			$sql = "SELECT * FROM `".$this->db_table_name."` WHERE `parent_cat_id` = '".clear_string($this->id)."'";
							
			$result = db_con::get_con()->fetchData($sql);
			
			if (!empty($result['rows'])){					
				foreach ($result['rows'] as $row){
					$obj = $this->data->infoCat->get_by_id($row['id']);
					if (!emptY($obj)){
						$res[] = $obj;
					}
				}
			}
			
			return $res;
		}	

		public function get_pages(){
			return $this->data->infoPage->get_by_column("cat_id", $this->id);
		}			
	}

	
//////////////////////////////////////////////////////////////////////////////////////	
//////////////////////////////////////////////////////////////////////////////////////	
//////////////////////////////////////////////////////////////////////////////////////
	
		
	class infoCat_view extends view_abstract{
				
		public function __construct($file_path) {
			global $is_admin;
			
			$this->url_page_name = ($is_admin?"admin_":"") . "info_cats";
			$this->object_class_name = "infoCat";
			
			parent::__construct($file_path);				
		}
		
		public function post_render_html(&$html){
			
		}
	}

	
//////////////////////////////////////////////////////////////////////////////////////	
//////////////////////////////////////////////////////////////////////////////////////	
//////////////////////////////////////////////////////////////////////////////////////
	
		
	class infoCat_controller extends controller_abstract{
				
		public function __construct() {
			global $is_admin;
			
			$this->url_page_name = ($is_admin?"admin_":"") . "info_cats";
			$this->object_class_name = "infoCat";
			$this->view_class_name = "infoCat_view";
			
			parent::__construct();	
			
			// db name - dt name - object path as array - formatter function
			$this->dt_columns = array(
				array( 'db' => 'id', 			'dt' => 'id',			'path' => "id"),
				array( 'db' => 'name_trans_id', 'dt' => 'name',			'path' => "name"),
				array( 'db' => 'parent_cat_id', 'dt' => 'parent_cat_id','path' => "parent_cat_id",
						'formatter' => 
							function( $value, $object ) {
								global $data;
								$cat = $data->infoCat->get_by_id($value);
								if (!empty($cat))
									return $cat->name;
								return $value;									
							}
						)
			);
		}
		
		public function extend_request_processing(){
			
		}
		
		public function admin_insert_custom_data($new_object){
			
		}
		
		public function admin_edit_custom_data(){
			
		}
	}
?>