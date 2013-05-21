<?php
/**
 * NOVIUS OS - Web OS for digital communication
 *
 * @copyright  2011 Novius
 * @license    GNU Affero General Public License v3 or (at your option) any later version
 *             http://www.gnu.org/licenses/agpl-3.0.html
 * @link http://www.novius-os.org
 */

/**
 * Extended Config class to allow callback on the loaded config
 */
class Config extends \Fuel\Core\Config
{
    public static function load($file, $group = null, $reload = false, $overwrite = false)
    {
        if ($file == 'db') {
            $group = 'db';
        }
        if (!$reload and is_array($file) and is_string($group)) {
            Event::trigger_function('config|'.$group, array(&$file));
        }

        return parent::load($file, $group, $reload, $overwrite);
    }

    public static function save($file, $config)
    {
        if ($file !== \Fuel::$env.DS.'migrations' || \Config::get('novius-os.migration_config_file')) {
            return parent::save($file, $config);
        } else {
            return true;
        }
    }

    public static function mergeWithUser($key, $config)
    {
        $user = Session::user();

        Arr::set($config, 'configuration_id', static::getDbName($key));

        return \Arr::merge($config, \Arr::get($user->getConfiguration(), static::getDbName($key), array()));
    }

    public static function getDbName($key)
    {
        $key = str_replace('::', '/config/', $key);
        $key = str_replace('/', '.', $key);

        return $key;
    }

    public static function configFile($class)
    {
        $namespace = trim(\Inflector::get_namespace($class), '\\');

        $application = mb_strtolower($namespace);
        $file = mb_strtolower(str_replace('_', DS, \Inflector::denamespace($class)));

        if ($application !== 'nos') {
            $namespaces = \Nos\Config_Data::get('app_namespaces', null);
            if ($app = array_search($namespace, $namespaces)) {
                $application = $app;
            }
        }

        return array($application, $file);
    }

    public static function loadConfiguration($application_name, $file_name = null)
    {
        if ($file_name === null) {
            list($application_name, $file_name) = explode('::', $application_name);
        }
        $config = \Config::load($application_name.'::'.$file_name, true);
        $dependencies = \Nos\Config_Data::get('app_dependencies', array());

        if (!empty($dependencies[$application_name])) {
            foreach ($dependencies[$application_name] as $application => $dependency) {
                if ($dependency['extend_configuration']) {
                    $config = \Arr::merge($config, \Config::load($application.'::'.$file_name, true));
                }
            }
        }
        $config = \Arr::recursive_filter(
            $config,
            function($var) {
                return $var !== null;
            }
        );

        return $config;
    }

    public static function extendable_load($module_name, $file_name)
    {
        logger(\Fuel::L_WARNING, '\Config::extendable_load is deprecated. Please rename to \Config::loadConfiguration.');
        return static::loadConfiguration($module_name, $file_name);
    }

    public static function metadata($application_name)
    {
        $metadata = \Config::load($application_name.'::metadata', true);

        // More treatment for launchers
        // Small fix relative to permissions
        // We MUST have the key "application" in order to know if a launcher has or has not to be displayed...
        foreach (array('launchers', 'enhancers', 'templates') as $section) {
            if (!isset($metadata[$section])) {
                continue;
            }
            foreach ($metadata[$section] as &$item) {
                $item['i18n_application'] = $application_name;
                if (!isset($item['application'])) {
                    $item['application'] = $application_name;
                }
            }
        }

        return $metadata;
    }

    public static function application($application_name)
    {
        return static::loadConfiguration($application_name, 'config');
    }

    public static function actions($params = array())
    {
        if (!isset($params['models'])) {
            return array();
        }

        if (!isset($params['all_targets'])) {
            $params['all_targets'] = false;
        }

        $selected_actions = array();
        foreach ($params['models'] as $model) {
            $common_config = \Nos\Config_Common::load($model, array());
            $actions = $common_config['actions']['list'];
            $actions_order = $common_config['actions']['order'];

            $params['model'] = $model;

            foreach ($actions_order as $key) {
                if (!isset($actions[$key])) {
                    throw new \Exception('You are trying to order an action which key would be "'.$key.'" but such an action doesn\'t seem to exist.');
                }
                $action = $actions[$key];
                if (static::canAddAction($action, $params)) {
                    $selected_actions[$key] = $action;
                }
                unset($actions[$key]);
            }

            foreach ($actions as $key => $action) {
                if (static::canAddAction($action, $params)) {
                    $selected_actions[$key] = $action;
                }
            }

            foreach ($selected_actions as $key => $action) {
                if (!empty($params['item']) && isset($action['disabled'])) {
                    $selected_actions[$key]['disabled'] = static::getActionDisabledState($action['disabled'], $params['item']);
                }
            }
        }

        return $selected_actions;
    }

