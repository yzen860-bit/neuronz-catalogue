<?php
/**
 * Build the public catalogue page from the plugin's own code.
 *
 *   php build/build.php                     rebuild docs/index.html from data/
 *   php build/build.php --sync              refresh from Zotero first
 *   php build/build.php --sync --check-links   ...and re-check every source link
 *
 * Reads and writes data/catalogue.json (the catalogue, including link status and
 * evidence that Zotero does not hold) and data/state.json (run logs), so each
 * run starts from what the previous one left. Exits non-zero on failure without
 * touching either file.
 *
 * Zotero settings come from the environment, with the project's group library
 * as the default: ZOTERO_LIBRARY_TYPE, ZOTERO_LIBRARY_ID, ZOTERO_API_KEY.
 */

$root = dirname( __DIR__ );
require __DIR__ . '/wp-shim.php';
require $root . '/plugin/neuronz-catalogue/neuronz-catalogue.php';

// Where "back to the NeuroNZ site" points from the hosted page.
const NNZ_HOME_URL   = 'https://neuronz6.wpcomstaging.com';
const NNZ_HOME_LABEL = 'NeuroNZ home';

$opts = getopt( '', array( 'sync', 'check-links', 'out::' ) );
$out  = isset( $opts['out'] ) ? $opts['out'] : $root . '/docs/index.html';

$data_file  = $root . '/data/catalogue.json';
$state_file = $root . '/data/state.json';

$data = file_get_contents( $data_file );
if ( ! $data || ! json_decode( $data ) ) {
	fwrite( STDERR, "data/catalogue.json is missing or not valid JSON.\n" );
	exit( 1 );
}
$state = is_readable( $state_file ) ? json_decode( file_get_contents( $state_file ), true ) : array();

$GLOBALS['nnz_options'] = array_merge( (array) $state, array(
	'nnz_catalogue_json'      => $data,
	'nnz_zotero_library_type' => getenv( 'ZOTERO_LIBRARY_TYPE' ) ?: 'groups',
	'nnz_zotero_library_id'   => getenv( 'ZOTERO_LIBRARY_ID' ) ?: '6708514',
	'nnz_zotero_api_key'      => getenv( 'ZOTERO_API_KEY' ) ?: '',
) );

if ( isset( $opts['sync'] ) ) {
	$res = nnz_sync_from_zotero();
	if ( is_wp_error( $res ) ) {
		fwrite( STDERR, 'Zotero refresh failed: ' . $res->get_error_message() . "\n" );
		exit( 1 );
	}
	echo $res, "\n";
}

if ( isset( $opts['check-links'] ) ) {
	$offset = 0;
	do {
		// One long batch is fine here: there is no web request to time out.
		$progress = nnz_recheck_batch( $offset, 900 );
		if ( is_wp_error( $progress ) ) {
			fwrite( STDERR, 'Link check failed: ' . $progress->get_error_message() . "\n" );
			exit( 1 );
		}
		$offset = $progress['offset'];
	} while ( ! $progress['done'] );
	printf( "Checked %d links; %d changed status.\n", $progress['total'], $progress['changed'] );
}

// Write the data back first, then the page built from it.
$catalogue = json_decode( get_option( 'nnz_catalogue_json' ), true );
file_put_contents( $data_file, json_encode( $catalogue,
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

$keep = array();
foreach ( array( 'nnz_sync_log', 'nnz_recheck_log', 'nnz_recheck_changes' ) as $k ) {
	$keep[ $k ] = get_option( $k, array() );
}
file_put_contents( $state_file, json_encode( $keep,
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

// Embed the data minified, whether or not this run refreshed it, so the page
// does not change shape between local and scheduled builds.
update_option( 'nnz_catalogue_json', wp_json_encode( $catalogue ) );
$html = nnz_build_standalone();

// A slim bar linking back to the WordPress.com site, which carries the
// introduction and methods pages for this catalogue.
$bar = nnz_home_bar( NNZ_HOME_URL, NNZ_HOME_LABEL );
$html = preg_replace( '/<body>\n/', "<body>\n" . $bar . "\n", $html, 1 );

if ( ! is_dir( dirname( $out ) ) ) {
	mkdir( dirname( $out ), 0777, true );
}
file_put_contents( $out, $html );
file_put_contents( dirname( $out ) . '/.nojekyll', '' );

$n = count( $catalogue['records'] );
$p = isset( $catalogue['publications'] ) ? count( $catalogue['publications'] ) : 0;
echo "Built $out ($n records, $p publications).\n";
