<?php

namespace App\Exception;

use JMS\Serializer\Annotation as JMS;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Class AbstractSerializableException
 */
class SerializableException extends \Exception
{
    /**
     * @JMS\Groups({"exception"})
     * @JMS\Type("string")
     */
    public $error;

    /**
     * @JMS\Groups({"exception"})
     * @JMS\Type("array<string>")
     */
    public $details;

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

    public function __construct($message = '', $code = 0, $error = '', $details = [], Throwable $previous = null)
    {
        if (!$message) {
            $message = 'Произошла ошибка.';
        }

        if (!$error) {
            $this->error = 'ERROR';
        }

        if (0 === $code) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $this->details = $details;
        $this->error = $error;

        parent::__construct($message, $code, $previous);
    }
}
