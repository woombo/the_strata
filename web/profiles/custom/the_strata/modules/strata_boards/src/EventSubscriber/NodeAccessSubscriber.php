<?php

namespace Drupal\strata_boards\EventSubscriber;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * Constructs a NodeAccessSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(AccountProxyInterface $current_user, RouteProviderInterface $route_provider) {
    $this->currentUser = $current_user;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after routing is resolved but before page cache.
    // RouterListener runs at priority 32, DynamicPageCache at 27.
    return [
      KernelEvents::REQUEST => ['onRequest', 28],
    ];
  }

  /**
   * Redirects anonymous users away from restricted node types.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    // Only handle main requests.
    if (!$event->isMainRequest()) {
      return;
    }

    // Only redirect anonymous users.
    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    // Check if this is a node view route.
    if ($route_name !== 'entity.node.canonical') {
      return;
    }

    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface) {
      return;
    }

    // Check if this is a restricted node type.
    if (in_array($node->bundle(), self::RESTRICTED_TYPES, TRUE)) {
      $url = Url::fromRoute('<front>')->toString();
      $response = new RedirectResponse($url);
      $event->setResponse($response);
    }
  }

}
