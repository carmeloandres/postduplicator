<?php

/*
Plugin Name: Duplicador de Posts
Plugin URI:        https://example.com/plugins/the-basics/
Description:    Plugin para hacer una copia de un post cualquiera
Version:     2021.10.21
Author:      Carmelo Andres Desco
Author URI:  https://carmeloandres.com
Text Domain: postdup
Domain Path: /Languages
License:     GPLv2 or later
*/
 
 if ( ! defined( 'ABSPATH' ) ) {die;} ; // to prevent direct access


 /* 
 * Informacón de duplicación en : https://raiolanetworks.es/blog/duplicar-pagina-wordpress/#duplicate_post_como_duplicar_contenido_de_cualquier_tipo_en_wordpress
 * /

 /*
* FUNCIÓN 1: Añadir el botón de clonar a páginas y entradas
*/
function insertar_boton_duplicar($acciones,$post) 
{
    //Si el usuario tiene permisos para editar entradas y páginas, entonces puede visualizar el botón de clonar
    if(current_user_can('edit_posts'))$acciones['clone']='<a href="'.wp_nonce_url('admin.php?action=duplicar_contenido&post='.$post->ID,basename(__FILE__),'clone_nonce').'" title="Duplicar este contenido" rel="permalink">Duplicar</a>';
    return $acciones;
}

/*
* Se añade la función a la lista de acciones de los post y páginas
*/
add_filter( 'post_row_actions', 'insertar_boton_duplicar', 10, 2 ); //Entradas
add_filter( 'page_row_actions', 'insertar_boton_duplicar', 10, 2 ); //Paginas

/*
* FUNCION 2: Crear el borrador del contenido
*/
function crear_borrador($id=0){
	//Comprobar que se recibe un id válido (distinto de 0). Si no es válido, se termina la función y no se realiza ninguna acción.
	if(!$id) return 0;
	//Como se ha comprobado que el id es valido se recuperan los datos del post con ese identificador y se comprueba si existe o no
	$post = get_post( $id );
	if (isset( $post ) && $post != null)
	{
	    //Se recopilan los datos que se necesitan para hacer la clonación: el usuario, el titulo, el tipo, el autor... todo lo referente al contenido a clonar.
        $datos = array(
            'post_title'     => $post->post_title,
            'post_name'      => $post->post_name,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_parent'    => $post->post_parent,
            'post_type'      => $post->post_type,
            'post_status'    => 'draft',
            'post_password'  => $post->post_password,
            'post_author'    => $post->post_author,
            'ping_status'    => $post->ping_status,
            'to_ping'        => $post->to_ping,
            'comment_status' => $post->comment_status,
            'menu_order'     => $post->menu_order
        );
        //wp_insert_post inserta el contenido del objeto que se construyó con anterioridad en la base de datos
        $id_borrador = wp_insert_post( $datos );
      
		//Si la inserción en la base de datos no ha sido correcta el identificador no es válido.
        if($id_borrador)
		{
			//Como la inserción se ha realizado correctamente. Se copian del original al borrador las taxonomías y el contenido meta.
			$taxonomies = get_object_taxonomies($post->post_type);
			foreach ($taxonomies as $taxonomy) {
				//Se recogen los datos
				$post_terms = wp_get_object_terms($post->ID,$taxonomy,array('fields'=>'slugs'));
				//Se asignan los datos
				wp_set_object_terms($id_borrador,$post_terms,$taxonomy,false);
			}
			
			$post_meta = get_post_meta($id);
			foreach($post_meta as $key=>$val) 
			{
				if($key=='_wp_old_slug'){continue;}
				$value=addslashes($val[0]);
				//Se inserta el contenido meta al borrador
				add_post_meta($id_borrador,$key,$value);
			}

			return array('id_borrador'=>$id_borrador,'post'=>$post);
		}
		else wp_die('Error al crear el borrador del contenido.');
        
	}else wp_die('Error al crear el duplicado. No se encuentra el contenido original');
    
}

/*
* FUNCTION 3: Finalmente se procesa el duplicado del borrador una vez que se pulsa en el botón
*/

