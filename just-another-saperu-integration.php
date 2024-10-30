<?php
/*
Plugin Name: Just another Sape.ru integration
Plugin URI: https://darx.net/projects/sape-api
Description: Integrate `Sape.ru` monetization to your site in two clicks.
Version: 2.03
Author: kowack
Author URI: https://darx.net
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: sape-api
*/

// todo: add to Dashboard stats about money profit https://codex.wordpress.org/Dashboard_Widgets_API
// todo: work with `cache` plugin
// todo: translate fully

// added only in PHP 5.5.0
if ( ! function_exists( 'boolval' ) ) {
	function boolval( $val ) {
		return (bool) $val;
	}
}

class Sape_API {

	private static $_options = array(
		'sape_user'             => '', // like d12d0d074c7ba7f6f78d60e2bb560e3f
		'sape_part_is_client'   => true,
		'sape_part_is_context'  => true,
		'sape_part_is_articles' => false,
		'sape_widget_class'     => 'advert',
		'sape_login'            => ' ',
		'sape_password'         => ' ',
	);

	// is `wp-content/upload` because this dir always writable
	private static $_sape_path;

	private $_sape_options = array(
		'charset'                 => 'UTF-8', // since WP 3.5 site encoding always utf-8
		'multi_site'              => true,
		'show_counter_separately' => true,
	);

	/** @var SAPE_client */
	private $_sape_client;

	/** @var SAPE_context */
	private $_sape_context;

	/** @var SAPE_articles */
	private $_sape_articles;

	private $_plugin_basename;

	public function __construct() {
		$this->_plugin_basename = plugin_basename( __FILE__ );
		// misc
		load_plugin_textdomain( 'sape-api', false, dirname( $this->_plugin_basename ) . '/languages' );
		register_activation_hook( __FILE__, array( __CLASS__, 'activation_hook' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivation_hook' ) );
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall_hook' ) );

		// init
		add_action( 'init', array( &$this, 'init' ) );

		// _SAPE_USER
		if ( ! defined( '_SAPE_USER' ) ) {
			define( '_SAPE_USER', get_option( 'sape_user' ) );
		} else {
			if ( is_admin() ) {
				add_action( 'admin_init', function () {
					add_action( 'admin_notices', function () {
						echo '<div class="update-nag"><p>';
						echo sprintf( __( 'The constant %s has been already defined!', 'sape-api' ), '<code>_SAPE_USER</code>' );
						echo ' ';
						echo sprintf( __( 'Settings of the plugin %s is not used!', 'sape-api' ), '<code>Just another Sape.ru integration</code>' );
						echo '</p></div>';
					} );
				} );
			}
		}

		// common links
		if ( get_option( 'sape_part_is_client' ) ) {
			// add widget
			add_action( 'widgets_init', function () {
				register_widget( 'Sape_API_Widget_Links' );
			}, 1 );

			if ( _SAPE_USER !== '' ) {
				// add shortcode
				add_shortcode( 'sape', array( &$this, 'shortcode_sape' ) );
				add_filter( 'no_texturize_shortcodes', function ( $list ) {
					$list[] = 'sape';

					return $list;
				} );

				// show all links that remained, to not to get status ERROR
				add_action( 'wp_footer', array( &$this, 'render_remained_links' ), 1 );
			}
		}

		if ( get_option( 'sape_part_is_context' ) && _SAPE_USER !== '' ) {
			// context links, perfect work with `wptexturize` filter
			add_filter( 'the_content', array( &$this, '_sape_replace_in_text_segment' ), 11, 1 );
			add_filter( 'the_excerpt', array( &$this, '_sape_replace_in_text_segment' ), 11, 1 );
			remove_filter( 'the_content', 'do_shortcode' );
			remove_filter( 'the_excerpt', 'do_shortcode' );
			add_filter( 'the_content', 'do_shortcode', 12 );
			add_filter( 'the_excerpt', 'do_shortcode', 12 );
		}

		if ( get_option( 'sape_part_is_articles' ) && _SAPE_USER !== '' ) {
			// articles
			// todo: sape articles

			// show all announces that remained, to not to get status ERROR
			// add_filter( 'wp_footer', array( &$this, '_sape_return_announcements' ), 1 );
		}

		if ( _SAPE_USER !== '' ) {
			// sape js counter
			add_action( 'wp_footer', array( &$this, '_sape_return_counter' ), 1 );
		}
	}

	public function render_remained_links() {
		if ( $this->_getSapeClient()->_links_page > 0 ) {
			echo do_shortcode( '[sape block=1 orientation=1]' );
		}
	}

	public function init() {
		// admin panel
		add_action( 'admin_init', array( &$this, 'admin_init' ), 1 ); // init settings
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 1 ); // create page
		add_filter( 'plugin_action_links_' . $this->_plugin_basename, array( &$this, 'plugin_action_links' ) ); # links
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 1, 2 ); # plugins meta

		// show code on front page -- need to add site to sape system
		if ( is_front_page() ) {
			add_action( 'wp_footer', array( &$this, '_sape_return_links' ), 1 );
		}
	}

