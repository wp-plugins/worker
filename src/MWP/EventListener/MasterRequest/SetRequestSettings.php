<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_MasterRequest_SetRequestSettings implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => array('onMasterRequest', -1000),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        if (!$event->getRequest()->isAuthenticated()) {
            return;
        }
        $data = $event->getRequest()->getData();

        $this->defineWpAdmin($data);
        $this->defineWpAjax($data);
        $this->setWpPage($data);

        // Master should never get redirected by the worker, since it expects worker response.
        $this->context->addFilter('wp_redirect', array($this, 'disableRedirect'));

        // Alternate WP cron can run on 'init' hook.
        $this->context->removeAction('init', 'wp_cron');
        $this->context->set('_wp_using_ext_object_cache', false);
    }

    private function defineWpAdmin(array $data)
    {
        if (empty($data['wpAdmin'])) {
            return;
        }

        $this->context->setConstant('WP_ADMIN', true, false);
        require_once $this->context->getConstant('ABSPATH').'wp-admin/includes/admin.php';
    }

    private function defineWpAjax(array $data)
    {
        if (empty($data['wpAjax'])) {
            return;
        }

        $this->context->setConstant('DOING_AJAX', true, false);
    }

    private function setWpPage(array $data)
    {
        if (empty($data['wpPage'])) {
            return;
        }

        $this->context->set('pagenow', $data['wpPage']);
    }

    /**
     * @internal
     */
    public function disableRedirect()
    {
        return false;
    }
}
