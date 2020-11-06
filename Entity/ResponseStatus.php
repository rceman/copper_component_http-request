<?php


namespace Copper\Component\HttpRequest\Entity;

class ResponseStatus
{
    /** @var int */
    public $code;
    /** @var string */
    public $text;

    const CODE_500 = 500;
    const CODE_409 = 409;
    const CODE_404 = 404;
    const CODE_403 = 403;
    const CODE_401 = 401;
    const CODE_400 = 400;
    const CODE_304 = 304;
    const CODE_302 = 302;
    const CODE_301 = 301;
    const CODE_204 = 204;
    const CODE_201 = 201;
    const CODE_200 = 200;
    const CODE_0 = 0;

    const CODE_TEXT = [
        self::CODE_500 => 'Internal Server Error',
        self::CODE_409 => 'Conflict',
        self::CODE_404 => 'Not Found',
        self::CODE_403 => 'Forbidden',
        self::CODE_401 => 'Unauthorized',
        self::CODE_400 => 'Bad Request',
        self::CODE_304 => 'Not Modified',
        self::CODE_302 => 'Found',
        self::CODE_301 => 'Moved Permanently',
        self::CODE_204 => 'No Content',
        self::CODE_201 => 'Created',
        self::CODE_200 => 'OK',
        self::CODE_0 => 'Unknown Code'
    ];

    /**
     * ResponseStatus constructor
     *
     * @param int $code
     * @param string $text
     */
    public function __construct($code, $text)
    {
        $this->code = $code;
        $this->text = $text;
    }

    public function toString()
    {
        return "$this->code $this->text";
    }

    /** @return ResponseStatus */
    public static function internalServerError()
    {
        return self::findByCode(self::CODE_500);
    }

    /** @return ResponseStatus */
    public static function conflict()
    {
        return self::findByCode(self::CODE_409);
    }

    /** @return ResponseStatus */
    public static function notFound()
    {
        return self::findByCode(self::CODE_404);
    }

    /** @return ResponseStatus */
    public static function forbidden()
    {
        return self::findByCode(self::CODE_403);
    }

    /** @return ResponseStatus */
    public static function unauthorized()
    {
        return self::findByCode(self::CODE_401);
    }

    /** @return ResponseStatus */
    public static function badRequest()
    {
        return self::findByCode(self::CODE_400);
    }

    /** @return ResponseStatus */
    public static function notModified()
    {
        return self::findByCode(self::CODE_304);
    }

    /** @return ResponseStatus */
    public static function found()
    {
        return self::findByCode(self::CODE_302);
    }

    /** @return ResponseStatus */
    public static function movedPermanently()
    {
        return self::findByCode(self::CODE_301);
    }

    /** @return ResponseStatus */
    public static function noContent()
    {
        return self::findByCode(self::CODE_204);
    }

    /** @return ResponseStatus */
    public static function created()
    {
        return self::findByCode(self::CODE_201);
    }

    /** @return ResponseStatus */
    public static function ok()
    {
        return self::findByCode(self::CODE_200);
    }

    /**
     * @param int $code
     *
     * @return bool
     */
    public static function codeExists($code)
    {
        return (array_key_exists($code, self::CODE_TEXT));
    }

    /**
     * @param int $code
     *
     * @return ResponseStatus
     */
    public static function findByCode($code)
    {
        $code = intval($code);

        if (self::codeExists($code))
            return new self($code, self::CODE_TEXT[$code]);

        return new self(self::CODE_0, self::CODE_TEXT[self::CODE_0]);
    }
}