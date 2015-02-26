<?php
/**
 * Plugin Name: Modulator
 * Description:
 * Version: 1.0
 * Author: Dennis Hingst
 * Author URI: https://www.qundg.de
 */


// Skript bei Direktaufruf killen
if(!defined('ABSPATH')) die();


/**
 * Module im Unterverzeichnis modules des aktuellen Themes automatisch einbinden
 */
$modules_path = get_template_directory() . '/modules';
$module_dirs = scandir($modules_path);
foreach ($module_dirs as $module_dir) {
    $module_path = $modules_path . '/' . $module_dir;

    // Verzeichnisse mit . im Namen ignorieren
    if (strpos($module_dir, '.') !== false OR !is_dir($module_path)) {
        continue;
    }

    // composer.php soll Klasse für den Visual Composer enthalten
    if (file_exists($module_path . '/composer.php')) {
        require_once($module_path . '/composer.php');
    }

    // generic.php sollte eine Funktion für eine generische Darstellung des Elements enthalten
    if (file_exists($module_path . '/generic.php')) {
        require_once($module_path . '/generic.php');
    }
}


class Modulator {

    private static $base_path = ''; // Basispfad für alle Module
    private static $base_url = ''; // Basis-URL für alle Module

    private $vars = array();
    private $loops = array();
    private $module_path = '';
    private $module_url = '';


    /**
     * Neues Modul erzeugen
     *
     * @param $name string Verzeichnisname des Moduls (in ../modules)
     */
    function __construct ($name) {
        // einmal Basispfad und -URL für alle Module definieren
        if (empty(self::$base_path) OR empty(self::$base_url)) {
            self::$base_path = get_template_directory() . '/modules';
            self::$base_url = get_template_directory_uri() . '/modules';
        }

        // Modulname (= Verzeichnis) bereinigen
        $name = strval($name);
        $name = str_replace('.', '', ($name));

        // Pfad und URL zu diesem Modul
        $this->module_path = self::$base_path . '/' . $name;
        $this->module_url = self::$base_url . '/' . $name;

        if (!is_dir($this->module_path) OR !file_exists($this->module_path) . '/index.html') {
            throw New Exception(sprintf('Kein Modul mit dem Namen %s gefunden.', $name));
        }

        // JS automatisch einbinden
        $js_path = $this->module_path . '/script.js';
        $js_url = $this->module_url . '/script.js';
        if (file_exists($js_path)) {
            wp_enqueue_script('module-' . $name, $js_url);
        }
    }


    /**
     * Wert einer Variable definieren
     *
     * Variablen werden in der index.html des Moduls im Format %%name%% angelegt.
     *
     * @param $name string Name der Variable
     * @param $value string Wert der Variable
     */
    public function set_var ($name, $value) {
        $this->vars[$name] = strval($value);
    }


    /**
     * Element zur Verwendung in einem Loop hinzufügen
     *
     * Loops werden in der index.html des Moduls wie folgt verwendet:
     * %%loop:NAME%%
     *   ...
     *   %%NAME.VARIABLE%%
     *   ...
     * %%/loop:NAME%%
     *
     * @param $loop_name string Name des Loops
     * @param $new_item array Werte des Elements, z.B. array('titel' => 'beispiel', 'content' => 'blah blah')
     */
    public function add_loop_item ($loop_name, $new_item) {
        // Loop anlegen, falls dies das erste Elemente für den Loop ist
        if (!isset($this->loops[$loop_name])) {
            $this->loops[$loop_name] = array();
        }

        // alle Schlüssel als LOOPNAME.VARNAME formatieren
        $formatted_item = array();
        foreach ($new_item as $key => $value) { // alle Werte des Items durchlaufen, jeweils z.B. 'name' => 'Beispiel GmbH'
            $formatted_item[$loop_name . '.' . $key] = $value; // z.B. 'kunden.name' => 'Beispiel GmbH'
        }
        $this->loops[$loop_name][] = $formatted_item;
    }


    private function parse_loops ($template) {
        $matches = array();
        $regex = '/%%loop:(.*)%%(.*)%%\/loop:\1%%/misU'; // %%loop:match1%% match2 %%/loop:match1%%
        preg_match_all($regex, $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $replace = $match[0]; // kompletter Match inkl %%loop:NAME%% und %%/loop:NAME%%
            $loop = $match[1];
            $content = $match[2];

            if (isset($this->loops[$loop])) {
                $loop_content = '';
                foreach ($this->loops[$loop] as $loop_vars) {
                    $loop_content .= $this->parse_ifs($content, $loop_vars);
                }
                $template = str_replace($replace, $loop_content, $template);
            } else {
                $template = str_replace($replace, '', $template);
            }
        }

        return $template;
    }


    private function parse_ifs ($template) {
        $matches = array();
        $regex = '/%%if:(.*)%%(.*)%%\/if:\1%%/misU'; // %%if:match1%% match2 %%/if:match1%%
        preg_match_all($regex, $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $replace = $match[0]; // kompletter Match inkl %%if:NAME%% und %%/if:NAME%%
            $condition = $match[1];
            $content = $match[2];

            // prüfen, ob eine Variable zu dieser Condition vorliegt und ob sie == true ist
            // nicht ===, damit auch Strings als Condition genutzt werden können
            if (isset($this->vars[$condition]) AND $this->vars[$condition] == true) {
                $template = str_replace($replace, $content, $template);
            } else { // kompletten if-Block entfernen, wenn weder Condition noch Variable mit dem Namen vorliegen
                $template = str_replace($replace, '', $template);
            }
        }

        return $template;
    }


    private function insert_vars ($template, $vars) {
        $matches = array();
        $regex = '/%%(.*)%%/misU'; // %%match%%
        preg_match_all($regex, $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $replace = $match[0];
            $var = $match[1];

            if (isset($vars[$var])) {
                $template = str_replace($replace, $vars[$var], $template);
            } else {
                $template = str_replace($replace, '', $template);
            }
        }

        return $template;
    }


    /**
     * Ifs, Loops und Variablen im HTML ersetzen und Template ausgeben
     */
    public function output () {
        // Template-HTML holen
        $template = file_get_contents($this->module_path . '/index.html');

        $template = $this->parse_loops($template);
        $template = $this->insert_vars($template, $this->vars);
        $template = $this->parse_ifs($template);

        echo $template;
    }

}