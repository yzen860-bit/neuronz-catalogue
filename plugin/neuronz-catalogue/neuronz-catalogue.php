<?php
/**
 * Plugin Name:  NeuroNZ Catalogue
 * Description:  Searchable register of New Zealand data sources on neurological conditions. Adds the [neuronz_catalogue] shortcode and an admin screen for refreshing the data from Zotero.
 * Version:      0.6.1
 * Requires PHP: 7.4
 * Author:       Yuxiang Zeng — COMPASS Research Centre
 * License:      GPL-2.0-or-later
 * Text Domain:  neuronz-catalogue
 *
 * The front end is the same CSS and JS used by the standalone build, so the
 * catalogue looks and behaves identically whether it is served by this plugin
 * or from a static host. Only the delivery differs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NNZ_VERSION', '0.6.1' );
define( 'NNZ_DIR', plugin_dir_path( __FILE__ ) );
define( 'NNZ_URL', plugin_dir_url( __FILE__ ) );
define( 'NNZ_DATA_FILE', NNZ_DIR . 'data/catalogue.json' );

/* -------------------------------------------------------------------------
 * Data
 * ---------------------------------------------------------------------- */

/**
 * The catalogue as a JSON string.
 *
 * A synced copy in the options table wins over the file bundled with the
 * plugin, so a refresh survives a plugin update.
 */
function nnz_get_data_json() {
	$synced = get_option( 'nnz_catalogue_json' );
	if ( is_string( $synced ) && strlen( $synced ) > 100 ) {
		return $synced;
	}
	if ( is_readable( NNZ_DATA_FILE ) ) {
		return file_get_contents( NNZ_DATA_FILE );
	}
	return '';
}

function nnz_record_count() {
	$data = json_decode( nnz_get_data_json(), true );
	return isset( $data['records'] ) ? count( $data['records'] ) : 0;
}

/* -------------------------------------------------------------------------
 * Shortcode
 * ---------------------------------------------------------------------- */

add_shortcode( 'neuronz_catalogue', 'nnz_render_shortcode' );

/**
 * Cache-busting version for an asset: its modification time, falling back to
 * the plugin version. Without this, editing a stylesheet during development
 * leaves the old file being served under an unchanged query string.
 */
function nnz_asset_version( $rel ) {
	$path = NNZ_DIR . $rel;
	return file_exists( $path ) ? (string) filemtime( $path ) : NNZ_VERSION;
}

