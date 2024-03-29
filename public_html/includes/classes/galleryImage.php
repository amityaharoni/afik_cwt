<?
	class galleryImage_factory extends base_factory{
//////////////////////////////////////////////////////////////////////////
//	BASIC FUNCTIONALITY		
		const db_table_name = "gallery_images";
		const instance_class_name = "galleryImage";		
		const cont_class_name = "galleryImage_controller";
			
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
			
		}
/////////////////////////////////////////////////////////////////////////			
	}
	
	class galleryImage extends base_data_object{
		
		protected function __construct($data, $fields_info, $db_table_name) {
			parent::__construct($data, $fields_info, $db_table_name);				
		}
		
		protected function extend_deletion(){
		
		}
		
		public static function sort_by_example($a, $b){
			return $a->something > $b->something ? 1 : -1;
		}
	}
	
	class galleryImage_view extends view_abstract{
				
		public function __construct($file_path) {
			global $is_admin;
			
			$this->url_page_name = ($is_admin?"admin_":"") . "galleryImage";
			if (!empty($_GET['gal_id'])){
				$this->url_page_name .= "&gal_id=" . (int)$_GET['gal_id'];
			}
			
			
			$this->object_class_name = "galleryImage";
			
			parent::__construct($file_path);				
		}
		
		public function post_render_html(&$html){
			
		}
	}
	
	class galleryImage_controller extends controller_abstract{
				
		public function __construct() {
			global $is_admin;
			
			$this->url_page_name = ($is_admin?"admin_":"") . "galleryImage";
			$this->object_class_name = "galleryImage";
			$this->view_class_name = "galleryImage_view";
			
			parent::__construct();	
			
			// db name - dt name - object path as array - formatter function
			$this->dt_columns = array(
				array( 'db' => 'id', 			'dt' => 'id',		'path' => "id"),
				array( 'db' => 'image', 		'dt' => 'image',	'path' => "image"),
				array( 'db' => 'name_trans_id', 'dt' => 'name',		'path' => "name")
			);
			
			if (!empty($_GET['gal_id'])){
				$where = array();
				$where = array("columns" => array(array("col_name" => "gal_id", "condition" => "=", "value" => (int)$_GET['gal_id'])), "relation" => "AND");
				
				$this->dt_data_source_added_filter = $where;
			}
		}
		
		public function extend_request_processing(){
			
		}
		
		public function admin_insert_custom_data($new_object){
			
		}
		
		public function admin_edit_custom_data(){
			
		}
	}
?>