	public static function activation_hook() {
		// init options
		foreach ( self::$_options as $option => $value ) {
			add_option( $option, $value );
		}

		// let make dir and copy sape's files to uploads/.sape/
		if ( ! wp_mkdir_p( self::_getSapePath() ) ) {
			$path = plugin_basename( __FILE__ );
			deactivate_plugins( $path );

			$path_upload = ABSPATH . WPINC . '/upload';
			$link        = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $path ), 'activate-plugin_' . $path );
			$string      = '';
			$string .= 'Sape: ' . sprintf( __( '%s directory not writable', 'sape-api' ), '<i>`' . $path_upload . '`</i>' ) . '.<br/>';
			$string .= sprintf( __( 'Fix it and reactivate plugin %s', 'sape-api' ), '<b>' . $path . '</b>' ) . '.<br/>';
			$string .= '<a href="' . $link . '" class="edit">' . __( 'Activate' ) . '</a>';

			wp_die( $string );
		} else {
			// let copy file to created dir
			$local_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'sape';
			copy( $local_path . DIRECTORY_SEPARATOR . 'sape.php', self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php' );
			copy( $local_path . DIRECTORY_SEPARATOR . '.htaccess', self::_getSapePath() . DIRECTORY_SEPARATOR . '.htaccess' );
		}
	}

	public static function deactivation_hook() {
		// clear cache?
	}

	public static function uninstall_hook() {
		// delete options
		foreach ( self::$_options as $option => $value ) {
			delete_option( $option );
		}

		// delete sape's files
		self::_deleteDir( self::_getSapePath() );
	}

	// delete directory recursive with subdirectories and files
	private static function _deleteDir( $path ) {
		$class_func = array( __CLASS__, __FUNCTION__ );

		return is_file( $path ) ? @unlink( $path ) : array_map( $class_func, glob( $path . '/*' ) ) == @rmdir( $path );
	}

	private static function _getSapePath() {
		if ( self::$_sape_path === null ) {
			self::$_sape_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.sape';
		}

		return self::$_sape_path;
	}

	//
	// SAPE LINKS
	//

	private function _getSapeClient() {
		if ( $this->_sape_client === null ) {
			include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
			$this->_sape_client = new SAPE_client( $this->_sape_options );
		}

		return $this->_sape_client;
	}

	private function _sape_return_links( $count, $options ) {
		return $this->_getSapeClient()->return_links( $count, $options );
	}

	public function _sape_return_counter() {
		return $this->_getSapeClient()->return_counter();
	}

	//
	// SAPE CONTEXT
	//

	private function _getSapeContext() {
		if ( $this->_sape_context === null ) {
			include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
			$this->_sape_context = new SAPE_context( $this->_sape_options );
		}

		return $this->_sape_context;
	}

	public function _sape_replace_in_text_segment( $text ) {
		return $this->_getSapeContext()->replace_in_text_segment( $text );
	}

	//
	// SAPE ARTICLES
	//

	private function _getSapeArticles() {
		if ( $this->_sape_articles === null ) {
			include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
			$this->_sape_articles = new SAPE_articles( $this->_sape_options );
		}

		return $this->_sape_articles;
	}

	public function _sape_return_announcements( $n ) {
		return $this->_getSapeArticles()->return_announcements( $n );
	}

	public function _sape_return_process_request() {
		return $this->_getSapeArticles()->process_request();
	}

	//
	// WP Front area
	//

	// [sape block=1 count=1 orientation=1]
	public function shortcode_sape( $atts, $content = null ) {
		$atts = shortcode_atts( array(
			'count'       => null,
			'block'       => 0,
			'orientation' => 0
		), $atts );

		$text = $this->_sape_return_links(
			$atts['count'],
			array(
				'as_block'          => $atts['block'] == 1 ? true : false,
				'block_orientation' => $atts['orientation'],
			)
		);

		return ! empty( $text ) ? $text : $content;
	}

	//
	// WP Admin area
	//

	public function plugin_action_links( $links ) {
		unset( $links['edit'] );
		$settings_link = '<a href="admin.php?page=page_sape">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	public function plugin_row_meta( $links, $file ) {
		if ( $file == $this->_plugin_basename ) {
			$settings_link = '<a href="admin.php?page=page_sape">' . __( 'Settings' ) . '</a>';
			$links[]       = $settings_link;
			$links[]       = 'Code is poetry!';
		}

		return $links;
	}

	public function admin_menu() {
		add_menu_page(
			'Sape ' . __( 'Settings' ), // title
			'Sape API', // menu title
			'manage_options', // capability
			'page_sape', // menu slug
			array( &$this, 'page_sape' ), // callback
			'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAa0lEQVQ4T2OUqlr7n4ECwEg1A562BmG4Q7p6HVwMXR4mB3cBSAGyBmTT0OWQ+SgGoDsBZiBRBqBrRtaEz3u0cwGxMUufaCQ6DNDjHVcsIHsPZzrAFwvIFpEVC0S5AD0l4kpk1IsFYuMdXR0AYDBvEZHcuRUAAAAASUVORK5CYII='
		);

		add_submenu_page(
			'page_sape',
			'Sape ' . __( 'Settings' ), // title
			__( 'Settings' ), // menu title
			'manage_options', // capability
			'page_sape', // menu slug
			array( &$this, 'page_sape' ) // callback
		);

		add_submenu_page(
			'page_sape',
			'Sape ' . __( 'Statistics', 'sape-api' ), // title
			__( 'Statistics', 'sape-api' ), // menu title
			'manage_options', // capability
			'page_sape_stats', // menu slug
			array( &$this, 'page_sape_stats' ) // callback
		);
	}

	public function page_sape_stats() {
		include_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'table.php';
		include_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'rpc-client.php';

		$table         = new Sape_List_Table();
		$table->items  = get_transient( 'sape.get_sites_EXT' );
		$from_cache    = false;
		$login_success = false;
		if ( $table->items === false ) {

			$rpc = new RPC_Client( 'https://api.sape.ru/xmlrpc/' );

			$login    = get_option( 'sape_login' );
			$password = get_option( 'sape_password' );

			// todo: show balance and profile
			//		$rpc->query( 'sape.get_user' );
			//		$error    = $rpc->getErrorMessage();
			//		$response = $rpc->getResponse();
			//
			//		$hash = $response['hash'];
			//		$balance = $response['balance'];
			//		$amount_earned_today = $response['amount_earned_today'];
			//		$amount_earned_week = $response['amount_earned_week'];
			//		$amount_result_today = $response['amount_result_today'];
			//		$amount_result_week = $response['amount_result_week'];

			$ok = $rpc->query( 'sape.login', $login, $password, true ) && $rpc->query( 'sape.get_sites', array(
					'show_days_to_recheck'      => false,
					'show_block_display_params' => false,
					'show_traf_pages'           => true,
					'pn'                        => 0,
					'ps'                        => 0,
				) );

			if ( $ok ) {
				$login_success = true;
				$table->items  = $rpc->getResponse();
				if ( ! empty( $table->items ) ) {
					$ok = $rpc->query( 'sape.get_sites_links_count', 'OK' );
					if ( $ok ) {
						$links_OK = $rpc->getResponse();
						$ok       = $rpc->query( 'sape.get_sites_links_count', 'SLEEP' );
						if ( $ok ) {
							$links_SLEEP = $rpc->getResponse();
							$ok          = $rpc->query( 'sape.get_sites_links_count', 'WAIT_SEO' );
							if ( $ok ) {
								$links_WAIT = $rpc->getResponse();
								foreach ( $table->items as &$item ) {
									$item['nof_OK']    = 0;
									$item['nof_SLEEP'] = 0;
									$item['nof_WAIT']  = 0;
									foreach ( $links_OK as $link ) {
										if ( $item['id'] === $link['site_id'] ) {
											$item['nof_OK'] = $link['nof'];
										}
									}
									foreach ( $links_SLEEP as $link ) {
										if ( $item['id'] === $link['site_id'] ) {
											$item['nof_SLEEP'] = $link['nof'];
										}
									}
									foreach ( $links_WAIT as $link ) {
										if ( $item['id'] === $link['site_id'] ) {
											$item['nof_WAIT'] = $link['nof'];
										}
									}
								}
								set_transient( 'sape.get_sites_EXT', $table->items, 60 * 30 );
							}
						}
					}
				}
			}

		} else {
			$from_cache = $login_success = true;
		}

		$table->prepare_items();

		?>
		<div class="wrap">

			<h1>Sape client version: <b><?php echo $this->_getSapeClient()->_version ?></b></h1>

			<?php echo $from_cache ? 'Loaded from cache (30 minutes)' : 'Loaded directly from api.sape.ru' ?>
			<?php echo $login_success ? '' : '<br/>Login/password wrong!' ?>
			<?php $table->display(); ?>

			<form action="options.php" method="post" novalidate="novalidate" autocomplete="off">

				<?php
				settings_fields( 'sape_api' );
				do_settings_sections( 'page_sape_stats' );
				submit_button();
				?>

			</form>

		</div>
		<?php
	}

	public function page_sape() {
		?>
		<div class="wrap">

			<h1>Sape API</h1>

			<form action="options.php" method="post" novalidate="novalidate">

				<?php
				settings_fields( 'sape_base' );
				do_settings_sections( 'page_sape' );
				submit_button();
				?>

			</form>

		</div>
		<?php
	}

	public function admin_init() {
		// register settings `base`
		register_setting( 'sape_base', 'sape_user', 'trim' );
		register_setting( 'sape_base', 'sape_part_is_client', 'boolval' );
		register_setting( 'sape_base', 'sape_part_is_context', 'boolval' );
		register_setting( 'sape_base', 'sape_part_is_articles', 'boolval' );
		register_setting( 'sape_base', 'sape_widget_class', 'trim' );

		// add sections
		add_settings_section(
			'section__sape_identification', // id
			__( 'Identification part', 'sape-api' ), // title
			function () {
				echo __( 'No need download any files and archives or install anything manually.', 'sape-api' );
				echo '<br/>';
				echo __( 'The plugin will do everything automatically. Simply fill in the settings below.', 'sape-api' );
			}, // callback
			'page_sape' // page
		);

		add_settings_section(
			'section__sape_parts', // id
			__( 'Systems monetization', 'sape-api' ), // title
			function () {
				echo __( 'Indicate below which wage system to activate.', 'sape-api' );
				echo '<br/>';
				echo sprintf( __( 'Plugin perfect works with %s filter.', 'sape-api' ), '<code>wptexturize</code>' );
				echo '<br/>';
				echo sprintf( __( 'If you do not print all sold links on the page, remained links will be added into the footer of site in order to avoid appearance of links status %s.', 'sape-api' ), '<code>ERROR</code>' );
			}, // callback
			'page_sape' // page
		);

		// add fields
		add_settings_field(
			'sape_user', // id
			'_SAPE_USER', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_identification', // section
			array(
				'label_for' => 'sape_user',
				'type'      => 'text',
				'descr'     => '
Это ваш уникальный идентификатор (хеш).<br/>
Можете найти его на сайте
<a target="_blank" href="//www.sape.ru/r.wgAdHeyVEp.php">sape.ru</a> (реф)
кликнув по кнопке <b>"добавить площадку"</b>.<br/>
Будет похож на что-то вроде <b>d12d0d074c7ba7f6f78d60e2bb560e3f</b>.',
			) // args
		);

		add_settings_field(
			'sape_part_is_client', // id
			'Простые ссылки', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_client',
				'type'      => 'checkbox',
				'descr'     => '
Текстовые и блочные ссылки.<br/>
После активации будет доступен как <a target="_blank" href="' . admin_url( 'widgets.php' ) . '">виджет</a> для вывода ссылок, так и шорткод:<br/>
<code>[sape]</code> -- вывод всех ссылок в формате текста<br/>
<code>[sape count=2]</code> -- вывод лишь двух ссылок<br/>
<code>[sape count=2 block=1]</code> -- вывод ссылок в формате блока<br/>
<code>[sape count=2 block=1 orientation=1]</code> -- вывод ссылок в формате блока горизонтально<br/>
<code>[sape]код другой биржи, html, js[/sape]</code> -- вывод альтернативного текста при отсутствии ссылок.<br/>
Для вывода внутри темы (шаблона) используйте следующий код: <code>' . esc_attr( '<?php echo do_shortcode(\'[sape]\') ?>' ) . '</code>',
			) // args
		);

		add_settings_field(
			'sape_part_is_context', // id
			'Контекстные ссылки', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_context',
				'type'      => 'checkbox',
				'descr'     => 'Ссылки внутри записей.',
			) // args
		);

		add_settings_field(
			'sape_part_is_articles', // id
			'Размещение статей', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_articles',
				'type'      => 'checkbox',
				'descr'     => 'В процессе реализации...',
			) // args
		);

		// register settings `api`
		register_setting( 'sape_api', 'sape_login', 'trim' );
		register_setting( 'sape_api', 'sape_password', '' );

		// add sections
		add_settings_section(
			'section__sape_api', // id
			'API ' . __( 'Access', 'sape-api' ), // title
			function () {
			}, // callback
			'page_sape_stats' // page
		);

		// add fields
		add_settings_field(
			'sape_login', // id
			__( 'Login' ), // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape_stats', // page
			'section__sape_api', // section
			array(
				'label_for' => 'sape_login',
				'type'      => 'text',
				'descr'     => '',
			) // args
		);

		add_settings_field(
			'sape_password', // id
			__( 'Password' ) . ' (md5)', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape_stats', // page
			'section__sape_api', // section
			array(
				'label_for' => 'sape_password',
				'type'      => 'password',
				'descr'     => 'Результат md5 от пароля для пущей безопасности.',
			) // args
		);

	}

	public function render_settings_field( $atts ) {
		$id    = $atts['label_for'];
		$type  = $atts['type'];
		$descr = $atts['descr'];

		switch ( $type ) {
			default:
				$form_option = esc_attr( get_option( $id ) );
				echo "<input name=\"{$id}\" type=\"{$type}\" id=\"{$id}\" value=\"{$form_option}\" class=\"regular-{$type}\" />";
				break;
			case 'checkbox':
				$checked = checked( '1', get_option( $id ), false );
				echo '<label>';
				echo "<input name=\"{$id}\" type=\"checkbox\" id=\"{$id}\" value=\"1\" {$checked} />\n";
				echo __( 'Activate' );
				echo '</label>';
				break;
		}

		if ( ! empty( $descr ) ) {
			echo "<p class=\"description\">{$descr}</p>";
		}
	}

}

