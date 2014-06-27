<?php

/**
 * MMB_Widget Class
 */
class MMB_Widget extends WP_Widget
{
    /** constructor -- name this the same as the class above */
    function __construct()
    {
        parent::__construct(false, 'ManageWP', array('description' => 'ManageWP widget.'));
    }

    /** @see WP_Widget::widget -- do not rename this */
    function widget($args, $instance)
    {
        extract($args);
        $instance['title']   = 'ManageWP';
        $instance['message'] = 'We are happily using <a href="http://managewp.com" target="_blank">ManageWP</a>';
        $title               = apply_filters('widget_title', $instance['title']);
        $message             = $instance['message'];
        ?>
        <?php echo $before_widget; ?>
        <?php if ($title) {
        echo $before_title.$title.$after_title;
    } ?>
        <ul>
            <li><?php echo $message; ?></li>
        </ul>
        <?php echo $after_widget; ?>
    <?php
    }

    /** @see WP_Widget::form -- do not rename this */
    function form($instance)
    {
        $title   = 'ManageWP';
        $message = 'We are happily using <a href="http://managewp.com" target="_blank">ManageWP</a>';
        echo '<p>'.$message.'</p>';
    }


}
