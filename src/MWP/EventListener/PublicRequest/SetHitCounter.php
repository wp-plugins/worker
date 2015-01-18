<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_SetHitCounter implements Symfony_EventDispatcher_EventSubscriberInterface
{
    const METHOD_BLACKLIST = false;

    const METHOD_WHITELIST = true;

    private $context;

    /**
     * @var MWP_Extension_HitCounter
     */
    private $hitCounter;

    /**
     * If set to METHOD_BLACKLIST, all non-master requests except those that match at least one
     * rule from the list.
     * If set to METHOD_WHITELIST, only requests that match at least one rule from the list will
     * be counted.
     *
     * @var bool
     */
    private $userAgentMatchingMethod;

    /**
     * @var array
     */
    private $blacklistedIps = array();

    private $userAgentList = array();

    /**
     * @param MWP_WordPress_Context    $context
     * @param MWP_Extension_HitCounter $hitCounter
     * @param string[]                 $blacklistedIps
     * @param string[]                 $userAgentList
     * @param bool                     $userAgentMatchingMethod
     */
    public function __construct(MWP_WordPress_Context $context, MWP_Extension_HitCounter $hitCounter, array $blacklistedIps = array(), array $userAgentList = array(), $userAgentMatchingMethod = self::METHOD_BLACKLIST)
    {
        $this->context                 = $context;
        $this->hitCounter              = $hitCounter;
        $this->blacklistedIps          = $blacklistedIps;
        $this->userAgentList           = $userAgentList;
        $this->userAgentMatchingMethod = $userAgentMatchingMethod;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => array('onPublicRequest', -300),
        );
    }

    public function onPublicRequest(MWP_Event_PublicRequest $event)
    {
        $request = $event->getRequest();

        if ($this->context->isInAdminPanel()) {
            return;
        }

        if ($this->isBlacklisted($request)) {
            return;
        }

        $this->hitCounter->increment();
    }

    /**
     * @param MWP_Worker_Request $request
     *
     * @return bool
     */
    protected function isBlacklisted(MWP_Worker_Request $request)
    {
        $userAgent = $request->getHeader('USER_AGENT');
        $ip        = $request->getHeader('REMOTE_ADDR');

        foreach ($this->blacklistedIps as $ipRegex) {
            if (preg_match($ipRegex, $ip)) {
                return true;
            }
        }

        foreach ($this->userAgentList as $uaRegex) {
            if (preg_match($uaRegex, $userAgent)) {
                return !$this->userAgentMatchingMethod;
            }
        }

        return $this->userAgentMatchingMethod;
    }
}