class Sape_API_Widget_Links extends WP_Widget {

	public function __construct() {
		parent::__construct(
			get_option( 'sape_widget_class' ) or 'advert',
			__( 'Sape: Links', 'sape-api' ),
			array(
				'description' => __( 'Show sape`s links on site. You can use several widget to show links in several places.', 'sape-api' ),
				'classname'   => get_option( 'sape_widget_class' ),
			)
		);
	}

	public function widget( $args, $instance ) {
		$o_count       = $instance['count'] ? ' count=' . $instance['count'] : '';
		$o_block       = $instance['block'] ? ' block=' . $instance['block'] : '';
		$o_orientation = $instance['orientation'] ? ' orientation=' . $instance['orientation'] : '';

		$shortcode = "[sape{$o_count}{$o_block}{$o_orientation}]{$instance['content']}[/sape]";

		$text = do_shortcode( $shortcode );

		if ( $text === '' || $text === $shortcode ) {
			$text = $instance['content'];
		}

		if ( ! empty( $text ) ) {
			echo $args['before_widget'];

			if ( ! empty( $instance['title'] ) ) {
				echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
			}

			echo $text;

			echo $args['after_widget'];
		}
	}

	public function form( $instance ) {
		$instance = wp_parse_args(
			(array) $instance,
			array( 'title' => '', 'block' => '0', 'count' => '', 'orientation' => '0', 'content' => '' )
		);
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php _e( 'Title:' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
			       name="<?php echo $this->get_field_name( 'title' ); ?>"
			       type="text"
			       value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>">
				<?php _e( 'Links count:', 'sape-api' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>"
			       name="<?php echo $this->get_field_name( 'count' ); ?>"
			       type="number"
			       value="<?php echo esc_attr( $instance['count'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'block' ); ?>">
				<?php _e( 'Format:', 'sape-api' ); ?>
			</label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'block' ); ?>"
			        name="<?php echo $this->get_field_name( 'block' ); ?>">
				<option value="0"<?php selected( $instance['block'], '0' ); ?>>
					<?php _e( 'Text', 'sape-api' ); ?>
				</option>
				<option value="1"<?php selected( $instance['block'], '1' ); ?>>
					<?php _e( 'Block', 'sape-api' ); ?>
				</option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'orientation' ); ?>">
				<?php _e( 'Block orientation:', 'sape-api' ); ?>
			</label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'orientation' ); ?>"
			        name="<?php echo $this->get_field_name( 'orientation' ); ?>">
				<option value="0"<?php selected( $instance['orientation'], '0' ); ?>>
					<?php _e( 'Vertically', 'sape-api' ); ?>
				</option>
				<option value="1"<?php selected( $instance['orientation'], '1' ); ?>>
					<?php _e( 'Horizontally', 'sape-api' ); ?>
				</option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'content' ); ?>">
				<?php _e( 'Alternative text:', 'sape-api' ); ?>
			</label>
			<textarea class="widefat" id="<?php echo $this->get_field_id( 'content' ); ?>"
			          name="<?php echo $this->get_field_name( 'content' ); ?>"
			><?php echo esc_attr( $instance['content'] ); ?></textarea>
		</p>

		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance['count']       = (int) $new_instance['count'];
		$new_instance['block']       = (int) $new_instance['block'];
		$new_instance['orientation'] = (int) $new_instance['orientation'];
		$new_instance['content']     = trim( $new_instance['content'] );

		return $new_instance;
	}
}

$sape_api = new Sape_API();