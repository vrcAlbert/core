<?php
/**
 * NOVIUS OS - Web OS for digital communication
 *
 * @copyright  2011 Novius
 * @license    GNU Affero General Public License v3 or (at your option) any later version
 *             http://www.gnu.org/licenses/agpl-3.0.html
 * @link http://www.novius-os.org
 */

namespace Nos;

use Format, Input, View;

/**
 * The cloud Controller.
 *
 * A basic controller example.  Has examples of how to set the
 * response body and status.
 *
 * @package  app
 * @extends  Controller
 */
class Controller_Admin_Appdesk extends Controller_Admin_Application
{
    public function before()
    {
        parent::before();
        $this->load_config();
    }

    public function load_config()
    {
        list($application, $file_name) = \Config::configFile(get_called_class());
        $this->config = \Config::mergeWithUser($application.'::'.$file_name, static::process_config($application, $this->config));

        return $this->config;
    }

    public function action_index($view = null)
    {
        if (empty($view)) {
            $view = \Input::get('view', $this->config['selectedView']);
        }
        $this->config['selectedView'] = $view;

        if (empty($this->config['custom'])) {
            $this->config['custom'] = array(
                'from' => 'default',
            );
        }

        $view = View::forge('admin/appdesk');

        $contexts = Tools_Context::contexts();
        $locales = Tools_Context::locales();
        $sites = Tools_Context::sites();

        foreach ($contexts as $context => $params) {
            $site = Tools_Context::site_code($context);
            $locale = Tools_Context::locale_code($context);

            if (!isset($sites[$site]['locales'])) {
                $sites[$site]['locales'] = array();
            }
            $sites[$site]['locales'][] = $locale;

            if (!isset($locales[$locale]['sites'])) {
                $locales[$locale]['sites'] = array();
            }
            $locales[$locale]['sites'][] = $site;
        }

        $params = array_merge(
            array(
                'contexts' => $contexts,
                'locales' => $locales,
                'sites' => $sites,
            ),
            $this->config
        );

        $view->set('appdesk', \Format::forge($params)->to_json(), false);

        return $view;
    }

