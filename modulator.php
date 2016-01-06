<?php
/**
 * Plugin Name: Modulator
 * Description: Modulare Webentwicklung für Wordpress!
 * Version: 2.2.2
 * Author: quäntchen + glück
 * Author URI: https://www.qundg.de/
 * GitHub Plugin URI: qundg/modulator
 */


// Disallow calls outside of Wordpress
defined ('ABSPATH') or die();


// Global shortcuts
define('MODULATOR_HOME_URL', home_url('/'));
define('MODULATOR_THEME_URL', get_template_directory_uri());
define('MODULATOR_IMAGES_URL', get_template_directory_uri() . '/assets/img');


// Autoloader for Twig
require_once __DIR__ . '/vendor/autoload.php';


// Automatically include all modules in the theme_base/modules/ directory
add_action('plugins_loaded', 'modulator_include_modules');
function modulator_include_modules() {
    $modules_path = get_template_directory() . '/modules';
    if (is_dir($modules_path)) {
        $module_dirs = scandir($modules_path);
        foreach ($module_dirs as $module_dir) {
            $module_path = $modules_path . '/' . $module_dir;

            // ignore directories containing '.'
            if (strpos($module_dir, '.') !== false OR !is_dir($module_path)) {
                continue;
            }

            if (class_exists('Layotter')) {
                // layotter.php should contain the Layotter element class
                if (file_exists($module_path . '/layotter.php')) {
                    require_once($module_path . '/layotter.php');
                }
                // deprecated old file name: composer.php
                if (file_exists($module_path . '/composer.php')) {
                    require_once($module_path . '/composer.php');
                }
            }

            // factory.php may contain an optional factory function for this module
            if (file_exists($module_path . '/factory.php')) {
                require_once($module_path . '/factory.php');
            }
            // deprecated old file name: generic.php
            if (file_exists($module_path . '/generic.php')) {
                require_once($module_path . '/generic.php');
            }
        }
    }
}


// Make global Modulator vars accessible in Timber templates (via the globals namespace)
add_filter('timber_context', 'modulator_add_to_timber_context');
function modulator_add_to_timber_context($context) {
    $context['globals'] = array(
        'home_url' => MODULATOR_HOME_URL,
        'theme_url' => MODULATOR_THEME_URL,
        'images_url' => MODULATOR_IMAGES_URL,
    );
    return $context;
}


class Modulator {

    const BACKEND_TEMPLATE = '/views/backend.twig';
    const FRONTEND_TEMPLATE = '/views/frontend.twig';

    private $name;
    private static $base_path = ''; // base path for all modules
    private static $base_url = ''; // base URL for all modules
    private $module_path = ''; // specific path for this module

    private static $twig_loader = null;
    private static $twig = null;
    private static $timber_vars = null;


    /**
     * Create module
     *
     * @param string $name Directory name for this module
     * @throws Exception If the directory doesn't exist
     */
    function __construct($name) {
        // define base path and URL for all modules once
        if (empty(self::$base_path)) {
            self::$base_path = get_template_directory() . '/modules';
            self::$base_url  = get_template_directory_uri() . '/modules';
        }

        // clean module name (= directory)
        $this->name = strval($name);
        $this->name = str_replace('.', '', ($this->name));

        // get path for this module
        $this->module_path = self::$base_path . '/' . $this->name;

        // error if the directory doesn't exist
        if (!is_dir($this->module_path)) {
            throw new Exception(sprintf('Couldn\'t find a module by the name %s.', $this->name));
        }

        // create Twig instance once
        if (self::$twig_loader === null) {
            // unfortunately, Timber currently doesn't provide a way to share its Twig instance, so we'll have to create our own
            self::$twig_loader = new Twig_Loader_Filesystem();
            self::$twig = new Twig_Environment();
            self::$twig->setLoader(self::$twig_loader);

            // if Timber is available, make use of its custom functions and variables
            if (class_exists('Timber')) {
                self::$twig = apply_filters('twig_apply_filters', self::$twig);
                self::$twig = apply_filters('timber/twig/filters', self::$twig);
                self::$twig = apply_filters('timber/loader/twig', self::$twig);

                // remove timber.posts, its content is undefined in Modulator's context
                $timber_context = Timber::get_context();
                if (isset($timber_context['posts'])) {
                    unset($timber_context['posts']);
                }

                // will be used in $this->output()
                self::$timber_vars = $timber_context;
            }
        }

        // set templates paths for this template
        self::$twig_loader->setPaths([
            $this->module_path,
            $this->module_path . '/views'
        ]);
    }


    /**
     * Output view
     *
     * @param array $values Field values for the Twig template
     * @param bool $render_backend true to render the backend template, false to render frontend
     * @throws Exception If the Twig template file doesn't exist
     */
    public function output($values, $render_backend = false) {
        $values = self::add_globals($values);

        // include Timber variables
        if (self::$timber_vars !== null) {
            $values['timber'] = self::$timber_vars;
        }

        if ($render_backend) {
            if (file_exists($this->module_path . self::BACKEND_TEMPLATE)) {
                echo self::$twig->render(self::BACKEND_TEMPLATE, $values);
            } else {
                throw new Exception(sprintf('Backend template is missing for the module %s.', $this->name));
            }
        } else {
            if (file_exists($this->module_path . self::FRONTEND_TEMPLATE)) {
                echo self::$twig->render(self::FRONTEND_TEMPLATE, $values);
            } else {
                throw new Exception(sprintf('Frontend template is missing for the module %s.', $this->name));
            }
        }
    }


    /**
     * Return view HTML
     *
     * @param array $values Field values for the Twig template
     * @param bool $render_backend true to render the backend template, false to render frontend
     * @return string View HTML
     */
    public function get_html($values, $render_backend = false) {
        ob_start();
        $this->output($values, $render_backend);
        return ob_get_clean();
    }


    /**
     * Add helpful global variables that can be used in any module
     *
     * Access through the globals namespace: {{ globals.home_url }}
     *
     * @param array $values User-defined values
     * @return array Values with globals
     */
    private static function add_globals($values) {
        $values['globals'] = [
            'home_url' => MODULATOR_HOME_URL,
            'theme_url' => MODULATOR_THEME_URL,
            'images_url' => MODULATOR_IMAGES_URL
        ];
        return $values;
    }

}