function nnz_render_shortcode( $atts = array() ) {
	$json = nnz_get_data_json();

	if ( '' === $json ) {
		return '<div class="nnz"><p>The catalogue data file is missing. '
			. 'An administrator can restore it from Settings → NeuroNZ Catalogue.</p></div>';
	}

	$markup = NNZ_DIR . 'assets/markup.html';
	if ( ! is_readable( $markup ) ) {
		return '<div class="nnz"><p>The catalogue template is missing.</p></div>';
	}

	wp_enqueue_style(
		'nnz-fonts',
		'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'nnz-app', NNZ_URL . 'assets/app.css', array(), nnz_asset_version( 'assets/app.css' ) );
	wp_enqueue_script( 'nnz-app', NNZ_URL . 'assets/app.js', array(), nnz_asset_version( 'assets/app.js' ), true );

	// Hand the data to the script as a JS object. wp_json_encode escapes it
	// safely; the browser never sees a Zotero credential because the sync runs
	// server side.
	wp_add_inline_script(
		'nnz-app',
		'window.NEURONZ_DATA = ' . wp_json_encode( json_decode( $json ) ) . ';',
		'before'
	);

	$atts = shortcode_atts( array(
		'width'   => 'wide',    // wide | full | content
		'heading' => 'on',      // on | off — off suits a page that already has a title
		'panel'   => 'off',     // on gives the block its own background
	), $atts, 'neuronz_catalogue' );

	$class = 'nnz';
	$class .= 'full' === $atts['width'] ? ' alignfull'
		: ( 'content' === $atts['width'] ? '' : ' alignwide' );
	if ( 'on' === $atts['panel'] ) {
		$class .= ' nnz-panel';
	}

	$html = file_get_contents( $markup );
	if ( 'off' === $atts['heading'] ) {
		// Keep the ledger, drop the headline: the page supplies its own.
		$html = str_replace( 'class="masthead" id="nnz-masthead"',
			'class="masthead is-bare" id="nnz-masthead"', $html );
	}

	return '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
}

/* -------------------------------------------------------------------------
 * Admin screen
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_options_page(
		'NeuroNZ Catalogue',
		'NeuroNZ Catalogue',
		'manage_options',
		'neuronz-catalogue',
		'nnz_admin_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'nnz_settings', 'nnz_zotero_library_type', array(
		'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'groups',
	) );
	register_setting( 'nnz_settings', 'nnz_zotero_library_id', array(
		'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '',
	) );
	register_setting( 'nnz_settings', 'nnz_zotero_api_key', array(
		'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '',
	) );
} );

function nnz_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'neuronz-catalogue' ) );
	}

	$notice   = '';
	$progress = null;

	if ( isset( $_POST['nnz_sync'] ) && check_admin_referer( 'nnz_sync_action', 'nnz_sync_nonce' ) ) {
		$result = nnz_sync_from_zotero();
		$notice = is_wp_error( $result )
			? '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'
			: '<div class="notice notice-success"><p>' . esc_html( $result ) . '</p></div>';
	}

	if ( isset( $_POST['nnz_recheck'] ) && check_admin_referer( 'nnz_recheck_action', 'nnz_recheck_nonce' ) ) {
		$offset   = isset( $_POST['nnz_offset'] ) ? absint( $_POST['nnz_offset'] ) : 0;
		$progress = nnz_recheck_batch( $offset );
		if ( is_wp_error( $progress ) ) {
			$notice   = '<div class="notice notice-error"><p>' . esc_html( $progress->get_error_message() ) . '</p></div>';
			$progress = null;
		} elseif ( $progress['done'] ) {
			$notice = '<div class="notice notice-success"><p>' . sprintf(
				esc_html__( 'Checked %1$d links. %2$d changed status since the last check.', 'neuronz-catalogue' ),
				(int) $progress['total'], (int) $progress['changed']
			) . '</p></div>';
		}
	}

	$log      = get_option( 'nnz_sync_log', array() );
	$key_set  = (bool) get_option( 'nnz_zotero_api_key' );
	?>
	<div class="wrap">
		<h1>NeuroNZ Catalogue</h1>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<p>Put <code>[neuronz_catalogue]</code> on any page to show the catalogue.
			It currently holds <strong><?php echo (int) nnz_record_count(); ?></strong> records.</p>
		<table class="widefat striped" style="max-width:760px;margin-bottom:1em">
			<thead><tr><th style="width:210px">Attribute</th><th>Effect</th></tr></thead>
			<tbody>
			<tr><td><code>width="wide"</code></td><td>Default. Asks the theme for its wide width.
				Use <code>"full"</code> if the theme still squeezes the block into a narrow
				column, or <code>"content"</code> to keep it inside the text column.</td></tr>
			<tr><td><code>heading="off"</code></td><td>Hides the built-in headline and keeps the
				link-status ledger. Use this when the page already shows its own title, so the
				heading is not stated twice.</td></tr>
			<tr><td><code>panel="on"</code></td><td>Gives the block its own background. By default
				it is transparent and sits on the page background.</td></tr>
			</tbody>
		</table>
		<p>For a page that has its own title:
			<code>[neuronz_catalogue heading="off"]</code></p>

		<h2>Zotero</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'nnz_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="nnz-lt">Library type</label></th>
					<td>
						<select name="nnz_zotero_library_type" id="nnz-lt">
							<?php $lt = get_option( 'nnz_zotero_library_type', 'groups' ); ?>
							<option value="groups" <?php selected( $lt, 'groups' ); ?>>Group library</option>
							<option value="users"  <?php selected( $lt, 'users' ); ?>>User library</option>
						</select>
						<p class="description">A group library is preferable: a personal library
							cannot be shared, and ownership moves with the individual.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nnz-lid">Library ID</label></th>
					<td><input type="text" id="nnz-lid" name="nnz_zotero_library_id" class="regular-text"
							value="<?php echo esc_attr( get_option( 'nnz_zotero_library_id', '' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="nnz-key">API key</label></th>
					<td>
						<input type="password" id="nnz-key" name="nnz_zotero_api_key" class="regular-text"
							autocomplete="off"
							value="<?php echo esc_attr( get_option( 'nnz_zotero_api_key', '' ) ); ?>">
						<p class="description">
							Use a read-only key. It is stored in the options table and is never sent
							to the browser — the catalogue page contains data, not credentials.
							Leave blank if the group is public.
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save settings' ); ?>
		</form>

		<h2>Refresh from Zotero</h2>
		<p>Fetches <code>01 Catalogue Sources</code> and all of its subfolders and rebuilds the
			published records. Each data source takes its category from the subfolder it sits in.
			Existing entries are matched on <code>NeuroNZ Record ID</code>, so a refresh
			updates rather than duplicates. Journal articles are published separately, in the
			Research publications list below the catalogue.</p>
		<?php if ( ! $key_set && 'users' === get_option( 'nnz_zotero_library_type' ) ) : ?>
			<p><em>A user library always needs a key.</em></p>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'nnz_sync_action', 'nnz_sync_nonce' ); ?>
			<?php submit_button( 'Refresh now', 'secondary', 'nnz_sync', false ); ?>
		</form>

		<h2>Re-check links</h2>
		<p>Requests every source link and records what happened. A redirect that keeps its path
			counts as a move; one that lands on a site root has lost its content and is marked
			accordingly. A 403 means the server refused an automated request, which is not
			evidence that the page is gone.</p>
		<p><strong>This is recorded separately from <code>last_verified</code>.</strong> An
			automated check shows that a URL responded — not that the claim it supports is still
			true. Only a person reading the source can do that, and those checks stay in the
			evidence log.</p>
		<?php
		$q     = json_decode( nnz_get_data_json(), true );
		$nlink = $q && ! empty( $q['records'] ) ? count( nnz_link_queue( $q['records'] ) ) : 0;
		$off   = $progress && ! $progress['done'] ? (int) $progress['offset'] : 0;
		?>
		<?php if ( $progress && ! $progress['done'] ) : ?>
			<div class="notice notice-info"><p>
				<?php printf( esc_html__( 'Checked %1$d of %2$d links so far. Continue to finish — each pass runs for about 18 seconds so the request cannot time out.', 'neuronz-catalogue' ),
					(int) $progress['offset'], (int) $progress['total'] ); ?>
			</p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'nnz_recheck_action', 'nnz_recheck_nonce' ); ?>
			<input type="hidden" name="nnz_offset" value="<?php echo (int) $off; ?>">
			<?php submit_button(
				$off ? sprintf( 'Continue — %d of %d done', $off, $nlink ) : sprintf( 'Check all %d links', $nlink ),
				'secondary', 'nnz_recheck', false
			); ?>
		</form>

		<?php
		$changes = (array) get_option( 'nnz_recheck_changes', array() );
		if ( $changes ) : ?>
			<h3>What changed at the last check</h3>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr><th>Record</th><th>Was</th><th>Now</th><th>Link</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( array_reverse( $changes ), 0, 25 ) as $c ) : ?>
					<tr>
						<td><code><?php echo esc_html( $c['record'] ); ?></code><br>
							<?php echo esc_html( $c['name'] ); ?></td>
						<td><?php echo esc_html( $c['from'] ); ?></td>
						<td><strong><?php echo esc_html( $c['to'] ); ?></strong></td>
						<td style="word-break:break-all"><?php echo esc_html( $c['url'] ); ?>
							<?php if ( ! empty( $c['note'] ) ) : ?>
								<br><em><?php echo esc_html( $c['note'] ); ?></em>
							<?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php $rlog = (array) get_option( 'nnz_recheck_log', array() ); ?>
		<?php if ( $rlog ) : ?>
			<h3>Check history</h3>
			<table class="widefat striped" style="max-width:600px">
				<thead><tr><th>When</th><th>Links</th><th>Changed</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( array_reverse( $rlog ), 0, 10 ) as $row ) : ?>
					<tr><td><?php echo esc_html( $row['time'] ); ?></td>
						<td><?php echo esc_html( $row['links'] ); ?></td>
						<td><?php echo esc_html( $row['changed'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Export</h2>
		<p>Builds a single file containing the catalogue exactly as this site now holds it —
			including anything brought in from Zotero and the latest link statuses. It opens by
			double-clicking, needs no server, and can be hosted anywhere. Use it to share the
			catalogue with people who cannot reach this site, or to publish it where WordPress
			is not available.</p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url(
				admin_url( 'admin-post.php?action=nnz_export&kind=html' ), 'nnz_export' ) ); ?>">
				Download standalone page</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url(
				admin_url( 'admin-post.php?action=nnz_export&kind=json' ), 'nnz_export' ) ); ?>">
				Download data as JSON</a>
		</p>

		<h2>Refresh history</h2>
		<?php if ( empty( $log ) ) : ?>
			<p>No refresh has been run yet. The records shown are the ones bundled with the plugin.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>When</th><th>Result</th><th>Records</th><th>Publications</th><th>Skipped</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( array_reverse( $log ), 0, 12 ) as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['time'] ); ?></td>
						<td><?php echo esc_html( $row['status'] ); ?></td>
						<td><?php echo esc_html( $row['count'] ); ?></td>
						<td><?php echo esc_html( isset( $row['pubs'] ) ? $row['pubs'] : '—' ); ?></td>
						<td><?php echo esc_html( $row['skipped'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Where this runs</h2>
		<p>This plugin needs installation rights, which belong to the network administrator
			on a WordPress multisite. On a site where those rights are not available, serve the
			same catalogue as a standalone page and link or embed it — the CSS and JS are identical,
			so the two routes produce the same interface.</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Zotero sync
 * ---------------------------------------------------------------------- */

/**
 * Parse Zotero's `extra` field: newline-delimited `Key: value` text.
 *
 * Splits on the first colon only, so values containing colons — URLs, times —
 * survive intact, and folds indented continuation lines into the previous key
 * rather than dropping them.
 */
function nnz_parse_extra( $extra ) {
	$meta = array();
	$last = null;
	foreach ( preg_split( '/\R/', (string) $extra ) as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		if ( false !== strpos( $line, ':' ) && ! preg_match( '/^\s/', $line ) ) {
			list( $k, $v ) = explode( ':', $line, 2 );
			$k = trim( $k );
			$v = trim( $v );
			$meta[ $k ] = isset( $meta[ $k ] ) ? $meta[ $k ] . ' ' . $v : $v;
			$last = $k;
		} elseif ( $last ) {
			$meta[ $last ] .= ' ' . trim( $line );
		}
	}
	return $meta;
}

/** Text held back from public pages, per the field contract. */
function nnz_strip_internal( $text ) {
	if ( ! $text ) {
		return array( null, null );
	}
	$pattern = '/verification pending|not verified|pending in this pass|returned 404|to be verified/i';
	$parts   = preg_split( '/(?<=[;.])\s+/', $text );
	$public  = array();
	$hidden  = array();
	foreach ( $parts as $p ) {
		if ( preg_match( $pattern, $p ) ) {
			$hidden[] = $p;
		} else {
			$public[] = $p;
		}
	}
	return array(
		trim( implode( ' ', $public ), " ;" ) ?: null,
		$hidden ? implode( ' ', $hidden ) : null,
	);
}

function nnz_zotero_get( $path, $args = array() ) {
	$key = get_option( 'nnz_zotero_api_key', '' );
	$url = add_query_arg(
		array_merge( array( 'format' => 'json', 'include' => 'data' ), $args ),
		'https://api.zotero.org' . $path
	);
	$headers = array( 'Zotero-API-Version' => '3' );
	if ( $key ) {
		$headers['Zotero-API-Key'] = $key;
	}
	$res = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => $headers ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( 403 === $code ) {
		return new WP_Error( 'nnz_forbidden', 'Zotero refused the request (403). The library is private and the key is missing, wrong, or not authorised for it.' );
	}
	if ( 404 === $code ) {
		return new WP_Error( 'nnz_notfound', 'Zotero returned 404. Check the library type and ID.' );
	}
	if ( 200 !== $code ) {
		return new WP_Error( 'nnz_http', 'Zotero returned HTTP ' . $code . '.' );
	}
	return json_decode( wp_remote_retrieve_body( $res ), true );
}

/** Every item at a collection path, following Zotero's pagination. */
function nnz_zotero_items( $path ) {
	$items = array();
	$start = 0;
	do {
		$page = nnz_zotero_get( $path, array( 'limit' => 100, 'start' => $start ) );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$items = array_merge( $items, (array) $page );
		$start += 100;
	} while ( count( (array) $page ) === 100 );
	return $items;
}

/**
 * A literature item as a publication entry. Returns null when it has no title.
 * Condition tags (`nzneuro:condition:NC-###`) are resolved to public names
 * through the condition taxonomy where possible.
 */
function nnz_publication_from_item( $it, $meta, $conditions ) {
	$d = isset( $it['data'] ) ? $it['data'] : array();
	if ( empty( $d['title'] ) ) {
		return null;
	}

	$authors = array();
	foreach ( (array) ( isset( $d['creators'] ) ? $d['creators'] : array() ) as $c ) {
		if ( isset( $c['creatorType'] ) && 'author' !== $c['creatorType'] ) {
			continue;
		}
		if ( ! empty( $c['lastName'] ) ) {
			$initials = '';
			foreach ( preg_split( '/[\s\-]+/', isset( $c['firstName'] ) ? $c['firstName'] : '' ) as $part ) {
				$initials .= $part ? mb_substr( $part, 0, 1 ) : '';
			}
			$authors[] = trim( $c['lastName'] . ' ' . $initials );
		} elseif ( ! empty( $c['name'] ) ) {
			$authors[] = $c['name'];
		}
	}

	$cond_ids = array();
	foreach ( (array) ( isset( $d['tags'] ) ? $d['tags'] : array() ) as $t ) {
		if ( isset( $t['tag'] ) && preg_match( '/^nzneuro:condition:(NC-\d+)$/i', $t['tag'], $m ) ) {
			$cond_ids[] = strtoupper( $m[1] );
		}
	}
	$cond_ids  = array_values( array_unique( $cond_ids ) );
	$cond_name = array();
	foreach ( $cond_ids as $cid ) {
		if ( isset( $conditions[ $cid ] ) ) {
			$cond_name[] = $conditions[ $cid ];
		}
	}

	// Zotero's own parse of free-text dates ("2026-Oct-07") is in meta.
	$date = isset( $it['meta']['parsedDate'] ) ? $it['meta']['parsedDate'] : '';
	if ( '' === $date && ! empty( $d['date'] ) && preg_match( '/\d{4}/', $d['date'], $m ) ) {
		$date = $m[0];
	}

	$pmid = ! empty( $meta['PMID'] ) ? $meta['PMID'] : ( ! empty( $d['PMID'] ) ? $d['PMID'] : null );
	list( $abstract ) = nnz_strip_internal( isset( $d['abstractNote'] ) ? $d['abstractNote'] : '' );

	return array(
		'id'            => ! empty( $meta['NeuroNZ Evidence ID'] ) ? $meta['NeuroNZ Evidence ID'] : $d['key'],
		'title'         => rtrim( $d['title'], '.' ),
		'authors'       => $authors,
		'journal'       => ! empty( $d['publicationTitle'] ) ? $d['publicationTitle'] : null,
		'journal_abbr'  => ! empty( $d['journalAbbreviation'] ) ? $d['journalAbbreviation'] : null,
		'date'          => $date ? $date : null,
		'year'          => $date ? (int) substr( $date, 0, 4 ) : null,
		'doi'           => ! empty( $d['DOI'] ) ? $d['DOI'] : null,
		'pmid'          => $pmid,
		'url'           => ! empty( $d['url'] ) ? $d['url'] : null,
		'abstract'      => $abstract,
		'condition_ids' => $cond_ids,
		'conditions'    => $cond_name,
		'source'        => ! empty( $d['libraryCatalog'] ) ? $d['libraryCatalog'] : null,
		'zotero_key'    => isset( $d['key'] ) ? $d['key'] : null,
	);
}

function nnz_sync_from_zotero() {
	$type = get_option( 'nnz_zotero_library_type', 'groups' );
	$id   = trim( (string) get_option( 'nnz_zotero_library_id', '' ) );
	if ( '' === $id ) {
		return new WP_Error( 'nnz_noid', 'Set the Zotero library ID before refreshing.' );
	}
	$base = '/' . $type . '/' . rawurlencode( $id );

	$cols = nnz_zotero_get( $base . '/collections', array( 'limit' => 100 ) );
	if ( is_wp_error( $cols ) ) {
		nnz_log( 'failed', 0, 0 );
		return $cols;
	}

	$target   = null;
	$taxonomy = null;
	foreach ( (array) $cols as $c ) {
		if ( ! isset( $c['data']['name'] ) ) {
			continue;
		}
		if ( '01 Catalogue Sources' === $c['data']['name'] ) {
			$target = $c['data']['key'];
		} elseif ( '03 Condition Taxonomy' === $c['data']['name'] ) {
			$taxonomy = $c['data']['key'];
		}
	}
	if ( ! $target ) {
		nnz_log( 'failed', 0, 0 );
		return new WP_Error( 'nnz_nocol', 'No collection named "01 Catalogue Sources" in this library. If the group library is still empty, its contents have not been moved across yet.' );
	}

	// "01 Catalogue Sources" is split into one subfolder per source type, and
	// some items (journal articles) live only in a subfolder. Read the parent
	// and every subfolder, and remember which subfolder each item sits in: the
	// folder is now the authoritative category.
	$folders = array( $target => '' );
	foreach ( (array) $cols as $c ) {
		if ( isset( $c['data']['parentCollection'] ) && $target === $c['data']['parentCollection'] ) {
			$folders[ $c['data']['key'] ] = $c['data']['name'];
		}
	}

	$items     = array();
	$folder_of = array();
	foreach ( $folders as $ckey => $cname ) {
		$page_items = nnz_zotero_items( $base . '/collections/' . $ckey . '/items/top' );
		if ( is_wp_error( $page_items ) ) {
			nnz_log( 'failed', 0, 0 );
			return $page_items;
		}
		foreach ( $page_items as $it ) {
			$k = isset( $it['data']['key'] ) ? $it['data']['key'] : md5( wp_json_encode( $it ) );
			$items[ $k ] = $it;
			if ( '' !== $cname && ! isset( $folder_of[ $k ] ) ) {
				$folder_of[ $k ] = $cname;
			}
		}
	}

	// Condition IDs (NC-###) to their public names, for tagging publications.
	$conditions = array();
	if ( $taxonomy ) {
		$tax_items = nnz_zotero_items( $base . '/collections/' . $taxonomy . '/items/top' );
		if ( ! is_wp_error( $tax_items ) ) {
			foreach ( $tax_items as $it ) {
				$m = nnz_parse_extra( isset( $it['data']['extra'] ) ? $it['data']['extra'] : '' );
				if ( ! empty( $m['NeuroNZ Condition ID'] ) ) {
					$conditions[ $m['NeuroNZ Condition ID'] ] = ! empty( $m['Public name'] )
						? $m['Public name'] : $it['data']['title'];
				}
			}
		}
	}

	$existing = json_decode( nnz_get_data_json(), true );
	$byid     = array();
	if ( ! empty( $existing['records'] ) ) {
		foreach ( $existing['records'] as $r ) {
			$byid[ $r['record_id'] ] = $r;
		}
	}

	$records      = array();
	$publications = array();
	$skipped      = 0;
	$seen         = array();
	$seen_pub     = array();
	foreach ( $items as $ikey => $it ) {
		$d      = isset( $it['data'] ) ? $it['data'] : array();
		$meta   = nnz_parse_extra( isset( $d['extra'] ) ? $d['extra'] : '' );
		$rid    = isset( $meta['NeuroNZ Record ID'] ) ? $meta['NeuroNZ Record ID'] : '';
		$folder = isset( $folder_of[ $ikey ] ) ? $folder_of[ $ikey ] : '';

		// Literature is not a data source: it goes to its own list, keyed on
		// the evidence ID Chandan's pipeline assigns (or the Zotero key).
		$is_pub = '' === $rid && (
			'journalArticle' === ( isset( $d['itemType'] ) ? $d['itemType'] : '' )
			|| 'Journal Articles' === $folder
		);
		if ( $is_pub ) {
			$pub = nnz_publication_from_item( $it, $meta, $conditions );
			if ( ! $pub || isset( $seen_pub[ $pub['id'] ] ) ) {
				$skipped++;
				continue;
			}
			$seen_pub[ $pub['id'] ] = true;
			$publications[] = $pub;
			continue;
		}

		// No stable ID, no title, or a duplicate: do not publish it silently.
		if ( '' === $rid || empty( $d['title'] ) || isset( $seen[ $rid ] ) ) {
			$skipped++;
			continue;
		}
		$seen[ $rid ] = true;

		list( $summary, $summary_int ) = nnz_strip_internal( isset( $d['abstractNote'] ) ? $d['abstractNote'] : '' );
		list( $access, $access_int )   = nnz_strip_internal( isset( $meta['Availability / Access'] ) ? $meta['Availability / Access'] : '' );

		$prev = isset( $byid[ $rid ] ) ? $byid[ $rid ] : array();
		$n    = (int) preg_replace( '/\D/', '', $rid );

		$rec = array(
			'record_id'         => $rid,
			'n'                 => $n,
			'name'              => $d['title'],
			// The Zotero subfolder is the category; `Source category` in
			// `extra` is the workbook's older label, kept as a fallback.
			'category'          => '' !== $folder ? $folder
				: ( isset( $meta['Source category'] ) ? $meta['Source category'] : null ),
			'source_category'   => isset( $meta['Source category'] ) ? $meta['Source category'] : null,
			'owner'             => isset( $d['websiteTitle'] ) && $d['websiteTitle'] ? $d['websiteTitle'] : ( isset( $prev['owner'] ) ? $prev['owner'] : null ),
			'summary'           => $summary,
			'summary_internal'  => $summary_int,
			'type_of_data'      => isset( $meta['Type of data'] ) ? $meta['Type of data'] : null,
			'age_group'         => isset( $meta['Age group'] ) ? $meta['Age group'] : null,
			'period'            => isset( $meta['Data period'] ) ? $meta['Data period'] : null,
			'design_type'       => isset( $meta['Design type'] ) ? $meta['Design type'] : null,
			'population'        => isset( $meta['Population / Coverage'] ) ? $meta['Population / Coverage'] : null,
			'sample_size'       => isset( $meta['Approx. sample size'] ) ? $meta['Approx. sample size'] : null,
			'idi'               => isset( $meta['Available in IDI'] ) ? $meta['Available in IDI'] : null,
			'access'            => $access,
			'access_internal'   => $access_int,
			'zotero_key'        => isset( $d['key'] ) ? $d['key'] : null,
			'zotero_version'    => isset( $d['version'] ) ? $d['version'] : null,
		);

		// Tags carry the neurological-content flags and the condition terms.
		$tags = array();
		foreach ( (array) ( isset( $d['tags'] ) ? $d['tags'] : array() ) as $t ) {
			if ( isset( $t['tag'] ) ) {
				$tags[] = $t['tag'];
			}
		}
		$has = function ( $needle ) use ( $tags ) {
			foreach ( $tags as $t ) {
				if ( false !== stripos( $t, $needle ) ) {
					return true;
				}
			}
			return false;
		};
		// Current scheme: `content:named_neurological_conditions` etc. When an
		// item carries any `content:` tag, the tags decide; otherwise fall back
		// to the older short tags and then to what is already held locally.
		$flag_tags = array(
			'ncc' => 'content:named_neurological_conditions',
			'sfl' => 'content:symptoms_functional_limitations',
			'nwf' => 'content:neurological_workforce',
		);
		$has_content = $has( 'content:' );
		foreach ( $flag_tags as $flag => $tag ) {
			if ( $has_content ) {
				$rec[ $flag ] = $has( $tag );
			} else {
				$rec[ $flag ] = $has( $flag ) ? true : ( isset( $prev[ $flag ] ) ? $prev[ $flag ] : false );
			}
		}

		$terms = array();
		foreach ( $tags as $t ) {
			if ( 0 === stripos( $t, 'neuro:' ) ) {
				$terms[] = trim( substr( $t, 6 ) );
			}
		}
		$rec['neuro_terms'] = $terms ? $terms : ( isset( $prev['neuro_terms'] ) ? $prev['neuro_terms'] : array() );

		// Links: prefer the item URL, fall back to the parsed list.
		$urls = array();
		if ( ! empty( $d['url'] ) ) {
			$urls[] = $d['url'];
		}
		if ( isset( $meta['All source links'] ) ) {
			preg_match_all( '#https?://[^\s;,]+#', $meta['All source links'], $m );
			$urls = array_merge( $urls, $m[0] );
		}
		$urls = array_values( array_unique( $urls ) );

		// Keep the link status and verification history already held locally:
		// Zotero does not carry them, and losing them would remove exactly the
		// provenance this catalogue is meant to show.
		// The whole link entry is kept, not just its status: the link re-check
		// adds `checked`, `final_url` and `check_note`, and a refresh must not
		// erase them.
		$prev_links = array();
		if ( ! empty( $prev['links'] ) ) {
			foreach ( $prev['links'] as $l ) {
				$prev_links[ $l['url'] ] = $l;
			}
		}
		$rec['links'] = array();
		foreach ( $urls as $u ) {
			$rec['links'][] = isset( $prev_links[ $u ] )
				? array_merge( $prev_links[ $u ], array( 'url' => $u ) )
				: array( 'url' => $u, 'status' => 'UNKNOWN' );
		}
		$rec['link_checked']  = isset( $prev['link_checked'] ) ? $prev['link_checked'] : null;
		$rec['last_verified'] = isset( $prev['last_verified'] ) ? $prev['last_verified'] : null;
		$rec['evidence']      = isset( $prev['evidence'] ) ? $prev['evidence'] : array();
		$rec['period_start']  = isset( $prev['period_start'] ) ? $prev['period_start'] : null;
		$rec['period_end']    = isset( $prev['period_end'] ) ? $prev['period_end'] : null;
		$rec['ongoing']       = isset( $prev['ongoing'] ) ? $prev['ongoing'] : false;

		$records[] = $rec;
	}

	if ( ! $records ) {
		nnz_log( 'failed', 0, $skipped );
		return new WP_Error( 'nnz_empty', 'Zotero returned no usable records. Nothing was changed.' );
	}

	usort( $records, function ( $a, $b ) {
		return strcasecmp( $a['name'], $b['name'] );
	} );

	// Newest first; undated items last.
	usort( $publications, function ( $a, $b ) {
		return strcmp( (string) $b['date'], (string) $a['date'] ) ?: strcasecmp( $a['title'], $b['title'] );
	} );

	$meta_out = isset( $existing['meta'] ) ? $existing['meta'] : array();
	$meta_out['record_count']      = count( $records );
	$meta_out['publication_count'] = count( $publications );
	$meta_out['build_date']        = gmdate( 'Y-m-d' );
	$meta_out['synced_from']       = 'Zotero ' . $type . '/' . $id;

	update_option( 'nnz_catalogue_json',
		wp_json_encode( array( 'meta' => $meta_out, 'records' => $records, 'publications' => $publications ) ), false );

	nnz_log( 'ok', count( $records ), $skipped, count( $publications ) );

	$msg = sprintf( 'Refreshed %d records and %d publications from Zotero.', count( $records ), count( $publications ) );
	if ( $skipped ) {
		$msg .= sprintf( ' %d item(s) were skipped for having no stable NeuroNZ Record ID, no title, or a duplicate ID — these are not published automatically.', $skipped );
	}
	return $msg;
}

/* -------------------------------------------------------------------------
 * Link re-check
 *
 * Content and verification age on different clocks. A Zotero refresh brings the
 * descriptions up to date; it says nothing about whether the sources still
 * resolve. Measured decay in this catalogue was 4.4% within three months of
 * publication, so the second clock needs winding too.
 *
 * An automated check is weaker evidence than a human one: it shows that a URL
 * responded, not that the claim it supports is still true. It is recorded as
 * `link_checked`, separately from the evidence-backed `last_verified`, rather
 * than being allowed to stand in for it.
 * ---------------------------------------------------------------------- */

/**
 * Classify one URL.
 *
 * Status codes alone overstate availability: a page withdrawn during a site
 * migration commonly answers 200 after redirecting to a home page. The final
 * URL is therefore compared with the requested one — a redirect that keeps the
 * path is a move, a redirect that lands on a bare root has lost its content.
 */
function nnz_check_url( $url ) {
	$args = array(
		'timeout'     => 12,
		'redirection' => 6,
		'user-agent'  => 'NeuroNZ-catalogue/1.0 (link check; University of Auckland)',
	);
	$res  = wp_remote_head( $url, $args );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );

	// Some servers reject HEAD outright; fall back to GET before judging.
	if ( is_wp_error( $res ) || in_array( $code, array( 400, 403, 405, 501 ), true ) ) {
		$res  = wp_remote_get( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}
	if ( is_wp_error( $res ) ) {
		return array( 'UNREACHABLE', $url, $res->get_error_message() );
	}

	$final = $url;
	if ( isset( $res['http_response'] ) && method_exists( $res['http_response'], 'get_response_object' ) ) {
		$obj = $res['http_response']->get_response_object();
		if ( isset( $obj->url ) && $obj->url ) {
			$final = $obj->url;
		}
	}

	if ( 401 === $code || 403 === $code ) {
		return array( 'UNVERIFIABLE', $final, 'Refused an automated request; not evidence of absence.' );
	}
	if ( 404 === $code || 410 === $code ) {
		return array( 'DEAD', $final, 'HTTP ' . $code );
	}
	if ( 0 === $code || $code >= 500 ) {
		return array( 'UNREACHABLE', $final, 'HTTP ' . $code );
	}
	if ( $code >= 400 ) {
		return array( 'UNVERIFIABLE', $final, 'HTTP ' . $code );
	}

	$a  = wp_parse_url( $url );
	$b  = wp_parse_url( $final );
	$pa = isset( $a['path'] ) ? rtrim( $a['path'], '/' ) : '';
	$pb = isset( $b['path'] ) ? rtrim( $b['path'], '/' ) : '';
	$ha = isset( $a['host'] ) ? preg_replace( '/^www\./', '', $a['host'] ) : '';
	$hb = isset( $b['host'] ) ? preg_replace( '/^www\./', '', $b['host'] ) : '';

	if ( $pa === $pb && $ha === $hb ) {
		return array( 'OK', $final, '' );
	}
	if ( '' === $pb && '' !== $pa ) {
		return array( 'CONTENT_LOST', $final, 'Redirected to the site root; the page it pointed at is gone.' );
	}
	return array( 'MOVED', $final, 'Redirected to ' . $final );
}

/** Flatten every link in the catalogue into a work queue. */
function nnz_link_queue( $records ) {
	$q = array();
	foreach ( $records as $i => $r ) {
		if ( empty( $r['links'] ) ) {
			continue;
		}
		foreach ( $r['links'] as $j => $l ) {
			$q[] = array( 'r' => $i, 'l' => $j, 'url' => $l['url'] );
		}
	}
	return $q;
}

/**
 * Check part of the queue within a time budget, so the request cannot time out.
 * Returns how far it reached; the caller resumes from there.
 */
function nnz_recheck_batch( $offset = 0, $budget = 18 ) {
	$data = json_decode( nnz_get_data_json(), true );
	if ( empty( $data['records'] ) ) {
		return new WP_Error( 'nnz_nodata', 'There is no catalogue data to check.' );
	}
	$records = $data['records'];
	$queue   = nnz_link_queue( $records );
	$total   = count( $queue );
	if ( $offset >= $total ) {
		return array( 'done' => true, 'offset' => $total, 'total' => $total, 'changed' => 0 );
	}

	$today   = current_time( 'Y-m-d' );
	$changed = ( 0 === $offset ) ? array() : (array) get_option( 'nnz_recheck_changes', array() );

	$start = microtime( true );
	$i     = $offset;
	while ( $i < $total && ( microtime( true ) - $start ) < $budget ) {
		$item = $queue[ $i ];
		list( $status, $final, $note ) = nnz_check_url( $item['url'] );

		$was = isset( $records[ $item['r'] ]['links'][ $item['l'] ]['status'] )
			? $records[ $item['r'] ]['links'][ $item['l'] ]['status'] : 'UNKNOWN';

		$records[ $item['r'] ]['links'][ $item['l'] ]['status']     = $status;
		$records[ $item['r'] ]['links'][ $item['l'] ]['checked']    = $today;
		$records[ $item['r'] ]['links'][ $item['l'] ]['final_url']  = $final;
		$records[ $item['r'] ]['links'][ $item['l'] ]['check_note'] = $note;
		$records[ $item['r'] ]['link_checked'] = $today;

		if ( $was !== $status ) {
			$changed[] = array(
				'record' => $records[ $item['r'] ]['record_id'],
				'name'   => $records[ $item['r'] ]['name'],
				'url'    => $item['url'],
				'from'   => $was,
				'to'     => $status,
				'note'   => $note,
			);
		}
		$i++;
	}

	$data['records'] = $records;
	$data['meta']['link_check_date'] = $today;
	update_option( 'nnz_catalogue_json', wp_json_encode( $data ), false );
	update_option( 'nnz_recheck_changes', array_slice( $changed, -200 ), false );

	$done = $i >= $total;
	if ( $done ) {
		$log   = (array) get_option( 'nnz_recheck_log', array() );
		$log[] = array(
			'time'    => current_time( 'Y-m-d H:i' ),
			'links'   => $total,
			'changed' => count( $changed ),
		);
		update_option( 'nnz_recheck_log', array_slice( $log, -50 ), false );
	}
	return array( 'done' => $done, 'offset' => $i, 'total' => $total, 'changed' => count( $changed ) );
}

/* -------------------------------------------------------------------------
 * Export — the standalone build, generated from whatever the site now holds
 * ---------------------------------------------------------------------- */

function nnz_build_standalone() {
	$css   = file_get_contents( NNZ_DIR . 'assets/app.css' );
	$js    = file_get_contents( NNZ_DIR . 'assets/app.js' );
	$mk    = file_get_contents( NNZ_DIR . 'assets/markup.html' );
	$data  = nnz_get_data_json();
	$fonts = 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800'
		. '&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap';

	return "<!DOCTYPE html>\n<html lang=\"en-NZ\">\n<head>\n<meta charset=\"utf-8\">\n"
		. "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
		. "<title>Neurological data sources \xe2\x80\x94 Aotearoa New Zealand</title>\n"
		. "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n"
		. "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n"
		. "<link href=\"" . esc_url( $fonts ) . "\" rel=\"stylesheet\">\n"
		. "<style>\nhtml,body{margin:0;padding:0;background:#EDF0EE;overflow-x:hidden}\n"
		. $css . "\n</style>\n</head>\n<body>\n<div class=\"nnz nnz-panel\">\n"
		. $mk . "\n</div>\n<script id=\"nnz-data\" type=\"application/json\">"
		. $data . "</script>\n<script>\n" . $js . "\n</script>\n</body>\n</html>\n";
}

add_action( 'admin_post_nnz_export', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'nnz_export' ) ) {
		wp_die( 'Not permitted.' );
	}
	$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'html';
	$date = current_time( 'Y-m-d' );
	nocache_headers();
	if ( 'json' === $kind ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="neuronz-catalogue-' . $date . '.json"' );
		echo nnz_get_data_json(); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="neuronz-catalogue-' . $date . '.html"' );
		echo nnz_build_standalone(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	exit;
} );

function nnz_log( $status, $count, $skipped, $pubs = 0 ) {
	$log   = get_option( 'nnz_sync_log', array() );
	$log[] = array(
		'time'    => current_time( 'Y-m-d H:i' ),
		'status'  => $status,
		'count'   => $count,
		'pubs'    => $pubs,
		'skipped' => $skipped,
	);
	update_option( 'nnz_sync_log', array_slice( $log, -50 ), false );
}
