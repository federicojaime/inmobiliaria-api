<?php
// middleware/ErrorHandlerMiddleware.php
namespace middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use exceptions\PropertyException;
use services\LogService;

class ErrorHandlerMiddleware {
    private $logService;

    public function __construct() {
        $this->logService = new LogService();
    }

    public function __invoke(Request $request, Response $response, callable $next) {
        $startTime = microtime(true);

        try {
            $response = $next($request, $response);
            
            // Log access
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logService->logAccess(
                $request->getMethod(),
                $request->getUri()->getPath(),
                $response->getStatusCode(),
                round($duration, 2)
            );

            return $response;
        } catch (PropertyException $e) {
            $this->logService->logError($e->getMessage(), [
                'errors' => $e->getErrors(),
                'trace' => $e->getTraceAsString()
            ]);

            $payload = json_encode($e->getResponseArray());
            $response->getBody()->write($payload);
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        } catch (\Exception $e) {
            $this->logService->logError($e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $payload = json_encode([
                'ok' => false,
                'msg' => 'Error interno del servidor',
                'errores' => [$e->getMessage()]
            ]);
            $response->getBody()->write($payload);
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}