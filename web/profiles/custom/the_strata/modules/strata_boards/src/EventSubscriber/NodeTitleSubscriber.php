<?php

namespace Drupal\strata_boards\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets custom page titles for board-enabled content types.
 */
class NodeTitleSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a new NodeTitleSubscriber.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', 0],
    ];
  }

  /**
   * Modifies the HTML title tag for board-enabled nodes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return;
    }

    if (!$node->hasField('field_board_status')) {
      return;
    }

    $node_type = $node->type->entity;
    if (!$node_type) {
      return;
    }

    $type_label = $node_type->label();
    $new_title = $type_label . ': ' . $node->label();

    $response = $event->getResponse();
    $content = $response->getContent();

    if ($content) {
      // Replace the <title> tag content.
      $content = preg_replace(
        '/<title>.*?<\/title>/s',
        '<title>' . htmlspecialchars($new_title) . ' | ' . \Drupal::config('system.site')->get('name') . '</title>',
        $content,
        1
      );

      // Replace the h1 page title - handles both Gin and standard themes.
      $escaped_node_title = preg_quote($node->label(), '/');
      $content = preg_replace(
        '/(<h1[^>]*class="[^"]*page-title[^"]*"[^>]*>)(\s*)' . $escaped_node_title . '(\s*)(<\/h1>)/i',
        '$1$2' . htmlspecialchars($new_title) . '$3$4',
        $content
      );

      // Also try matching h1 without specific class.
      $content = preg_replace(
        '/(<h1[^>]*>)(\s*)' . $escaped_node_title . '(\s*)(<\/h1>)/i',
        '$1$2' . htmlspecialchars($new_title) . '$3$4',
        $content,
        1
      );

      $response->setContent($content);
    }
  }

}

