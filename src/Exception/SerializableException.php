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
     * @JMS\Type("array")
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

    public function __construct(
        $message = 'Произошла ошибка.',
        $code = Response::HTTP_INTERNAL_SERVER_ERROR,
        $error = 'ERROR',
        $details = [],
        Throwable $previous = null
    ) {
        $this->details = $details;
        $this->error = $error;

        parent::__construct($message, $code, $previous);
    }
}
