<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Taken from WordPress's automatic updater skin, which was added in version 3.7.
 *
 * @see Automatic_Upgrader_Skin
 */
class MWP_Updater_TraceableUpdaterSkin
{

    public $upgrader;

    public $messages = array();

    public $result;

    public function request_filesystem_credentials($error = false, $context = '', $allow_relaxed_file_ownership = false)
    {
    }

    public function get_upgrade_messages()
    {
        return $this->messages;
    }

    /**
     * @param string|array|WP_Error $data
     */
    public function feedback($data)
    {
        if (is_wp_error($data)) {
            $string = $data->get_error_message();
        } else {
            if (is_array($data)) {
                return;
            } else {
                $string = $data;
            }
        }

        if (!empty($this->upgrader->strings[$string])) {
            $string = $this->upgrader->strings[$string];
        }

        if (strpos($string, '%') !== false) {
            $args = func_get_args();
            $args = array_splice($args, 1);
            if (!empty($args)) {
                $string = vsprintf($string, $args);
            }
        }

        $string = trim($string);

        // Only allow basic HTML in the messages, as it'll be used in emails/logs rather than direct browser output.
        $string = wp_kses($string, array(
            'a'      => array(
                'href' => true
            ),
            'br'     => true,
            'em'     => true,
            'strong' => true,
        ));

        if (empty($string)) {
            return;
        }

        $this->messages[] = array(
            'message' => $string,
            'key'     => $data,
            'args'    => isset($args) ? $args : array(),
        );
    }

    public function header()
    {
        ob_start();
    }

    public function footer()
    {
        $output = ob_get_contents();
        if (!empty($output)) {
            $this->feedback($output);
        }
        ob_end_clean();
    }

    public function bulk_header()
    {
    }

    public function bulk_footer()
    {
    }

    public function before()
    {
    }

    public function after()
    {
    }

    // Below was taken from WP_Upgrader_Skin, so we don't autoload it and cause trouble.
    public function decrement_update_count()
    {
    }

    public function error($errors)
    {
        if (is_string($errors)) {
            $this->feedback($errors);

            return;
        }

        if (!$errors instanceof WP_Error || !$errors->get_error_code()) {
            return;
        }

        foreach ($errors->get_error_messages() as $message) {
            if ($errors->get_error_data() && is_string($errors->get_error_data())) {
                $this->feedback($message.' '.esc_html(strip_tags($errors->get_error_data())));
            } else {
                $this->feedback($message);
            }
        }
    }

    /**
     * @param WP_Upgrader $upgrader
     */
    public function set_upgrader($upgrader)
    {
        if (is_object($upgrader)) {
            $this->upgrader = $upgrader;
        }
        $this->add_strings();
    }

    public function add_strings()
    {
    }

    public function set_result($result)
    {
        $this->result = $result;
    }
}
