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

        if ($exception instanceof MWP_Worker_Exception) {
            $exceptionData = $this->getDataForWorkerException($exception);
        } else {
            $exceptionData = $this->getDataForGenericException($exception);
        }

        $data = array(
            'error'     => $exception->getMessage(),
            'exception' => $exceptionData,
        );

        $event->setData($data);
    }

    public function getDataForWorkerException(MWP_Worker_Exception $exception)
    {
        return array(
            'context' => $exception->getContext(),
            'type'    => $exception->getErrorName(),
        ) + $this->getDataForGenericException($exception);
    }

    public function getDataForGenericException(Exception $exception)
    {
        return array(
            'class'       => get_class($exception),
            'message'     => $exception->getMessage(),
            'line'        => $exception->getLine(),
            'file'        => $exception->getFile(),
            'code'        => $exception->getCode(),
            'traceString' => $exception->getTraceAsString(),
        );
    }
}
