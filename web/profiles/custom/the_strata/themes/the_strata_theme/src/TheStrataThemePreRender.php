<?php

namespace Drupal\the_strata_theme;

use Drupal\Core\Security\TrustedCallbackInterface;

include_once __DIR__ . '/../the_strata_theme.theme';
_the_strata_theme_include_theme_includes();

/**
 * Implements trusted prerender callbacks for the the_strata_theme theme.
 *
 * @internal
 */
class TheStrataThemePreRender implements TrustedCallbackInterface {

  /**
   * Prepare description toggle for output in template.
   */
  public static function textFormat($element) {

    if (\Drupal::classResolver(TheStrataThemeDescriptionToggle::class)->isEnabled() && !empty($element['#description'])) {
      if ($element['#type'] === 'text_format') {
        $element['value']['#description_toggle'] = TRUE;
      }
      else {
        $element['#description_toggle'] = TRUE;
        $element['#description_display'] = 'invisible';
      }

    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'textFormat',
    ];
  }

}
