<?php
/**
 * Standalone smoke and GD integration test.
 *
 * Run with: php tests/smoke.php
 */

if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagewebp' ) ) {
	fwrite( STDERR, "SKIP: PHP GD with WebP support is required for this test.\n" );
	exit( 2 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_DEBUG', false );

$test_options           = array();
$force_editor_error     = false;
$editor_instances       = array();

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '' ) {
		unset( $code );
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class Test_GD_Editor {
	public $quality = null;
	public $rotated = false;
	private $path;

	public function __construct( $path ) {
		$this->path = $path;
	}

	public function set_quality( $quality ) {
		$this->quality = $quality;
		return true;
	}

	public function maybe_exif_rotate() {
		$this->rotated = true;
		return true;
	}

	public function save( $destination, $mime_type ) {
		if ( 'image/webp' !== $mime_type ) {
			return new WP_Error( 'wrong_mime', 'Unexpected output MIME type.' );
		}

		$image = imagecreatefromjpeg( $this->path );
		if ( false === $image || ! imagewebp( $image, $destination, $this->quality ) ) {
			return new WP_Error( 'save_failed', 'GD could not write the WebP image.' );
		}
		imagedestroy( $image );

		return array(
			'path'      => $destination,
			'file'      => basename( $destination ),
			'mime-type' => 'image/webp',
		);
	}
}

function get_option( $name, $default = false ) {
	global $test_options;
	return array_key_exists( $name, $test_options ) ? $test_options[ $name ] : $default;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_get_image_mime( $path ) {
	$details = @getimagesize( $path );
	return isset( $details['mime'] ) ? $details['mime'] : false;
}

function wp_get_image_editor( $path ) {
	global $editor_instances, $force_editor_error;

	if ( $force_editor_error ) {
		return new WP_Error( 'unsupported', 'Source image is unsupported.' );
	}

	$editor = new Test_GD_Editor( $path );
	$editor_instances[] = $editor;
	return $editor;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_basename( $path ) {
	return basename( $path );
}

function sanitize_file_name( $filename ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '-', $filename );
}

function wp_unique_filename( $directory, $filename ) {
	$stem      = pathinfo( $filename, PATHINFO_FILENAME );
	$extension = pathinfo( $filename, PATHINFO_EXTENSION );
	$candidate = $filename;
	$index     = 1;

	while ( file_exists( $directory . '/' . $candidate ) ) {
		$candidate = $stem . '-' . $index . ( $extension ? '.' . $extension : '' );
		++$index;
	}

	return $candidate;
}

function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}

function wp_delete_file( $path ) {
	return unlink( $path );
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function create_jpeg( $path ) {
	$image = imagecreatetruecolor( 64, 64 );
	$blue  = imagecolorallocate( $image, 20, 100, 220 );
	$white = imagecolorallocate( $image, 255, 255, 255 );
	imagefill( $image, 0, 0, $blue );
	imagefilledellipse( $image, 32, 32, 36, 36, $white );
	imagejpeg( $image, $path, 95 );
	imagedestroy( $image );
}

require dirname( __DIR__ ) . '/includes/class-esee-webp-converter.php';

$temporary_directory = sys_get_temp_dir() . '/esee-webp-' . bin2hex( random_bytes( 5 ) );
mkdir( $temporary_directory );

try {
	$converter = new Esee_WebP_Converter();

	$formats = $converter->configure_heic_output( array( 'image/avif' => 'image/jpeg' ) );
	assert_true( 'image/jpeg' === $formats['image/heic'], 'HEIC must map to JPEG.' );
	assert_true( 'image/jpeg' === $formats['image/heif'], 'HEIF must map to JPEG.' );
	assert_true( 'image/jpeg' === $formats['image/heic-sequence'], 'HEIC sequence must map to JPEG.' );
	assert_true( 'image/jpeg' === $formats['image/heif-sequence'], 'HEIF sequence must map to JPEG.' );
	assert_true( 'image/jpeg' === $formats['image/avif'], 'Existing output mappings must be preserved.' );

	$mime_types = $converter->allow_heic_uploads( array( 'jpg|jpeg|jpe' => 'image/jpeg' ) );
	assert_true( 'image/heic' === $mime_types['heic'], 'HEIC upload MIME must be registered.' );
	assert_true( 'image/heif' === $mime_types['heif'], 'HEIF upload MIME must be registered.' );

	$source_path = $temporary_directory . '/sample.jpg';
	create_jpeg( $source_path );
	$upload = array(
		'file' => $source_path,
		'url'  => 'https://example.test/wp-content/uploads/2026/08/sample.jpg',
		'type' => 'image/jpeg',
	);
	$result = $converter->convert_upload( $upload );

	assert_true( 'image/webp' === $result['type'], 'Converted upload MIME must be WebP.' );
	assert_true( '.webp' === substr( $result['file'], -5 ), 'Converted path must have a WebP extension.' );
	assert_true( is_file( $result['file'] ), 'Converted WebP file must exist.' );
	assert_true( ! is_file( $source_path ), 'Original should be removed after successful conversion.' );
	assert_true( 'image/webp' === getimagesize( $result['file'] )['mime'], 'GD output must be a valid WebP image.' );
	assert_true( 88 === $editor_instances[0]->quality, 'Default quality must be 88.' );
	assert_true( $editor_instances[0]->rotated, 'EXIF orientation handling must be requested.' );
	assert_true(
		'https://example.test/wp-content/uploads/2026/08/sample.webp' === $result['url'],
		'Upload URL must point at the converted file.'
	);

	unlink( $result['file'] );

	$source_path = $temporary_directory . '/retained.jpg';
	create_jpeg( $source_path );
	$test_options[ Esee_WebP_Converter::OPTION_NAME ] = array(
		'quality'       => 94,
		'keep_original' => true,
	);
	$result = $converter->convert_upload(
		array(
			'file' => $source_path,
			'url'  => 'https://example.test/uploads/retained.jpg',
			'type' => 'image/jpeg',
		)
	);
	assert_true( is_file( $source_path ), 'Original must remain when retention is enabled.' );
	assert_true( 94 === $editor_instances[1]->quality, 'Configured quality must be applied.' );
	unlink( $source_path );
	unlink( $result['file'] );

	$gif_path = $temporary_directory . '/animated.gif';
	file_put_contents( $gif_path, base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' ) );
	$upload = array(
		'file' => $gif_path,
		'url'  => 'https://example.test/uploads/animated.gif',
		'type' => 'image/gif',
	);
	assert_true( $upload === $converter->convert_upload( $upload ), 'GIF uploads must remain unchanged.' );
	unlink( $gif_path );

	$source_path = $temporary_directory . '/unsupported.jpg';
	create_jpeg( $source_path );
	$force_editor_error = true;
	$upload = array(
		'file' => $source_path,
		'url'  => 'https://example.test/uploads/unsupported.jpg',
		'type' => 'image/jpeg',
	);
	assert_true( $upload === $converter->convert_upload( $upload ), 'Editor failures must preserve the upload.' );
	assert_true( is_file( $source_path ), 'Failed conversion must not delete the original.' );
	unlink( $source_path );

	echo "PASS: mappings, MIME registration, GD conversion, quality, retention, GIF safety, and failure fallback.\n";
} finally {
	@rmdir( $temporary_directory );
}
