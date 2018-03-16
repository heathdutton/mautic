<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!isset($class)) {
    $class = '';
}
?>

<?php echo $view['form']->start($dateRangeGroupByForm, ['attr' => ['class' => 'form-filter '.$class]]); ?>
    <div class="input-group">
        <?php if (isset($dateRangeGroupByForm['group_by'])): ?>
            <?php echo $view['form']->widget($dateRangeGroupByForm['group_by']); ?>
            <span class="input-group-addon" style="border-left: 0;border-right: 0;">
                <?php echo $view['form']->label($dateRangeGroupByForm['date_from']); ?>
            </span>
        <?php else: ?>
            <span class="input-group-addon" style="border-right: 0;">
                <?php echo $view['form']->label($dateRangeGroupByForm['date_from']); ?>
            </span>
        <?php endif; ?>
        <?php echo $view['form']->widget($dateRangeGroupByForm['date_from']); ?>
        <span class="input-group-addon" style="border-left: 0;border-right: 0;">
            <?php echo $view['form']->label($dateRangeGroupByForm['date_to']); ?>
        </span>
        <?php echo $view['form']->widget($dateRangeGroupByForm['date_to']); ?>
        <span class="input-group-btn">
            <?php echo $view['form']->row($dateRangeGroupByForm['apply']); ?>
        </span>
    </div>
<?php echo $view['form']->end($dateRangeGroupByForm); ?>