    protected static function canAddAction($action, $params)
    {
        if ($params['all_targets']) {
            return true;
        }

        if (isset($action['targets']) && (!isset($action['targets'][$params['target']]) || !$action['targets'][$params['target']])) {
            return false;
        }

        if (isset($action['visible'])) {
            return static::processCallbackValue($action['visible'], true, $params);
        }

        return true;
    }

    static public function getActionDisabledState($disabled, $item)
    {
        $common_config = \Nos\Config_Common::load(get_class($item), array());
        return static::processCallbackValue($disabled, false, $item, array('config' => $common_config));
    }

    /**
     * General function to process callback value : can be simple values, callbacks, or array of callbacks
     *
     * @param  mixed  $value  Value to process
     * @param  mixed  $positive_value   If the value is an array of callbacks, it defines which value is expected.
     * If callback return the expected value, then we call next callback. Otherwise, we return the value.
     *
     * All appended parameters are sent to the callback functions (if there is any)
     *
     * @return mixed
     */
    static public function processCallbackValue()
    {
        $arg_list = func_get_args();
        $value = $arg_list[0];
        $expected_value = $arg_list[1];
        $params = array_slice($arg_list, 2);
        if (is_callable($value)) {
            return call_user_func_array($value, $params);
        }

        if (is_array($value)) {
            foreach ($value as $value_item) {
                $new_arg_list = $arg_list;
                $new_arg_list[0] = $value_item;
                $return = call_user_func_array('static::processCallbackValue', $new_arg_list);
                if ($return !== $expected_value) {
                    return $return;
                }
            }
            return $expected_value;
        }

        return $value;
    }

    /**
     * Replace placeholders recursively in an array
     *
     * @param  mixed  $to_be_replaced  Array to look placeholders inside
     * @param  mixed  $placeholders   Array or object used to fetch placeholders
     * @param  bool   $remove_unset  Should placeholders not found in data be removed?
     * @return array  A new array with replacement done
     */
    public static function placeholderReplace($to_be_replaced, $placeholders, $remove_unset = true)
    {
        if (is_string($to_be_replaced)) {
            $retrieveFromData = function($placeholder, $fallback) use ($placeholders) {
                if (is_array($placeholders) && isset($placeholders[$placeholder])) {
                    return $placeholders[$placeholder];
                }
                if (is_object($placeholders)) {
                    if (isset($placeholders->{$placeholder})) {
                        return $placeholders->{$placeholder};
                    }
                    try {
                        return $placeholders->{$placeholder}();
                    } catch (\Exception $e) {
                    }
                }
                return $fallback;
            };

            $to_be_replaced = preg_replace_callback('/{{([\w]+)}}/', function($matches) use($retrieveFromData, $remove_unset) {
                return $retrieveFromData($matches[1], $remove_unset ? '' : $matches[0]);
            }, $to_be_replaced);
            $to_be_replaced = preg_replace_callback('/{{urlencode:([\w]+)}}/', function($matches) use($retrieveFromData, $remove_unset) {
                $value = $retrieveFromData($matches[1], $remove_unset ? '' : false);
                return $value === false ? $matches[0] : urlencode($value);
            }, $to_be_replaced);
            $to_be_replaced = preg_replace_callback('/{{htmlspecialchars:([\w]+)}}/', function($matches) use($retrieveFromData, $remove_unset) {
                $value = $retrieveFromData($matches[1], $remove_unset ? '' : false);
                return $value === false ? $matches[0] : htmlspecialchars($value);
            }, $to_be_replaced);
        } else if (is_array($to_be_replaced)) {
            foreach ($to_be_replaced as $key => $value) {
                $new_key = static::placeholderReplace($key, $placeholders, $remove_unset);
                $to_be_replaced[$new_key] = static::placeholderReplace($value, $placeholders, $remove_unset);
                if ($new_key !== $key) {
                    unset($to_be_replaced[$key]);
                }
            }
        }
        return $to_be_replaced;
    }

    public static function icon($application_or_model_name, $icon_key)
    {
        if (strpos($application_or_model_name, '\\') === false) {
            $metadata = \Nos\Config_Data::get('app_installed.'.$application_or_model_name);
        } else {
            $metadata = \Nos\Config_Common::load($application_or_model_name);
        }

        if (!empty($metadata['icons'][$icon_key])) {
            return $metadata['icons'][$icon_key];
        }
        return '';
    }

}

/* End of file config.php */
