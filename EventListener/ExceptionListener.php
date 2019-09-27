<?php


namespace RValin\ExceptionListenerClientBundle\EventListener;


use Doctrine\Common\Util\Debug;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\SecurityContext;

class ExceptionListener
{
    private $api_url;
    /**
     * @var SecurityContext
     */
    private $securityContext;

    public function __construct(Router $router, SecurityContext $securityContext, $endpoint)
    {
        $this->securityContext = $securityContext;
        if (!empty($endpoint['url'])) {
            $this->api_url = $endpoint['url'];
        } elseif (!empty($endpoint['route'])) {
            $this->api_url = $router->generate($endpoint['route'], [], UrlGeneratorInterface::NETWORK_PATH);
        } else {
            throw new \Exception('Invalide configuration for RValinExceptionListenerBundle. The config require endpoint.url or endpoint.route');
        }
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if ($event->getRequest()->attributes->get('_route') === 'rvalin_exception_occurence_new') {
            return;
        }

        $trace = $event->getException()->getTrace();

        foreach ($trace as &$item) {
            $lines = file($item['file']);
            $startLine = $item['line'] - 2;
            if ($startLine < 0) {
                $startLine = 0;
            }

            $item['lines'] = array_slice($lines, $startLine, 5);
        }

        $data = [
            'user_agent' => $event->getRequest()->server->get('HTTP_USER_AGENT'),
            'POST' => $event->getRequest()->request->all(),
            'COOKIES' => $event->getRequest()->cookies->all(),
            'SESSION' => $event->getRequest()->getSession()->all(),
            'php_version' => phpversion()
        ];
        if (!empty($data['COOKIES']['PHPSESSID'])) {
            $data['COOKIES']['PHPSESSID'] = '******';
        }

        $exception = $event->getException();
        $request = $event->getRequest();
        $user = $this->securityContext->getToken()->getUser();

        $post = [
            'line' => $exception->getLine(),
            'file' => $exception->getFile(),
            'url' => $request->getUri(),
            'message' => $exception->getMessage(),
            'user' => ($user instanceof UserInterface) ? $user->getUsername() : null,
            'code' => $exception->getCode(),
            'trace' => $trace,
            'extra' => $data,
            'exception_class' => get_class($exception),
            'language' => 'php'
        ];
        $data = json_encode($post);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
        );
        curl_setopt( $ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_USERAGENT, 'api');
//        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch,  CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
//        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, -1);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_exec($ch);
    }
}