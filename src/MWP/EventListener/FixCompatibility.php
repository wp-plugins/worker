<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_FixCompatibility implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => 'fixWpSuperCache',
            MWP_Event_Events::MASTER_REQUEST  => array('fixAllInOneSecurity', -10000),
        );
    }

    public function fixWpSuperCache()
    {
        if ($this->context->hasConstant('ADVANCEDCACHEPROBLEM') && $this->context->getConstant('ADVANCEDCACHEPROBLEM')) {
            $this->context->set('wp_cache_config_file', null);
        }
    }

    public function fixAllInOneSecurity()
    {
        if (!$this->context->isPluginEnabled('all-in-one-wp-security-and-firewall/wp-security.php')) {
            return;
        }

        $this->context->addAction('init', array($this, '_fixAllInOneSecurity'), -1);
    }

    /**
     * @internal
     */
    public function _fixAllInOneSecurity()
    {
        $user = $this->context->getCurrentUser();

        if (empty($user->ID)) {
            return;
        }

        $this->context->updateUserMeta($user->ID, 'last_login_time', $this->context->getCurrentTime()->format('Y-m-d H:i:s'));
    }
}
