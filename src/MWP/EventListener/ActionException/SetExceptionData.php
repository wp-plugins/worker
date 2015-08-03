<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionException_SetExceptionData implements Symfony_EventDispatcher_EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_EXCEPTION => array('onActionException', 200),
        );
    }

    public function onActionException(MWP_Event_ActionException $event)
    {
        $exception = $event->getException();

        $verbose = $event->getRequest()->isAuthenticated();

        if ($exception instanceof MWP_Worker_Exception) {
            $exceptionData = $this->getDataForWorkerException($exception, $verbose);
        } else {
            $exceptionData = $this->getDataForGenericException($exception, $verbose);
        }

        $data = array(
            'error'     => $exception->getMessage(),
            'exception' => $exceptionData,
        );

        $event->setData($data);
    }

    private function getDataForWorkerException(MWP_Worker_Exception $exception, $verbose)
    {
        return array(
            'context' => $exception->getContext(),
            'type'    => $exception->getErrorName(),
        ) + $this->getDataForGenericException($exception, $verbose);
    }

    private function getDataForGenericException(Exception $exception, $verbose)
    {
        $data = array(
            'class'   => get_class($exception),
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
        );

        if ($verbose) {
            $data += array(
                'line'        => $exception->getLine(),
                'file'        => $exception->getFile(),
                'traceString' => $exception->getTraceAsString(),
            );
        }

        return $data;
    }
}
