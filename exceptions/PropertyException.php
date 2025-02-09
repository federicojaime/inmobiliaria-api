<?php
// exceptions/PropertyException.php
namespace exceptions;

class PropertyException extends \Exception
{
    private $errors = [];

    public function __construct($message, $errors = [], $code = 0)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getResponseArray()
    {
        return [
            'ok' => false,
            'msg' => $this->getMessage(),
            'errores' => $this->getErrors()
        ];
    }
}