    public static function process_config($application, $config)
    {
        $valid_keys = array('query', 'search_text', 'dataset', 'selectedView', 'views', 'appdesk', 'tree', 'configuration_id');
        if (isset($config['model'])) {
            $namespace_model = substr($config['model'], 0, strrpos($config['model'], '\\'));

            $appdesk_path = static::get_path();
            $inspectors_class_prefix = get_called_class();
            $inspectors_class_prefix = explode('_', $inspectors_class_prefix);
            $inspectors_class_prefix[count($inspectors_class_prefix) - 1] = 'Inspector';
            $inspectors_class_prefix = implode('_', $inspectors_class_prefix).'_';

            $application_config = \Config::metadata($application);

            $behaviours = array(
                'contextable' => $config['model']::behaviours('Nos\Orm_Behaviour_Contextable', false),
                'twinnable' => $config['model']::behaviours('Nos\Orm_Behaviour_Twinnable', false),
                'sharable' => $config['model']::behaviours('Nos\Orm_Behaviour_Sharable', false),
                'tree' => $config['model']::behaviours('Nos\Orm_Behaviour_Tree', false),
                'url' => $config['model']::behaviours('Nos\Orm_Behaviour_Urlenhancer', false),
                'sortable' => $config['model']::behaviours('Nos\Orm_Behaviour_Sortable', false),
            );


            if (!isset($config['data_mapping'])) {
                $config['data_mapping'] = null;
            }

            $common_config = \Nos\Config_Common::load($config['model'], $config['data_mapping']);
            $data_mapping = isset($common_config['data_mapping']) ? $common_config['data_mapping'] : array();

            if (!isset($config['query'])) {
                $config['query'] = isset($common_config['query']) ? $common_config['query'] : array();
            }

            if (!isset($config['query']['model'])) {
                $config['query']['model'] = $config['model'];
            }

            if (!isset($config['search_text'])) {
                $config['search_text'] = $common_config['search_text'];
            }

            if (!isset($config['dataset'])) {
                $config['dataset'] = $data_mapping;
            }
            $config['dataset']['id'] = array(
                'column' => 'id',
                'visible' => false
            );

            if (!isset($config['dataset']['actions'])) {
                $item_actions = \Config::actions(array('models' => array($config['model']), 'type' => 'list'));
                foreach ($item_actions as $action_key => $action_value) {

                    if (isset($action_value['enabled'])) {
                        $config['dataset']['actions'][$action_key] = $action_value['enabled'];
                    }
                }
            }



            if (!isset($config['selectedView'])) {
                $config['selectedView'] = isset($common_config['selectedView']) ? $common_config['selectedView'] : 'default';
            }

            if (!isset($config['views'])) {
                $config['views'] = isset($common_config['views']) ? $common_config['views'] : array(
                    'default' => array(
                        'name' => __('Default view'),
                    )
                );
            }

            if (!isset($config['inspectors'])) {
                $config['inspectors'] = array();
            }

            if (!isset($config['toolbar'])) {
                $config['toolbar'] = array();
            }

            $wasToolbarModelsSet = isset($config['toolbar']['models']);
            if (!isset($config['toolbar']['models'])) {
                $config['toolbar']['models'] = array($config['model']);
            }

            if (!isset($config['toolbar']['actions'])) {
                $config['toolbar']['actions'] = array();
            }


            if (!isset($config['tree'])) {
                if ($behaviours['tree']) {
                    $config['tree'] = array();
                }
            }

            if (isset($config['tree'])) {


                if (!isset($config['tree']['models'])) {
                    $config['tree']['models'] = array();
                }

                if (count($config['tree']['models']) == 0) {
                    $config['tree']['models'][] = array();
                }

                foreach ($config['tree']['models'] as &$model) {
                    if (!isset($model['model'])) {
                        $model['model'] = $config['model'];
                    }

                    $sortable_behaviour = $model['model']::behaviours('Nos\Orm_Behaviour_Sortable', false);
                    if (!isset($model['order_by']) && $sortable_behaviour) {
                        $model['order_by'] = $sortable_behaviour['sort_property'];
                    }

                    if (!isset($model['childs'])) {
                        $model['childs'] = array($model['model']);
                    }

                    if (!isset($model['dataset'])) {
                        $model['dataset'] = $config['dataset'];
                    }
                }


                if (!isset($config['tree']['roots'])) {
                    $config['tree']['roots'] = array();
                }

                if (count($config['tree']['roots']) == 0) {
                    $config['tree']['roots'][] = array();
                }

                foreach ($config['tree']['roots'] as &$root) {
                    if (!isset($root['model'])) {
                        $root['model'] = $config['model'];
                    }

                    if (!isset($root['where'])) {
                        $tree_behaviour = $root['model']::behaviours('Nos\Orm_Behaviour_Tree', false);
                        $relation = $root['model']::relations($tree_behaviour['parent_relation']);
                        $root['where'] = array(array($relation->key_from[0], 'IS', \DB::expr('NULL')));
                    }

                    $sortable_behaviour = $root['model']::behaviours('Nos\Orm_Behaviour_Sortable', false);
                    if (!isset($root['order_by']) && $sortable_behaviour) {
                        $root['order_by'] = $sortable_behaviour['sort_property'];
                    }
                }
            }

            $inspectors = array();
            foreach ($config['inspectors'] as $key => $value) {
                $inspector_key = is_array($value) ? $key : $value;
                $inspector_name = $inspectors_class_prefix.ucfirst($inspector_key);
                list($application, $file_name) = \Config::configFile($inspector_name);
                $inspector_config = \Config::loadConfiguration($application, $file_name);
                if (is_array($value)) {
                    $inspector_config = \Arr::merge($inspector_config, $value);
                }
                $inspector_config = $inspector_name::process_config($application, $inspector_config);
                $inspectors[$inspector_key] = $inspector_config;
                if (isset($inspector_config['model']) && !$wasToolbarModelsSet) {
                    $inspector_model_namespace = substr($inspector_config['model'], 0, strrpos($inspector_config['model'], '\\'));
                    if ($inspector_model_namespace == $namespace_model) {
                        $config['toolbar']['models'][] = $inspector_config['model'];
                    }
                }
            }

            $config['inspectors'] = $inspectors;

            if (!isset($config['inputs'])) {
                $config['inputs'] = array();
            }

            foreach ($config['inspectors'] as $inspector_config) {
                if ($inspector_config['input'] && !isset($config['inputs'][$inspector_config['input']['key']])) {
                    $config['inputs'][$inspector_config['input']['key']] = $inspector_config['input']['query'];
                }
            }

            if (!isset($config['appdesk'])) {
                $config['appdesk'] = array();
            }

            if (!isset($config['appdesk']['tab'])) {
                $config['appdesk']['tab'] = array(
                    'label' => $application_config['name'],
                    'iconUrl' => \Config::icon($application, 32),
                );
            }

            if (!isset($config['appdesk']['reloadEvent'])) {
                $config['appdesk']['reloadEvent'] = isset($common_config['reloadEvent']) ? $common_config['reloadEvent'] : $config['model'];
            }

            if (!isset($config['appdesk']['actions'])) {
                $config['appdesk']['actions'] = \Config::actions(array('models' => array($config['model']), 'type' => 'list'));
            }

            if (!isset($config['appdesk']['appdesk'])) {
                $config['appdesk']['appdesk'] = array();
            }

            if (!isset($config['appdesk']['appdesk']['buttons'])) {
                $config['appdesk']['appdesk']['buttons'] = array();
                $actions = \Arr::merge(\Config::actions(array('models' => $config['toolbar']['models'], 'type' => 'appdeskToolbar')), $config['toolbar']['actions']);
                foreach ($actions as $key => $action) {
                    if ($action !== false) {
                        $config['appdesk']['appdesk']['buttons'][$key] = $action;
                    }
                }
            }

            if (!isset($config['appdesk']['appdesk']['splittersVertical'])) {
                if (isset($config['splittersVertical'])) {
                    $config['appdesk']['appdesk']['splittersVertical'] = $config['splittersVertical'];
                } else {
                    $config['appdesk']['appdesk']['splittersVertical'] = 250; // @todo could it be done via javascript
                }
            }

            if (!isset($config['appdesk']['appdesk']['inspectors'])) {
                $config['appdesk']['appdesk']['inspectors'] = $config['inspectors'];
            }

            $new_inspectors = array();
            foreach ($config['appdesk']['appdesk']['inspectors'] as $key => $inspector_config) {
                $new_inspectors[$key] = $inspector_config['appdesk'];
            }
            $config['appdesk']['appdesk']['inspectors'] = $new_inspectors;


            if (!isset($config['appdesk']['appdesk']['grid'])) {
                $config['appdesk']['appdesk']['grid'] = array();
            }

            if (!isset($config['appdesk']['appdesk']['grid']['urlJson'])) {
                $config['appdesk']['appdesk']['grid']['urlJson'] = $appdesk_path.'/json';
            }

            if (!isset($config['appdesk']['appdesk']['grid']['columns'])) {
                $config['appdesk']['appdesk']['grid']['columns'] = array();
                foreach ($config['dataset'] as $key => $value) {
                    if ($key == 'context') {
                        $config['appdesk']['appdesk']['grid']['columns'][$key] = array('context' => true);
                    } else if ($key == 'published') {
                        $config['appdesk']['appdesk']['grid']['columns']['published'] = array(
                            'headerText' => __('Status'),
                            'dataKey' => 'publication_status'
                        );
                    } else if (!isset($value['visible']) || $value['visible']) {
                        $config['appdesk']['appdesk']['grid']['columns'][$key]['headerText'] = isset($value['headerText']) ? $value['headerText'] : '';
                        $config['appdesk']['appdesk']['grid']['columns'][$key]['dataKey'] = $key;
                    }

                }

                $config['appdesk']['appdesk']['grid']['columns']['actions'] = array('actions' => array());
                foreach ($config['appdesk']['actions'] as $action_key => $action_value) {
                    $config['appdesk']['appdesk']['grid']['columns']['actions']['actions'][] = $action_key;
                }
            }

            if (!isset($config['appdesk']['appdesk']['treeGrid'])) {
                $config['appdesk']['appdesk']['treeGrid'] = array();
            }

            if (!isset($config['appdesk']['appdesk']['treeGrid']['urlJson'])) {
                $config['appdesk']['appdesk']['treeGrid']['urlJson'] = $appdesk_path.'/tree_json';
            }
        }

        foreach ($config as $key => $idc) {
            if (!in_array($key, $valid_keys)) {
                unset($config[$key]);
            }
        }

        return $config;
    }

