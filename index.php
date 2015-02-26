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
if (is_dir($modules_path)) {
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
}


class Modulator {

    private static $base_path = ''; // Basispfad für alle Module
    private static $base_url = ''; // Basis-URL für alle Module

    private $vars = array();
    private $loops = array();
    private $module_path = '';
    private $module_url = '';


    /**
     * Modul erzeugen
     *
     * @param string $name Verzeichnisname des Moduls
     * @throws Exception Wenn kein Modul mit diesem Namen existiert
     */
    function __construct($name) {
        // einmalig Basispfad und -URL für alle Module definieren
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

        // Fehler, wenn kein Verzeichnis mit dem Namen existiert oder keine index.html enthalten ist
        if (!is_dir($this->module_path) OR !file_exists($this->module_path . '/index.html')) {
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
     * @param string $name Name der Variable
     * @param string $value Wert der Variable
     */
    public function set_var($name, $value) {
        $this->vars[$name] = $value;
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
     * @param string $loop_name Name des Loops
     * @param array $new_item Werte des Elements, z.B. array('titel' => 'beispiel', 'content' => 'blah blah')
     */
    public function add_loop_item($loop_name, $new_item) {
        // Loop anlegen, falls dies das erste Elemente für den Loop ist
        if (!isset($this->loops[$loop_name])) {
            $this->loops[$loop_name] = array();
        }

        $this->loops[$loop_name][] = $new_item;
    }


    /**
     * Ifs in einem Template-String verarbeiten
     *
     * @param string $template HTML des Templates
     * @param array $vars Variablen und ihre Werte zum Prüfen der Ifs (je 'varname' => 'Wert')
     * @param string $prefix Präfix für Ifs, z.B. wäre bei %%if:loopname.varname%% der Präfix 'loopname'
     * @return string Template-String mit eingesetzten Ifs
     */
    private function parse_ifs($template, $vars, $prefix = '') {
        if ($prefix) {
            $regex = '/%%if:' . $prefix . '\.(.*)%%(.*)%%\/if:' . $prefix . '\.\1%%/misU'; // %%if:PREFIX.match1%% match2 %%/if:PREFIX.match1%%
        } else {
            $regex = '/%%if:([^.]*)%%(.*)%%\/if:\1%%/misU'; // %%if:match1%% match2 %%/if:match1%% - match1 ohne .
        }

        $matches = array();
        preg_match_all($regex, $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $replace = $match[0]; // kompletter Match inkl %%if:NAME%% und %%/if:NAME%%
            $condition = $match[1];
            $content = $match[2];

            // prüfen, ob eine Variable zu dieser Condition vorliegt und ob sie == true ist
            // nicht ===, damit auch Strings als Condition genutzt werden können
            // != true statt == true, wenn ein ! am Anfang der Condition steht
            if (substr($condition, 0, 1) == '!') {
                $condition_is_true = (!isset($vars[$condition]) OR !$vars[$condition]);
            } else {
                $condition_is_true = (isset($vars[$condition]) AND $vars[$condition]);
            }

            if ($condition_is_true) {
                $template = str_replace($replace, $content, $template);
            } else { // kompletten if-Block entfernen, wenn weder Condition noch Variable mit dem Namen vorliegen
                $template = str_replace($replace, '', $template);
            }
        }

        return $template;
    }


    /**
     * Loops in einem Template-String verarbeiten
     *
     * @param string $template HTML des Templates
     * @param array $loops Zu verarbeitende Loops (je 'loopname' => array('varname' => 'Wert'))
     * @return string Template-String mit verarbeiteten Loops
     */
    private function parse_loops($template, $loops) {
        $matches = array();
        $regex = '/%%loop:(.*)%%(.*)%%\/loop:\1%%/misU'; // %%loop:match1%% match2 %%/loop:match1%%
        preg_match_all($regex, $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $replace = $match[0]; // kompletter Match inkl %%loop:NAME%% und %%/loop:NAME%%
            $loop = $match[1];
            $content = $match[2];

            if (isset($loops[$loop])) {
                $loop_content = '';
                foreach ($loops[$loop] as $loop_vars) {
                    $parsed_content = $this->parse_ifs($content, $loop_vars, $loop);
                    $parsed_content = $this->insert_vars($parsed_content, $loop_vars, $loop);
                    $loop_content .= $parsed_content;
                }
                $template = str_replace($replace, $loop_content, $template);
            } else {
                $template = str_replace($replace, '', $template);
            }
        }

        return $template;
    }


    /**
     * Variablen in einen Template-String einsetzen
     *
     * @param string $template HTML des Templates
     * @param array $vars Einzusetzende Variablen (je 'varname' => 'Wert')
     * @param string $prefix Präfix für Variablen, z.B. wäre bei %%loopname.varname%% der Präfix 'loopname'
     * @return string Template-String mit eingesetzten Variablen
     */
    private function insert_vars($template, $vars, $prefix = '') {
        if ($prefix) {
            $regex = '/%%' . $prefix . '\.(.*)%%/misU'; // %%PREFIX.match%%
        } else {
            $regex = '/%%([^.]*)%%/misU'; // %%match%% ohne .
        }

        $matches = array();
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
    public function output() {
        // Template-HTML holen
        $template = file_get_contents($this->module_path . '/index.html');

        $template = $this->parse_ifs($template, $this->vars);
        $template = $this->parse_loops($template, $this->loops);
        $template = $this->insert_vars($template, $this->vars);

        echo $template;
    }

}