<?php
/**
 * General hooks go here
 */
defined( 'ABSPATH' ) || exit;


require_once WEBP_ROCKET_PHP . '/classes/class-webp-rocket-image-editor-gd.php';

add_filter( 'mime_types', 'webp_rocket_upload_mimes' );
add_filter( 'upload_mimes', 'webp_rocket_upload_mimes' );

function webp_rocket_upload_mimes( $existing_mimes ) {
	$existing_mimes['webp'] = 'image/webp';
	return $existing_mimes;
}

add_filter( 'file_is_displayable_image', 'webp_rocket_make_displayable', 10, 2 );

function webp_rocket_make_displayable( $result, $path ) {

	if ( ! $result ) {
		$info = @getimagesize( $path );
		if ( empty( $info ) ) {
			$result = false;
		} elseif ( $info[2] != IMAGETYPE_WEBP ) {
			$result = false;
		} else {
			$result = true;
		}
	}

	return $result;
}

if ( class_exists( 'WP_CLI' ) ) {

	/**
	 * WebP Rocket plugin's command
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : Subcommand for the webp-rocket command
	 *
	 * ## EXAMPLES
	 *
	 *     wp webp-rocket generate
	 *
	 * @when after_wp_load
	 *
	 */
	function webp_rocket_cli( $args, $assoc_args ) {
		[$subcommand] = $args;
		switch ( $subcommand ) {
			case 'generate':
				$image_attachments = get_posts( [
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image/jpeg',
					'posts_per_page' => -1
				] );

				if ( $image_attachments ) {
					$successfully_generated = 0;
					$all_images_count = count( $image_attachments );
					$progress = \WP_CLI\Utils\make_progress_bar( 'webp generation', $all_images_count );
					/* @var $product WP_Post  */
					foreach( $image_attachments as $image_attachment ) {

						/** @var $webp_children WP_Post[] */
						$webp_children = get_children( [
							'post_parent'    => $image_attachment->ID,
							'post_status'    => 'inherit',
							'post_mime_type' => 'image/webp',
							'posts_per_page' => 1
						] );

						if ( $webp_children ) {
							$webp_attachment_id = current( $webp_children )->ID;
						} else {
							$webp_attachment_id = null;
						}

						if ( $webp_attachment_id ) {
							$successfully_generated++;
						} else {
							$fullsize_path = get_attached_file( $image_attachment->ID );
							$im_resource = @imagecreatefromjpeg( $fullsize_path );
							if ( $im_resource ) {
								$wp_upload_dir = wp_upload_dir();

								$webp_name = basename( $image_attachment->post_title, '.jpg' ) . '.webp';
								$webp_image = $wp_upload_dir['path'] . '/' . $webp_name;
								imagewebp( $im_resource, $webp_image, 82 );
								imagedestroy( $im_resource );

								$webp_attachment = [
									'post_mime_type' => 'image/webp',
									'post_title'     => $image_attachment->post_title,
									'post_content'   => $image_attachment->post_content,
									'post_status'    => 'inherit'
								];

								$webp_attachment_id = wp_insert_attachment( $webp_attachment, $webp_image, $image_attachment->ID );

								require_once ABSPATH . 'wp-admin/includes/image.php';

								$webp_attachment_data = wp_generate_attachment_metadata( $webp_attachment_id, $webp_image );
								wp_update_attachment_metadata( $webp_attachment_id, $webp_attachment_data );
							}
						}
						$progress->tick();
					}
					$progress->finish();
					if ( $successfully_generated ) {
						WP_CLI::success( "Total number of generated webp: $successfully_generated of $all_images_count." );
					}
				} else {
					WP_CLI::log( 'No images found.' );
				}
				break;
			default:
				WP_CLI::warning( 'webp-rocket subcommand is missing!' );
				break;
		}
	}

	WP_CLI::add_command( 'webp-rocket', 'webp_rocket_cli' );
}

add_filter( 'post_thumbnail_html', 'webp_rocket_post_thumbnail_html', 10, 5 );

function webp_rocket_post_thumbnail_html( $html, $post_ID, $post_thumbnail_id, $size, $attr ) {
	$picture = '<picture>';
	$thumbnail = get_post( $post_thumbnail_id );
	$regex_picture = '/(width|height|class|alt|src)="[^"]*"/';
	if ( 'image/jpeg' == $thumbnail->post_mime_type ) {
		$webp_children = get_children( [
			'post_parent'    => $thumbnail->ID,
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/webp',
			'posts_per_page' => 1
		] );
		if ( $webp_children ) {
			$webp_attachment = current( $webp_children );
			remove_filter( 'post_thumbnail_html', 'webp_rocket_post_thumbnail_html' );
			$webp_picture = str_replace( '<img ', "<source type=\"$webp_attachment->post_mime_type\" ", wp_get_attachment_image( $webp_attachment->ID, $size ) );
			add_filter( 'post_thumbnail_html', 'webp_rocket_post_thumbnail_html', 10, 5 );
			$picture .= preg_replace( $regex_picture, '', $webp_picture );
		}
	}
	$picture_current = str_replace( '<img ', "<source type=\"$thumbnail->post_mime_type\" ", $html );
	$picture .= preg_replace( $regex_picture, '', $picture_current );
	$picture .= preg_replace( '/(srcset|sizes)="[^"]+"/', '', $html );
	$picture .= '</picture>';
	return $picture;
}

add_filter( 'wp_image_editors', 'webp_rocket_image_editors' );

function webp_rocket_image_editors( $implementations ) {
	$implementations []= 'WebP_Rocket_Image_Editor_GD';
	return $implementations;
}