    public function action_json()
    {
        $config = $this->config;
        $where = function ($query) use ($config) {
            foreach (\Arr::get($config, 'inputs', array()) as $input => $condition) {
                $value = Input::get('inspectors');
                if (isset($value[$input])) {
                    $value = $value[$input];
                    if (is_callable($condition)) {
                        $query = $condition($value, $query);
                    }
                }
            }

            $value = Input::get('inspectors.search');
            $condition = $config['search_text'];
            if (!empty($value)) {
                $query->and_where_open();
                foreach ((array) $condition as $field) {
                    if (is_callable($field)) {
                        $query = $field($value, $query);
                    } else {
                        $query->or_where(array($field, 'LIKE', '%'.$value.'%'));
                    }
                }
                $query->and_where_close();
            }

            Filter::apply($query, $config);

            return $query;
        };

        $return = $this->items(
            array_merge(
                $this->config['query'],
                array(
                    'callback' => array_merge(\Arr::get($this->config['query'], 'callback', array()), array($where)),
                    'dataset' => $this->config['dataset'],
                    'context' => Input::get('context', null),
                    'limit' => intval(Input::get('limit', \Arr::get($this->config['query'], 'limit'))),
                    'offset' => intval(Input::get('offset', 0)),
                )
            )
        );

        $json = array(
            'get' => '',
            'query' => '',
            'query2' => '',
            'offset' => $return['offset'],
            'items' => $return['items'],
            'total' => $return['total'],
        );

        if (\Fuel::$env === \Fuel::DEVELOPMENT) {
            $json['get'] = Input::get();
            $json['query'] = $return['query'];
            $json['query2'] = $return['query2'];
        }
        if (\Input::get('debug') !== null) {
            \Debug::dump($json);
            exit();
        }

        \Response::json($json);
    }

    public function action_tree_json()
    {

        $tree_config = $this->config['tree'];
        $tree_config['id'] = $this->config['configuration_id'];

        $json = $this->tree($tree_config);

        if (\Fuel::$env === \Fuel::DEVELOPMENT) {
            $json['get'] = Input::get();
        }
        if (\Input::get('debug') !== null) {
            \Debug::dump($json);
            exit();
        }

        \Response::json($json);
    }
}

/* End of file list.php */
