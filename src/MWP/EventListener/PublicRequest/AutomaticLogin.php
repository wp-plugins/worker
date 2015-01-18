<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_AutomaticLogin implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    private $signer;

    private $nonceManager;

    private $configuration;

    public function __construct(MWP_WordPress_Context $context, MWP_Security_NonceManager $nonceManager, MWP_Signer_Interface $signer, MWP_Worker_Configuration $configuration)
    {
        $this->context       = $context;
        $this->nonceManager  = $nonceManager;
        $this->signer        = $signer;
        $this->configuration = $configuration;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => array(
                array('checkLoginToken', 2),
                array('setXframeHeader', 3),
            ),
        );
    }

    public function checkLoginToken(MWP_Event_PublicRequest $event)
    {
        $request = $event->getRequest();

        if (empty($request->query['auto_login']) || empty($request->query['signature']) || empty($request->query['message_id']) || !array_key_exists('mwp_goto', $request->query)) {
            return;
        }

        if (!$this->configuration->getPublicKey()) {
            // Site is not connected to a master instance.
            return;
        }

        $username = empty($request->query['username']) ? null : $request->query['username'];

        if ($username === null) {
            $users = $this->context->getUsers(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID'));
            if (empty($users[0]->user_login)) {
                throw new MWP_Worker_Exception(MWP_Worker_Exception::AUTO_LOGIN_USERNAME_REQUIRED, "We could not find an administrator user to use. Please contact support.");
            }
            $username = $users[0]->user_login;
        }

        $where = isset($request->query['mwp_goto']) ? $request->query['mwp_goto'] : '';

        $signature = base64_decode($request->query['signature']);
        $messageId = $request->query['message_id'];

        try {
            $this->nonceManager->useNonce($messageId);
        } catch (MWP_Security_Exception_NonceFormatInvalid $e) {
            $this->context->wpDie("The automatic login token is invalid. Please try again, or, if this keeps happening, contact support.");
        } catch (MWP_Security_Exception_NonceExpired $e) {
            $this->context->wpDie("The automatic login token has expired. Please try again, or, if this keeps happening, contact support.");
        } catch (MWP_Security_Exception_NonceAlreadyUsed $e) {
            $this->context->wpDie("The automatic login token was already used. Please try again, or, if this keeps happening, contact support.");
        }

        if ($secureKey = $this->configuration->getSecureKey()) {
            // Legacy support, to be removed.
            $verify = (md5($where.$messageId.$secureKey) === $signature);
        } else {
            $verify = $this->signer->verify($where.$messageId, $signature, $this->configuration->getPublicKey());
        }

        if (!$verify) {
            $this->context->wpDie("The automatic login token is invalid. Please check if this website is properly connected with your dashboard, or, if this keeps happening, contact support.");
        }

        $user = $this->context->getUserByUsername($username);

        if ($user === null) {
            $this->context->wpDie(sprintf("User <strong>%s</strong> could not be found.", htmlspecialchars($username)));
        }

        $this->context->setCurrentUser($user);
        $this->context->setAuthCookie($user);

        $currentUri  = empty($request->server['REQUEST_URI']) ? '/' : $request->server['REQUEST_URI'];
        $redirectUri = $this->omitUriParameters($currentUri, array('signature', 'username', 'auto_login', 'message_id', 'mwp_goto', 'mwpredirect'));

        $this->context->setCookie($this->getCookieName(), '1');

        $event->setResponse(new MWP_Http_RedirectResponse($redirectUri, 302, array(
            'P3P' => 'CP="CAO PSA OUR"',
        )));
    }

    private function getCookieName()
    {
        return 'wordpress_'.md5($this->context->getSiteUrl()).'_xframe';
    }

    public function setXframeHeader(MWP_Event_PublicRequest $event)
    {
        if (!isset($_COOKIE[$this->getCookieName()])) {
            return;
        }

        $this->context->removeAction('admin_init', 'send_frame_options_header');
        $this->context->removeAction('login_init', 'send_frame_options_header');

        if (!headers_sent()) {
            header('P3P: CP="CAO PSA OUR"');
        }
    }

    private function omitUriParameters($uri, array $omitParameters)
    {
        if (strpos($uri, '?') === false) {
            return $uri;
        }

        $rawQuery = parse_url($uri, PHP_URL_QUERY);
        parse_str($rawQuery, $query);

        foreach ($omitParameters as $key) {
            if (array_key_exists($key, $query)) {
                unset($query[$key]);
            }
        }

        // Replace everything from "?" onwards with "?key=value" or an empty string.
        return substr($uri, 0, strpos($uri, '?')).(count($query) ? '?'.http_build_query($query) : '');
    }
}
