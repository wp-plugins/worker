<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_MasterRequest_AuthenticateRequest implements Symfony_EventDispatcher_EventSubscriberInterface
{
    private $configuration;

    private $signer;

    function __construct(MWP_Worker_Configuration $configuration, MWP_Signer_Interface $signer)
    {
        $this->configuration = $configuration;
        $this->signer        = $signer;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => array('onMasterRequest', 300),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        $request = $event->getRequest();

        if ($request->isAuthenticated()) {
            return;
        }

        if ($request->getAction() === 'add_site') {
            return;
        }

        $publicKey = $this->configuration->getPublicKey();

        if (!$publicKey) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::AUTHENTICATION_PUBLIC_KEY_EMPTY, "Authentication failed. Deactivate and activate the ManageWP Worker plugin on this site, then re-add it to your ManageWP account.");
        }

        $messageId  = $request->getAction().$request->getNonce();
        $signature = $request->getSignature();

        if (!$messageId) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::AUTHENTICATION_MESSAGE_ID_EMPTY, null, array(
                'messageId' => $messageId,
                'signature' => base64_encode($signature),
            ));
        }

        $verify = $this->signer->verify($messageId, $signature, $publicKey);

        if (!$verify) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::AUTHENTICATION_INVALID_SIGNATURE, "Invalid message signature. Deactivate and activate the ManageWP Worker plugin on this site, then re-add it to your ManageWP account.");
        }

        $request->setAuthenticated(true);
    }
}
