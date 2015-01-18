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
 * Attaches initial site statistics after connecting the worker to a master instance.
 *
 * @deprecated Not used in master v2.
 */
class MWP_EventListener_ActionResponse_SetLegacyWebsiteConnectionData implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => array('onActionResponse', 200),
        );
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        if ($event->getRequest()->getAction() !== 'add_site') {
            return;
        }

        $params = $event->getRequest()->getParams();

        if (!array_key_exists('notifications', $params)) {
            // This is not a legacy call.
            return;
        }

        $this->context->requireWpRewrite();
        $this->context->requireTaxonomies();
        $this->context->requirePostTypes();
        $this->context->requireTheme();

        $stats = new MMB_Stats();
        $this->context->optionSet('mwp_notifications', $params['notifications']);
        $event->setData($stats->get_initial_stats());
    }
}
