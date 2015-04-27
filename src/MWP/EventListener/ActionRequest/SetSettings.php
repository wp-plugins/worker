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
            MWP_Event_Events::ACTION_RESPONSE => array('onActionResponse', 300),
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

        $this->resetVersions($event);
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        if ($event->getRequest()->getAction() !== 'add_site') {
            return;
        }

        set_time_limit(1800);
        $this->context->set('_wp_using_ext_object_cache', false);
        $this->setMemoryLimit();
        $this->requireVersions();
    }

    /**
     * Reset WordPress core versions, because a certain "security" plugin can modify it
     * and break other plugins. Only do this for master requests.
     *
     * @param MWP_Event_ActionRequest $event
     */
    private function resetVersions(MWP_Event_ActionRequest $event)
    {
        $actionHook = $event->getActionDefinition()->getOption('hook_name');

        if ($actionHook) {
            // Set the user on the earliest hook after pluggable.php is loaded.
            $hookProxy = new MWP_WordPress_HookProxy(array($this, 'requireVersions'));
            $this->context->addAction('init', $hookProxy->getCallable(), 11);

            return;
        }

        $this->requireVersions();
    }

    /**
     * Callback for version resetting, has to work with PHP 5.2.
     *
     * @internal
     */
    public function requireVersions()
    {
        $versionFile = $this->context->getConstant('ABSPATH').$this->context->getConstant('WPINC').'/version.php';
        if (!file_exists($versionFile)) {
            // For whatever reason.
            return;
        }

        include $versionFile;

        $varNames = array(
            'wp_version',
            'wp_db_version',
            'tinymce_version',
            'required_php_version',
            'required_mysql_version',
        );

        foreach ($varNames as $varName) {
            if (!isset($$varName)) {
                continue;
            }
            $this->context->set($varName, $$varName);
        }
    }

    /**
     * By default, WordPress sets limits of 40MB for regular installations and 60MB for multi-sites.
     * If the limit is lower, try to increase it a bit here.
     */
    private function setMemoryLimit()
    {
        $wantedLimit = 64 * 1024 * 1024;
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
