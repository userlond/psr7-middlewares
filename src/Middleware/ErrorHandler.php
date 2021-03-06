<?php

namespace Psr7Middlewares\Middleware;

use Psr7Middlewares\Utils;
use Psr7Middlewares\Middleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Whoops\Run;

/**
 * Middleware to handle php errors and exceptions.
 */
class ErrorHandler
{
    const KEY = 'EXCEPTION';

    use Utils\RouterTrait;
    use Utils\ArgumentsTrait;

    /**
     * @var callable|string|null Error handler
     */
    protected $handler;

    /**
     * @var Run|null To handle errors using whoops
     */
    protected $whoops;

    /**
     * @var bool Whether or not catch exceptions
     */
    protected $catchExceptions = false;

    /**
     * Returns the exception throwed.
     *
     * @param ServerRequestInterface $request
     *
     * @return \Exception|null
     */
    public static function getException(ServerRequestInterface $request)
    {
        return Middleware::getAttribute($request, self::KEY);
    }

    /**
     * Constructor.
     *
     * @param callable|string|null $handler
     */
    public function __construct($handler = null)
    {
        if ($handler !== null) {
            $this->handler($handler);
        }
    }

    /**
     * Set the error handler.
     *
     * @param string|callable $handler
     *
     * @return self
     */
    public function handler($handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Set an instance of Whoops.
     *
     * @param Run $whoops
     *
     * @return self
     */
    public function whoops(Run $whoops)
    {
        $this->whoops = $whoops;

        return $this;
    }

    /**
     * Configure the catchExceptions.
     *
     * @param bool $catch
     *
     * @return self
     */
    public function catchExceptions($catch = true)
    {
        $this->catchExceptions = (boolean) $catch;

        return $this;
    }

    /**
     * Execute the middleware.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $next
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if ($this->whoops) {
            $this->whoops->pushHandler(function ($exception) use ($request, $response) {
                echo self::executeTarget($this->handler, $this->arguments, Middleware::setAttribute($request, self::KEY, $exception), $response)->getBody();
            });
        }

        ob_start();

        try {
            $response = $next($request, $response);
        } catch (\Exception $exception) {
            if (!$this->catchExceptions) {
                throw $exception;
            }

            $request = Middleware::setAttribute($request, self::KEY, $exception);
            $response = $response->withStatus(500);
        }

        ob_end_clean();

        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 600) {
            return self::executeTarget($this->handler, $this->arguments, $request, $response);
        }

        if ($this->whoops) {
            $this->whoops->popHandler();
        }

        return $response;
    }
}
