<?php
/**
 * NOVIUS OS - Web OS for digital communication
 *
 * @copyright  2011 Novius
 * @license    GNU Affero General Public License v3 or (at your option) any later version
 *             http://www.gnu.org/licenses/agpl-3.0.html
 * @link http://www.novius-os.org
 */


Nos\I18n::current_dictionary(array('noviusos_media::common', 'nos::common'));

$appdeskview = (string) Request::forge('admin/noviusos_media/appdesk/index')->execute(array('image_pick'))->response();
$uniqid = uniqid('tabs_');
$id_library = $uniqid.'_library';
$id_properties = $uniqid.'_properties';

?>
<style type="text/css">
    .box-sizing-border {
        box-sizing: border-box;
        -moz-box-sizing: border-box;
        -webkit-box-sizing: border-box;
        height: 100%;
    }
</style>
<div id="<?= $uniqid ?>" class="box-sizing-border">
    <ul>
        <li><a href="#<?= $id_library ?>"><?= $edit ? __('Pick another image') : __('1. Pick an image') ?></a></li>
        <li><a href="#<?= $id_properties ?>"><?= $edit ? __('Edit properties') : __('2. Set the properties') ?></a></li>
    </ul>
    <div id="<?= $id_library ?>" class="box-sizing-border"></div>

    <form action="#">
        <div id="<?= $id_properties ?>">
            <table class="fieldset">
                <tr>
                    <td rowspan="6"><img /></td>
                    <th><label for="<?= $uniqid ?>_title"><?= __('Title:') ?> </label></th>
                    <td><input type="text" name="title" data-id="title" size="30" id="<?= $uniqid ?>_title" /></td>
                </tr>
                <tr>
                    <th><label for="<?= $uniqid ?>_alt"><?= __('Alternative text (for accessibility):') ?> </label></th>
                    <td><input type="text" name="alt" data-id="alt" size="30" id="<?= $uniqid ?>_alt" /> &nbsp; <label><input type="checkbox" data-id="same_title_alt" checked> &nbsp;<?= __('Use title') ?></label></td>
                </tr>
                <tr>
                    <th><label for="<?= $uniqid ?>_width"><?= __('Width:') ?> </label></th>
                    <td><input type="text" name="width" data-id="width" size="5" id="<?= $uniqid ?>_width" /> &nbsp; <label><input type="checkbox" data-id="proportional" checked> &nbsp;<?= __('Keep proportions') ?></label></td>
                </tr>
                <tr>
                    <th><label for="<?= $uniqid ?>_height"><?= __('Height:') ?> </label></th>
                    <td><input type="text" name="height" data-id="height" size="5" readonly id="<?= $uniqid ?>_height" /></td>
                </tr>
                <tr>
                    <th><label for="<?= $uniqid ?>_style"><?= __('Style:') ?> </label></th>
                    <td><input type="text" name="style" data-id="style" id="<?= $uniqid ?>_style" /></td>
                </tr>
                <tr>
                    <th></th>
                    <td> <button type="submit" class="primary" data-icon="check" data-id="save"><?= $edit ? __('Update this image') : __('Insert this image') ?></button> &nbsp; <?= __('or') ?> &nbsp; <a data-id="close" href="#"><?= __('Cancel') ?></a></td>
                </tr>
            </table>
        </div>
    </form>
</div>
<script type="text/javascript">
require(
    ['jquery-nos-image-wysiwyg'],
    function($) {
        $(function() {
            $('#<?= $uniqid ?>').nosImageWysiwyg({
                newImg: !'<?= $edit ?>',
                appdeskView: <?= \Format::forge()->to_json($appdeskview) ?>,
                base_url: '<?= \Uri::base(true) ?>',
                texts: {
                    imageFirst: <?= \Format::forge()->to_json(__('This is unusual: It seems that no image has been selected. Please try again. Contact your developer or Novius OS if the problem persists. We apologise for the inconvenience caused.')) ?>
                }
            });
        });
    });
</script>
