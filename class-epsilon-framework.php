<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once dirname( __FILE__ ) . '/class-epsilon-autoloader.php';

/**
 * Class Epsilon_Framework
 */
class Epsilon_Framework {
	/**
	 * @var array|mixed
	 */
	private $controls = array();
	/**
	 * @var array|mixed
	 */
	private $sections = array();
	/**
	 * @var mixed|string
	 */
	private $path = '/inc/libraries';
	/**
	 * @var bool
	 */
	private $backup = false;

	/**
	 * Epsilon_Framework constructor.
	 *
	 * @param $args array
	 */
	public function __construct( $args ) {
		foreach ( $args as $k => $v ) {

			if ( ! in_array( $k, array( 'controls', 'sections', 'path', 'backup' ) ) ) {
				continue;
			}

			$this->$k = $v;
		}

		/**
		 * Let's initiate a backup instance
		 */
		if ( $this->backup ) {
			$backup = Epsilon_Content_Backup::get_instance();
		}
		/**
		 * Define URI and PATH for the framework
		 */
		define( 'EPSILON_URI', get_template_directory_uri() . $this->path . '/epsilon-framework' );
		define( 'EPSILON_PATH', get_template_directory() . $this->path . '/epsilon-framework' );
		define( 'EPSILON_BACKUP', $this->backup );
		/**
		 * Admin enqueues
		 */
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		/**
		 * Customizer enqueues & controls
		 */
		add_action( 'customize_register', array( $this, 'init_controls' ), 10 );

		add_action( 'customize_controls_enqueue_scripts', array( $this, 'customizer_enqueue_scripts' ), 25 );
		add_action( 'customize_preview_init', array( $this, 'customize_preview_styles' ), 25 );

		/**
		 * Action for easier AJAX handling
		 */
		add_action( 'wp_ajax_epsilon_framework_ajax_action', array(
			$this,
			'epsilon_framework_ajax_action',
		) );
		add_action( 'wp_ajax_nopriv_epsilon_framework_ajax_action', array(
			$this,
			'epsilon_framework_ajax_action',
		) );

		/**
		 * Repeater fields templates
		 */
		add_action( 'customize_controls_print_footer_scripts', array(
			'Epsilon_Repeater_Templates',
			'field_repeater_js_template',
		), 0 );
	}

	/**
	 * Init custom controls
	 *
	 * @param object $wp_customize
	 */
	public function init_controls( $wp_customize ) {
		$path = get_template_directory() . $this->path . '/epsilon-framework';

		foreach ( $this->controls as $control ) {
			if ( file_exists( $path . '/customizer/controls/class-epsilon-control-' . $control . '.php' ) ) {
				require_once $path . '/customizer/controls/class-epsilon-control-' . $control . '.php';
			}
			if ( file_exists( $path . '/customizer/settings/class-epsilon-setting-' . $control . '.php' ) ) {
				require_once $path . '/customizer/settings/class-epsilon-setting-' . $control . '.php';
			}
		}

		foreach ( $this->sections as $section ) {
			if ( file_exists( $path . '/customizer/sections/class-epsilon-section-' . $section . '.php' ) ) {
				require_once $path . '/customizer/sections/class-epsilon-section-' . $section . '.php';
			}
		}
	}

	/**
	 * @since 1.2.0
	 */
	public function enqueue() {
		wp_enqueue_script( 'epsilon-admin', get_template_directory_uri() . $this->path . '/epsilon-framework/assets/js/epsilon-admin.min.js', array( 'jquery' ) );
	}

	/**
	 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
	 */
	public function customize_preview_styles() {
		wp_enqueue_style( 'epsilon-styles', get_template_directory_uri() . $this->path . '/epsilon-framework/assets/css/style.css' );
		wp_enqueue_script( 'epsilon-previewer', get_template_directory_uri() . $this->path . '/epsilon-framework/assets/js/epsilon-previewer.js', array(
			'jquery',
			'customize-preview',
		), 2, true );

		wp_localize_script( 'epsilon-previewer', 'WPUrls', array(
			'siteurl' => get_option( 'siteurl' ),
			'theme'   => get_template_directory_uri(),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		) );
	}

	/*
	 * Our Customizer script
	 *
	 * Dependencies: Customizer Controls script (core)
	 */
	public function customizer_enqueue_scripts() {
		wp_enqueue_script( 'epsilon-object', get_template_directory_uri() . $this->path . '/epsilon-framework/assets/js/epsilon.js', array(
			'jquery',
			'customize-controls',
		) );
		wp_localize_script( 'epsilon-object', 'WPUrls', array(
			'siteurl' => get_option( 'siteurl' ),
			'theme'   => get_template_directory_uri(),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		) );

		wp_localize_script( 'epsilon-object', 'EpsilonTranslations', array(
			'remove'     => esc_html__( 'Remove', 'epsilon-framework' ),
			'add'        => esc_html__( 'Add', 'epsilon-framework' ),
			'selectFile' => esc_html__( 'Select a file', 'epsilon-framework' ),
			'row'        => esc_html__( 'Row', 'epsilon-framework' ),
		) );

		wp_enqueue_style( 'font-awesome', get_template_directory_uri() . $this->path . '/epsilon-framework/assets/vendors/fontawesome/font-awesome.css' );
		wp_enqueue_style( 'epsilon-styles', get_template_directory_uri() . $this->path . '/epsilon-framework/assets/css/style.css' );
	}

	/**
	 * Ajax handler
	 */
	public function epsilon_framework_ajax_action() {
		if ( 'epsilon_framework_ajax_action' !== $_POST['action'] ) {
			wp_die(
				json_encode(
					array(
						'status' => false,
						'error'  => __( 'Not allowed', 'epsilon-framework' ),
					)
				)
			);
		}

		if ( count( $_POST['args']['action'] ) !== 2 ) {
			wp_die(
				json_encode(
					array(
						'status' => false,
						'error'  => __( 'Not allowed', 'epsilon-framework' ),
					)
				)
			);
		}

		if ( ! class_exists( $_POST['args']['action'][0] ) ) {
			wp_die(
				json_encode(
					array(
						'status' => false,
						'error'  => __( 'Class does not exist', 'epsilon-framework' ),
					)
				)
			);
		}

		$class  = $_POST['args']['action'][0];
		$method = $_POST['args']['action'][1];
		$args   = $_POST['args']['args'];

		$response = $class::$method( $args );

		if ( is_array( $response ) ) {
			wp_die( json_encode( $response ) );
		}

		if ( 'ok' == $response ) {
			wp_die(
				json_encode(
					array(
						'status'  => true,
						'message' => 'ok',
					)
				)
			);
		}

		wp_die(
			json_encode(
				array(
					'status'  => false,
					'message' => 'nok',
				)
			)
		);
	}
}
