<?php

namespace Drupal\strata_boards\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restricts access to ticket and violation nodes for anonymous users.
 */
class NodeAccessSubscriber implements EventSubscriberInterface {

  /**
   * Node types that require authentication.
   */
  protected const RESTRICTED_TYPES = ['ticket', 'violation'];

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a NodeAccessSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 28],
      KernelEvents::EXCEPTION => ['onException', 100],
    ];
  }

  /**
   * Redirects anonymous users away from restricted node types.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if ($route_name !== 'entity.node.canonical') {
      return;
    }

    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface) {
      return;
    }

    if (in_array($node->bundle(), self::RESTRICTED_TYPES, TRUE)) {
      $url = Url::fromRoute('<front>')->toString();
      $response = new RedirectResponse($url);
      $event->setResponse($response);
    }
  }

  /**
   * Redirects anonymous users on access denied for restricted node types.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();

    if (!$exception instanceof AccessDeniedHttpException) {
      return;
    }

    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if ($route_name !== 'entity.node.canonical') {
      return;
    }

    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface) {
      return;
    }

    if (in_array($node->bundle(), self::RESTRICTED_TYPES, TRUE)) {
      $url = Url::fromRoute('<front>')->toString();
      $response = new RedirectResponse($url);
      $event->setResponse($response);
    }
  }

}
