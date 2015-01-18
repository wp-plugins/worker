<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionRequest_SetSettings implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_REQUEST => 'onActionRequest',
        );
    }

    public function onActionRequest(MWP_Event_ActionRequest $event)
    {
        set_time_limit(1800);

        $this->context->set('_wp_using_ext_object_cache', false);

        $data = $event->getRequest()->getData();

        if (empty($data['setting'])) {
            return;
        }

        if (!empty($data['setting']['dataown'])) {
            $oldSettings = (array) $this->context->optionGet('wrksettings');
            $this->context->optionSet('wrksettings', array_merge($oldSettings, array('dataown' => $data['setting']['dataown'])));
        }

        $configurationService = new MWP_Configuration_Service();
        $configuration        = new MWP_Configuration_Conf($data['setting']);
        $configurationService->saveConfiguration($configuration);
    }
}