function duplicar_contenido()
{
    global $wpdb;
    
	//Se comprueba si se ha pulsado en el botón de duplicar o clonar y si se recibe la información necesaria del contenido original
    if (!(isset( $_REQUEST['post'])  || ( isset($_REQUEST['action']) && $_REQUEST['action']== 'duplicar_contenido' ) ) ) wp_die('Error: No existe referencia al contenido que se quiere copiar');
    
    $id_post = absint( $_REQUEST['post'] ) ;
    //Se comprueba que el id que se recibe es válido
    if($id_post > 0 ){
        //Se crea el borrador
        $borrador = crear_borrador($id_post);
        //Si todo ha salido bien, al final del duplicado se redirige al usuario a la pagina de edición del borrador
		if($borrador) wp_redirect( admin_url( 'post.php?action=edit&post=' . $borrador['id_borrador'] ) );
        else wp_die('Error al crear el borrador del contenido duplicado. No se encuentra el contenido principal' . $id_post);
    }else wp_die('Error al duplicar. No se encuentra el contenido original' . $id_post);
}

/*
* Después de pulsar el botón de duplicar, se inicia la acción y se duplica el contenido.
*/
add_action( 'admin_action_duplicar_contenido', 'duplicar_contenido' );


/* 
* Creacion de custom fields con codigo
* extraido de: https://tutorialeswp.com/crear-custom-post-types-y-custom-fields-en-wordpress/
* Para crear campos personalizados en los post e identificar el Post padre (del que se traducen los hijos),
* y establecer el idioma para poder establecerlos al renderizar el post
*
* Usar la función "switch_to_locale()" para cambiar el idioma por programa  
*
*/

/*
**** Register meta box(es) ****
*/
function twp_register_meta_boxes() {
	add_meta_box( 'mi-meta-box-id', __( 'Mis Custom Fields', 'tutorialeswp' ), 'twp_mi_display_callback', 'post' );
}
add_action( 'add_meta_boxes', 'twp_register_meta_boxes' );


/*
**** Meta box display callback ****
*/
function twp_mi_display_callback( $post ) {
	
	$web1 = get_post_meta( $post->ID, 'web1', true );
	$web2 = get_post_meta( $post->ID, 'web2', true );
	
	// Usaremos este nonce field más adelante cuando guardemos en twp_save_meta_box()
	wp_nonce_field( 'mi_meta_box_nonce', 'meta_box_nonce' );
	
	
	echo '<p><label for="web1_label">Web de referencia 1</label> <input type="text" name="web1" id="web1" value="'. $web1 .'" /></p>';
	echo '<p><label for="web2_label">Web de referencia 2</label> <input type="text" name="web2" id="web2" value="'. $web2 .'" /></p>';
}


/*
**** Save meta box content ****
*/
function twp_save_meta_box( $post_id ) {
	// Comprobamos si es auto guardado
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	// Comprobamos el valor nonce creado en twp_mi_display_callback()
	if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'mi_meta_box_nonce' ) ) return;
	// Comprobamos si el usuario actual no puede editar el post
	if( !current_user_can( 'edit_post' ) ) return;
	
	
	// Guardamos...
	if( isset( $_POST['web1'] ) )
	update_post_meta( $post_id, 'web1', $_POST['web1'] );
	if( isset( $_POST['web2'] ) )
	update_post_meta( $post_id, 'web2', $_POST['web2'] );
}
add_action( 'save_post', 'twp_save_meta_box' );


/**
* Add a custom link to the end of a specific menu that uses the wp_nav_menu() function
* https://wpscholar.com/blog/append-items-to-wordpress-nav-menu/
* Para poder añadir una bandera y una lista deplegable al menu, para mostrar el idioma  
* y seleccionar otro idioma
*/

add_filter('wp_nav_menu_items', 'add_admin_link', 10, 2);
function add_admin_link($items, $args){
    if( $args->theme_location == 'footer_menu' ){
        $items .= '<li><a title="Admin" href="'. esc_url( admin_url() ) .'">' . __( 'Admin' ) . '</a></li>';
    }
    return $items;
}