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

class Controller_Admin_Wysiwyg extends \Controller
{
    public function action_image($edit = false)
    {
        $view = \View::forge('noviusos_media::admin/wysiwyg_image');
        $view->set('edit', $edit, false);
        return $view;
    }

    public function action_link($edit = false)
    {
        $view = \View::forge('noviusos_page::admin/wysiwyg_link');
        $view->set('edit', $edit, false);

        return $view;
    }

    public function action_enhancers()
    {
        $urlEnhancers = \Input::get('urlEnhancers', false);

        \Config::load(APPPATH.'metadata'.DS.'enhancers.php', 'data::enhancers');
        $enhancers = \Config::get('data::enhancers', array());

        if (!$urlEnhancers) {
            $enhancers = array_filter($enhancers, function($enhancer) {
                return empty($enhancer['urlEnhancer']);
            });
        }

        \Response::json($enhancers);
    }
}
