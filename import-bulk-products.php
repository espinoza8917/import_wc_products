<?php
/**
* Plugin Name: Import Bulk Products
* Description: Custom upload products (simple and variable) from csv file to woocommerce.
* Version: 1.0
* Author: Milton Espinoza
* License: GPL12
*/
//defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
define('ALLOW_UNFILTERED_UPLOADS', true);



/*adding the settings page for the theme*/
function theme_settings_pageImportCSV()
{
    ?>
    <div class="wrap">
	    <h1>Import Bulk Products</h1>
	        <?php
	            settings_fields("section");
	            do_settings_sections("theme-options");
	            //submit_button();
	        ?>
	    </div>
	<?php
}


function display_upload_element_icsv()
{

  ?>
         <h2>Insert new products from CSV file</h2>
         <!-- Form to handle the upload - The enctype value here is very important -->
         <form  method="post" enctype="multipart/form-data">
                 <input type='file' id='upload_csv_new_products' name='upload_csv_new_products'></input>
                 <?php submit_button('Upload', 'primary', 'new'); ?>
         </form>

  <?php
  upload_csv_new_products();
}

function upload_csv_new_products(){
  // First check if the file appears on the _FILES array
  if (isset($_POST['new'])) {
    if(isset($_FILES['upload_csv_new_products'])){
            $pdf = $_FILES['upload_csv_new_products'];

            // Use the wordpress function to upload
            // test_upload_pdf corresponds to the position in the $_FILES array
            // 0 means the content is not associated with any other posts
            $uploaded=media_handle_upload('upload_csv_new_products', 0);
            // Error checking using WP functions
            if(is_wp_error($uploaded)){
                    echo "Error uploading file: " . $uploaded->get_error_message();
            }else{
                    echo "File upload successful!!!".$uploaded;
                    import_csv_new_products($uploaded);
                  }
    }
  }

}

