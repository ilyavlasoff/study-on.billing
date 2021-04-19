<?php

namespace App\Exception;

use JMS\Serializer\Annotation as JMS;

/**
 * Class AbstractSerializableException
 * @package App\Exception
 */
class SerializableException extends \Exception
{
    /**
     * @JMS\VirtualProperty()
     * @JMS\SerializedName("message")
     * @JMS\Groups({"exception"})
     */
    public function getErrorMessage()
    {
        return $this->message;
    }

    /**
     * @JMS\VirtualProperty()
     * @JMS\SerializedName("code")
     * @JMS\Groups({"exception"})
     */
    public function getErrorCode()
    {
        return $this->code;
    }

}
