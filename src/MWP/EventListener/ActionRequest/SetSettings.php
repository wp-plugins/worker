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

    private $system;

    public function __construct(MWP_WordPress_Context $context, MWP_System_Environment $system)
    {
        $this->context = $context;
        $this->system  = $system;
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

        $this->setMemoryLimit();

        $this->context->set('_wp_using_ext_object_cache', false);

        // Alternate WP cron can run on 'init' hook.
        $this->context->removeAction('init', 'wp_cron');

        $this->saveWorkerConfiguration($event->getRequest()->getData());
    }

    /**
     * By default, WordPress sets limits of 40MB for regular installations and 60MB for multi-sites.
     * If the limit is lower, try to increase it a bit here.
     */
    private function setMemoryLimit()
    {
        if ($this->context->isMultisite()) {
            $wantedLimit = 60 * 1024 * 1024;
        } else {
            $wantedLimit = 40 * 1024 * 1024;
        }

        $memoryLimit = $this->system->getMemoryLimit();

        if ($memoryLimit !== -1 && $memoryLimit < $wantedLimit) {
            ini_set('memory_limit', $wantedLimit);
        }
    }

    private function saveWorkerConfiguration(array $data)
    {
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