function import_csv_new_products($id){
  echo "<br>obteniendo archivo";
  $file = get_post($id);
  $fila = 0;
 if (($gestor = fopen(wp_get_attachment_url($id), "r")) !== FALSE) {
   echo "<br>abriendo archivo";
     $count = 0;
     $fila = 0;
     $avail_attributes = array();
     $regular_price =array();
     $price=array();

     while (($datos = fgetcsv($gestor)) !== FALSE) {
       $fila+=1;

       if ($fila<10) {
         $numero = count($datos);
        for ($i=0; $i < $numero ; $i++) {
          echo "<br>[$i] => ".$datos[$i];
        }
       }
       if($fila>1){//la fila 1 tiene los nombres de columnas
         $variationCount=$datos[0];
         $variationCount = trim(str_replace ( '"' , '' , $variationCount ));
         if ($variationCount === "1") {//simple product
           echo "<br>here we have a simple product";
           $prices = quitarComilla(filter_var($datos[89], FILTER_SANITIZE_STRING));
           $title = quitarComilla(filter_var($datos[3], FILTER_SANITIZE_STRING));
            $summary = quitarComilla(filter_var($datos[4], FILTER_SANITIZE_STRING));
            $description = quitarComilla(filter_var($datos[5], FILTER_SANITIZE_STRING));
            $sku = quitarComilla(filter_var($datos[21], FILTER_SANITIZE_STRING));
            $category_slug = filter_var($datos[106], FILTER_SANITIZE_STRING);
            $category = get_term_by('slug', $category_slug, 'product_cat', 'ARRAY_A');
            echo "<br>".$category['name'];
            $idCategory = $category['term_id'];
             $post = array(
             'post_title'   => $title,
             'post_content' => $description,
             'post_status'  => "publish",
             'post_name'    => $sku, //name/slug
             'post_type'    => "product"
             );
              $new_post_id = wp_insert_post( $post );
             echo "<br>product inserted with the id: ".$new_post_id;
              //make product type be variable:
             wp_set_object_terms ($new_post_id,'simple','product_type');
             //we don't have cat yet //add category to product:
             wp_set_object_terms( $new_post_id, $idCategory, 'product_cat');
             //set product values:
             update_post_meta( $new_post_id, '_stock_status', 'instock');
             //update_post_meta( $new_post_id, '_weight', "0.16" );
             update_post_meta( $new_post_id, '_sku', $sku);
             update_post_meta( $new_post_id, '_stock', "1000" );
             update_post_meta( $new_post_id, '_visibility', 'visible' );

              update_post_meta($new_post_id, '_price', $prices);
              update_post_meta($new_post_id, '_regular_price', $prices);

              update_post_meta($new_post_id, '_virtual', 'no');
              update_post_meta($new_post_id, '_downloadable', 'no');

                //start adding the product image
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $thumb_url = 'http://nwartglass.com/images/Product/large/'.$datos[62];//check this
            $thumb_url = str_replace(" ", "%20", $thumb_url);
            echo "<br>imagen urls : ".$thumb_url."<br>";
            $thumb_url = filter_var($thumb_url, FILTER_SANITIZE_URL);
            var_dump($thumb_url);

            // Download file to temp location
            $tmp = download_url( trim($thumb_url) );
            echo "<br>resultado de descarga de imagen<br>";
            var_dump($tmp);
            echo "<br><br><br>";
            // Set variables for storage
            // fix file name for query strings
            preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
            $file_array['name'] = basename($matches[0]);
            $file_array['tmp_name'] = $tmp;

            //use media_handle_sideload to upload img:
            $thumbid = media_handle_sideload( $file_array, $new_post_id, 'gallery desc' );

            set_post_thumbnail($new_post_id, $thumbid);

         }else{//variation product
           //getting the available attributes
           $count+=1;
           $avail_attributes[] = filter_var($datos[81], FILTER_SANITIZE_STRING);
           $price[] = filter_var($datos[89], FILTER_SANITIZE_STRING);
           $regular_price[] = filter_var($datos[89], FILTER_SANITIZE_STRING);
           echo "<br>enter in variation". ((int)$count)." = ".((int)($variationCount));

           if($count == $variationCount){//en este punto hay que guardar el variable product
             global $wpdb;
            //$cats = array(25);
            $title = filter_var($datos[3], FILTER_SANITIZE_STRING);
            $summary = filter_var($datos[4], FILTER_SANITIZE_STRING);
            $description = filter_var($datos[5], FILTER_SANITIZE_STRING);
            $sku = filter_var($datos[21], FILTER_SANITIZE_STRING);
            $category_slug = filter_var($datos[106], FILTER_SANITIZE_STRING);

            $category = get_term_by('slug', $category_slug, 'product_cat', 'ARRAY_A');
            echo "<br><br><br>".$category['name'];
            $idCategory = $category['term_id'];
            echo "<br> Category id => ".$idCategory;
            $insertLog = "insert_product_logs.txt";//name the log file in wp-admin folder
            $post = array(
             'post_title'   => $title,
             'post_content' => $description,
             'post_status'  => "publish",
             'post_name'    => $sku, //name/slug
             'post_type'    => "product"
             );

            //Create product/post:
            $new_post_id = wp_insert_post( $post );
            echo "<br>product inserted with the id: ".$new_post_id;
            echo "<br>precios a insertar: <br>";
             var_dump($price);
             var_dump($regular_price);
             $count=0;
             //make product type be variable:
             wp_set_object_terms ($new_post_id,'variable','product_type');
             //we don't have cat yet //add category to product:
             wp_set_object_terms( $new_post_id, $idCategory, 'product_cat');
             //insert the avail variations
             echo "<br>";
             var_dump($avail_attributes);

             //set product values:
             update_post_meta( $new_post_id, '_stock_status', 'instock');
             update_post_meta( $new_post_id, '_sku', $sku);
             update_post_meta( $new_post_id, '_stock', "1000" );
             update_post_meta( $new_post_id, '_visibility', 'visible' );

             //###################### Add Variation post types for sizes #############################
             wp_set_object_terms($new_post_id, implode('|', $avail_attributes), 'options');
              $product_attributes = array();
              $product_attributes['options'] = array(
                'name' => 'Options',
                'value' => implode('|', $avail_attributes),
                'position' => 0,
                'is_visible' => 0,
                'is_variation' => 1,
                'is_taxonomy' => 0
              );
              update_post_meta($new_post_id, '_product_attributes', $product_attributes);
              $countPrice = 0;
              foreach ($avail_attributes as $option) {
                $optionClean = preg_replace("/[^0-9a-zA-Z_-] +/", "", $option);
                $post_name = 'pr-' . $new_post_id . '-opt-' .  $optionClean ;
                echo "<br>inserting post_name ".$post_name;
                $my_post = array(
                  'post_title' => 'Opt ' . $option . ' for #' . $new_post_id,
                  'post_name' => $post_name,
                  'post_status' => 'publish',
                  'post_parent' => $new_post_id,
                  'post_type' => 'product_variation',
                  'guid' => home_url() . '/?product_variation=' . $post_name
                );
                $attID = $wpdb->get_var("SELECT count(post_title) FROM $wpdb->posts WHERE post_name like '$post_name'");

                if ($attID < 1) {
                  $attID = wp_insert_post($my_post);
                  echo "<br>variation not exist, we will insert it now ";
                }
                echo "<br>variation inserted with the ID: ".$attID;
                update_post_meta($attID, 'attribute_options', $option);
                update_post_meta($attID, '_price', $price[$countPrice]);
                update_post_meta($attID, '_regular_price', $regular_price[$countPrice]);
                update_post_meta($attID, '_sku', $post_name);
                update_post_meta($attID, '_virtual', 'no');
                update_post_meta($attID, '_downloadable', 'no');
                update_post_meta($attID, '_manage_stock', 'no');
                update_post_meta($attID, '_stock_status', 'instock');
                $countPrice+=1;
              }
              $countPrice = 0;
            //insert the variations post_types for avail_attributes:
            //we reset the available avail_attributes for later
            unset($avail_attributes);
            unset($price);
            unset($regular_price);
            $avail_attributes = array();
            $price = array();//check this
            $regular_price =  array();//check this
            //############################ Done adding variation posts ############################

            //start adding the product image
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $thumb_url = 'http://nwartglass.com/images/Product/large/'.$datos[62];//check this
            $thumb_url = str_replace(" ", "%20", $thumb_url);
            echo "<br>imagen urls : ".$thumb_url."<br>";
            $thumb_url = filter_var($thumb_url, FILTER_SANITIZE_URL);
            var_dump($thumb_url);

            // Download file to temp location

            $tmp = download_url( trim($thumb_url) );
            echo "<br>resultado de descarga de imagen<br>";
            var_dump($tmp);
            echo "<br><br><br>";
            // Set variables for storage
            // fix file name for query strings
            preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
            $file_array['name'] = basename($matches[0]);
            $file_array['tmp_name'] = $tmp;

            //use media_handle_sideload to upload img:
            $thumbid = media_handle_sideload( $file_array, $new_post_id, 'gallery desc' );

            set_post_thumbnail($new_post_id, $thumbid);



           }else{
           }
         }

       }

       }
     }
     fclose($gestor);
     wp_delete_attachment( $id, true );
     define('ALLOW_UNFILTERED_UPLOADS', false);
}

function quitarComilla($texto){
  $texto = str_replace('"','',$texto);
  return $texto;
}
function display_theme_panel_fields()
{
  add_settings_section("section", "Upload CSV products", null, "theme-options");
  add_settings_field("upload", "Upload", "display_upload_element_icsv", "theme-options", "section");

}

add_action("admin_init", "display_theme_panel_fields");
/*adding the menu in the backend*/
function add_theme_menu_item()
{
	add_menu_page("Import Bulk Products", "Import Bulk Products", "manage_options", "import_bulk_products", "theme_settings_pageImportCSV", 'dashicons-id', 10);
}

add_action("admin_menu", "add_theme_menu_item");
