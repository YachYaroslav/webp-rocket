<?php
defined( 'ABSPATH' ) || exit;


if ( ! class_exists( 'WP_Image_Editor_GD' ) ) {
	require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
	require_once ABSPATH . 'wp-includes/class-wp-image-editor-gd.php';
}

/**
 * Class WebP_Rocket_Image_Editor_GD
 *
 * Adds support for editor operations over WebP images.
 */
class WebP_Rocket_Image_Editor_GD extends WP_Image_Editor_GD {

	/**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		$image_types = imagetypes();
		switch ( $mime_type ) {
			case 'image/jpeg':
				return ( $image_types & IMG_JPG ) != 0;
			case 'image/png':
				return ( $image_types & IMG_PNG ) != 0;
			case 'image/gif':
				return ( $image_types & IMG_GIF ) != 0;
			case 'image/webp':
				return ( $image_types & IMG_WEBP ) != 0;
		}

		return false;
	}

	/**
	 * Loads image from $this->file into new GD Resource.
	 *
	 * @return bool|WP_Error True if loaded successfully; WP_Error on failure.
	 */
	public function load() {
		if ( $this->image ) {
			return true;
		}

		if ( ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) ) {
			return new WP_Error( 'error_loading_image', __( 'File doesn&#8217;t exist?' ), $this->file );
		}

		// Set artificially high because GD uses uncompressed images in memory.
		wp_raise_memory_limit( 'image' );

		$size = @getimagesize( $this->file );

		if ( $size && 'image/webp' == $size['mime'] ) {
			$this->image = @imagecreatefromwebp( $this->file );
		} else {
			$this->image = @imagecreatefromstring( file_get_contents( $this->file ) );
		}

		if ( ! is_resource( $this->image ) ) {
			return new WP_Error( 'invalid_image', __( 'File is not an image.' ), $this->file );
		}

		if ( ! $size ) {
			return new WP_Error( 'invalid_image', __( 'Could not read image size.' ), $this->file );
		}

		if ( function_exists( 'imagealphablending' ) && function_exists( 'imagesavealpha' ) ) {
			imagealphablending( $this->image, false );
			imagesavealpha( $this->image, true );
		}

		$this->update_size( $size[0], $size[1] );
		$this->mime_type = $size['mime'];

		return $this->set_quality();
	}

	/**
	 * @param resource $image
	 * @param string|null $filename
	 * @param string|null $mime_type
	 * @return WP_Error|array
	 */
	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		if ( 'image/gif' == $mime_type ) {
			if ( ! $this->make_image( $filename, 'imagegif', array( $image, $filename ) ) ) {
				return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed' ) );
			}
		} elseif ( 'image/png' == $mime_type ) {
			// convert from full colors to index colors, like original PNG.
			if ( function_exists( 'imageistruecolor' ) && ! imageistruecolor( $image ) ) {
				imagetruecolortopalette( $image, false, imagecolorstotal( $image ) );
			}

			if ( ! $this->make_image( $filename, 'imagepng', array( $image, $filename ) ) ) {
				return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed' ) );
			}
		} elseif ( 'image/jpeg' == $mime_type ) {
			if ( ! $this->make_image( $filename, 'imagejpeg', array( $image, $filename, $this->get_quality() ) ) ) {
				return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed' ) );
			}
		} elseif ( 'image/webp' == $mime_type ) {
			if ( ! $this->make_image( $filename, 'imagewebp', array( $image, $filename, $this->get_quality() ) ) ) {
				return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed' ) );
			}
		} else {
			return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed' ) );
		}

		// Set correct file permissions
		$stat  = stat( dirname( $filename ) );
		$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
		@ chmod( $filename, $perms );

		/**
		 * Filters the name of the saved image file.
		 *
		 * @param string $filename Name of the file.
		 */
		return array(
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

	/**
	 * Returns stream of current image.
	 *
	 * @param string $mime_type The mime type of the image.
	 * @return bool True on success, false on failure.
	 */
	public function stream( $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

		switch ( $mime_type ) {
			case 'image/png':
				header( 'Content-Type: image/png' );
				return imagepng( $this->image );
			case 'image/gif':
				header( 'Content-Type: image/gif' );
				return imagegif( $this->image );
			case 'image/webp':
				header( 'Content-Type: image/webp' );
				return imagewebp( $this->image );
			default:
				header( 'Content-Type: image/jpeg' );
				return imagejpeg( $this->image, null, $this->get_quality() );
		}
	}
}