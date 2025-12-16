<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Event\MedicalDataChangedEvent;
use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuditSubscriber implements EventSubscriberInterface
{
    private AuditLogger $auditLogger;
    private TokenStorageInterface $tokenStorage;
    private array $ignoredRoutes = [
        '_wdt',
        '_profiler',
        '_profiler_search',
        '_profiler_search_bar',
        '_profiler_phpinfo',
        '_profiler_search_results',
        '_profiler_open_file',
        '_profiler_router'
    ];

    public function __construct(AuditLogger $auditLogger, TokenStorageInterface $tokenStorage)
    {
        $this->auditLogger = $auditLogger;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            MedicalDataChangedEvent::class => 'onMedicalDataChanged',
            SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (!$route || in_array($route, $this->ignoredRoutes, true)) {
            return;
        }

        if (str_contains($route, '_new') || 
            str_contains($route, '_edit') || 
            str_contains($route, '_delete') ||
            str_contains($route, '_cancel')) {
            
            $token = $this->tokenStorage->getToken();
            $username = 'anonymous';
            
            if ($token && $token->getUser()) {
                $user = $token->getUser();
                $username = $user->getUserIdentifier();
            }

            $this->auditLogger->log(
                'ROUTE_ACCESSED',
                [
                    'route' => $route,
                    'method' => $request->getMethod(),
                    'parameters' => $request->attributes->get('_route_params'),
                    'query' => $request->query->all(),
                    'ip' => $request->getClientIp(),
                    'username' => $username
                ],
                'system'
            );
        }
    }

    public function onMedicalDataChanged(MedicalDataChangedEvent $event): void
    {
        $this->auditLogger->logMedicalDataChange(
            $event->getAction(),
            $event->getOldData(),
            $event->getNewData(),
            $event->getEntityType(),
            $event->getEntityId()
        );
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        $userId = null;
        if ($user instanceof User) {
            $userId = $user->getId();
        }
        
        $this->auditLogger->log(
            'USER_LOGIN',
            [
                'username' => $user->getUserIdentifier(),
                'roles' => $user->getRoles()
            ],
            'security',
            $userId
        );
    }
}