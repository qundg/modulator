<?php
/**
 * Plugin Name: Modulator
 * Description: Modulare Webentwicklung für Wordpress!
 * Version: 2.1.0
 * Author: quäntchen + glück
 * Author URI: https://www.qundg.de/
 */

defined ('ABSPATH') or die ();


// autoloader for Twig
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

            // composer.php should contain the Layotter element class
            if (file_exists($module_path . '/composer.php') AND class_exists('Layotter')) {
                require_once($module_path . '/composer.php');
            }

            // generic.php should contain a function for a generic representation of this module
            if (file_exists($module_path . '/generic.php')) {
                require_once($module_path . '/generic.php');
            }
        }
    }
}


class Modulator {

    const BACKEND_TEMPLATE = '/views/backend.twig';
    const FRONTEND_TEMPLATE = '/views/frontend.twig';
    const JS_FILE = '/assets/js/script.js';
    const CSS_FILE = '/assets/css/style.css';

    private static $base_path = ''; // base path for all modules
    private static $base_url = ''; // base URL for all modules
    private $module_path = ''; // specific path for this module
    private $module_url = ''; // specific URL for this module

    private $name;

    private static $twig_loader = null;
    private static $twig = null;


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

        // define path and URL for this module
        $this->module_path = self::$base_path . '/' . $this->name;
        $this->module_url  = self::$base_url . '/' . $this->name;

        // error if the directory doesn't exist
        if (!is_dir($this->module_path)) {
            throw new Exception(sprintf('Kein Modul mit dem Namen %s gefunden.', $this->name));
        }

        // include JS automatically
        $js_path = $this->module_path . self::JS_FILE;
        $js_url  = $this->module_url . self::JS_FILE;
        if (file_exists($js_path)) {
            wp_enqueue_script('module-' . $this->name, $js_url, ['jquery']);
        }

        // include CSS automatically
        $css_path = $this->module_path . self::CSS_FILE;
        $css_url  = $this->module_url . self::CSS_FILE;
        if (file_exists($css_path)) {
            wp_enqueue_style('module-' . $this->name, $css_url);
        }

        // create Twig instance once
        if (self::$twig_loader === null) {
            self::$twig_loader = new Twig_Loader_Filesystem();
            self::$twig = new Twig_Environment();
            self::$twig->setLoader(self::$twig_loader);
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

        if ($render_backend) {
            if (file_exists($this->module_path . self::BACKEND_TEMPLATE)) {
                echo self::$twig->render(self::BACKEND_TEMPLATE, $values);
            } else {
                throw new Exception(sprintf('Kein Backend-Template für das Modul %s gefunden.', $this->name));
            }
        } else {
            if (file_exists($this->module_path . self::FRONTEND_TEMPLATE)) {
                echo self::$twig->render(self::FRONTEND_TEMPLATE, $values);
            } else {
                throw new Exception(sprintf('Kein Backend-Template für das Modul %s gefunden.', $this->name));
            }
        }
    }


    /**
     * Add global variables that can be used in any module
     *
     * Access these variables like this: {{ globals.home_url }}
     *
     * @param array $values User-defined values
     * @return array Values with globals
     */
    private static function add_globals($values) {
        $values['globals'] = [
            'home_url' => home_url('/'),
            'theme_url' => get_template_directory_uri(),
            'images_url' => get_template_directory_uri() . '/assets/img'
        ];
        return $values;
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

}
